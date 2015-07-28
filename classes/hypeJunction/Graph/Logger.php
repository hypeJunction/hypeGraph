<?php

namespace hypeJunction\Graph;

use Exception;

class Logger {

	private $levels = array(
		200 => 'INFO',
		250 => 'NOTICE',
		300 => 'WARNING',
		400 => 'ERROR'
	);

	/**
	 * Router
	 * @var Router
	 */
	private $router;

	/**
	 * Log
	 * @var array
	 */
	private $log = array();

	/**
	 * Vardump
	 * @var array
	 */
	private $vardump = array();

	/**
	 * Constructor
	 *
	 * @param Router $router
	 */
	public function __construct(Router $router = null) {
		$this->router = $router;
	}

	/**
	 * Logs an error or returns an error log
	 *
	 * @param mixed $message Message
	 * @param level $level   Message level
	 * @return array|void
	 */
	public function log($message = null, $level = 'NOTICE') {
		if ($message) {
			if (is_int($level)) {
				$level = (isset($this->levels[$level])) ? $this->levels[$level] : 'NOTICE';
			}
			$this->log[] = array('message' => $message, 'level' => $level);
			if (is_array($message)) {
				$message = implode(', ', $message);
			}
			error_log("$level: $message");
		} else {
			return $this->log;
		}
	}

	/**
	 * Dumps a variable to display in debug mode
	 *
	 * @param string $varname Name of the variable
	 * @param mixed  $var     Value
	 * @return array|void
	 */
	public function vardump($varname = '', $var = null) {
		if ($varname) {
			$this->vardump[] = array('varname' => $varname, 'var' => $var);
		}
		return $this->vardump;
	}

	/**
	 * Do not let Elgg logger output data in API context
	 *
	 * @param string $hook   "debug"
	 * @param string $level  "log"
	 * @param bool   $return Prevent logging
	 * @param array  $params Hook params
	 * @return false
	 */
	public function debugLogHandler($hook, $level, $return, $params) {
		$this->log($params['msg'], $params['level']);
		return false;
	}

	/**
	 * API PHP Error handler function.
	 * This function acts as a wrapper to catch and report PHP error messages.
	 *
	 * @see http://uk3.php.net/set-error-handler
	 *
	 * @param int    $errno    Error number
	 * @param string $errmsg   Human readable message
	 * @param string $filename Filename
	 * @param int    $linenum  Line number
	 * @param array  $vars     Vars
	 *
	 * @return void
	 * @access private
	 *
	 * @throws Exception
	 */
	public function errorHandler($errno, $errmsg, $filename, $linenum, $vars) {

		$time = date("Y-m-d H:i:s (T)");
		$error = "$time: $errmsg in file $filename (line $linenum)";

		switch ($errno) {
			case E_USER_ERROR:
				$this > log($error, 'ERROR');
				// Since this is a fatal error, we want to stop any further execution but do so gracefully.
				throw new Exception($error);

			case E_WARNING :
			case E_USER_WARNING :
				$this->log($error, 'WARNING');
				break;

			default:
				$this->log($error, 'NOTICE');
				break;
		}
	}

	/**
	 * API PHP Exception handler.
	 * This is a generic exception handler for PHP exceptions. This will catch any
	 * uncaught exception, end API execution and return the result to the requestor
	 * as an ErrorResult in the requested format.
	 *
	 * @param Exception $exception Exception
	 *
	 * @return void
	 * @access private
	 */
	public function exceptionHandler($exception) {

		$time = date("Y-m-d H:i:s (T)");
		$msg = $exception->getMessage() ? : elgg_echo('Exception:UnknownType');
		$code = $exception->getCode() ? : 500;

		$error = "$time: $msg in file {$exception->getFile()} (line {$exception->getLine()})";
		$this->log($error, "EXCEPTION");

		$result = new ErrorResult($msg, $code, $exception);

		if ($this->router) {
			$this->router->send($result);
		} else {
			$output = elgg_view('graph/output', array(
				'result' => $result,
			));

			if (elgg_get_viewtype() === 'default') {
				$layout = elgg_view_layout('one_column', array(
					'content' => $output,
				));
				$output = elgg_view_page('', $layout);
			}
			HttpResponse::create($output, $result->getStatusCode())->send();
		}
	}

}
