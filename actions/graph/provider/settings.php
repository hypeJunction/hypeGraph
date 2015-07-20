<?php

$plugin = elgg_get_plugin_from_id('hypeGraph');
if (!$plugin) {
	forward('', '404');
}

$api_auth = get_input('api_auth', array());

$plugin->setSetting('auth_api_key', in_array('auth_api_key', $api_auth));
$plugin->setSetting('auth_hmac', in_array('auth_hmac', $api_auth));
//$plugin->setSetting('auth_http_basic_auth', in_array('auth_http_basic_auth', $api_auth));

$user_auth = get_input('user_auth', array());

$plugin->setSetting('auth_usertoken', in_array('auth_usertoken', $user_auth));
$plugin->setSetting('auth_consumer_userpass', in_array('auth_consumer_userpass', $user_auth));
