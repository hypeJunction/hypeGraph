<?php

namespace hypeJunction\Graph;

use ElggObject;

/**
 * API Consumer object
 *
 * @property array  $methods Methods that this consumer is allowed to consume
 */
class Consumer extends ElggObject {

	const CLASSNAME = __CLASS__;
	const SUBTYPE = 'api_consumer';

	/**
	 * Initialize attributes
	 * @return void
	 */
	protected function initializeAttributes() {
		parent::initializeAttributes();
		$this->attributes['subtype'] = self::SUBTYPE;
	}

	/**
	 * Initialize a consumer from API key
	 * 
	 * @param string $api_key API Key
	 * @return Consumer|void
	 */
	public static function factory($api_key) {
		if (!$api_key) {
			return;
		}

		$ia = elgg_set_ignore_access(true);

		$consumers = elgg_get_entities_from_private_settings(array(
			'types' => 'object',
			'subtypes' => self::SUBTYPE,
			'private_setting_name_value_pairs' => array(
				'name' => 'api_key',
				'value' => $api_key,
			),
			'limit' => 1,
		));

		$consumer = (!empty($consumers)) ? $consumers[0] : null;

		elgg_set_ignore_access($ia);

		return $consumer;
	}

	/**
	 * Force access to private when saving the consumer
	 * @return int|false
	 */
	public function save() {
		$this->access_id = ACCESS_PRIVATE;
		return parent::save();
	}

	/**
	 * Returns public API key
	 * @return string
	 */
	public function getPublicKey() {
		$ia = elgg_set_ignore_access();
		$key = $this->getPrivateSetting('api_key');
		elgg_set_ignore_access($ia);
		return $key;
	}

	/**
	 * Returns secret API key
	 * @return string
	 */
	public function getPrivateKey() {
		$api_user = hypeGraph()->api_keys->get($this->getPublicKey());
		return $api_user->secret;
	}

	/**
	 * Generate API Keys and other credentials for this API consumer
	 * @return void
	 */
	public function generateApiKeys() {
		$this->deleteApiKeys();
		$api_user = hypeGraph()->api_keys->create();
		$this->setPrivateSetting('api_key', $api_user->api_key);
	}

	/**
	 * Deletes api keys
	 * @return bool
	 */
	public function deleteApiKeys() {
		return hypeGraph()->api_keys->revoke($this->getPublicKey());
	}

	/**
	 * Save consumer username and password
	 *
	 * @param string $api_username API Consumer username
	 * @param string $api_password API Consumer password
	 * @return void
	 */
	public function setCredentials($api_username, $api_password) {
		$this->setPrivateSetting('api_username', $api_username);
		$this->setPrivateSetting('api_password_hash', password_hash($api_password, PASSWORD_DEFAULT));
	}

	/**
	 * Verify consumer username and password
	 * 
	 * @param string $api_username API Consumer username
	 * @param string $api_password API Consumer password
	 * @return bool
	 */
	public function verifyCredentials($api_username, $api_password) {
		$ia = elgg_set_ignore_access();
		$set_username = $this->getPrivateSetting('api_username');
		$set_hash = $this->getPrivateSetting('api_password_hash');
		elgg_set_ignore_access($ia);
		
		if (strcmp((string) $set_username, $api_username)) {
			return false;
		}

		/**
		 * @todo: implement rehash logic
		 */

		return (password_verify($api_password, (string) $set_hash));
	}
}
