<?php

$guid = get_input('guid');
$consumer = get_entity($guid);

if (!$consumer instanceof \hypeJunction\Graph\Consumer || !$consumer->canEdit()) {
	register_error(elgg_echo('graph:consumers:not_found'));
	forward(REFERER);
}

$consumer->generateApiKeys();

system_message(elgg_echo('graph:consumers:keygen:success'));
forward(REFERER);