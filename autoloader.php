<?php

if (!is_callable('hypeApps')) {
	throw new Exception("hypeGraph requires hypeApps");
}

/**
 * Plugin container
 * @return \hypeJunction\Graph\Plugin
 */
function hypeGraph() {
	return \hypeJunction\Graph\Plugin::factory();
}