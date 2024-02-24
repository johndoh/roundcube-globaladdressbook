<?php

/**
 * GlobalAddressbook
 *
 * Plugin to add a global address book
 *
 * @author Philip Weir
 *
 * Copyright (C) Philip Weir
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
    public const DEFAULT_NAME = 'Shared Contacts';
    public const DEFAULT_USER = '_global_addressbook_user_';
    public const DEFAULT_HOST = 'localhost';

    public $task = '?(?!login$|logout$|cli$).*';
    private $abook_ids = [];
    private $config = [];
    private $rcube;
    private $autocomplete = false;
    private $check_safe = false;

    public function init()
    {
        $this->rcube = rcube::get_instance();

        $this->_load_config();

        // Host exceptions
        $hosts = $this->rcube->config->get('globaladdressbook_allowed_hosts');
        if (!empty($hosts) && !in_array($_SESSION['storage_host'], (array) $hosts)) {
            return;
        }

        $this->add_hook('addressbooks_list', [$this, 'address_sources']);
        $this->add_hook('addressbook_get', [$this, 'get_address_book']);

        // use this address book for autocompletion queries
        if ($this->autocomplete) {
            $sources = (array) $this->rcube->config->get('autocomplete_addressbooks', 'sql');
            foreach ($this->abook_ids as $id) {
                if (!empty($this->config[$id]['autocomplete']) && !in_array($id, $sources)) {
                    $sources[] = $id;
                }
            }

            $this->rcube->config->set('autocomplete_addressbooks', $sources);
        }

        if ($this->check_safe) {
            $this->add_hook('message_check_safe', [$this, 'check_known_senders']);
        }
    }

    public function address_sources($args)
    {
        foreach ($this->abook_ids as $id) {
            $args['sources'][$id] = [
                'id' => $id,
                'name' => $this->_get_config('name', $id, self::DEFAULT_NAME),
                'readonly' => $this->_get_config('readonly', $id),
                'groups' => $this->_get_config('groups', $id, false),
            ];
        }

        return $args;
    }

    public function get_address_book($args)
    {
        if (in_array($args['id'], $this->abook_ids)) {
            $args['instance'] = new rcube_contacts($this->rcube->db, $this->_get_config('user_id', $args['id']));
            $args['instance']->readonly = $this->_get_config('readonly', $args['id']);
            $args['instance']->groups = $this->_get_config('groups', $args['id'], false);
            $args['instance']->name = $this->_get_config('name', $args['id'], self::DEFAULT_NAME);
        }

        return $args;
    }

    public function check_known_senders($args)
    {
        // don't bother checking if the message is already marked as safe
        if ($args['message']->is_safe) {
            return;
        }

        foreach ($this->abook_ids as $id) {
            if (!empty($this->config[$id]['check_safe'])) {
                $contacts = $this->rcube->get_address_book($id);
                if ($contacts) {
                    $result = $contacts->search('email', $args['message']->sender['mailto'], 1, false);
                    if ($result->count) {
                        $args['message']->set_safe(true);
                        break;
                    }
                }
            }
        }

        return $args;
    }

    private function _load_config()
    {
        parent::load_config();
        $this->config = $this->rcube->config->get('globaladdressbooks');

        // backwards compatibility with v1 config
        if (!is_array($this->config)) {
            $this->config['global'] = [
                'name' => self::DEFAULT_NAME,
                'user' => $this->rcube->config->get('globaladdressbook_user', null),
                'perms' => $this->rcube->config->get('globaladdressbook_perms', 0),
                'force_copy' => $this->rcube->config->get('globaladdressbook_force_copy', true),
                'groups' => $this->rcube->config->get('globaladdressbook_groups', false),
                'admin' => $this->rcube->config->get('globaladdressbook_admin', null),
                'autocomplete' => $this->rcube->config->get('globaladdressbook_autocomplete', true),
                'check_safe' => $this->rcube->config->get('globaladdressbook_check_safe', true),
                'visibility' => null,
            ];
        }

        foreach ($this->config as $id => &$config) {
            // allow other plugins to update permission related config options
            $data = $this->rcube->plugins->exec_hook('globaladdressbook_permissions', [
                'id' => $id,
                'perms' => $config['perms'] ?? 0,
                'admin' => isset($config['admin']) ? $this->_check_constraint($config['admin']) : false,
                'visibility' => isset($config['visibility']) ? $this->_check_constraint($config['visibility']) : true,
            ]);

            // update config with plugin response
            $config['perms'] = (int) $data['perms'];
            $config['admin'] = (bool) $data['admin'];
            $config['visibility'] = (bool) $data['visibility'];

            if ($config['visibility']) {
                $username = isset($config['user']) ? $this->_parse_macros($config['user']) : self::DEFAULT_USER;
                $host = isset($config['host']) ? $this->_parse_macros($config['host']) : self::DEFAULT_HOST;

                $this->_set_readonly($id, $config);

                // email2user hook can be used by other plugins to do post processing on usernames, not just virtual user lookup
                // matches process of user lookup and creation in the core
                if (strpos($username, '@') !== false && ($virtuser = rcube_user::email2user($username))) {
                    $username = $virtuser;
                }

                // get user id for the global address book user
                if ($user_id = $this->_get_user($username, $host)) {
                    $this->abook_ids[] = $id;
                    $config['user_id'] = $user_id;
                } else {
                    rcube::raise_error([
                        'code' => 500, 'line' => __LINE__, 'file' => __FILE__,
                        'message' => 'Globaladdressbook plugin: Failed to create user',
                    ], true, false);
                }

                // set autocomplete and check_safe globally to save checking each address book every time
                if (!empty($config['autocomplete'])) {
                    $this->autocomplete = true;
                }

                if (!empty($config['check_safe'])) {
                    $this->check_safe = true;
                }
            }
        }
    }

    private function _get_config($name, $abook_id, $default = null)
    {
        if (array_key_exists($name, $this->config[$abook_id])) {
            $return = $this->config[$abook_id][$name];

            if ($name == 'name' && is_array($return)) {
                // special handling for localized address book name
                $return = $this->rcube->gettext(['name' => 'globaladdressbook.name'] + $return);
                $return = $return == '[globaladdressbook.name]' ? $default : $return;
            }
        }

        return !empty($return) ? $return : $default;
    }

    private function _get_user($username, $host)
    {
        // check if the global address book user exists
        if (!($user = rcube_user::query($username, $host))) {
            // from rcube_user::create()
            $data = $this->rcube->plugins->exec_hook('user_create', [
                'host' => $host,
                'user' => $username,
                'user_name' => '',
                'user_email' => '',
                'email_list' => null,
                'language' => null,
                'preferences' => [],
            ]);

            if ($data['abort']) {
                return;
            }

            $dbh = $this->rcube->get_dbh();
            $insert = $dbh->query(
                'INSERT INTO ' . $dbh->table_name('users', true)
                . ' (`created`, `username`, `mail_host`)'
                . ' VALUES (' . $dbh->now() . ', ?, ?)',
                $data['user'],
                $data['host']
            );

            if ($dbh->affected_rows($insert) && ($user_id = $dbh->insert_id('users'))) {
                $user = (object) ['ID' => $user_id];
            }
        }

        return $user->ID;
    }

    private function _parse_macros($str)
    {
        $user = $this->rcube->user;

        // %h - IMAP host
        $h = $_SESSION['storage_host'];
        // %d - domain name after the '@' from username
        $d = $user->get_username('domain');
        // %i - domain name after the '@' from e-mail address of default identity
        $i = '';
        if (strpos($str, '%i') !== false) {
            $user_ident = $user->list_emails(true);
            [$local, $domain] = explode('@', $user_ident['email']);
            $i = $domain;
        }

        return str_replace(['%h', '%d', '%i'], [$h, $d, $i], $str);
    }

    private function _check_constraint($var)
    {
        $result = false;

        if (!empty($var)) {
            if (!is_array($var)) {
                $var = [$var];
            }

            foreach ($var as $user) {
                if ($user == $_SESSION['username']) {
                    $result = true;
                    break;
                }
            }
        }

        return $result;
    }

    private function _set_readonly($abook_id, &$config)
    {
        $config['readonly'] = true;

        // check for full permissions
        if (in_array($config['perms'], [1, 2, 3]) || $config['admin'] === true) {
            $config['readonly'] = false;
        }

        // check for task specific permissions
        if ($this->rcube->task == 'addressbook') {
            if (rcube_utils::get_input_string('_source', rcube_utils::INPUT_GPC) == $abook_id) {
                if ($this->rcube->action == 'move' && !empty($config['force_copy'])) {
                    $this->rcube->overwrite_action('copy');
                    $this->rcube->output->command('list_contacts');
                } elseif ($this->rcube->action == 'delete' && in_array($config['perms'], [2, 3]) && !$config['admin']) {
                    $this->rcube->output->show_message('contactdelerror', 'error');
                    $this->rcube->output->command('list_contacts');
                    $this->rcube->output->send();
                }
            }

            // do not override permissions for admins
            if (!$config['admin'] && !$config['readonly']) {
                if (in_array($this->rcube->action, ['show', 'edit']) && $config['perms'] == 2) {
                    $config['readonly'] = true;
                } elseif ($this->rcube->action == 'import' && in_array($config['perms'], [2, 3])) {
                    $config['readonly'] = true;
                }
            }
        }
    }
}
