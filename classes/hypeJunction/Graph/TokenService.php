<?php

namespace hypeJunction\Graph;

use ElggSite;
use ElggUser;

class TokenService {

	/**
	 * Default token validity in minutes
	 */
	const DEFAULT_EXPIRES = 60; // 1 hour

	/**
	 * Long living token validity
	 */
	const LONG_EXPIRES = 43200; // 30 days

	/**
	 * Constructor
	 */

	public function __construct() {
		$this->dbprefix = elgg_get_config('dbprefix');
	}

	/**
	 * Obtain a token for a user
	 *
	 * @param ElggUser $user    User entity
	 * @param ElggSite $site    Site entity token applies to
	 * @param int       $expire Minutes until token expires (default is 60 minutes)
	 * @return UserToken|false
	 */
	public function create(ElggUser $user, ElggSite $site, $expire = self::DEFAULT_EXPIRES) {

		$time = time();
		$time += 60 * $expire;
		$token = md5(sha1(rand() . microtime() . $user->username . $time . $site->guid));

		$result = insert_data("INSERT into {$this->dbprefix}users_apisessions
				(user_guid, site_guid, token, expires) values
				({$user->guid}, {$site->guid}, '{$token}', '{$time}')
				on duplicate key update token='{$token}', expires='{$time}'");

		if (!$result) {
			return false;
		}

		return UserToken::load($token);
	}

	/**
	 * Returns a token object from token string
	 * 
	 * @param string $token Token
	 * @return UserToken|false
	 */
	public function get($token) {
		return UserToken::load($token);
	}

	/**
	 * Get all tokens attached to a user
	 *
	 * @param ElggUser $user User entity
	 * @param ElggSite $site Site entity
	 * @return UserToken[]|false
	 */
	public function all(ElggUser $user, ElggSite $site = null) {

		$query = "SELECT * FROM {$this->dbprefix}users_apisessions WHERE user_guid={$user->guid}";

		if ($site) {
			$query .= " AND site_guid={$site->guid}";
		}

		$tokens = get_data($query);

		if (empty($tokens)) {
			return false;
		}

		array_walk($tokens, function($row) {
			return new UserToken($row);
		});

		return $tokens;
	}

	/**
	 * Remove expired tokens
	 * @return bool
	 */
	public function removeExpiredTokens() {
		$time = time();

		$result = delete_data("DELETE FROM {$this->dbprefix}users_apisessions
								WHERE expires < $time");

		if (elgg_in_context('cron')) {
			if ($result !== false) {
				return "$result expired user tokens were removed" . PHP_EOL;
			} else {
				return "ERROR: Expired user tokens could not be removed" . PHP_EOL;
			}
		}

		return $result;
	}

}
