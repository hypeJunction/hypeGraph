<?php

namespace hypeJunction\Graph;

/**
 * Convenience class to output batch results
 */
class BatchResult extends \hypeJunction\BatchResult {
	
	/**
	 * Export batch into an array
	 *
	 * @param array $params Export params
	 * @return stdClass
	 */
	public function export(array $params = array()) {

		hypeGraph()->logger->log(array('BatchOptions' => $this->prepareBatchOptions($this->options)));

		$result = (object) parent::export($params);
		$result->nodes = $result->items;
		unset($result->items);
		
		$result->total = $result->count;
		unset($result->count);

		return $result;
	}
}
