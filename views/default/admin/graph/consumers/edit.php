<?php

$guid = get_input('guid');
$vars['entity'] = get_entity($guid);

if (elgg_is_sticky_form('graph/consumers/edit')) {
	$vars = array_merge($vars, elgg_get_sticky_values('graph/consumers/edit'));
	elgg_clear_sticky_form('graph/consumers/edit');
}

echo elgg_view_form('admin/graph/consumers/edit', array(), $vars);