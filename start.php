<?php

/**
 * RESTful Graph
 *
 * @package hypeJunction
 * @subpackage hypeGraph
 *
 * @author Ismayil Khayredinov <ismayil.khayredinov@gmail.com>
 */
try {
	require_once __DIR__ . '/autoloader.php';
	hypeGraph()->boot();
} catch (Exception $ex) {
	register_error($ex->getMessage());
}