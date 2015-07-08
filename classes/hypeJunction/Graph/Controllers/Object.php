<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class Object extends Controller {

	/**
	 * {@inheritdoc}
	 */
	public function params($method) {

		switch ($method) {
			case HttpRequest::METHOD_GET :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
				);

			case HttpRequest::METHOD_DELETE :
				return array(
					new HiddenParameter('guid', true, Parameter::TYPE_INT),
				);

			default :
				return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete(ParameterBag $params) {
		$object = get_entity($params->guid);
		if (!$object || !$object->canDelete()) {
			throw new GraphException('You are not permitted to delete this object', 403);
		}
		return $object->delete();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		return array('nodes' => array(get_entity($params->guid)));
	}

}
