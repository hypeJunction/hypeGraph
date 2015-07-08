<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class SiteActivity extends Controller {

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
					new Parameter('subject_uid', true),
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
		$options = array(
			'types' => !empty($params->types) ? $params->types : ELGG_ENTITIES_ANY_VALUE,
			'subtypes' => !empty($params->subtypes) ? $params->subtypes : ELGG_ENTITIES_ANY_VALUE,
			'action_types' => !empty($params->action_types) ? $params->action_types : ELGG_ENTITIES_ANY_VALUE,
			'limit' => $params->limit,
			'offset' => $params->offset,
		);
		return new BatchResult('elgg_get_river', $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function post(ParameterBag $params) {
		$subject = $this->graph->get($params->subject_uid);
		if (!$subject || !$subject->canEdit()) {
			throw new GraphException('You are not allowed to create new activity items with this subject', 403);
		}

		$id = elgg_create_river_item(array(
			'view' => $params->view,
			'subject_guid' => $this->graph->get($params->subject_uid)->guid ? : ELGG_ENTITIES_ANY_VALUE,
			'object_guid' => $this->graph->get($params->object_uid)->guid ? : ELGG_ENTITIES_ANY_VALUE,
			'target_guid' => $this->graph->get($params->target_uid)->guid ? : ELGG_ENTITIES_ANY_VALUE,
			'action_type' => $params->action,
			'annotation_id' => $this->graph->get($params->annotation_uid)->id,
		));

		if (!$id) {
			throw new GraphException('Unable to create a new activity item with such parameters', 400);
		}

		$params->id = $id;
		$ctrl = new RiverItem($this->request, $this->graph);
		return $ctrl->get($params);
	}

}
