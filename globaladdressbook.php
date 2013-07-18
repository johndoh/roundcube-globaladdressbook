<?php

/**
 * GlobalAddressbook
 *
 * Plugin to add a global address book
 *
 * @version @package_version@
 * @author Philip Weir
 */
class globaladdressbook extends rcube_plugin
{
	public $task = 'mail|addressbook|settings|dummy';
	private $abook_id = 'global';
	private $readonly;
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

		$this->user_name = $rcmail->config->get('globaladdressbook_user');
		$this->user_name = str_replace('%d', $rcmail->user->get_username('domain'), $this->user_name);
		$this->user_name = str_replace('%h', $_SESSION['storage_host'], $this->user_name);
		$this->readonly = $this->_is_readonly();
		$this->groups = $rcmail->config->get('globaladdressbook_groups', false);
		$this->name = $this->gettext('globaladdressbook');

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

	private function _is_readonly()
	{
		$rcmail = rcube::get_instance();

		if (!$rcmail->config->get('globaladdressbook_readonly'))
			return false;

		if ($admin = $rcmail->config->get('globaladdressbook_admin')) {
			if (!is_array($admin)) $admin = array($admin);

			foreach ($admin as $user) {
				if (strpos($user, '/') == 0 && substr($user, -1) == '/') {
					if (preg_match($user, $_SESSION['username']))
						return false;
				}
				elseif ($user == $_SESSION['username'])
					return false;
			}
		}

		return true;
	}
}

?>