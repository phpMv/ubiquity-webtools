<?php
namespace Ubiquity\controllers\admin\popo;

use Ubiquity\client\oauth\OAuthAdmin;

/**
 * Ubiquity\controllers\admin\popo$OAuthProvider
 * This class is part of Ubiquity
 *
 * @author jc
 * @version 1.0.0
 *
 */
class OAuthProvider {

	private $name;

	private $enabled;

	private $keys;

	private $values;

	private $type;

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

	public function __construct($name = '', $values = [], $type = 'OAuth2') {
		$this->name = $name;
		$this->values = $values;
		$this->enabled = $values['enabled'] ?? false;
		$this->keys = $values['keys'] ?? [
			'id' => '',
			'secret' => ''
		];
		$this->type = $type;
	}

	public function __get(string $name) {
		return $this->values[$name] ?? null;
	}

	public function needsApplication() {
		return $this->type === 'OAuth2' || $this->type === 'OAuth1';
	}

	public static function load($providers) {
		$refProviders = OAuthAdmin::PROVIDERS;
		$result = [];
		foreach ($providers as $name => $values) {
			$result[] = new OAuthProvider($name, $values, $refProviders[$name]['type'] ?? 'OAuth2');
		}
		return $result;
	}
}

