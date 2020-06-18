<?php
namespace Ubiquity\controllers\admin\traits;

use Ubiquity\devtools\cmd\Command;
use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\Startup;
use Ubiquity\utils\http\URequest;

/**
 * Ubiquity\controllers\admin\traits$CommandsTrait
 * This class is part of Ubiquity
 *
 * @author jc
 * @version 1.0.0
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 */
trait CommandsTrait {

	protected $devtoolsConfig;

	protected function loadDevtoolsConfig() {
		if (\file_exists(\ROOT . \DS . 'config' . \DS . 'devtoolsConfig.php')) {
			$this->devtoolsConfig = include \ROOT . \DS . 'config' . \DS . 'devtoolsConfig.php';
		}
		$this->devtoolsConfig['cmd-pattern'] ??= 'commands' . \DS . '*.cmd.php';
	}

	protected function getCommandByName($name) {
		$infos = Command::getInfo($name);
		return current($infos)['cmd'] ?? null;
	}

	public function _displayCommand($name = 'version') {
		$cmd = $this->getCommandByName($name);
		if (isset($cmd)) {
			if ($cmd->isImmediate()) {
				$this->sendExecCommand("Ubiquity $name");
			} else {
				$form = $this->jquery->semantic()->htmlForm('frm-command');
				if ($cmd->hasValue()) {
					$form->addInput('value', 'value', 'text', '', $cmd->getValue());
				}
				if ($cmd->hasParameters()) {
					$fields = $form->addFields([], 'Parameters');
					foreach ($cmd->getParameters() as $pname => $param) {
						$values = $param->getValues();
						if ($param->getName() === 'model') {
							$values = CacheManager::getModels(Startup::$config, true);
						}
						if (is_array($values) && count($values) > 0) {
							$dd = $fields->addDropdown($pname, array_combine($values, $values), $param->getName() . '(' . $pname . ')', $param->getDefaultValue());
							$dd->getField()->setProperty('style', 'min-width: 200px!important;');
						} else {
							$fields->addInput($pname, $param->getName() . '(' . $pname . ')', 'text', $param->getDefaultValue(), $pname);
						}
					}
				}
				$form->setSubmitParams($this->_getFiles()
					->getAdminBaseRoute() . '/_sendCommand/' . $name, '#response', [
					'hasLoader' => false
				]);
				$this->jquery->click('#validate-btn', '$("#frm-command").form("submit");');
				$this->jquery->click('#cancel-btn', '$("#command").html("");');
				$this->jquery->renderView($this->_getFiles()
					->getViewDisplayCommandForm(), [
					'cmd' => $cmd
				]);
			}
		}
	}

	public function _sendCommand($name) {
		$cmd = $this->getCommandByName($name);
		$cmdString = 'Ubiquity ' . $name;
		if (isset($cmd)) {
			$post = URequest::getRealPOST();
			if ($cmd->hasValue() && isset($post['value'])) {
				$value = $post['value'];
				$cmdString .= " $value";
			}
			if ($cmd->hasParameters()) {
				foreach ($cmd->getParameters() as $pname => $param) {
					if (isset($post[$pname]) && $post[$pname] != null) {
						$cmdString .= " -$pname=" . $post[$pname];
					}
				}
			}

			$this->sendExecCommand($cmdString);
		}
	}

	protected function sendExecCommand($command) {
		$command = str_replace('\\', '\\\\', $command);
		$this->jquery->post($this->_getFiles()
			->getAdminBaseRoute() . '/_execCommand', '{commands: "' . $command . '"}', '#partial', [
			'before' => '$("#response").html(' . $this->getConsoleMessage_('partial', 'Execute devtools...') . ');',
			'hasLoader' => false,
			'partial' => "$('#partial').html(response);"
		]);
		echo $this->jquery->compile();
	}

	public function _execCommand() {
		header('Content-type: text/html; charset=utf-8');
		$post = URequest::getRealPOST();
		$this->addCloseToMessage();

		$commands = \explode("\n", $post['commands']);
		if (\ob_get_length())
			\ob_end_clean();
		ob_end_flush();
		foreach ($commands as $cmd) {
			echo "<span class='ui teal text'>$cmd</span>\n<pre>";
			flush();
			ob_flush();
			$this->liveExecuteCommand($cmd);
		}
		echo "</pre>";
		echo $this->jquery->compile($this->view);
	}
}

