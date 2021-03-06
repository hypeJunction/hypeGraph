<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class UserActivity extends Controller {

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
					new Parameter('types', false, Parameter::TYPE_ARRAY),
					new Parameter('subtypes', false, Parameter::TYPE_ARRAY),
					new Parameter('action_types', false, Parameter::TYPE_ARRAY),
				);

			case HttpRequest::METHOD_POST :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('action', true, Parameter::TYPE_STRING),
					new Parameter('view', true, Parameter::TYPE_STRING, 'river/elements/layout'),
					new Parameter('object_uid', false),
					new Parameter('target_uid', false),
					new Parameter('annotation_uid', false),
				);

			default :
				return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		$guid = sanitize_int($params->guid);
		$options = array(
			'types' => !empty($params->types) ? $params->types : ELGG_ENTITIES_ANY_VALUE,
			'subtypes' => !empty($params->subtypes) ? $params->subtypes : ELGG_ENTITIES_ANY_VALUE,
			'action_types' => !empty($params->action_types) ? $params->action_types : ELGG_ENTITIES_ANY_VALUE,
			'limit' => $params->limit,
			'offset' => $params->offset,
			'wheres' => array(
				"rv.subject_guid = $guid OR rv.object_guid = $guid OR rv.target_guid",
			)
		);
		return new BatchResult('elgg_get_river', $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function post(ParameterBag $params) {
		$params->subject_uid = "ue{$params->guid}";
		$params->guid = elgg_get_site_entity()->guid;
		$ctrl = new SiteActivity($this->request, $this->graph);
		return $ctrl->post($params);
	}
}
