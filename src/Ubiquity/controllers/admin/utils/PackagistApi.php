<?php
namespace Ubiquity\controllers\admin\utils;

/**
 * Ubiquity\controllers\admin\utils$PackagistApi
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.0
 *
 */
class PackagistApi {

	protected const MAIN_URL = 'https://packagist.org/packages/';

	protected static function _curlJson($url) {
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
		return \json_decode($result, true);
	}

	public static function getVersions($vendor, $package) {
		$url = self::MAIN_URL . "{$vendor}/{$package}.json";
		$result = self::_curlJson($url);
		return \array_keys($result['package']['versions'] ?? []);
	}

	public static function getPackages($vendor) {
		$url = self::MAIN_URL . "list.json?vendor={$vendor}";
		$result = self::_curlJson($url);
		return $result['packageNames'] ?? [];
	}

	public static function getPackageInfos($vendor, $package) {
		$url = self::MAIN_URL . "{$vendor}/{$package}.json";
		return self::_curlJson($url);
	}

	public static function getPackageInfo($vendor, $package, $info) {
		$infos = self::getPackageInfos($vendor, $package);
		return $infos[$info] ?? 'none';
	}

	public static function getPackageType($vendor, $package) {
		return self::getPackageInfo($vendor, $package, 'type');
	}
}

