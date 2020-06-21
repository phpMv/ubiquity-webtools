<?php
namespace Ubiquity\controllers\admin\popo;

class CommandList {

	/**
	 *
	 * @var string
	 */
	private $name;

	/**
	 *
	 * @var array
	 */
	private $commandValues;

	public function __construct(?string $name = "", ?array $commandValues = []) {
		$this->name = $name;
		$this->commandValues = $commandValues;
	}

	/**
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 *
	 * @return array
	 */
	public function getCommandValues() {
		return $this->commandValues;
	}

	/**
	 *
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 *
	 * @param array $commandValues
	 */
	public function setCommandValues($commandValues) {
		$this->commandValues = $commandValues;
	}

	public static function initFromArray(array $array, ?array &$names = null) {
		$result = [];
		$names = [];
		foreach ($array as $name => $commandValuesList) {
			$resultCommands = [];
			foreach ($commandValuesList as $commandValues) {
				$names[$commandValues['command']] = true;
				$resultCommands[] = CommandValues::initFromArray($commandValues);
			}
			$result[] = new CommandList($name, $resultCommands);
		}
		return $result;
	}
}

