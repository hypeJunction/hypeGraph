<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class Like extends Controller {

	/**
	 * {@inheritdoc}
	 */
	public function params($method) {

		switch ($method) {
			case HttpRequest::METHOD_GET :
				return array(
					new HiddenParameter('id', true, Parameter::TYPE_INT),
				);

			case HttpRequest::METHOD_DELETE :
				return array(
					new HiddenParameter('id', true, Parameter::TYPE_INT),
				);

			default :
				return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete(ParameterBag $params) {
		$like = elgg_get_annotation_from_id($params->id);
		if (!$like || !$like->canEdit()) {
			throw new GraphException('You are not permitted remove this like', 403);
		}
		return $like->delete();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		return array('nodes' => array(elgg_get_annotation_from_id($params->id)));
	}

}
