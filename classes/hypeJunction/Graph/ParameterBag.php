<?php

namespace hypeJunction\Graph;

class ParameterBag {

	/**
	 * Constructor
	 * 
	 * @param Parameter[] $parameters
	 */
	public function __construct(array $parameters = array()) {
		foreach ($parameters as $parameter) {
			if (!$parameter instanceof Parameter) {
				continue;
			}
			$key = $parameter->get('name');
			$this->$key = $parameter->prepare()->get('value');
		}
	}

}
