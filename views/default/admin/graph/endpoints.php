<?php
$routes = hypeGraph()->router->exportRoutes();
$allowed_endpoints = (array) elgg_extract('endpoints', $vars, $entity->endpoints);
?>
<table class="elgg-table-alt ws-endpoints-form" style="width:100%">
	<thead>
		<tr>
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