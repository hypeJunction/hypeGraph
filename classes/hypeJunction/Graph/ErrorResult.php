<?php

namespace hypeJunction\Graph;

use Exception;
use hypeJunction\Graph\GenericResult;
use hypeJunction\Graph\HttpResponse;

/**
 * Error result
 */
class ErrorResult extends GenericResult {

	/**
	 * A new error result
	 *
	 * @param string    $message   Message
	 * @param int       $code      Error Code
	 * @param Exception $exception Exception object
	 *
	 * @return void
	 */
	public function __construct($message, $code = null, $exception = null) {
		$this->status_code = $code ?: HttpResponse::HTTP_BAD_REQUEST;
		$this->message = $message;
		$this->exception = $exception;
	}
	
}
