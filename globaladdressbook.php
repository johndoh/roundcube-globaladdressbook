<?php

/**
 * GlobalAddressbook
 *
 * Plugin to add a global address book
 *
 * @author Philip Weir
 *
 * Copyright (C) 2009-2017 Philip Weir
 *
 * This program is a Roundcube (https://roundcube.net) plugin.
 * For more information see README.md.
 * For configuration see config.inc.php.dist.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Roundcube. If not, see https://www.gnu.org/licenses/.
 */
class globaladdressbook extends rcube_plugin
{
    public $task = '^((?!login).)*$';
    private $abook_id = 'global';
    private $readonly = true;
    private $groups = false;
    private $name;
    private $user_id;
    private $rcube;

    public function init()
    {
        $this->rcube = rcube::get_instance();

        $this->load_config();

        // Host exceptions
        $hosts = $this->rcube->config->get('globaladdressbook_allowed_hosts');
        if (!empty($hosts) && !in_array($_SESSION['storage_host'], (array) $hosts)) {
            return;
        }

        $this->add_texts('localization/');

        $username = self::parse_user($this->rcube->config->get('globaladdressbook_user', '[global_addressbook_user]'));
        $host = 'localhost';

        $this->groups = $this->rcube->config->get('globaladdressbook_groups', false);
        $this->name = $this->gettext('globaladdressbook');
        $this->_set_permissions();

        // email2user hook can be used by other plugins to do post processing on usernames, not just virtual user lookup
        // matches process of user lookup and creation in the core
        if (strpos($username, '@') !== false && ($virtuser = rcube_user::email2user($username))) {
            $username = $virtuser;
        }

        // check if the global address book user exists
        if (!($user = rcube_user::query($username, $host))) {
            // this action overrides the current user information so make a copy and then restore it
            $cur_user = $this->rcube->user;
            $user = rcube_user::create($username, $host);
            $this->rcube->user = $cur_user;

            // prevent new_user_dialog plugin from triggering
            $_SESSION['plugin.newuserdialog'] = false;
        }

        // global address book user ID
        $this->user_id = $user->ID;

        $this->add_hook('addressbooks_list', array($this, 'address_sources'));
        $this->add_hook('addressbook_get', array($this, 'get_address_book'));

        // use this address book for autocompletion queries
        if ($this->rcube->config->get('globaladdressbook_autocomplete')) {
            $sources = (array) $this->rcube->config->get('autocomplete_addressbooks', 'sql');
            if (!in_array($this->abook_id, $sources)) {
                $sources[] = $this->abook_id;
                $this->rcube->config->set('autocomplete_addressbooks', $sources);
            }
        }

        if ($this->rcube->config->get('globaladdressbook_check_safe')) {
            $this->add_hook('message_check_safe', array($this, 'check_known_senders'));
        }
    }

    public function address_sources($args)
    {
        $args['sources'][$this->abook_id] = array('id' => $this->abook_id, 'name' => $this->name, 'readonly' => $this->readonly, 'groups' => $this->groups);

        return $args;
    }

    public function get_address_book($args)
    {
        if ($args['id'] === $this->abook_id) {
            $args['instance'] = new rcube_contacts($this->rcube->db, $this->user_id);
            $args['instance']->readonly = $this->readonly;
            $args['instance']->groups = $this->groups;
            $args['instance']->name = $this->name;
        }

        return $args;
    }

    public function check_known_senders($args)
    {
        // don't bother checking if the message is already marked as safe
        if ($args['message']->is_safe) {
            return;
        }
        $contacts = $this->rcube->get_address_book($this->abook_id);
        if ($contacts) {
            $result = $contacts->search('email', $args['message']->sender['mailto'], 1, false);
            if ($result->count) {
                $args['message']->set_safe(true);
            }
        }

        return $args;
    }

    public static function parse_user($name)
    {
        $user = rcube::get_instance()->user;

        // %h - IMAP host
        $h = $_SESSION['storage_host'];
        // %d - domain name after the '@' from username
        $d = $user->get_username('domain');
        // %i - domain name after the '@' from e-mail address of default identity
        $i = '';
        if (strpos($name, '%i') !== false) {
            $user_ident = $user->list_emails(true);
            list($local, $domain) = explode('@', $user_ident['email']);
            $i = $domain;
        }

        return str_replace(array('%h', '%d', '%i'), array($h, $d, $i), $name);
    }

    private function _set_permissions()
    {
        $isAdmin = false;

        // fix deprecated globaladdressbook_readonly option removed 20140525
        if ($this->rcube->config->get('globaladdressbook_perms') === null) {
            $this->rcube->config->set('globaladdressbook_perms', $this->rcube->config->get('globaladdressbook_readonly') ? 0 : 1);
        }

        // check for full permissions
        $perms = $this->rcube->config->get('globaladdressbook_perms', array());
        if (in_array($perms, array(1, 2, 3))) {
            $this->readonly = false;
        }

        // check if the user is an admin
        if ($admin = $this->rcube->config->get('globaladdressbook_admin')) {
            if (!is_array($admin)) {
                $admin = array($admin);
            }

            foreach ($admin as $user) {
                if ($user == $_SESSION['username'] || @preg_match($user, $_SESSION['username']) !== false) {
                    $this->readonly = false;
                    $isAdmin = true;
                    break;
                }
            }
        }

        // check for task specific permissions
        if ($this->rcube->task == 'addressbook' && rcube_utils::get_input_value('_source', rcube_utils::INPUT_GPC) == $this->abook_id) {
            if ($this->rcube->action == 'move' && $this->rcube->config->get('globaladdressbook_force_copy')) {
                $this->rcube->overwrite_action('copy');
                $this->rcube->output->command('list_contacts');
            }

            // do not override permissions for admins
            if (!$isAdmin && !$this->readonly) {
                if (in_array($this->rcube->action, array('show', 'edit')) && in_array($perms, array(2))) {
                    $this->readonly = true;
                }
                elseif ($this->rcube->action == 'delete' && in_array($perms, array(2, 3))) {
                    $this->rcube->output->command('display_message', $this->gettext('errornoperm'), 'info');
                    $this->rcube->output->command('list_contacts');
                    $this->rcube->output->send();
                }
            }
        }
    }
}
