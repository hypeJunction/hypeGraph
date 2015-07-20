<?php
$plugin = elgg_get_plugin_from_id('hypeGraph');
if (!$plugin) {
	return;
}

$plugin_settings = $plugin->getAllSettings();
$values = array_keys(array_filter($plugin_settings));
?>

<div>
	<label><?php echo elgg_echo('graph:provider:api_auth') ?></label>
	<?php
	echo elgg_view('input/checkboxes', array(
		'name' => 'api_auth',
		'default' => false,
		'value' => $values,
		'options' => array(
			elgg_echo('graph:provider:auth_api_key') => 'auth_api_key',
			elgg_echo('graph:provider:auth_hmac') => 'auth_hmac',
		//elgg_echo('graph:provider:auth_http_basic_auth') => 'auth_http_basic_auth',
		)
	));
	?>
</div>

<div>
	<label><?php echo elgg_echo('graph:provider:user_auth') ?></label>
	<?php
	echo elgg_view('input/checkboxes', array(
		'name' => 'user_auth',
		'default' => false,
		'value' => $values,
		'options' => array(
			//elgg_echo('graph:provider:auth_userpass') => 'auth_userpass',
			elgg_echo('graph:provider:auth_usertoken') => 'auth_usertoken',
			elgg_echo('graph:provider:auth_consumer_userpass') => 'auth_consumer_userpass',
		)
	));
	?>
</div>

<div class="elgg-foot">
	<?php
	echo elgg_view('input/submit', array(
		'value' => elgg_echo('save'),
	));
	?>
</div>
