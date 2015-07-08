<?php

elgg_register_menu_item('title', array(
	'name' => 'add',
	'text' => elgg_echo('admin:graph:add_consumer'),
	'href' => 'admin/graph/consumers/edit',
	'class' => 'elgg-button elgg-button-action',
));

echo elgg_list_entities(array(
	'types' => 'object',
	'subtypes' => hypeJunction\Graph\Consumer::SUBTYPE,
	'no_results' => elgg_echo('admin:graph:consumers:none'),
));