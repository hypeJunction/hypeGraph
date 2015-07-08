<?php

use hypeJunction\Graph\GenericResult;
use hypeJunction\Graph\SuccessResult;

$result = elgg_extract('result', $vars);

if (!$result instanceof GenericResult) {
	$result = new SuccessResult($result);
}

$export = $result->export();
echo json_encode($export);