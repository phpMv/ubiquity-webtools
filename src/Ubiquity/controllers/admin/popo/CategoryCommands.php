<?php
namespace Ubiquity\controllers\admin\popo;

class CategoryCommands {

	private $category;

	private $commands;

	public function __construct(?string $category = null, ?array $commands = []) {
		$this->category = $category;
		$this->commands = $commands;
	}

	public function getCategory() {
		return $this->category;
	}

	public function getCommands() {
		return $this->commands;
	}

	public function addCommand($command) {
		$this->commands[] = $command;
	}

	public static function init($excludeCategories, $commands) {
		\usort($commands, function ($left, $right) {
			return $left->getCategory() <=> $right->getCategory();
		});
		$result = [];
		foreach ($commands as $command) {
			$cat = $command->getCategory();
			if (! isset($excludeCategories[$cat])) {
				if (! isset($result[$cat])) {
					$result[$cat] = new CategoryCommands($cat);
				}
				$result[$cat]->addCommand($command);
			}
		}
		return $result;
	}
}

