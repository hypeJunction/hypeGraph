<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\HttpResponse;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class UserProfile extends Controller {

	/**
	 * {@inheritdoc}
	 */
	public function params($method) {

		switch ($method) {
			case HttpRequest::METHOD_GET :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('fields', false, Parameter::TYPE_STRING),
				);

			case HttpRequest::METHOD_PUT :
				$params = array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('access_id', true, Parameter::TYPE_INT),
				);
				$profile_fields = array_keys((array) elgg_get_config('profile_fields'));
				foreach ($profile_fields as $field) {
					$params[] = new Parameter($field, false, Parameter::TYPE_STRING, null, null, elgg_echo("profile:$field"));
				}
				return $params;

			default :
				return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function put(ParameterBag $params) {
		$owner = get_entity($params->guid);

		if (!$owner->canEdit()) {
			throw new GraphException("You are not allowed to modify this user's profile", HttpResponse::HTTP_FORBIDDEN);
		}

		$profile_fields = (array) elgg_get_config('profile_fields');
		$access_id = $params->access_id !== null ? $params->access_id : get_default_access($owner);

		$input = array();
		foreach ($profile_fields as $field => $valuetype) {
			// Making sure the consumer has sent these fields with the request
			if (isset($params->$field) && $this->request->get($field) !== null) {

				$value = $params->$field;
				$value = _elgg_html_decode($value);

				if (!is_array($value) && $valuetype != 'longtext' && elgg_strlen($value) > 250) {
					throw new GraphException(elgg_echo('profile:field_too_long', array(elgg_echo("profile:{$field}")), HttpResponse::HTTP_BAD_REQUEST));
				}

				if ($value && $valuetype == 'url' && !preg_match('~^https?\://~i', $value)) {
					$value = "http://$value";
				}

				if ($valuetype == 'tags') {
					$value = string_to_tag_array($value);
				}

				if ($valuetype == 'email' && !empty($value) && !is_email_address($value)) {
					throw new GraphException(elgg_echo('profile:invalid_email', array(elgg_echo("profile:{$field}"))), HttpResponse::HTTP_BAD_REQUEST);
				}

				$input[$field] = $value;
			}
		}

		// go through custom fields
		if (sizeof($input) > 0) {
			foreach ($input as $shortname => $value) {
				$options = array(
					'guid' => $owner->guid,
					'metadata_name' => $shortname,
					'limit' => false
				);
				elgg_delete_metadata($options);

				if (!is_null($value) && ($value !== '')) {
					// only create metadata for non empty values (0 is allowed) to prevent metadata records
					// with empty string values #4858
					if (is_array($value)) {
						$i = 0;
						foreach ($value as $interval) {
							$i++;
							$multiple = ($i > 1) ? TRUE : FALSE;
							create_metadata($owner->guid, $shortname, $interval, 'text', $owner->guid, $access_id, $multiple);
						}
					} else {
						create_metadata($owner->getGUID(), $shortname, $value, 'text', $owner->getGUID(), $access_id);
					}
				}
			}

			$owner->save();

			// Notify of profile update
			elgg_trigger_event('profileupdate', $owner->type, $owner);
		}

		return $this->get($params);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		$user = get_entity($params->guid);

		$profile_fields = array_keys((array) elgg_get_config('profile_fields'));

		$requested_fields = $profile_fields;
		if (!empty($params->fields)) {
			$requested_fields = string_to_tag_array($params->fields);
		}

		$profile = array(
			'uid' => "ue{$user->guid}",
			'name' => $user->name,
			'username' => $user->username,
		);

		foreach ($profile_fields as $field) {
			if (in_array($field, $requested_fields)) {
				$profile[$field] = $user->$field;
			}
		}

		return $profile;
	}

}
