<?php

namespace hypeJunction\Graph;

/**
 * Resource controller interface
 */
interface ControllerInterface {

	/**
	 * Constructor
	 * 
	 * @param HttpRequest $request Http Request
	 * @param Graph       $nodes   Nodes lib
	 */
	public function __construct(HttpRequest $request, Graph $nodes);

	/**
	 * Returns parameter config for a given HTTP method
	 * Returns false to indicate that the method is not allowed
	 * 
	 * @param string $method HTTP request method
	 * @return Parameter[]|false
	 */
	public function params($method);

	/**
	 * Executes a GET request
	 *
	 * @param ParameterBag $params Input params
	 * @return mixed
	 * @throws GraphException
	 */
	public function get(ParameterBag $params);

	/**
	 * Executes a POST request
	 *
	 * @param ParameterBag $params Input params
	 * @return mixed
	 * @throws GraphException
	 */
	public function post(ParameterBag $params);

	/**
	 * Executes a PUT request
	 *
	 * @param ParameterBag $params Input params
	 * @return mixed
	 * @throws GraphException
	 */
	public function put(ParameterBag $params);

	/**
	 * Executes a GET request
	 *
	 * @param ParameterBag $params Input params
	 * @return mixed
	 * @throws GraphException
	 */
	public function delete(ParameterBag $params);

	/**
	 * Calls the controller
	 * This prepares the parameters and executes the corresponding method
	 * @return mixed
	 * @throws GraphException
	 */
	public function call();
}
