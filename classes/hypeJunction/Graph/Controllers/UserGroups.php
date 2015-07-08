<?php

namespace hypeJunction\Graph\Controllers;

use ElggGroup;
use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class UserGroups extends Controller {

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
				$params = array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('name', true, Parameter::TYPE_STRING, null, null, elgg_echo('groups:name')),
					new Parameter('membership', false, Parameter::TYPE_ENUM, (string) ACCESS_PRIVATE, array((string) ACCESS_PRIVATE, (string) ACCESS_PUBLIC), elgg_echo('groups:membership')),
					new Parameter('vis', false, Parameter::TYPE_ENUM, (string) ACCESS_PRIVATE, array((string) ACCESS_PRIVATE, (string) ACCESS_PUBLIC, (string) ACCESS_LOGGED_IN), elgg_echo('groups:visibility')),
					new Parameter('content_access_mode', false, Parameter::TYPE_ENUM, ElggGroup::CONTENT_ACCESS_MODE_MEMBERS_ONLY, array(ElggGroup::CONTENT_ACCESS_MODE_UNRESTRICTED, ElggGroup::CONTENT_ACCESS_MODE_MEMBERS_ONLY), elgg_echo('groups:content_access_mode')),
				);
				foreach (elgg_get_config('group') as $shortname => $valuetype) {
					$params[] = new Parameter($shortname, false, Parameter::TYPE_STRING, null, null, elgg_echo("groups:$shortname"));
				}
				$tool_options = elgg_get_config('group_tool_options');
				if ($tool_options) {
					foreach ($tool_options as $group_option) {
						$option_toggle_name = $group_option->name . "_enable";
						$option_default = $group_option->default_on ? 'yes' : 'no';
						$params[] = new Parameter($option_toggle_name, false, Parameter::TYPE_STRING, $option_default, array('yes', 'no'), $group_option->label);
					}
				}
				return $params;

			default :
				return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		$options = array(
			'types' => 'group',
			'limit' => $params->limit,
			'offset' => $params->offset,
			'owner_guids' => sanitize_int($params->guid),
			'sort' => $params->sort,
			'preload_owners' => true,
			'preload_containers' => true,
		);
		return new BatchResult('elgg_get_entities', $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function post(ParameterBag $params) {
		$params->owner_guid = $params->guid;
		unset($params->guid); // user guid
		$ctrl = new Group($this->request, $this->graph);
		return $ctrl->put($params);
	}
}
