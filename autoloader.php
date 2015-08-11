<?php

$path = __DIR__;
if (file_exists("{$path}/vendor/autoload.php")) {
	require_once "{$path}/vendor/autoload.php";
}

/**
 * Plugin container
 * @return \hypeJunction\Graph\Plugin
 */
function hypeGraph() {
	return \hypeJunction\Graph\Plugin::factory();
}