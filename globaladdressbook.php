<?php

/**
 * GlobalAddressbook
 *
 * Plugin to add a global address book
 *
 * @version 1.1
 * @author Philip Weir
 * @url http://roundcube.net/plugins/globaladdressbook
 */
class globaladdressbook extends rcube_plugin
{
	public $task = 'mail|addressbook';
	private $abook_id = 'global';
	private $readonly;
	private $user_id;
	private $user_name;
	private $host;
	private $abook_name;

	public function init()
	{
		$rcmail = rcmail::get_instance();
		if (!empty($rcmail->user->ID)) {
			$this->load_config();
			$this->user_name = $rcmail->config->get('globaladdressbook_user');
			$user_info = explode('@', $_SESSION['username']);
			$this->user_name = (count($user_info) >= 2) ? str_replace('%d', $user_info[1], $this->user_name) : $_SESSION['imap_host'];
			$this->readonly = $rcmail->config->get('globaladdressbook_readonly');
			$this->abook_name = $rcmail->config->get('globaladdressbook_name');
			$this->host = $rcmail->config->get('globaladdressbook_per_host') ? $_SESSION['imap_host'] : 'localhost';

			// check if the global address book user exists
			if (!($user = rcube_user::query($this->user_name, $this->host)))
				$user = rcube_user::create($this->user_name, $this->host);

			$this->user_id = $user->ID;

			// use this address book for autocompletion queries
			if ($rcmail->config->get('globaladdressbook_autocomplete')) {
				$sources = $rcmail->config->get('autocomplete_addressbooks', array('sql'));
				if (!in_array($this->abook_id, $sources)) {
					$sources[] = $this->abook_id;
					$rcmail->config->set('autocomplete_addressbooks', $sources);
				}
			}
		}

		$this->add_hook('address_sources', array($this, 'address_sources'));
		$this->add_hook('get_address_book', array($this, 'get_address_book'));
	}

	public function address_sources($args)
	{
		$args['sources'][$this->abook_id] = array('id' => $this->abook_id, 'name' => $this->abook_name, 'readonly' => $this->readonly);
		return $args;
	}

	public function get_address_book($args)
	{
		if ($args['id'] === $this->abook_id) {
			$args['instance'] = new rcube_contacts(rcmail::get_instance()->db, $this->user_id);
			$args['instance']->readonly = $this->readonly;
		}

		return $args;
	}
}
