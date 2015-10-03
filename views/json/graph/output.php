<?php

use hypeJunction\Graph\GenericResult;
use hypeJunction\Graph\SuccessResult;

$result = elgg_extract('result', $vars);

if (!$result instanceof GenericResult) {
	$result = new SuccessResult($result);
}

$params = (array) elgg_extract('params', $vars, array());
$export = $result->export($params);
echo json_encode($export);