<?php

use hypeJunction\Graph\Consumer;

elgg_require_js('graph/edit_consumer');

$entity = elgg_extract('entity', $vars);
if ($entity && !$entity instanceof Consumer) {
	return;
}
?>
<div>
	<label><?php echo elgg_echo('graph:consumers:title') ?></label>
	<div class="elgg-text-help"><?php echo elgg_echo('graph:consumers:title:help') ?></div>
	<?php
	echo elgg_view('input/text', array(
		'name' => 'title',
		'value' => elgg_extract('title', $vars, $entity->title),
		'required' => true,
	));
	?>
</div>
<div>
	<label><?php echo elgg_echo('graph:consumers:description') ?></label>
	<div class="elgg-text-help"><?php echo elgg_echo('graph:consumers:description:help') ?></div>
	<?php
	echo elgg_view('input/plaintext', array(
		'name' => 'description',
		'value' => elgg_extract('description', $vars, $entity->description),
	));
	?>
</div>
<div>
	<label><?php echo elgg_echo('graph:consumers:owner_username') ?></label>
	<div class="elgg-text-help"><?php echo elgg_echo('graph:consumers:owner_username:help') ?></div>
	<?php
	echo elgg_view('input/autocomplete', array(
		'name' => 'owner_username',
		'value' => elgg_extract('owner_username', $vars, ($entity) ? $entity->getOwnerEntity()->username : elgg_get_logged_in_user_entity()->username),
		'match_on' => array('users'),
	));
	?>
</div>

<?php
if (hypeGraph()->config->get('auth_consumer_userpass')) {
	// if config allows authenticating consumer owners with API consumer username and password
	?>
	<div>
		<label><?php echo elgg_echo('graph:consumers:api_user') ?></label>
		<div class="elgg-text-help"><?php echo elgg_echo('graph:consumers:api_user:help') ?></div>
		<div class="clearfix">
			<div class="elgg-col elgg-col-1of3">
				<label><?php echo elgg_echo('graph:consumers:api_username') ?></label>
				<?php
				echo elgg_view('input/text', array(
					'name' => 'api_username',
					'value' => elgg_extract('api_username', $vars, ($entity) ? $entity->getPrivateSetting('api_username') : ''),
				));
				?>
			</div>
			<div class="elgg-col elgg-col-1of3">
				<label><?php echo elgg_echo('graph:consumers:api_password') ?></label>
				<?php
				echo elgg_view('input/password', array(
					'name' => 'api_password',
				));
				?>
			</div>
			<div class="elgg-col elgg-col-1of3">
				<label><?php echo elgg_echo('graph:consumers:api_password2') ?></label>
				<?php
				echo elgg_view('input/password', array(
					'name' => 'api_password2',
				));
				?>
			</div>
		</div>
	</div>
	<?php
}
?>

<div>
	<label><?php echo elgg_echo('graph:consumers:allowed_endpoints') ?></label>
	<div class="elgg-text-help"><?php echo elgg_echo('graph:consumers:allowed_endpoints:help') ?></div>
	<?php
	$routes = hypeGraph()->graph->exportRoutes();
	$allowed_endpoints = (array) elgg_extract('endpoints', $vars, $entity->endpoints);
	?>
	<table class="elgg-table-alt ws-endpoints-form" style="width:100%">
		<thead>
			<tr>
				<th><?php
					echo elgg_view('input/checkbox', array(
						'default' => false,
						'class' => 'ws-endpoints-select-all',
						'title' => elgg_echo('graph:toggle_all'),
					));
					?></th>
				<th><?php echo elgg_echo('graph:endpoint') ?></th>
				<th><?php echo elgg_echo('graph:description') ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($routes as $route) {
				$endpoint = $route['endpoint'];
				?>
				<tr>
					<td>
						<?php
						echo elgg_view('input/checkbox', array(
							'name' => "endpoints[$endpoint]",
							'value' => 1,
							'default' => false,
							'checked' => in_array($endpoint, $allowed_endpoints),
							'class' => 'ws-endpoints-endpoint-enable',
						));
						?>
					</td>
					<td>
						<strong>
							<?php
							echo $endpoint;
							?>
						</strong>
					</td>
					<td>
						<?php
						echo elgg_autop($route['description']);
						if (!empty($route['parameters'])) {
							?>
							<table class="elgg-table mtl mbl">
								<thead>
									<tr>
										<th><?php echo elgg_echo('graph:param') ?></th>
										<th><?php echo elgg_echo('graph:type') ?></th>
										<th><?php echo elgg_echo('graph:required') ?></th>
										<th><?php echo elgg_echo('graph:default') ?></th>
										<th><?php echo elgg_echo('graph:enum_options') ?></th>
										<th><?php echo elgg_echo('graph:description') ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									foreach ($route['parameters'] as $details) {
										?>
										<tr>
											<td><?php echo $details['name'] ?></td>
											<td><?php echo $details['type'] ?></td>
											<td><?php echo (!empty($details['required'])) ? "&times;" : '' ?></td>
											<td><?php echo elgg_extract('default', $details, '') ?></td>
											<td><?php echo implode('<br />', (array) elgg_extract('enum_values', $details, array())) ?></td>
											<td><?php echo elgg_extract('description', $details, '') ?></td>
										</tr>
										<?php
									}
									?>
								</tbody>
							</table>
							<?php
						}
						?>
					</td>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>
</div>
<div class="elgg-foot">
	<?php
	echo elgg_view('input/hidden', array(
		'name' => 'guid',
		'value' => elgg_extract('guid', $vars, $entity->guid),
	));
	echo elgg_view('input/submit', array(
		'value' => elgg_echo('save'),
	));
	?>
</div>
