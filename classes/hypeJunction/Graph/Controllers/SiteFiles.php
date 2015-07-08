<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\Controller;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class SiteFiles extends Controller {

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

}
