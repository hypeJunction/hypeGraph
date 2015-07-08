<?php

namespace hypeJunction\Graph;

use ElggPAM;
use ElggUser;
use ErrorResult;
use SecurityException;
use stdClass;

class PAM {

	/**
	 * Config
	 * @var Config
	 */
	private $config;

	/**
	 * User PAM
	 * @var ElggPAM
	 */
	private $pam_user;

	/**
	 * API PAM
	 * @var ElggPAM
	 */
	private $pam_api;

	/**
	 * Session
	 * @var Session
	 */
	private $session;

	/**
	 * Http request
	 * @var HttpRequest
	 */
	private $request;

	/**
	 * Token service
	 * @var TokenService
	 */
	private $tokens;

	/**
	 * Keys service
	 * @var KeysService
	 */
	private $api_keys;

	/**
	 * HMAC Lib
	 * @var HmacService
	 */
	private $hmac;

	/**
	 * HMAC Lib
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param Config       $config
	 * @param ElggPAM      $pam_user
	 * @param ElggPAM      $pam_api
	 * @param Session      $session
	 * @param HttpRequest  $request
	 * @param TokenService $tokens
	 * @param KeysService  $api_keys
	 * @param HmacService  $hmac
	 */
	public function __construct(Config $config, ElggPAM $pam_user, ElggPAM $pam_api, Session $session, HttpRequest $request, TokenService $tokens, KeysService $api_keys, HmacService $hmac, Logger $logger) {
		$this->config = $config;
		$this->pam_user = $pam_user;
		$this->pam_api = $pam_api;
		$this->session = $session;
		$this->request = $request;
		$this->tokens = $tokens;
		$this->api_keys = $api_keys;
		$this->hmac = $hmac;
		$this->logger = $logger;
	}

	/**
	 * Authenticates the API and the user
	 * 
	 * @param string $hook   "auth"
	 * @param string $type   "graph"
	 * @return void
	 */
	public function authenticate($hook, $type) {

		// We want a clean session
		if (elgg_is_logged_in()) {
			logout();
		}

		if ($this->config->get('auth_usertoken')) {
			$this->registerHandler(array($this, 'authUserToken'), 'sufficient', 'user');
		}

		if ($this->config->get('auth_consumer_userpass')) {
			$this->registerHandler(array($this, 'authConsumerUserPass'), 'sufficient', 'user');
		}

		if ($this->config->get('auth_api_key')) {
			$this->registerHandler(array($this, 'authApiKey'), 'sufficient', 'api');
		}

		if ($this->config->get('auth_hmac')) {
			$this->registerHandler(array($this, 'authHmacSignature'), 'sufficient', 'api');
		}

		$api_key = get_input('api_key', $this->request->server->get('HTTP_X_ELGG_APIKEY'));
		$consumer = Consumer::factory($api_key);

		$api_pam_result = $this->pam_api->authenticate(array(
			'api_consumer' => $consumer,
			'api_key' => $api_key,
		));

		if ($api_pam_result == true) {
			$this->session->consumer($consumer);
		} else {
			$message = $this->pam_api->getFailureMessage();
			$this->logger->log($message, 'ERROR');
			throw new \SecurityException("API Authentication Failed", 401);
		}

		$user_pam_result = $this->pam_user->authenticate(array(
			'api_consumer' => $consumer,
			'username' => get_input('username'),
			'password' => get_input('password'),
			'auth_token' => get_input('auth_token'),
		));

		if ($user_pam_result === true) {
			$this->session->user(elgg_get_logged_in_user_entity());
		} else {
			$message = $this->pam_user->getFailureMessage();
			$this->logger->log($message, 'ERROR');
			if ($this->request->getMethod() !== HttpRequest::METHOD_GET) {
				throw new \SecurityException("User Authentication Failed", 401);
			}
		}
	}

	/**
	 * PAM: 'api'
	 * Authenticate API consumer with API Key
	 *
	 * @param array $credentials Credentials
	 * @return bool
	 * @throws SecurityException
	 */
	public function authApiKey($credentials = array()) {

		$api_consumer = elgg_extract('api_consumer', $credentials);
		$api_key = elgg_extract('api_key', $credentials);

		if (!$api_key && !$api_consumer) {
			return false;
		}

		if (!$api_key || !$api_consumer instanceof Consumer) {
			throw new SecurityException(elgg_echo('Exception:MissingAPIKey'), 401);
		}

		$api_user = $this->api_keys->get($api_key);
		if (!$api_user->active || $api_consumer->getPrivateKey() !== $api_user->secret) {
			throw new SecurityException(elgg_echo('Exception:BadAPIKey'), 401);
		}

		return elgg_trigger_plugin_hook('api_key', 'use', $credentials, true);
	}

	/**
	 * PAM: 'api'
	 * Authenticate API consumer with HMAC signature
	 *
	 * @param array $credentials Credentials
	 * @return bool
	 * @throws SecurityException
	 */
	function authHmacSignature($credentials = array()) {

		// Get api header
		$api_header = $this->hmac->getHeaders();

		// Throw security exception
		$this->hmac->validateHeaders($api_header);

		// Pull API user details
		$api_user = $this->api_keys->get($api_header->api_key);

		if (!$api_user || !$api_user->active || $api_header->api_key !== elgg_extract('api_key', $credentials, false)) {
			// No API user or API key in the $_REQUEST doesn't match the key in the header
			throw new SecurityException(elgg_echo('Exception:InvalidAPIKey'), ErrorResult::$RESULT_FAIL_APIKEY_INVALID);
		}

		// Get the secret key
		$secret_key = $api_user->secret;

		// get the query string
		$query = $this->request->server->get('REQUEST_URI');
		$query = substr($query, strpos($query, '?') + 1);

		// calculate expected HMAC
		$posthash = ($api_header->method != 'GET') ? $api_header->posthash : '';
		$hmac = $this->hmac->calculateHmac($api_header->hmac_algo, $api_header->time, $api_header->nonce, $api_header->api_key, $secret_key, $query, $posthash);

		if ($api_header->hmac !== $hmac) {
			throw new SecurityException('HMAC is invalid.  {$api_header->hmac} != [calc]$hmac', 401);
		}

		// Now make sure this is not a replay
		if ($this->hmac->cacheHmacCheckReplay($hmac)) {
			throw new SecurityException(elgg_echo('Exception:DupePacket'), 401);
		}

		// Validate post data
		if ($api_header->method != 'GET') {
			$calculated_posthash = $this->hmac->calculatePostHash(file_get_contents('php://input'), $api_header->posthash_algo);
			if (strcmp($api_header->posthash, $calculated_posthash) != 0) {
				$msg = elgg_echo('Exception:InvalidPostHash', array($calculated_posthash, $api_header->posthash));
				throw new SecurityException($msg, 401);
			}
		}

		return elgg_trigger_plugin_hook('api_key', 'use', $credentials, true);
	}

	/**
	 * PAM: 'user'
	 * Hooks into PAM to check if username and password passed to the rest provider
	 * belong to an API Consumer. Logs in the owner of the API Consumer if credentials match
	 *
	 * @param array $credentials Credentials
	 * @return bool
	 * @throws SecurityException
	 */
	public function authConsumerUserPass($credentials) {

		$consumer = elgg_extract('api_consumer', $credentials);
		$username = elgg_extract('username', $credentials);
		$password = elgg_extract('password', $credentials);

		if (!$consumer instanceof Consumer) {
			// Nothing to validate
			return false;
		}

		if (!$consumer->verifyCredentials($username, $password)) {
			throw new SecurityException('Invalid API consumer username or password', 401);
		}

		$user = $consumer->getOwnerEntity();
		if (!$user instanceof ElggUser || !login($user)) {
			throw new SecurityException('Unable to login the user', 401);
		}

		return ($user->guid == elgg_get_logged_in_user_guid());
	}

	/**
	 * PAM: 'user'
	 * Authenticate user with a token
	 * This examines whether an authentication token is present and returns true if
	 * it is present and is valid. The user gets logged in so with the current
	 * session code of Elgg, that user will be logged out of all other sessions.
	 *
	 * @param array $credentials Credentials
	 * @return bool
	 * @throws SecurityException
	 */
	public function authUserToken($credentials = array()) {

		$auth_token = elgg_extract('auth_token', $credentials);
		if (!$auth_token && $auth_token !== '') {
			return false;
		}

		$user = false;
		$token = $this->tokens->get($auth_token);
		if ($token && $token->validate(elgg_get_site_entity())) {
			$user = get_entity($token->user_guid);
		}

		if (!$user instanceof ElggUser || !login($user)) {
			throw new SecurityException('Unable to login the user', 401);
		}

		return ($user->guid == elgg_get_logged_in_user_guid());
	}

	/**
	 * Register a PAM handler.
	 *
	 * A PAM handler should return true if the authentication attempt passed. For a
	 * failure, return false or throw an exception. Returning nothing indicates that
	 * the handler wants to be skipped.
	 *
	 * Note, $handler must be string callback (not an array/Closure).
	 *
	 * @param callable $handler    Callable global handler function in the format ()
	 * 		                       pam_handler($credentials = null);
	 * @param string   $importance The importance - "sufficient" (default) or "required"
	 * @param string   $policy     The policy type, default is "user"
	 *
	 * @todo Remove once core does not require handlers to be strings
	 * @return bool
	 */
	public function registerHandler(callable $handler, $importance = "sufficient", $policy = "user") {
		global $_PAM_HANDLERS;
		if (!isset($_PAM_HANDLERS[$policy])) {
			$_PAM_HANDLERS[$policy] = array();
		}
		if (is_callable($handler)) {
			$pam = new stdClass();
			$pam->handler = $handler;
			$pam->importance = strtolower($importance);
			$_PAM_HANDLERS[$policy][] = $pam;
			return true;
		}
		return false;
	}

	/**
	 * Checks if the consumer can access a graph endpoint
	 * 
	 * @param string $hook   "permissions_check:graph"
	 * @param string $route  Route
	 * @param bool   $return Current permission
	 * @param array  $params Hook params
	 * @return bool Filtered permission
	 */
	public function checkAccess($hook, $route, $return, $params) {

		$consumer = $this->session->consumer();
		if (!$consumer) {
			return $return;
		}

		$request_type = $this->request->getMethod();
		$request = "{$request_type} /{$route}";

		if (!in_array($request, (array) $consumer->endpoints)) {
			return false;
		}

		return $return;
	}
}
