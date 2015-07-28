<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class UserWall extends Controller {

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
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('status', true),
					new Parameter('address', false),
					new Parameter('location', false),
					new Parameter('friend_uids', false, Parameter::TYPE_ARRAY),
					new Parameter('upload_uids', false, Parameter::TYPE_ARRAY),
					new Parameter('attachment_uids', false, Parameter::TYPE_ARRAY),
					new Parameter('tags', false, Parameter::TYPE_STRING),
					new Parameter('access_id', false, Parameter::TYPE_INT),
					new Parameter('make_bookmark', false, Parameter::TYPE_BOOL, false),
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
			'types' => 'object',
			'subtypes' => \hypeJunction\Wall\Post::SUBTYPE,
			'limit' => $params->limit,
			'offset' => $params->offset,
			'container_guids' => sanitize_int($params->guid),
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
		$params->container_guid = $params->guid;
		unset($params->guid); // user guid
		$ctrl = new Wall($this->request, $this->graph);
		return $ctrl->put($params);
	}
}
