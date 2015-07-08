<?php

namespace hypeJunction\Graph;

/**
 * Successful result class
 */
class SuccessResult extends GenericResult {

	public static $RESULT_SUCCESS = 200;

	/**
	 * Constructor
	 *
	 * @param string $data Result data
	 */
	public function __construct($data = array(), $status_code = HttpResponse::HTTP_OK) {
		$this->result = $data;
		$this->status_code = $status_code;
	}

}
