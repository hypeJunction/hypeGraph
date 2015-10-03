<?php

namespace hypeJunction\Graph;

use Exception;
use hypeJunction\Controllers\HttpController;
use hypeJunction\Entities\Exporter;
use stdClass;

/**
 * Abstract API Resource
 */
class Graph extends Exporter {

	const LIMIT_MAX = 50;

	/**
	 * Returns all configured routes
	 * @return array
	 */
	public function exportRoutes() {
		$output = array();
		$request_types = array(
			HttpRequest::METHOD_GET,
			HttpRequest::METHOD_POST,
			HttpRequest::METHOD_PUT,
			HttpRequest::METHOD_DELETE
		);
		$routes = hypeGraph()->router->getRoutes();

		foreach ($routes as $route => $ctrl) {
			try {
				if (!class_exists($ctrl) || !in_array(HttpController::class, class_implements($ctrl))) {
					continue;
				}

				$ctrl = new $ctrl();
				foreach ($request_types as $request_type) {
					$method_parameters = $ctrl->params($request_type);
					if (!is_array($method_parameters) || !is_callable(array($ctrl, strtolower($request_type)))) {
						continue;
					}
					$visible_parameters = array_filter($method_parameters, function($pe) {
						return !$pe instanceof HiddenParameter;
					});
					$parameters = array_map(function($pe) {
						if ($pe instanceof Parameter) {
							return $pe->toArray();
						}
					}, $visible_parameters);
					$request = "{$request_type} /{$route}";
					$output[] = array_filter(array(
						'method' => $route,
						'call_method' => $request_type,
						'endpoint' => $request,
						'description' => elgg_echo($request),
						'parameters' => !empty($parameters) ? $parameters : null,
					));
				}
			} catch (Exception $ex) {
				// do nothing
			}
		}
		return $output;
	}

	/**
	 * Site export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "entity"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 * @deprecated since version 1.3
	 */
	public function exportSite($hook, $type, $return, $params) {
		return $return;
	}

	/**
	 * User export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "entity"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 * @deprecated since version 1.3
	 */
	public function exportUser($hook, $type, $return, $params) {
		return $return;
	}

	/**
	 * Object export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "entity"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 * @deprecated since version 1.3
	 */
	public function exportObject($hook, $type, $return, $params) {
		return $return;
	}

	/**
	 * Message export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "entity"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 * @deprecated since version 1.3
	 */
	public function exportMessage($hook, $type, $return, $params) {
		return $return;
	}

	/**
	 * Wall post export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "entity"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 * @deprecated since version 1.3
	 */
	public function exportWall($hook, $type, $return, $params) {
		return $return;
	}

	/**
	 * Group export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "entity"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 * @deprecated since version 1.3
	 */
	public function exportGroup($hook, $type, $return, $params) {
		return $return;
	}

	/**
	 * River export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "river_item"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 * @deprecated since version 1.3
	 */
	public function exportRiver($hook, $type, $return, $params) {
		return $return;
	}

	/**
	 * Relationship export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   "relationship"
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 */
	public function exportRelationship($hook, $type, $return, $params) {
		return $return;
	}

	/**
	 * Extender export
	 *
	 * @param string   $hook   "to:object"
	 * @param string   $type   Metadata or annotation name
	 * @param stdClass $return Export object
	 * @param array    $params Hook params
	 * @return stdClass
	 * @deprecated since version 1.3
	 */
	public function exportExtender($hook, $type, $return, $params) {
		return $return;
	}

}
