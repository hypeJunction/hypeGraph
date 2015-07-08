<?php

namespace hypeJunction\Graph;

/**
 * Graph service provider
 *
 * @property-read \ElggPlugin                      $plugin
 * @property-read \hypeJunction\Graph\Config       $config
 * @property-read \hypeJunction\Graph\HookHandlers $hooks
 * @property-read \hypeJunction\Graph\Router	   $router
 * @property-read \hypeJunction\Graph\HttpRequest  $request
 * @property-read \hypeJunction\Graph\HttpResponse $response
 * @property-read \hypeJunction\Graph\Session	   $session
 * @property-read \hypeJunction\Graph\PAM          $pam
 * @property-read \ElggPAM                         $pam_api
 * @property-read \ElggPAM                         $pam_user
 * @property-read \hypeJunction\Graph\TokenService $tokens
 * @property-read \hypeJunction\Graph\KeysService  $api_keys
 * @property-read \hypeJunction\Graph\HmacService  $hmac
 * @property-read \hypeJunction\Graph\Logger       $logger
 * @property-read \hypeJunction\Graph\Graph        $graph
 */
final class Plugin extends \hypeJunction\Plugin {

	/**
	 * {@inheritdoc}
	 */
	static $instance;

	/**
	 * {@inheritdoc}
	 */
	public function __construct(\ElggPlugin $plugin) {

		$this->setValue('plugin', $plugin);
		$this->setFactory('config', function (\hypeJunction\Graph\Plugin $p) {
			return new \hypeJunction\Graph\Config($p->plugin);
		});
		$this->setFactory('hooks', function (\hypeJunction\Graph\Plugin $p) {
			return new \hypeJunction\Graph\HookHandlers($p->config, $p->router);
		});
		$this->setFactory('router', function (\hypeJunction\Graph\Plugin $p) {
			return new \hypeJunction\Graph\Router($p->config, $p->graph, $p->request, $p->response, $p->logger);
		});
		$this->setFactory('request', '\hypeJunction\Graph\HttpRequest::createFromGlobals');
		$this->setFactory('response', function(\hypeJunction\Graph\Plugin $p) {
			return \hypeJunction\Graph\HttpResponse::create('');
		});
		
		$this->setClassName('session', '\hypeJunction\Graph\Session');
		$this->setFactory('pam', function (\hypeJunction\Graph\Plugin $p) {
			return new \hypeJunction\Graph\PAM($p->config, $p->pam_user, $p->pam_api, $p->session, $p->request, $p->tokens, $p->api_keys, $p->hmac, $p->logger);
		});
		$this->setFactory('pam_user', function (\hypeJunction\Graph\Plugin $p) {
			return new \ElggPAM('user');
		});
		$this->setFactory('pam_api', function(\hypeJunction\Graph\Plugin $p) {
			return new \ElggPAM('api');
		});
		$this->setFactory('api_keys', function(\hypeJunction\Graph\Plugin $p) {
			return new \hypeJunction\Graph\KeysService($p->config);
		});
		$this->setClassName('tokens', '\hypeJunction\Graph\TokenService');
		$this->setFactory('hmac', function (\hypeJunction\Graph\Plugin $p) {
			return new \hypeJunction\Graph\HmacService($p->request);
		});
		$this->setFactory('logger', function (\hypeJunction\Graph\Plugin $p) {
			return new \hypeJunction\Graph\Logger($p->router);
		});		
		$this->setFactory('graph', function(\hypeJunction\Graph\Plugin $p) {
			return new Graph();
		});
	}

	/**
	 * {@inheritdoc}
	 */
	public static function factory() {
		if (null === self::$instance) {
			$plugin = elgg_get_plugin_from_id('hypeGraph');
			self::$instance = new self($plugin);
		}
		return self::$instance;
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot() {
		elgg_register_event_handler('init', 'system', array($this, 'init'));
	}

	/**
	 * System init callback
	 * @return void
	 */
	public function init() {

		elgg_register_plugin_hook_handler('route', 'services', array($this->router, 'routeServices'));
		elgg_register_plugin_hook_handler('route', 'action', array($this->router, 'routeActions'));
		elgg_register_page_handler($this->router->getPageHandlerId(), array($this->router, 'pageHandler'));

		elgg_register_admin_menu_item('administer', 'provider', 'graph');
		elgg_register_admin_menu_item('administer', 'consumers', 'graph');

		elgg_register_action('admin/graph/provider/settings', $this->plugin->getPath() . 'actions/graph/provider/settings.php', 'admin');
		elgg_register_action('admin/graph/consumers/edit', $this->plugin->getPath() . 'actions/graph/consumers/edit.php', 'admin');
		elgg_register_action('admin/graph/consumers/keygen', $this->plugin->getPath() . 'actions/graph/consumers/keygen.php', 'admin');
		elgg_register_action('admin/graph/consumers/delete',$this->plugin->getPath() . 'actions/graph/consumers/delete.php', 'admin');

		elgg_extend_view('css/admin', 'css/graph/admin.css');

		elgg_register_plugin_hook_handler('register', 'menu:entity', array($this->hooks, 'setupEntityMenu'));

		elgg_register_plugin_hook_handler('auth', 'graph', array($this->pam, 'authenticate'), 999);

		// clean up expired tokens
		elgg_register_plugin_hook_handler('cron', 'daily', array($this->tokens, 'removeExpiredTokens'));

		// Entity export for graph output
		elgg_register_plugin_hook_handler('to:object', 'all', array($this->graph, 'exportSite'));
		elgg_register_plugin_hook_handler('to:object', 'all', array($this->graph, 'exportUser'));
		elgg_register_plugin_hook_handler('to:object', 'all', array($this->graph, 'exportGroup'));
		elgg_register_plugin_hook_handler('to:object', 'all', array($this->graph, 'exportObject'));
		elgg_register_plugin_hook_handler('to:object', 'all', array($this->graph, 'exportRiver'));
		elgg_register_plugin_hook_handler('to:object', 'all', array($this->graph, 'exportRelationship'));
		elgg_register_plugin_hook_handler('to:object', 'all', array($this->graph, 'exportExtender'));

		// Restrict access to graph endpoints for certain consumers
		elgg_register_plugin_hook_handler('permissions_check:graph', 'all', array($this->pam, 'checkAccess'));
	}

}
