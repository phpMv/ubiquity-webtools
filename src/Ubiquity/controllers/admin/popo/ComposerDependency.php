<?php
namespace Ubiquity\controllers\admin\popo;

/**
 * Ubiquity\controllers\admin\popo$ComposerDependency
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.0
 *
 */
class ComposerDependency {

	private $name;

	private $optional;

	private $category;

	private $loaded;

	private $part;

	private $version;

	private static $composerContent;

	public function __construct($part = '', $name = '', $optional = true, $category = 'none', $class = null) {
		$this->part = $part;
		$this->loaded = ! isset($class) || \class_exists($class, true);
		$this->name = $name;
		$this->optional = $optional;
		$this->category = $category;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getOptional() {
		return $this->optional;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 *
	 * @return boolean
	 */
	public function getLoaded() {
		return $this->loaded;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getPart() {
		return $this->part;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getVersion() {
		return $this->version;
	}

	/**
	 *
	 * @param mixed $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 *
	 * @param mixed $optional
	 */
	public function setOptional($optional) {
		$this->optional = $optional;
	}

	/**
	 *
	 * @param mixed $category
	 */
	public function setCategory($category) {
		$this->category = $category;
	}

	/**
	 *
	 * @param boolean $loaded
	 */
	public function setLoaded($loaded) {
		$this->loaded = $loaded;
	}

	/**
	 *
	 * @param mixed $part
	 */
	public function setPart($part) {
		$this->part = $part;
	}

	/**
	 *
	 * @param mixed $version
	 */
	public function setVersion($version) {
		$this->version = $version;
	}

	public static function load($dependencies) {
		$result = [];
		$composer = self::getComposer();
		foreach ($dependencies as $part => $deps) {
			$composerPart = $composer[$part] ?? [];
			foreach ($deps as $dependency) {
				$depInstance = new ComposerDependency($part, $dependency['name'], $dependency['optional'], $dependency['category'], $dependency['class']);
				if (isset($composerPart[$dependency['name']])) {
					$depInstance->setVersion($composerPart[$dependency['name']]);
					unset(self::$composerContent[$part][$dependency['name']]);
				}
				$result[] = $depInstance;
			}
			self::addComposerPart($part, $result);
		}
		return $result;
	}

	private static function addComposerPart($part, &$result) {
		foreach (self::$composerContent[$part] as $dep => $version) {
			$depInstance = new ComposerDependency($part, $dep);
			$depInstance->setVersion($version);
			$result[] = $depInstance;
		}
	}

	public static function getComposer() {
		if (! isset(self::$composerContent['require'])) {
			$content = \file_get_contents(\ROOT . './../composer.json');
			self::$composerContent = \json_decode($content, true);
		}
		return self::$composerContent;
	}

	public static function getVersions($vendor, $package) {
		$url = "https://packagist.org/packages/{$vendor}/{$package}.json";
		$curl = \curl_init();
		\curl_setopt($curl, CURLOPT_URL, $url);
		\curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		\curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		\curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		$result = \curl_exec($curl);
		if (! $result) {
			return false;
		}
		\curl_close($curl);
		$result = \json_decode($result, true);
		return \array_keys($result['package']['versions'] ?? []);
	}
}

