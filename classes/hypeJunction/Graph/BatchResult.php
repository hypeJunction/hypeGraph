<?php

namespace hypeJunction\Graph;

use ElggBatch;
use stdClass;

/**
 * Convenience class to output batch results
 */
class BatchResult {

	private $getter;
	private $options;

	/**
	 * Constructor
	 *
	 * @param callable $getter  Getter function
	 * @param array    $options Options
	 */
	public function __construct(callable $getter = null, array $options = array()) {
		$this->getter = $getter;
		$this->options = $this->prepareBatchOptions($options);
	}

	/**
	 * Export batch
	 * @return stdClass
	 */
	public function export() {
		$result = new stdClass;

		$this->options['count'] = true;
		$result->total = (int) call_user_func($this->getter, $this->options);
		unset($this->options['count']);

		$result->limit = elgg_extract('limit', $this->options, elgg_get_config('default_limit'));
		$result->offset = elgg_extract('offset', $this->options, 0);

		$batch = new ElggBatch($this->getter, $this->options);
		$result->nodes = array();
		$i = $result->offset;
		foreach ($batch as $entity) {
			if (is_callable(array($entity, 'toObject'))) {
				$result->nodes["$i"] = $entity->toObject();
			} else {
				$result->nodes["$i"] = $entity;
			}
			$i++;
		}

		hypeGraph()->logger->log(array('BatchOptions' => $this->options));
		
		return $result;
	}

	/**
	 * Prepares batch options
	 *
	 * @param array $options ege* options
	 * @return array
	 */
	protected function prepareBatchOptions(array $options = array()) {

		if (!in_array($this->getter, array(
			'elgg_get_entities',
			'elgg_get_entities_from_metadata',
			'elgg_get_entities_from_relationship',
		))) {
			return $options;
		}
		
		$sort = elgg_extract('sort', $options);
		unset($options['sort']);

		if (!is_array($sort)) {
			$sort = array(
				'time_created' => 'DESC',
			);
		}

		$dbprefix = elgg_get_config('dbprefix');

		$order_by = array();

		foreach ($sort as $field => $direction) {

			$field = sanitize_string($field);
			$direction = strtoupper(sanitize_string($direction));

			if (!in_array($direction, array('ASC', 'DESC'))) {
				$direction = 'ASC';
			}
			
			switch ($field) {

				case 'alpha' :
					if (elgg_extract('types', $options) == 'user') {
						$options['joins']['ue'] = "JOIN {$dbprefix}users_entity ue ON ue.guid = e.guid";
						$order_by[] = "ue.name  {$direction}";
					} else if (elgg_extract('types', $options) == 'group') {
						$options['joins']['ge'] = "JOIN {$dbprefix}groups_entity ge ON ge.guid = e.guid";
						$order_by[] = "ge.name  {$direction}";
					} else if (elgg_extract('types', $options) == 'object') {
						$options['joins']['oe'] = "JOIN {$dbprefix}objects_entity oe ON oe.guid = e.guid";
						$order_by[] = "oe.title {$direction}";
					}
					break;

				case 'type' :
				case 'subtype' :
				case 'guid' :
				case 'owner_guid' :
				case 'container_guid' :
				case 'site_guid' :
				case 'enabled' :
				case 'time_created';
				case 'time_updated' :
				case 'last_action' :
				case 'access_id' :
					$order_by[] = "e.{$field} {$direction}";
					break;
			}
		}

		$options['order_by'] = implode(',', $order_by);
		
		return $options;
	}

}
