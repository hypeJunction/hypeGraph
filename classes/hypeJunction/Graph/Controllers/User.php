<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class User extends Controller {

	/**
	 * {@inheritdoc}
	 */
	public function params($method) {

		switch ($method) {
			case HttpRequest::METHOD_GET :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
				);

			case HttpRequest::METHOD_DELETE :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
				);

			default :
				return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete(ParameterBag $params) {
		$user = get_entity($params->guid);
		if (!$user->canDelete()) {
			throw new GraphException('You are not permitted to delete this user', 403);
		}
		if ($user->guid == elgg_get_logged_in_user_guid()) {
			throw new GraphException('Deleting yourself is not allowed', 403);
		}
		return $user->delete();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		return array('nodes' => array(get_entity($params->guid)));
	}

}
