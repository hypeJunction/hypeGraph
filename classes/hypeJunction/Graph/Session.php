<?php

namespace hypeJunction\Graph;

use ElggUser;

class Session {

	/**
	 * API Consumer currently consuming the api
	 * @var Consumer
	 */
	private $consumer;

	/**
	 * Currently authenticated Elgg user
	 * @var ElggUser
	 */
	private $user;

	/**
	 * Set or get a consumer
	 *
	 * @param Consumer $consumer API Consumer
	 * @return Consumer
	 */
	public function consumer(Consumer $consumer = null) {
		if ($consumer) {
			$this->consumer = $consumer;
		}
		return $this->consumer;
	}

	/**
	 * Set or get a user
	 *
	 * @param ElggUser $user User
	 * @return ElggUser
	 */
	public function user(ElggUser $user = null) {
		if ($user) {
			$this->user = $user;
		}
		return $this->user;
	}

}
