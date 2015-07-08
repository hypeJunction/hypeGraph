<?php

if (!is_callable('hypeApps')) {
	throw new Exception("hypeGraph requires hypeApps");
}

$path = __DIR__;

if (!file_exists("{$path}/vendor/autoload.php")) {
	throw new Exception('hypeGraph can not resolve composer dependencies. Run composer install');
}

require_once "{$path}/vendor/autoload.php";

/**
 * Plugin container
 * @return \hypeJunction\Graph\Plugin
 */
function hypeGraph() {
	return \hypeJunction\Graph\Plugin::factory();
}