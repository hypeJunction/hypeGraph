<?php

namespace hypeJunction\Graph;

use ElggMenuItem;

/**
 * Plugin hooks service
 */
class HookHandlers {

	/**
	 * Config
	 * @var Config
	 */
	private $config;

	/**
	 * Router
	 * @var Router
	 */
	private $router;

	/**
	 * Constructor
	 * 
	 * @param Config $config
	 * @param Router $router
	 */
	public function __construct(Config $config, Router $router) {
		$this->config = $config;
		$this->router = $router;
	}

	/**
	 * Setup API Consumer entity menu
	 *
	 * @param string $hook   "register"
	 * @param string $type   "menu:entity"
	 * @param array  $return Menu
	 * @param array  $params Hook params
	 * @return array
	 */
	function setupEntityMenu($hook, $type, $return, $params) {

		$entity = elgg_extract('entity', $params);

		if (!$entity instanceof Consumer) {
			return;
		}

		if (!$entity->canEdit()) {
			return;
		}

		$return[] = ElggMenuItem::factory(array(
					'name' => 'keygen',
					'text' => elgg_echo('graph:consumers:keygen'),
					'href' => elgg_http_add_url_query_elements('action/admin/graph/consumers/keygen', array(
						'guid' => $entity->guid,
					)),
					'is_action' => true,
					'priority' => 100,
		));

		$return[] = ElggMenuItem::factory(array(
					'name' => 'edit',
					'text' => elgg_echo('edit'),
					'href' => elgg_http_add_url_query_elements('admin/graph/consumers/edit', array(
						'guid' => $entity->guid,
					)),
					'priority' => 200,
		));

		$return[] = ElggMenuItem::factory(array(
					'name' => 'delete',
					'text' => elgg_echo('delete'),
					'href' => elgg_http_add_url_query_elements('action/admin/graph/consumers/delete', array(
						'guid' => $entity->guid,
					)),
					'is_action' => true,
					'data-confirm' => elgg_echo('question:areyousure'),
					'priority' => 900,
		));

		return $return;
	}

	
}
