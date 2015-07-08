<?php

namespace hypeJunction\Graph;

use hypeJunction\Config as ParentConfig;

/**
 * Config
 */
class Config extends ParentConfig {

	/**
	 * {@inheritdoc}
	 */
	public function getDefaults() {
		return array(
			'pagehandler_id' => 'graph',
			'auth_api_key' => true,
			'auth_hmac' => true,
			'auth_usertoken' => true,
			'auth_consumer_userpass' => false,
			//'auth_http_basic_auth' => false,
			'debug_mode' => false,
			'site_guid' => elgg_get_site_entity()->guid,
			'dbprefix' => elgg_get_config('dbprefix'),
		);
	}

}
