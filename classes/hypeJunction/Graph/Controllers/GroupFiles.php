<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class GroupFiles extends Controller {

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
					new Parameter('simpletype', false),
				);

			case HttpRequest::METHOD_POST :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
					new Parameter('title', false, Parameter::TYPE_STRING, null, null, elgg_echo('title')),
					new Parameter('description', false, Parameter::TYPE_STRING, null, null, elgg_echo('description')),
					new Parameter('tags', false, Parameter::TYPE_STRING, null, null, elgg_echo('tags')),
					new Parameter('access_id', false, Parameter::TYPE_INT),
					new Parameter('filename', true, Parameter::TYPE_STRING, null, null, elgg_echo('graph:file:filename')),
					new Parameter('contents', true, Parameter::TYPE_STRING, null, null, elgg_echo('graph:file:contents')),
					new Parameter('mimetype', true, Parameter::TYPE_STRING, null, null, elgg_echo('graph:file:mimetype')),
					new Parameter('checksum', true, Parameter::TYPE_STRING, null, null, elgg_echo('graph:file:checksum')),
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
			'subtypes' => 'file',
			'limit' => $params->limit,
			'offset' => $params->offset,
			'container_guids' => sanitize_int($params->guid),
			'sort' => $params->sort,
			'preload_owners' => true,
			'preload_containers' => true,
		);
		$getter = 'elgg_get_entities';
		if ($params->simpletype) {
			$options['metadata_name_value_pairs'] = array(
				'name' => 'simpletype', 'value' => $params->simpletype,
			);
			$getter = 'elgg_get_entities_from_metadata';
		}
		return new BatchResult($getter, $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function post(ParameterBag $params) {
		$params->container_guid = $params->guid;
		unset($params->guid); // group guid
		$ctrl = new File($this->request, $this->graph);
		return $ctrl->put($params);
	}
}
