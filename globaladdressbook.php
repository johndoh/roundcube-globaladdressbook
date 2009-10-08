<?php

/**
 * GlobalAddressbook
 *
 * Plugin to add a global address book
 *
 * @version 1.0
 * @author Philip Weir
 * @url http://roundcube.net/plugins/globaladdressbook
 */
class globaladdressbook extends rcube_plugin
{
	private $abook_id = 'global';
	private $readonly;
	private $user_id;
	private $name;

	public function init()
	{
		$rcmail = rcmail::get_instance();
		$this->load_config();

		// check if the global address book user exists
		if (!($user = rcube_user::query($rcmail->config->get('globaladdressbook_user'), 'localhost')))
			$user = rcube_user::create($rcmail->config->get('globaladdressbook_user'), 'localhost');

		$this->user_id = $user->ID;
		$this->readonly = $rcmail->config->get('globaladdressbook_readonly');
		$this->name = $rcmail->config->get('globaladdressbook_name');

		$this->add_hook('address_sources', array($this, 'address_sources'));
		$this->add_hook('get_address_book', array($this, 'get_address_book'));

		// use this address book for autocompletion queries
		if ($rcmail->config->get('globaladdressbook_autocomplete')) {
			$sources = $rcmail->config->get('autocomplete_addressbooks', array('sql'));
			if (!in_array($this->abook_id, $sources)) {
				$sources[] = $this->abook_id;
				$rcmail->config->set('autocomplete_addressbooks', $sources);
			}
		}
	}

	public function address_sources($args)
	{
		$args['sources'][$this->abook_id] = array('id' => $this->abook_id, 'name' => $this->name, 'readonly' => $this->readonly);
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
