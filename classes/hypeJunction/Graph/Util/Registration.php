<?php

namespace hypeJunction\Graph\Util;

class Registration {
	
	/**
	 * Generates random unique username
	 * @return string
	 */
	public static function generateUsername() {

		$username = false;

		$ia = elgg_set_ignore_access(true);
		$ha = access_get_show_hidden_status();
		access_show_hidden_entities(true);

		while (!$username) {
			$temp = "u" . rand(100000000,999999999);
			if (!get_user_by_username($temp)) {
				$username = $temp;
			}
		}

		access_show_hidden_entities($ha);
		elgg_set_ignore_access($ia);

		return $username;
	}
	
}
