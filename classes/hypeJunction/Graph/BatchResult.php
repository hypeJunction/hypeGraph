<?php

namespace hypeJunction\Graph;

/**
 * Convenience class to output batch results
 */
class BatchResult extends \hypeJunction\BatchResult {
	
	/**
	 * Export batch into an array
	 *
	 * @param array  $fields    Fields to export
	 * @param bool   $recursive Export owners and containers recursively
	 * @param string $key       Parameter name to hold batch item export
	 * @return stdClass
	 */
	public function export($fields = array(), $recursive = true, $key = 'nodes') {

		hypeGraph()->logger->log(array('BatchOptions' => $this->prepareBatchOptions($this->options)));

		$result = (object) parent::export($fields, $recursive, $key);
		$result->total = $result->count;
		unset($result->count);

		return $result;
	}
}
