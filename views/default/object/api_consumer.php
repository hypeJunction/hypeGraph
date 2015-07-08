<?php

$entity = elgg_extract('entity', $vars);

if (!$entity instanceof \hypeJunction\Graph\Consumer) {
	return;
}

$attributes = array(
	elgg_echo('graph:consumers:owner_username') => $entity->getOwnerEntity()->username,
	elgg_echo('graph:consumers:api_username') => $entity->getPrivateSetting('api_username'),
	elgg_echo('graph:consumers:public_key') => $entity->getPublicKey(),
	elgg_echo('graph:consumers:private_key') => $entity->getPrivateKey(),
);

$content = elgg_view('output/longtext', array(
	'value' => $entity->description,
));
foreach ($attributes as $key => $value) {
	if (!$value) {
		continue;
	}
	$label = elgg_format_element('label', array('class' => 'ws-consumer-attribute-label'), $key);
	$value_str = elgg_format_element('span', array('class' => 'ws-consumer-attribute-value'), $value);
	$content .= elgg_format_element('div', array('class' => 'ws-consumer-attribute'), "$label$value_str");
}

$metadata = elgg_view_menu('entity', array(
	'sort_by' => 'priority',
	'entity' => $entity,
	'class' => 'elgg-menu-hz',
		));

echo elgg_view('object/elements/summary', array(
	'entity' => $entity,
	'title' => $entity->title,
	'metadata' => $metadata,
	'subtitle' => $subtitle,
	'content' => $content,
));

