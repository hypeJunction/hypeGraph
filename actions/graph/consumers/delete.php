<?php

$guid = get_input('guid');
$consumer = get_entity($guid);

if (!$consumer instanceof \hypeJunction\Graph\Consumer || !$consumer->canEdit()) {
	register_error(elgg_echo('graph:consumers:not_found'));
	forward(REFERER);
}

if ($consumer->deleteApiKeys() && $consumer->delete()) {
	system_message(elgg_echo('graph:consumers:delete:success'));
	forward(REFERER);
}

register_error(elgg_echo('graph:consumers:delete:error'));
forward(REFERER);
