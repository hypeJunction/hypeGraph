<?php
$entity = elgg_extract('entity', $vars);
?>

<div>
	<label><?php echo elgg_echo('graph:settings:debug_mode') ?></label>
	<?php
	echo elgg_view('input/dropdown', array(
		'name' => 'params[debug_mode]',
		'value' => $entity->debug_mode,
		'options_values' => array(
			false => elgg_echo('option:no'),
			true => elgg_echo('option:yes'),
		),
	));
	?>
</div>
