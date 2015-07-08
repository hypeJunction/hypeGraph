<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\Controller;
use hypeJunction\Graph\GraphException;
use hypeJunction\Graph\HiddenParameter;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\Parameter;
use hypeJunction\Graph\ParameterBag;

class RiverItem extends Controller {

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
		if (!elgg_is_admin_logged_in()) {
			throw new GraphException("You are not permitted to delete river items", 403);
		}
		return elgg_delete_river(array(
			'ids' => sanitize_int($params->id),
		));
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		$river = elgg_get_river(array(
			'ids' => sanitize_int($params->id),
		));
		return array(
			'nodes' => array(
				$river[0]
			)
		);
	}

}
