<?php
namespace Ubiquity\controllers\admin\popo;

class OAuthProvider {

	private $name;

	private $enabled;

	private $keys;

	private $values;

	/**
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 *
	 * @return boolean
	 */
	public function getEnabled() {
		return $this->enabled;
	}

	/**
	 *
	 * @return array
	 */
	public function getKeys() {
		return $this->keys;
	}

	/**
	 *
	 * @param boolean $enabled
	 */
	public function setEnabled($enabled) {
		$this->enabled = $enabled;
	}

	/**
	 *
	 * @param array $keys
	 */
	public function setKeys($keys) {
		$this->keys = $keys;
	}

	public function __construct($name = '', $values = []) {
		$this->name = $name;
		$this->values = $values;
		$this->enabled = $values['enabled'] ?? false;
		$this->keys = $values['keys'] ?? [
			'id' => '',
			'secret' => ''
		];
	}

	public function __get(string $name) {
		return $this->values[$name] ?? null;
	}

	public static function load($providers) {
		$result = [];
		foreach ($providers as $name => $values) {
			$result[] = new OAuthProvider($name, $values);
		}
		return $result;
	}
}

