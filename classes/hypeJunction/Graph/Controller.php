<?php

namespace hypeJunction\Graph;

/**
 * Abstract resource controller
 */
abstract class Controller implements ControllerInterface {

	/**
	 * Http request
	 * @var HttpRequest
	 */
	protected $request;

	/**
	 * Node lib
	 * @var Graph
	 */
	protected $graph;

	/**
	 * Request
	 * 
	 * @param HttpRequest $request Http request
	 * @param Graph       $graph   Node lib
	 */
	public function __construct(HttpRequest $request = null, Graph $graph = null) {
		$this->request = $request;
		$this->graph = $graph;
	}

	/**
	 * Throws API exception for unknown methods
	 * 
	 * @param string $name      Method name
	 * @param array  $arguments Arguments
	 * @thorws GraphException
	 */
	public function __call($name, $arguments) {
		throw new GraphException("Method $name not allowed", HttpResponse::HTTP_METHOD_NOT_ALLOWED);
	}

	/**
	 * {@inheritdoc}
	 */
	public function call() {

		$method = $this->request->getMethod();
		$call = strtolower($method);
		$params = $this->params($method);

		if ($params === false || $params === null || !is_callable(array($this, $call))) {
			throw new GraphException("Method not allowed", HttpResponse::HTTP_METHOD_NOT_ALLOWED);
		}

		return $this->$call(new ParameterBag((array) $params));
	}

	/**
	 * {@inheritdoc}
	 */
	public function get(ParameterBag $params) {
		throw new GraphException("Method not allowed", HttpResponse::HTTP_METHOD_NOT_ALLOWED);
	}

	/**
	 * {@inheritdoc}
	 */
	public function post(ParameterBag $params) {
		throw new GraphException("Method not allowed", HttpResponse::HTTP_METHOD_NOT_ALLOWED);
	}

	/**
	 * {@inheritdoc}
	 */
	public function put(ParameterBag $params) {
		throw new GraphException("Method not allowed", HttpResponse::HTTP_METHOD_NOT_ALLOWED);
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete(ParameterBag $params) {
		throw new GraphException("Method not allowed", HttpResponse::HTTP_METHOD_NOT_ALLOWED);
	}

}
