<?php
namespace Ubiquity\controllers\admin\popo;

use Ubiquity\devtools\cmd\Command;

class CommandValues {

	private $command;

	private $values;

	private $order;

	public function __construct(string $command = "", ?array $values = []) {
		$this->command = $command;
		$this->values = $values;
	}

	/**
	 *
	 * @return string
	 */
	public function getCommand() {
		return $this->command;
	}

	/**
	 *
	 * @return array
	 */
	public function getValues() {
		return $this->values;
	}

	/**
	 *
	 * @return int
	 */
	public function getOrder() {
		return $this->order;
	}

	/**
	 *
	 * @param string $command
	 */
	public function setCommand($command) {
		$this->command = $command;
	}

	/**
	 *
	 * @param array $values
	 */
	public function setValues($values) {
		$this->values = $values;
	}

	/**
	 *
	 * @param int $order
	 */
	public function setOrder($order) {
		$this->order = $order;
	}

	/**
	 *
	 * @return Command|NULL
	 */
	public function getCommandObject(): ?Command {
		$infos = Command::getInfo($this->command);
		return current($infos)['cmd'] ?? null;
	}

	public function __toString() {
		$result = $this->command . ' ';
		$values = $this->values;
		if (isset($values['value'])) {
			$result .= $values['value'] . ' ';
			unset($values['value']);
		}
		$params = [];
		foreach ($values as $k => $v) {
			$params[] = "-$k=$v";
		}
		return $result . implode(' ', $params);
	}

	public function asHtml() {
		$result = '<span class="ui blue text">' . $this->command . '</span> ';
		$values = $this->values;
		if (isset($values['value'])) {
			$result .= '<span class="ui orange text">' . $values['value'] . '</span> ';
			unset($values['value']);
		}
		$params = [];
		foreach ($values as $k => $v) {
			$params[] = "-$k=<span class='ui green text'>$v</span>";
		}
		return $result . implode(' ', $params);
	}

	public static function initFromArray(array $values) {
		return new CommandValues($values['command'], $values['values'] ?? []);
	}
}

