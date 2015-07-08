<?php

namespace hypeJunction\Graph\Controllers;

use hypeJunction\Graph\Controller;
use hypeJunction\Graph\HttpRequest;
use hypeJunction\Graph\ParameterBag;

/**
 * Graph index controller
 */
class Front extends Controller {

	/**
	 * {@inheritdoc}
	 */
	public function params($method) {
		if ($method == HttpRequest::METHOD_GET) {
			return array();
		}
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		return $this->graph->exportRoutes();
	}

}
