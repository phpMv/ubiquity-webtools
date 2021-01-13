<?php
namespace Ubiquity\controllers\admin\traits;

use Ubiquity\devtools\cmd\Command;
use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\Startup;
use Ubiquity\utils\http\URequest;
use Ubiquity\controllers\admin\popo\CommandList;
use Ubiquity\controllers\admin\popo\CommandValues;
use Ajax\semantic\html\elements\HtmlLabel;
use Ajax\semantic\html\collections\form\HtmlForm;
use Ajax\common\html\html5\HtmlInput;
use Ajax\semantic\html\collections\HtmlMessage;
use Ajax\semantic\widgets\datatable\DataTable;
use Ubiquity\devtools\cmd\Parameter;
use Ubiquity\utils\base\UArray;
use Ubiquity\utils\base\UString;
use Ajax\service\JString;

/**
 * Ubiquity\controllers\admin\traits$CommandsTrait
 * This class is part of Ubiquity
 *
 * @author jc
 * @version 1.0.0
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 */
trait CommandsTrait {

	abstract public function showConfMessage($content, $type, $title, $icon, $url, $responseElement, $data, $attributes = NULL): HtmlMessage;

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

	protected function getAvailableCommands() {
		$this->loadDevtoolsConfig();
		\Ubiquity\devtools\cmd\Command::preloadCustomCommands($this->devtoolsConfig);
		return Command::getCommandsWithExclusions();
	}

	public function _myCommands(bool $asString = true) {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$myCommands = CommandList::initFromArray($this->config['commands'] ?? []);
		$dt = $this->jquery->semantic()->dataTable('myCommands', CommandList::class, $myCommands);
		$dt->setFields([
			'name',
			'commandValues'
		]);
		$dt->setValueFunction('commandValues', function ($commands, $commandList) {
			$result = [];
			foreach ($commands as $index => $commandValues) {
				$result[] = $this->_getAdminViewer()
					->getCommandButton($commandValues, $index, $commandList->getName());
			}
			$result[] = '<div class="_help"></div>';
			return $result;
		});
			$dt->addEditDeleteButtons(true,[],function($bt){$bt->addClass($this->style);},function($bt){$bt->addClass($this->style);});
		$dt->insertDefaultButtonIn(2, 'play', '_executeCommandSuite', true, function ($elm, $commandList) {
			$elm->setProperty('data-suite', \urlencode($commandList->getName()));
		});
		$dt->setIdentifierFunction(function ($i, $o) {
			return \urlencode($o->getName());
		});
		$dt->setUrls([
			'refresh' => '',
			'delete' => $baseRoute . '/_deleteCommandSuite',
			'edit' => $baseRoute . '/_myCommandsFrm'
		]);

		$dt->setTargetSelector('#command');
		$this->_setStyle($dt);
		return $this->jquery->renderView($this->_getFiles()
			->getViewDisplayMyCommands(), [], true);
	}

	protected function addMyCommandsBehavior($baseRoute) {
		$this->jquery->postOnClick('._executeOneCommand', $baseRoute . '/_executeOneCommand', '{index:$(this).attr("data-index"),suite:$(this).attr("data-suite")}', '#response', [
			'hasLoader' => 'internal'
		]);
		$this->jquery->postOnClick('._executeCommandSuite', $baseRoute . '/_executeCommandSuite', '{suite:$(this).attr("data-suite")}', '#response', [
			'hasLoader' => 'internal'
		]);
		$this->jquery->getOnClick('._displayMyHelp', $baseRoute . '/_displayHelp', '$(self).closest("tr").find("._help")', [
			'hasLoader' => false,
			'attr' => 'data-command',
			'jsCallback' => $this->activateHelpLabel()
		]);
	}

	protected function activateHelpLabel() {
		return 'var ct=$(event.currentTarget);ct.closest("tr").find(".ui.label.inverted.olive").removeClass("inverted olive").addClass("basic");ct.removeClass("basic").addClass("olive inverted");';
	}

	public function _executeOneCommand() {
		$index = $_POST['index'];
		$suite = $_POST['suite'];
		if (isset($this->config['commands'][$suite][$index])) {
			$commandValues = CommandValues::initFromArray($this->config['commands'][$suite][$index]);
			$this->sendExecCommand('Ubiquity ' . $commandValues->__toString());
		}
	}

	public function _executeCommandSuite() {
		$suite = urldecode($_POST['suite']);

		if (isset($this->config['commands'][$suite])) {
			$resultCommands = [];
			$commandValuesList = $this->config['commands'][$suite];
			foreach ($commandValuesList as $commandValues) {
				$resultCommands[] = 'Ubiquity ' . CommandValues::initFromArray($commandValues);
			}
			$this->sendExecCommand(implode("\n", $resultCommands));
		}
	}

	public function _deleteCommandSuite($name, $conf = true) {
		$dname = urldecode($name);
		$name = urlencode($name);
		if ($conf) {
			$msg = $this->showConfMessage("Do you want to delete the command suite <b>{$dname}</b>?", 'error', 'Suite deleting', 'remove circle', $this->_getFiles()
				->getAdminBaseRoute() . "/_deleteCommandSuite/{$name}/0", '#command', '');
			$this->loadViewCompo($msg);
		} else {
			if (isset($this->config['commands'][$dname])) {
				unset($this->config['commands'][$dname]);
				$this->_saveConfig();
				echo $this->toast("Suite $dname deleted!", 'Suite deleting', 'info', true);
				$this->jquery->execAtLast("$('tr[data-ajax=\"{$name}\"]').remove();");
				echo $this->jquery->compile($this->view);
			}
		}
	}

	public function _saveInMyCommands($name) {
		$suitename = 'suite #' . \count($this->config['commands'] ?? []);
		$this->config['commands'][$suitename][] = [
			'command' => $name,
			'values' => URequest::getRealPOST()
		];
		$this->_myCommandsFrm($suitename);
	}

	public function _newCommandSuite() {
		$name = 'suite #' . \count($this->config['commands'] ?? []);
		$this->_myCommandsFrm($name);
	}

	public function _myCommandsFrm($name) {
		$name = \urldecode($name);
		Command::preloadCustomCommands($this->config);
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$list = [
			$name => $this->config['commands'][$name] ?? []
		];
		$suite = \current(CommandList::initFromArray($list, $names));
		$frm = $this->jquery->semantic()->htmlForm('frm-suite');
		$frm->addClass($this->style);
		$commandNames = Command::getCommandNames([
			'installation' => false,
			'servers' => false
		], $names);
		$input = $frm->addInput('cmd-add');
		$input->setName('');
		$input->getField()->addDataList($commandNames);
		$bt=$input->addAction('Add command', 'right', 'plus', true);
		$bt->addClass($this->style);
		$input->setPlaceholder('New command name');
		$frm->addInput('name', 'Name', 'text', $name);
		$models = CacheManager::getModels(Startup::$config, true);
		foreach ($suite->getCommandValues() as $index => $commandValues) {
			$this->addCommandForm($frm, $name, $commandValues, $models, $index);
		}
		$this->jquery->execOn('drag', '._drag', '$(event.currentTarget).addClass("inverted");');
		$this->jquery->execOn('dragend', '._drag', '$(event.currentTarget).removeClass("inverted");');
		$this->jquery->execOn('dragover', '._drag', 'if(!$(event.currentTarget).hasClass("inverted")) $(event.currentTarget).addClass("blue");');
		$this->jquery->execOn('dragleave', '._drag', '$(event.currentTarget).removeClass("blue");', [
			'stopPropagation' => true
		]);
		$this->jquery->postOnClick('#action-field-cmd-add', $baseRoute . '/_addNewCommandForm', '{commandName: $("#cmd-add").val(), index: $("#frm-suite").find("._drag").length}', '#frm-suite', [
			'hasLoader' => false,
			'jqueryDone' => 'append'
		]);
		$this->jquery->setDraggable('._drag');
		$this->jquery->asDropZone('._drag', 'var dElm=$("#"+_data.id);var tElm=$(event.target).closest("._drag");if(tElm.length && dElm.attr("id")!=tElm.attr("id")){if(tElm.next("._drag").length && tElm.next("._drag").attr("id")==dElm.attr("id")){dElm.detach().insertBefore(tElm);}else{dElm.detach().insertAfter(tElm);
}									$(event.target).closest("form").find("._pos").each(function( index, element ) {
									$(element).val(index);
								});}', [
			'jqueryDone' => null
		]);
		$frm->setValidationParams([
			"on" => "blur",
			"inline" => true
		]);
		$frm->addExtraFieldRule('name', 'empty');
		$frm->setSubmitParams($baseRoute . '/_validateCommandSuite/' . $name, '#command-suites', [
			'hasLoader' => 'internal',
			'jsCallback' => '$("#command").html("");'
		]);

		$this->jquery->click('#cancel-btn', '$("#command").html("");');
		$this->jquery->click('#validate-btn', '$("#frm-suite").form("submit");');
		$this->jquery->click('._close', '$(this).closest(".ui.message._drag").remove();');

		$this->jquery->execAtLast('$("html, body").animate({ scrollTop: $("#command").offset().top}, 1000);');
		$this->jquery->renderView($this->_getFiles()
			->getViewCommandSuiteFrm(), [
			'suite' => $suite,
			'inverted'=>$this->style
		]);
	}

	protected function saveCommandSuite($oldName = '') {
		$elements = $_POST['elm'] ?? [];
		$name = $_POST['name'] ?? 'no name';
		unset($_POST['name']);
		if ($oldName != null && $name !== $oldName) {
			unset($this->config['commands'][$oldName]);
		}
		$result = [];
		$positions = $_POST['pos'] ?? [];
		unset($_POST['elm']);
		unset($_POST['pos']);
		foreach ($elements as $elm) {
			$pos = $positions[$elm];

			$result[$pos]['command'] = $_POST['command'][$elm];
			unset($_POST['command'][$elm]);

			if (isset($_POST['value'][$elm])) {
				$result[$pos]['values'] = [
					'value' => $_POST['value'][$elm]
				];
				unset($_POST['value'][$elm]);
			}

			foreach ($_POST as $k => $v) {
				if (isset($v[$elm]) && $v[$elm] != null && isset($_POST[$k][$elm])) {
					if (array_search($k, [
						'command',
						'value'
					]) === false) {
						$result[$pos]['values'][$k] = $v[$elm];
					}
				}
			}
		}
		$this->config['commands'][$name] = $result;
		$this->_saveConfig();
		echo $this->_myCommands(false);
		$this->jquery->execAtLast("$('[data-tab=myCommands]').click();");
		$this->addMyCommandsBehavior($this->_getFiles()
			->getAdminBaseRoute());
		echo $this->jquery->compile($this->view);
	}

	public function _validateCommandSuite($name = '') {
		$this->saveCommandSuite($name);
	}

	protected function addCommandForm(HtmlForm $form, string $suiteName, $commandValues, $models, $index = 0) {
		$cmd = $commandValues->getCommandObject();
		$values = $commandValues->getValues();
		$cForm = new HtmlForm('frm-command-' . $index);
		$this->getCommandForm($cForm, $cmd, $index, $suiteName, $models, $values);
		$form->addItem($cForm);
	}

	public function _addNewCommandForm() {
		$index = $_POST['index'];
		$commandName = $_POST['commandName'];
		$cForm = $this->jquery->semantic()->htmlForm('frm-command-' . $index);
		$cmd = $this->getCommandByName($commandName);
		$models = CacheManager::getModels(Startup::$config, true);
		$this->getCommandForm($cForm, $cmd, $index, '', $models, []);
		$this->jquery->execAtLast('$("#cmd-add").val("");$("html, body").animate({ scrollTop: $("#frm-command-' . $index . '").offset().top}, 1000);');
		$this->jquery->click('#frm-command-' . $index . ' ._close', '$(this).closest(".ui.message._drag").remove();');
		$this->loadViewCompo($cForm);
	}

	private function getCommandForm(HtmlForm $cForm, Command $cmd, $index, $suite, $models, $values = []) {
		$cForm->addContent(new HtmlInput("elm[$index]", 'hidden', $index));
		$input = $cForm->addContent(new HtmlInput("pos[$index]", 'hidden', $index));
		$input->addClass('_pos');
		$cForm->setTagName('div');
		$cForm->setClass('ui small positive message _drag');
		$cForm->setProperty('draggable', 'true');
		$cForm->addContent("<i class='icon close _close' data-index='$index' data-suite='$suite'></i>");
		$fields = $cForm->addFields();
		$lbl = new HtmlLabel("", 'Ubiquity <span class="ui blue text">' . $cmd->getName() . '</span>');
		$lbl->addClass('large pointing below');
		$lbl->addIcon('code');
		$fields->addItem($lbl);
		$cForm->addContent(new HtmlInput("command[$index]", 'hidden', $cmd->getName()));
		if ($cmd->hasValue()) {
			$fields->addInput("value[$index]", null, 'text', $values['value'] ?? '', $cmd->getValue());
			unset($values['value']);
		}
		if ($cmd->hasParameters()) {
			$this->getCommandFormParameters($cmd, $cForm, $models, $values, $index);
		}
	}

	private function getCommandFormParameters(Command $cmd, HtmlForm $form, array $models, array $existingValues, $index = null) {
		if ($cmd->hasParameters()) {
			$fields = $form->addFields([], 'Parameters');
			foreach ($cmd->getParameters() as $pname => $param) {
				$id = ($index !== null) ? $pname . "[$index]" : $pname;
				$dValue = $param->getDefaultValue() ?? '';
				$values = $param->getValues();
				$name = $param->getName();
				if ($name === 'model') {
					$values = $models;
				}
				if (is_array($values) && count($values) > 0) {
					if ($values == [
						'true',
						'false'
					]) {
						$ck = $fields->addCheckbox($id, $name . " ($pname)", 'true');
						$ck->setChecked(UString::isBooleanTrue($dValue));
					} else {
						$dd = $fields->addDropdown($id, array_combine($values, $values), $name . "( $pname)", $existingValues[$pname] ?? $dValue, strpos($dValue, ',') !== false);
						$dd->getField()->setProperty('style', 'min-width: 200px!important;');
					}
				} else {
					$fields->addInput($id, $name . "( $pname)", 'text', $existingValues[$pname] ?? $dValue, $pname);
				}
			}
		}
	}

	public function _displayCommand($name = 'version') {
		Command::preloadCustomCommands($this->config);
		$cmd = $this->getCommandByName($name);
		if (isset($cmd)) {
			if ($cmd->isImmediate()) {
				$this->sendExecCommand("Ubiquity $name");
			} else {
				$form = $this->jquery->semantic()->htmlForm('frm-command');
				$form->addClass($this->style);
				if ($cmd->hasValue()) {
					$form->setValidationParams([
						'on' => 'blur',
						'inline' => true
					]);
					$value = $cmd->getValue();
					$form->addInput('value', $value, 'text', '', $value);
					if ($cmd->hasRequiredValue()) {
						$form->addFieldRule(0, 'empty', "$value must have a value");
					}
				}
				if ($cmd->hasParameters()) {
					$models = CacheManager::getModels(Startup::$config, true);
					$this->getCommandFormParameters($cmd, $form, $models, []);
				}
				$form->setSubmitParams($this->_getFiles()
					->getAdminBaseRoute() . '/_sendCommand/' . $name, '#response', [
					'hasLoader' => false
				]);
				$this->jquery->click('#validate-btn', '$("#frm-command").form("submit");');
				$this->jquery->click('#cancel-btn', '$("#command").html("");');
				$this->jquery->postFormOnClick('#save-btn', $this->_getFiles()
					->getAdminBaseRoute() . '/_saveInMyCommands/' . $name, 'frm-command', '#command', [
					'hasLoader' => 'internal'
				]);
				$this->jquery->execAtLast('$("html, body").animate({ scrollTop: $("#command").offset().top}, 1000);');

				$this->jquery->renderView($this->_getFiles()
					->getViewDisplayCommandForm(), [
					'cmd' => $cmd,
					'inverted'=>$this->style
				]);
			}
		}
	}

	public function _displayHelp($commandName) {
		Command::preloadCustomCommands($this->config);
		$cmd = $this->getCommandByName($commandName);
		if (isset($cmd)) {
			$msg = $this->jquery->semantic()->htmlMessage('help-' . $commandName);
			$msg->addClass('visibleover olive '.$this->style);
			$msg->setIcon('circle question');
			$v = "";
			if ($cmd->hasValue()) {
				$v = "<span class='ui green text'>{$cmd->getValue()}</span>";
			}
			$msg->addHeader("<div><b><span class='ui black text'>{$commandName}</span></b> {$v}</div>");
			$msg->addContent('<div><span class="ui blue text">' . $cmd->getDescription() . '</span></div>');

			$aliases = $cmd->getAliases();
			if (count($aliases) > 0) {
				$msg->addContent('<h5>Aliases</h5>');
				$msg->addContent("<div><b><span class='ui black text'>" . \implode(', ', $aliases) . "</span></b></div>");
			}
			if ($cmd->hasParameters()) {
				$msg->addContent('<h5>Parameters</h5>');
				$parameters = $cmd->getParameters();
				$dt = new DataTable('dt-' . $commandName, Parameter::class, $parameters);
				$dt->setFields([
					'name',
					'description',
					'values',
					'defaultValue'
				]);
				$dt->setValueFunction('name', function ($v) {
					return "<b><span class='ui brown text'>$v</span></b>";
				});
				$dt->setValueFunction('values', function ($v) {
					return "<pre>" . \json_encode($v, (\count($v) > 3) ? JSON_PRETTY_PRINT : null) . "</pre>";
				});
				$dt->setValueFunction('defaultValue', function ($v) {
					return "<pre>$v</pre>";
				});
				$dt->addClass('compact '.$this->style);
				$dt->setVisibleHover(false);
				$msg->addContent($dt);
			}

			$examples = $cmd->getExamples();
			if (count($examples) > 0) {
				$msg->addContent('<h5>Samples</h5><ul>');
				if (UArray::isAssociative($examples)) {
					foreach ($cmd->getExamples() as $desc => $sample) {
						$msg->addContent("<li>$sample <span class='ui black text'><br>$desc</span></li>");
					}
				} else {
					foreach ($cmd->getExamples() as $sample) {
						$msg->addContent("<li>$sample</li>");
					}
				}
				$msg->addContent('</ul>');
			}
			$msg->setDismissable();
			$msg->addEvent('close-message', 'var ct=$(event.currentTarget);ct.closest("tr").find(".ui.label.inverted.olive").removeClass("inverted olive").addClass("basic");');
			$id = JString::cleanIdentifier($commandName);
			$this->jquery->execAtLast('$("html, body").animate({ scrollTop: $("#help-' . $id . '").offset().top}, 1000);$("#help-' . $id . '").closest("tr").mouseover();');
			$this->loadViewCompo($msg);
		}
	}

	public function _sendCommand($name) {
		Command::preloadCustomCommands($this->config);
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
						$cmdString .= " -$pname=" . $this->safeValue($post[$pname]);
					}
				}
			}

			$this->sendExecCommand($cmdString);
		}
	}

	protected function safeValue($v) {
		if (\strpos($v, ' ') !== false && \trim($v, '"') === $v) {
			return '"' . $v . '"';
		}
		return $v;
	}

	protected function sendExecCommand($command) {
		$command = str_replace('\\', '\\\\', $command);
		$command = str_replace("\n", '%nl%', $command);
		$this->jquery->post($this->_getFiles()
			->getAdminBaseRoute() . '/_execCommand', '{commands: "' . \addslashes($command) . '"}', '#partial', [
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
		$postCommands = str_replace('%nl%', "\n", $post['commands']);
		$commands = \explode("\n", $postCommands);

		if (\ob_get_length())
			\ob_end_clean();
		ob_end_flush();
		foreach ($commands as $cmd) {
			echo "<span class='ui teal text'>$cmd</span>\n<pre style='line-height: 1.25em;white-space: pre-wrap;'>";
			flush();
			ob_flush();
			$this->liveExecuteCommand($cmd);
		}
		echo "</pre>";
		echo $this->jquery->compile($this->view);
	}
}

