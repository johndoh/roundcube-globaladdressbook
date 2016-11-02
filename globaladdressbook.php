<?php

/**
 * GlobalAddressbook
 *
 * Plugin to add a global address book
 *
 * @author Philip Weir
 *
 * Copyright (C) 2009-2014 Philip Weir
 *
 * This program is a Roundcube (http://www.roundcube.net) plugin.
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
 * along with Roundcube. If not, see http://www.gnu.org/licenses/.
 */
class globaladdressbook extends rcube_plugin
{
	public $task = 'mail|addressbook|settings|dummy';
	private $abook_id = 'global';
	private $readonly = true;
	private $groups;
	private $name;
	private $user_id;
	private $user_name;
	private $host = 'localhost';

	public function init()
	{
		$rcmail = rcube::get_instance();
		$this->load_config();
		$this->add_texts('localization/');

		$this->user_name = $rcmail->config->get('globaladdressbook_user', '[global_addressbook_user]');
		$this->user_name = str_replace('%d', $rcmail->user->get_username('domain'), $this->user_name);
		$this->user_name = str_replace('%h', $_SESSION['storage_host'], $this->user_name);
		$this->groups = $rcmail->config->get('globaladdressbook_groups', false);
		$this->name = $this->gettext('globaladdressbook');
		$this->_set_permissions();

		// email2user hook can be used by other plugins to do post processing on usernames, not just virtual user lookup
		// matches process of user lookup and creation in the core
		if (strpos($this->user_name, '@') && ($virtuser = rcube_user::email2user($this->user_name))) {
			$this->user_name = $virtuser;
		}

		// check if the global address book user exists
		if (!($user = rcube_user::query($this->user_name, $this->host))) {
			// this action overrides the current user information so make a copy and then restore it
			$cur_user = $rcmail->user;
			$user = rcube_user::create($this->user_name, $this->host);
			$rcmail->user = $cur_user;

			// prevent new_user_dialog plugin from triggering
			$_SESSION['plugin.newuserdialog'] = false;
		}

		$this->user_id = $user->ID;

		// use this address book for autocompletion queries
		if ($rcmail->config->get('globaladdressbook_autocomplete')) {
			$sources = $rcmail->config->get('autocomplete_addressbooks', array('sql'));
			if (!in_array($this->abook_id, $sources)) {
				$sources[] = $this->abook_id;
				$rcmail->config->set('autocomplete_addressbooks', $sources);
			}
		}

		$this->add_hook('addressbooks_list', array($this, 'address_sources'));
		$this->add_hook('addressbook_get', array($this, 'get_address_book'));

		if ($rcmail->config->get('globaladdressbook_check_safe'))
			$this->add_hook('message_check_safe', array($this, 'check_known_senders'));
	}

	public function address_sources($args)
	{
		$args['sources'][$this->abook_id] = array('id' => $this->abook_id, 'name' => $this->name, 'readonly' => $this->readonly, 'groups' => $this->groups);
		return $args;
	}

	public function get_address_book($args)
	{
		if ($args['id'] === $this->abook_id) {
			$args['instance'] = new rcube_contacts(rcube::get_instance()->db, $this->user_id);
			$args['instance']->readonly = $this->readonly;
			$args['instance']->groups = $this->groups;
			$args['instance']->name = $this->name;
		}

		return $args;
	}

	public function check_known_senders($args)
	{
		// don't bother checking if the message is already marked as safe
		if ($args['message']->is_safe)
			return;

		$contacts = rcube::get_instance()->get_address_book($this->abook_id);
		if ($contacts) {
			$result = $contacts->search('email', $args['message']->sender['mailto'], 1, false);
			if ($result->count) {
				$args['message']->set_safe(true);
			}
		}

		return $args;
	}

	private function _set_permissions()
	{
		$rcmail = rcube::get_instance();
		$isAdmin = false;

		// fix deprecated globaladdressbook_readonly option removed 20140525
		if ($rcmail->config->get('globaladdressbook_perms') === null) {
			$rcmail->config->set('globaladdressbook_perms', $rcmail->config->get('globaladdressbook_readonly') ? 0 : 1);
		}

		// check for full permissions
		$perms = $rcmail->config->get('globaladdressbook_perms');
		if (in_array($perms, array(1, 2, 3))) {
			$this->readonly = false;
		}

		// check if the user is an admin
		if ($admin = $rcmail->config->get('globaladdressbook_admin')) {
			if (!is_array($admin)) {
				$admin = array($admin);
			}

			foreach ($admin as $user) {
				if (strpos($user, '/') == 0 && substr($user, -1) == '/') {
					if (preg_match($user, $_SESSION['username'])) {
						$this->readonly = false;
						$isAdmin = true;
					}
				}
				elseif ($user == $_SESSION['username']) {
					$this->readonly = false;
					$isAdmin = true;
				}
			}
		}

		// check for task specific permissions
		if ($rcmail->task == 'addressbook' && rcube_utils::get_input_value('_source', rcube_utils::INPUT_GPC) == $this->abook_id) {
			if ($rcmail->action == 'move' && $rcmail->config->get('globaladdressbook_force_copy')) {
				$rcmail->overwrite_action('copy');
				$this->api->output->command('list_contacts');
			}

			// do not override permissions for admins
			if (!$isAdmin && !$this->readonly) {
				if (in_array($rcmail->action, array('show', 'edit')) && in_array($perms, array(2))) {
					$this->readonly = true;
				}
				elseif ($rcmail->action == 'delete' && in_array($perms, array(2, 3))) {
					$this->api->output->command('display_message', $this->gettext('errornoperm'), 'info');
					$this->api->output->command('list_contacts');
					$this->api->output->send();
				}
			}
		}
	}
}

?>