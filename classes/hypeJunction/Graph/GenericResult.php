<?php

namespace hypeJunction\Graph;

use ElggBatch;
use Exception;
use hypeJunction\Graph\BatchResult;
use hypeJunction\Graph\HttpResponse;
use stdClass;

/**
 * GenericResult Result superclass.
 */
abstract class GenericResult {

	/**
	 * The status of the result.
	 * @var int
	 */
	protected $status_code;

	/**
	 * Message returned along with the status which is almost always an error message.
	 * This must be human readable, understandable and localised.
	 * @var string
	 */
	protected $message;

	/**
	 * Result store.
	 * Attach result specific informaton here.
	 * @var mixed. Should probably be an object of some sort.
	 */
	protected $result;

	/**
	 * Exception
	 * @var Exception
	 */
	protected $exception;

	/**
	 * Returns an array of HTTP codes used in the API
	 * @return array
	 */
	protected function getCodes() {
		return array(
			HttpResponse::HTTP_OK,
			HttpResponse::HTTP_CREATED,
			HttpResponse::HTTP_NOT_MODIFIED,
			HttpResponse::HTTP_BAD_REQUEST,
			HttpResponse::HTTP_UNAUTHORIZED,
			HttpResponse::HTTP_FORBIDDEN,
			HttpResponse::HTTP_NOT_FOUND,
			HttpResponse::HTTP_METHOD_NOT_ALLOWED,
			HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
		);
	}

	/**
	 * Return the current status code
	 *
	 * @return string
	 */
	public function getStatusCode() {
		$codes = $this->getCodes();
		$status = (int) $this->status_code;
		return (in_array($status, $codes)) ? $status : HttpResponse::HTTP_OK;
	}

	/**
	 * Return the current status message
	 * @return string
	 */
	public function getStatusMessage() {
		return $this->message ? : 'OK';
	}

	/**
	 * Get normalized result suitable for export
	 * @return array
	 */
	public function getResult() {
		return $this->prepareValue($this->result);
	}

	/**
	 * Prepares a value for export
	 * @param mixed $value Value to prepare
	 * @return stdClass
	 */
	protected function prepareValue($value = null) {
		if ($value instanceof ElggBatch) {
			$return = array('nodes' => array());
			foreach ($value as $v) {
				$return['nodes'][] = $this->prepareValue($v);
			}
			$value = $return;
		}

		if (is_callable(array($value, 'toObject'))) {
			return $value->toObject();
		} else if (is_array($value)) {
			$return = array();
			foreach ($value as $key => $v) {
				$return[$key] = $this->prepareValue($v);
			}
			if (isset($return['nodes'])) {
				if (!isset($return['total'])) {
					$return['total'] = count($return['nodes']);
				}
				if (!isset($return['offset'])) {
					$return['offset'] = 0;
				}
				if (!isset($return['limit'])) {
					$return['limit'] = 0;
				}
				$nodes = $return['nodes'];
				$return['nodes'] = array();
				$i = $return['offset'];
				foreach ($nodes as $node) {
					$i++;
					$return['nodes']["$i"] = $node;
				}
			}
			return $return;
		} else if ($value instanceof BatchResult) {
			return $value->export();
		}
		return $value;
	}

	/**
	 * Prepare API request result
	 * @return stdClass Object containing the result
	 */
	public function export() {

		$result = new stdClass;

		$result->result = $this->getResult();
		$result->status = $this->getStatusCode();
		$result->message = $this->getStatusMessage();

		if (get_input('debug') || hypeGraph()->config->get('debug_mode')) {
			$result->log = hypeGraph()->logger->log();
			$result->viewtype = elgg_get_viewtype();
		}
		if (hypeGraph()->config->get('debug_mode')) {
			$result->debug = new stdClass();
			$result->debug->input = array_merge((array) $_REQUEST, (array) elgg_get_config('input'));
			$result->debug->postdata = file_get_contents('php://input');
			$result->debug->vardump = hypeGraph()->logger->vardump();
			$result->debug->session = new stdClass();
			$consumer = hypeGraph()->session->consumer();
			$user = hypeGraph()->session->user();
			$result->debug->session->logged_in_user = ($user) ? $user->toObject() : null;
			$result->debug->session->consumer = ($consumer) ? $consumer->toObject() : null;
			$result->debug->exception_trace = ($this->exception instanceof Exception) ? $this->exception->getTrace()[0] : null;
		}

		return $result;
	}

}
