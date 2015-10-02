<?php

namespace hypeJunction\Graph;

/**
 * Convenience class to output batch results
 */
class BatchResult extends \hypeJunction\BatchResult {
	
	/**
	 * {@inheritdoc}
	 */
	public function export($fields = array(), $key = 'nodes') {
		hypeGraph()->logger->log(array('BatchOptions' => $this->prepareBatchOptions($this->options)));
		return parent::export($fields, $key);
	}
}
