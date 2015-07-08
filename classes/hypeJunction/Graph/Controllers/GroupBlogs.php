<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class GroupBlogs extends Controller {

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
					new Parameter('title', true, Parameter::TYPE_STRING, null, null, elgg_echo('title')),
					new Parameter('description', true, Parameter::TYPE_STRING, null, null, elgg_echo('blog:body')),
					new Parameter('excerpt', false, Parameter::TYPE_STRING, '', null, elgg_echo('blog:excerpt')),
					new Parameter('status', true, Parameter::TYPE_ENUM, 'published', array('draft', 'published'), elgg_echo('status')),
					new Parameter('comments_on', true, Parameter::TYPE_ENUM, 'On', array('On', 'Off'), elgg_echo('comments')),
					new Parameter('tags', false, Parameter::TYPE_STRING, '', null, elgg_echo('tags')),
					new Parameter('access_id', false, Parameter::TYPE_INT, ACCESS_PRIVATE),
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
			'subtypes' => 'blog',
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
		unset($params->guid); // group guid
		$ctrl = new Blog($this->request, $this->graph);
		return $ctrl->put($params);
	}
}
