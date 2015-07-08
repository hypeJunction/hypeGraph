<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;
use hypeJunction\Graph\Util\Registration;
use RegistrationException;

class SiteUsers extends Controller {

	/**
	 * {@inheritdoc}
	 */
	public function params($method) {

		switch ($method) {
			case HttpRequest::METHOD_GET :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('limit', false, Parameter::TYPE_INT, elgg_get_config('default_limit')),
					new Parameter('offset', false, Parameter::TYPE_INT, 0),
					new Parameter('sort', false, Parameter::TYPE_ARRAY, array('time_created' => 'DESC')),
				);

			case HttpRequest::METHOD_POST :
				return array(
					new Parameter('email', true),
					new Parameter('username', false),
					new Parameter('password', false),
					new Parameter('name', false),
					new Parameter('language', false, Parameter::TYPE_STRING, 'en'),
					new Parameter('notify', false, Parameter::TYPE_STRING, true),
					new Parameter('friend_uid', false),
					new Parameter('invitecode', false),
				);

			default :
				return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		$options = array(
			'types' => 'user',
			'limit' => $params->limit,
			'offset' => $params->offset,
			'sort' => $params->sort,
		);
		return new BatchResult('elgg_get_entities', $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function post(ParameterBag $params) {

		if (!elgg_get_config('allow_registration') && !elgg_trigger_plugin_hook('allow_registration', 'graph')) {
			throw new \RegistrationException(elgg_echo('registerdisabled'), 403);
		}

		$email = $params->email;
		$email_parts = explode('@', $params->email);
		$username = $params->username ? : Registration::generateUsername();
		$password = $params->password ? : generate_random_cleartext_password();
		$name = $params->name ? : array_shift($email_parts);

		$guid = register_user($username, $password, $name, $email);

		if (!$guid) {
			throw new RegistrationException(elgg_echo('registerbad'));
		}
		
		$new_user = get_entity($guid);
		$new_user->language = $params->language;

		$hook_params = array(
			'user' => $new_user,
			'password' => $password,
			'friend_guid' => $this->graph->get($params->friend_uid)->guid,
			'invitecode' => $params->invitecode,
		);

		if (!elgg_trigger_plugin_hook('validate_registration', 'graph', null, true)) {
			// disable uservalidationbyemail
			elgg_unregister_plugin_hook_handler('register', 'user', 'uservalidationbyemail_disable_new_user');
		}
		
		if (!elgg_trigger_plugin_hook('register', 'user', $hook_params, true)) {
			$ia = elgg_set_ignore_access(true);
			$new_user->delete();
			elgg_set_ignore_access($ia);
			throw new RegistrationException(elgg_echo('registerbad'));
		}

		if ($params->notify) {
			$subject = elgg_echo('useradd:subject', array(), $new_user->language);
			$body = elgg_echo('useradd:body', array(
				$name,
				elgg_get_site_entity()->name,
				elgg_get_site_entity()->url,
				$username,
				$password,
					), $new_user->language);

			notify_user($new_user->guid, elgg_get_site_entity()->guid, $subject, $body);
		}

		return array('nodes' => array($new_user));
	}

}
