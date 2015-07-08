<?php

namespace hypeJunction\Graph;

class Parameter {

	const TYPE_STRING = 'string';
	const TYPE_INT = 'integer';
	const TYPE_ARRAY = 'array';
	const TYPE_BOOL = 'boolean';
	const TYPE_FLOAT = 'float';
	const TYPE_ENUM = 'enum';

	protected $name;
	protected $description;
	protected $type;
	protected $required;
	protected $default;
	protected $value;
	protected $enum_values;

	/**
	 * Constructor
	 *
	 * @param string $name          Parameter name
	 * @param bool   $required      Is input required
	 * @param string $type          One of 5 available types
	 * @param mixed  $default       Default value
	 * @param mixed  $enum_values An array of options for 'enum' type
	 * @param string $desc          Parameter description (if true, will pass "graph:param:$name" to elgg_echo())
	 */
	public function __construct($name, $required = true, $type = self::TYPE_STRING, $default = null, array $enum_values = null, $desc = true) {
		$this->name = $name;
		$this->description = $desc === true ? elgg_echo("graph:param:$name") : $desc;
		$this->type = $type;
		$this->required = (bool) $required;
		$this->default = $default;
		$this->enum_values = (array) $enum_values;
		$this->value = null;
	}

	/**
	 * Returns property value
	 * 
	 * @param string $name Property name
	 * @return mixed
	 */
	public function get($name) {
		return $this->$name;
	}

	/**
	 * Sanitizes and validates user input
	 * @throws GraphException
	 * @return Parameter
	 */
	public function prepare() {
		$this->value = get_input($this->name, $this->default);

		if ($this->type == self::TYPE_ENUM) {
			if ($this->value !== null && !in_array($this->value, $this->enum_values)) {
				$msg = elgg_echo('Exception:UnsupportedEnumValue', array($this->value, $this->name, implode(', ', $this->enum_values)));
				throw new GraphException($msg);
			}
		} else {
			// Cast values to specified type
			if (!settype($this->value, $this->type)) {
				if (isset($this->default)) {
					$this->value = $this->default;
				} else {
					$msg = elgg_echo('Exception:UnrecognisedTypeCast', array($this->type, $this->name));
					throw new GraphException($msg);
				}
			}
		}

		// Validate required values
		if ($this->required) {
			if (($this->type == Parameter::TYPE_ARRAY && empty($this->value)) || $this->value == '' || $this->value == null) {
				$msg = elgg_echo('Exception:MissingParameterInMethod', array($this->name));
				throw new GraphException($msg);
			}
		}

		if ($this->name == 'limit' && $this->value > Graph::LIMIT_MAX) {
			$this->value = Graph::LIMIT_MAX;
		}

		return $this;
	}

	public function toArray() {
		return array_filter(get_object_vars($this));
	}

}
