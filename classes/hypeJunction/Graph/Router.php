<?php

namespace hypeJunction\Graph;

use ElggEntity;
use Exception;

/**
 * Routing and page handling service
 */
class Router {

	/**
	 * Config
	 * @var Config
	 */
	private $config;

	/**
	 * Nodes
	 * @var Graph
	 */
	private $graph;

	/**
	 * HTTP request
	 * @var HttpRequest
	 */
	private $request;

	/**
	 * HTTP response
	 * @var HttpResponse
	 */
	private $response;

	/**
	 * Logger
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param Config       $config   Config
	 * @param Graph        $graph    Nodes
	 * @param HttpRequest  $request  Http Request
	 * @param HttpResponse $response Http Response
	 * @param Logger       $logger   Logger
	 */
	public function __construct(Config $config, Graph $graph, HttpRequest $request, HttpResponse $response, Logger $logger) {
		$this->config = $config;
		$this->graph = $graph;
		$this->request = $request;
		$this->response = $response;
		$this->logger = $logger;
	}

	/**
	 * Router
	 * /services/<service>/<handler>/<request>
	 * 
	 * @param string $hook   "route"
	 * @param string $type   "services"
	 * @param array  $return Handler ID and segments
	 * @param array  $params Hook params
	 * @return array
	 */
	public function routeServices($hook, $type, $return, $params) {

		$segments = elgg_extract('segments', $params);

		$service = array_shift($segments);
		$handler = array_shift($segments);

		if ($service == 'api' && $handler == 'graph') {
			return array(
				'identifier' => $this->getPageHandlerId(),
				'handler' => $this->getPageHandlerId(),
				'segments' => $segments,
			);
		}

		return $return;
	}

	/**
	 * Router
	 * /action/graph/<endpoint>
	 *
	 * This allows us to use elgg.action() to access graph
	 * 
	 * @param string $hook   "route"
	 * @param string $type   "action"
	 * @param array  $return Handler ID and segments
	 * @param array  $params Hook params
	 * @return array
	 */
	public function routeActions($hook, $type, $return, $params) {

		$segments = elgg_extract('segments', $params);

		$handler = array_shift($segments);

		if ($handler == 'graph') {
			return array(
				'identifier' => $this->getPageHandlerId(),
				'handler' => $this->getPageHandlerId(),
				'segments' => $segments,
			);
		}

		return $return;
	}

	/**
	 * Handles graph requests
	 *
	 * /graph/<node>[/<edge>]
	 *
	 * @param array $segments URL segments
	 * @return bool
	 */
	public function pageHandler($segments) {

		elgg_register_plugin_hook_handler('debug', 'log', array($this->logger, 'debugLogHandler'));

		error_reporting(E_ALL);
		set_error_handler(array($this->logger, 'errorHandler'));
		set_exception_handler(array($this->logger, 'exceptionHandler'));

		try {

			if ($this->request->getUrlSegments()[0] == 'services') {
				elgg_trigger_plugin_hook('auth', 'graph');
			} else {				
				if ($this->request->getMethod() != HttpRequest::METHOD_GET) {
					// graph page handler is being accessed directly, and not routed to from services
					// check csrf tokens
					action_gatekeeper('');
					elgg_gatekeeper();
				}
			}

			elgg_set_context('services');
			elgg_push_context('api');
			elgg_push_context('graph');

			$viewtype = get_input('format', $this->mapViewtype());
			$endpoint = implode('/', $segments);

			if (!elgg_is_registered_viewtype($viewtype)) {
				$viewtype = 'json';
			}

			elgg_set_viewtype($viewtype);

			$result = $this->route($endpoint);
			
		} catch (Exception $ex) {
			$result = new ErrorResult($ex->getMessage(), $ex->getCode(), $ex);
		}

		$fields = get_input('fields', '');
		if (is_string($fields)) {
			$fields = string_to_tag_array('fields');
		}
		
		$params = array(
			'fields' => $fields,
			'recursive' => get_input('recursive', true),
		);

		$this->send($result, $params);
		return true;
	}

	/**
	 * Serves graph endpoint
	 *
	 * @param string $request Graph endpoint to serve, e.g. /me or /35/friends
	 * @return GenericResult
	 * @throws GraphException
	 */
	protected function route($request = '/') {

		$ctrl = $this->getController($request);
		if ($ctrl instanceof ControllerInterface) {
			return $ctrl->call($this->request);
		}

		$segments = explode('/', trim($request, '/'));
		$root_node_id = array_shift($segments);

		$node = $this->graph->get($root_node_id);
		if (!$node) {
			throw new GraphException("Node with id $root_node_id not found", HttpResponse::HTTP_NOT_FOUND);
		}

		$alias = $this->graph->getAlias($node);
		if (!$alias) {
			throw new GraphException("Nodes of this type can not be accessed via web services", 403);
		}

		if ($node instanceof ElggEntity) {
			set_input('guid', $node->guid);
		} else {
			set_input('id', $node->id);
		}

		// Check the hierarchy in ascending order, e.g.
		// :blog/likes
		// :object/likes
		// :entity/likes
		$aliases = array($alias, ":{$node->getType()}");
		if ($node instanceof \ElggEntity) {
			$aliases[] = ':entity';
		} else if ($node instanceof \ElggExtender) {
			$aliases[] = ':extender';
		}

		foreach ($aliases as $alias) {
			$alt_segments = $segments;

			array_unshift($alt_segments, $alias);

			$alt_route = implode('/', $alt_segments);
			$can_access = elgg_trigger_plugin_hook('permissions_check:graph', $alt_route, ['node' => $node], true);
			if (!$can_access) {
				continue;
			}

			$ctrl = $this->getController($alt_route);
			if ($ctrl instanceof ControllerInterface) {
				break;
			}
		}

		if (!$ctrl instanceof ControllerInterface) {
			throw new GraphException("You do not have access to the requested endpoint", 403);
		}

		return $ctrl->call();
	}

	/**
	 * Sends the response using the result
	 * 
	 * @param mixed $result    Result
	 * @param array $params    Additional params
	 *                         $params['fields'] Fields to export
	 *                         $params['recursive'] Export recursively
	 * @return void
	 */
	public function send($result = null, $params = array()) {

		if ($result instanceof HttpResponse) {
			$result->send();
			exit;
		}
		
		// Output the result
		if (!$result instanceof GenericResult) {
			$result = new SuccessResult($result);
		}

		$result = elgg_trigger_plugin_hook('result', 'graph', $params, $result);

		$output = elgg_view('graph/output', array(
			'result' => $result,
			'params' => $params,
		));

		if (elgg_get_viewtype() === 'default') {
			$layout = elgg_view_layout('one_column', array(
				'content' => $output,
			));
			$output = elgg_view_page('', $layout);
		}

		$this->response
				->setStatusCode($result->getStatusCode())
				->setContent($output)
				->prepare($this->request)
				->send();

		exit;
	}

	/**
	 * Returns registered route => controller map
	 * @return ControllerInterface[]
	 */
	public function getRoutes() {
		$routes = array(
			// Front
			'' => Controllers\Front::class,
			// Site
			':site' => Controllers\Site::class,
			':site/users' => Controllers\SiteUsers::class,
			':site/activity' => Controllers\SiteActivity::class,
			// User
			':user' => Controllers\User::class,
			':user/token' => Controllers\AccessToken::class,
			':user/friends' => Controllers\UserFriends::class,
			':user/profile' => Controllers\UserProfile::class,
			// Object
			':object' => Controllers\Object::class,
			// River
			':activity' => Controllers\RiverItem::class,
			':user/activity' => Controllers\UserActivity::class,
			// Comments
			':comment' => Controllers\Comment::class,
			':object/comments' => Controllers\ObjectComments::class,
			// Likes
			':like' => Controllers\Like::class,
			':object/likes' => Controllers\ObjectLikes::class,
		);

		if (elgg_is_active_plugin('groups')) {
			$group_routes = array(
				':group' => Controllers\Group::class,
				':group/members' => Controllers\GroupMembers::class,
				':site/groups' => Controllers\SiteGroups::class,
				':user/groups' => Controllers\UserGroups::class,
				':user/groups/membership' => Controllers\UserGroupsMembership::class,
			);
			$routes = array_merge($routes, $group_routes);
		}
		if (elgg_is_active_plugin('blog')) {
			$blog_routes = array(
				':blog' => Controllers\Blog::class,
				':group/blogs' => elgg_is_active_plugin('groups') ? Controllers\GroupBlogs::class : null,
				':site/blogs' => Controllers\SiteBlogs::class,
				':user/blogs' => Controllers\UserBlogs::class,
			);
			$routes = array_merge($routes, $blog_routes);
		}
		if (elgg_is_active_plugin('file')) {
			$file_routes = array(
				':file' => Controllers\File::class,
				':group/files' => elgg_is_active_plugin('groups') ? Controllers\GroupFiles::class : null,
				':site/files' => Controllers\SiteFiles::class,
				':user/files' => Controllers\UserFiles::class,
			);
			$routes = array_merge($routes, $file_routes);
		}
		if (elgg_is_active_plugin('messages') || elgg_is_active_plugin('hypeInbox')) {
			$file_routes = array(
				':message' => Controllers\Message::class,
				':message/replies' => Controllers\MessageReplies::class,
				':user/messages' => Controllers\UserMessages::class,
			);
			$routes = array_merge($routes, $file_routes);
		}

		if (elgg_is_active_plugin('hypeWall')) {
			$wall_routes = array(
				':wall' => Controllers\Wall::class,
				':group/wall' => elgg_is_active_plugin('groups') ? Controllers\GroupWall::class : null,
				':user/wall' => Controllers\UserWall::class,
			);
			$routes = array_merge($routes, $wall_routes);
		}
		return elgg_trigger_plugin_hook('routes', 'graph', null, array_filter($routes));
	}

	/**
	 * Returns a controller instance
	 *
	 * @param string $request Requested endpoint
	 * @return ControllerInterface|false
	 * @throws GraphException
	 */
	protected function getController($request = '/') {
		$request = trim($request, '/');
		$ctrl = elgg_extract($request, $this->getRoutes());
		if (!$ctrl || !class_exists($ctrl) || !in_array(ControllerInterface::class, class_implements($ctrl))) {
			return false;
		}
		return new $ctrl($this->request, $this->graph);
	}

	/**
	 * Returns page handler ID
	 * @return string
	 */
	public function getPageHandlerId() {
		return hypeGraph()->config->get('pagehandler_id');
	}

	/**
	 * Prefixes the URL with the page handler ID and normalizes it
	 *
	 * @param mixed $url   URL as string or array of segments
	 * @param array $query Query params to add to the URL
	 * @return string
	 */
	public function normalize($url = '', $query = array()) {

		if (is_array($url)) {
			$url = implode('/', $url);
		}

		$url = implode('/', array($this->getPageHandlerId(), $url));

		if (!empty($query)) {
			$url = elgg_http_add_url_query_elements($url, $query);
		}

		return elgg_normalize_url($url);
	}

	/**
	 * Maps Accept headers to an Elgg viewtype
	 * @return string
	 */
	protected function mapViewtype() {

		foreach ($this->request->getAcceptableContentTypes() as $ct) {
			switch ($ct) {
				case 'application/json' :
					return 'json';

				case 'text/html' :
					return 'default';

				case 'application/xml' :
					return 'xml';

				case 'application/rss+xml' :
					return 'rss';
			}
		}
	}

}
