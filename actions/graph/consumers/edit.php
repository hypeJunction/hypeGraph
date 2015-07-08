<?php

elgg_make_sticky_form('graph/consumers/edit');

$guid = get_input('guid');

if (!$guid) {
	$new_consumer = true;
	$consumer = new \hypeJunction\Graph\Consumer();
} else {
	$new_consumer = false;
	$consumer = get_entity($guid);
}

if (!$consumer instanceof \hypeJunction\Graph\Consumer || !$consumer->canEdit()) {
	register_error(elgg_echo('graph:consumers:not_found'));
	forward(REFERER);
}

$owner_username = get_input('owner_username');
$owner = get_user_by_username($owner_username);

if (!$owner instanceof ElggUser || !$owner->canEdit()) {
	register_error(elgg_echo('graph:users:not_found'));
	forward(REFERER);
}

$consumer->owner_guid = $owner->guid;
$consumer->container_guid = $owner->guid;
$consumer->title = htmlspecialchars(get_input('title', ''), ENT_QUOTES, 'UTF-8');
$consumer->description = htmlspecialchars(get_input('description', ''), ENT_QUOTES, 'UTF-8');

$endpoints = array_keys((array) get_input('endpoints', array()));
$consumer->endpoints = $endpoints;

if ($consumer->save()) {
	if ($new_consumer) {
		$consumer->generateApiKeys();
	}

	$api_username = get_input('api_username');
	$api_password = get_input('api_password');
	$api_password2 = get_input('api_password2');

	if ($api_username && $api_password) {
		try {
			if (trim($api_password) == "" || trim($api_password2) == "") {
				throw new RegistrationException(elgg_echo('RegistrationException:EmptyPassword'));
			}
			if (strcmp($api_password, $api_password2) != 0) {
				throw new RegistrationException(elgg_echo('RegistrationException:PasswordMismatch'));
			}
			if (!validate_password($api_password)) {
				throw new RegistrationException(elgg_echo('registration:passwordnotvalid'));
			}
			if (!validate_username($api_username)) {
				throw new RegistrationException(elgg_echo('registration:usernamenotvalid'));
			}
			$consumer->setCredentials($api_username, $api_password);
		} catch (RegistrationException $ex) {
			register_error($ex->getMessage());
		}
	} else if (!$api_username) {
		$consumer->setCredentials(null, null);
	}

	elgg_clear_sticky_form('graph/consumers/edit');
	system_message(elgg_echo('graph:consumers:edit:success'));
	forward('admin/graph/consumers');
}

register_error(elgg_echo('graph:consumers:edit:error'));
forward(REFERER);
