<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;
use hypeJunction\Graph\TokenService;

/**
 * User.AccessToken controller
 */
class AccessToken extends Controller {

	/**
	 * {@inheritdoc}
	 */
	public function params($method) {

		switch ($method) {
			case HttpRequest::METHOD_POST :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
				);

			case HttpRequest::METHOD_PUT :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('auth_token', true, Parameter::TYPE_STRING),
				);

			case HttpRequest::METHOD_DELETE :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('auth_token', true),
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
		if (!$user || !$user->canEdit()) {
			throw new GraphException("You are not allowed to manage this user's tokens", 403);
		}
		$token = hypeGraph()->tokens->get($params->auth_token);
		if (!$token || !$token->validate(elgg_get_site_entity())) {
			throw new GraphException("Token not found", 404);
		}
		return $token->delete();
	}

	/**
	 * {@inheritdoc}
	 */
	public function post(ParameterBag $params) {
		$user = get_entity($params->guid);
		$site = elgg_get_site_entity();
		if (!$user || !$site || !check_entity_relationship($user->guid, 'member_of_site', $site->guid)) {
			throw new GraphException("User is not a member of this site", 403);
		}
		$token = hypeGraph()->tokens->create($user, $site);
		if (!$token) {
			throw new GraphException("Unable to generate a new user token", 500);
		}
		return array(
			'token' => $token->token,
			'expires' => date(DATE_ATOM, $token->expires),
			'user' => $user,
			'site_uid' => "se$token->site_guid",
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function put(ParameterBag $params) {
		$user = get_entity($params->guid);
		$site = elgg_get_site_entity();

		if ($this->delete($params)) {
			$token = hypeGraph()->tokens->create($user, $site, TokenService::LONG_EXPIRES);
		}
		if (empty($token)) {
			throw new GraphException("Unable to generate a new user token", 500);
		}
		return array(
			'token' => $token->token,
			'expires' => date(DATE_ATOM, $token->expires),
			'user' => $user,
			'site_uid' => "se$token->site_guid",
		);
	}

}
