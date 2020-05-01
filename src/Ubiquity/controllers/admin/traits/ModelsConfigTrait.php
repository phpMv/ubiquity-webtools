<?php
namespace Ubiquity\controllers\admin\traits;

use Ubiquity\utils\http\URequest;
use Ajax\semantic\html\collections\menus\HtmlMenu;
use Ajax\semantic\html\modules\HtmlDropdown;
use Ubiquity\orm\creator\yuml\YumlModelsCreator;
use Ubiquity\controllers\Startup;
use Ubiquity\controllers\admin\UbiquityMyAdminFiles;
use Ajax\semantic\components\validation\Rule;
use Ubiquity\orm\DAO;
use Ajax\JsUtils;
use Ubiquity\db\Database;
use Ubiquity\exceptions\DBException;
use Ubiquity\db\SqlUtils;

/**
 *
 * @author jc
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 * @property View $view
 */
trait ModelsConfigTrait {
	use CheckTrait;

	abstract public function _getAdminData();

	abstract public function _getAdminViewer();

	/**
	 *
	 * @return UbiquityMyAdminFiles
	 */
	abstract public function _getFiles();

	private $activeStep = 5;

	private $engineering = "forward";

	private $steps = [
		"forward" => [
			[
				"toggle on",
				"Engineering",
				"Forward"
			],
			[
				"settings",
				"Conf",
				"Database configuration"
			],
			[
				"database",
				"Connexion",
				"Database connexion"
			],
			[
				"sticky note",
				"Models",
				"Models generation"
			],
			[
				"lightning",
				"Cache",
				"Models cache generation"
			]
		],
		"reverse" => [
			[
				"toggle off",
				"Engineering",
				"Reverse"
			],
			[
				"sticky note",
				"Models",
				"Models configuration/implementation"
			],
			[
				"lightning",
				"Cache",
				"Models cache generation"
			],
			[
				"database plus",
				"Database",
				"Database creation"
			]
		]
	];

	public function _getModelsStepper() {
		$this->_checkStep();
		$stepper = $this->jquery->semantic()->htmlStep("stepper");
		$stepper->setStartStep(1);
		$steps = $this->steps[$this->engineering];
		$count = \sizeof($steps);
		$completed = ($this->_isModelsCompleted()) ? "completed" : "";
		for ($index = 0; $index < $count; $index ++) {
			$step = $steps[$index];
			$step = $stepper->addStep($step);
			if ($index === 0) {
				$step->addClass("_noStep")->getOnClick($this->_getFiles()
					->getAdminBaseRoute() . "/_changeEngineering/" . $this->engineering . "/" . $completed, "#stepper", [
					"jqueryDone" => "replaceWith",
					"hasLoader" => false
				]);
			} else {
				$step->setProperty("data-ajax", $index);
			}
		}
		$stepper->setActiveStep($this->activeStep);
		$_SESSION["step"] = $this->activeStep;
		$stepper->asLinks();
		$this->jquery->getOnClick(".step:not(._noStep)", $this->_getFiles()
			->getAdminBaseRoute() . "/_loadModelStep/" . $this->engineering . "/", "#models-main", [
			"attr" => "data-ajax"
		]);
		return $stepper;
	}

	public function _isModelsCompleted() {
		return \sizeof($this->steps[$this->engineering]) === $this->activeStep;
	}

	public function _changeEngineering($oldEngineering, $completed = null) {
		$this->engineering = "forward";
		if ($oldEngineering === "forward") {
			$this->engineering = "reverse";
		}
		$this->activeStep = \sizeof($this->getModelSteps());
		echo $this->_getModelsStepper();
		if ($completed !== "completed")
			$this->jquery->get($this->_getFiles()
				->getAdminBaseRoute() . "/_loadModelStep/" . $this->engineering . "/" . $this->activeStep, "#models-main");
		echo $this->jquery->compile($this->view);
	}

	protected function getModelSteps() {
		return $this->steps[$this->engineering];
	}

	protected function getActiveModelStep() {
		if (isset($this->getModelSteps()[$this->activeStep]))
			return $this->getModelSteps()[$this->activeStep];
		return end($this->steps[$this->engineering]);
	}

	protected function getNextModelStep() {
		$steps = $this->getModelSteps();
		$nextIndex = $this->activeStep + 1;
		if ($nextIndex < \sizeof($steps))
			return $steps[$nextIndex];
		return null;
	}

	public function _loadModelStep($engineering = null, $newStep = null) {
		if (isset($engineering))
			$this->engineering = $engineering;
		if (isset($newStep)) {
			$this->_checkStep($newStep);
			if ($newStep !== @$_SESSION["step"]) {
				if (isset($_SESSION["step"])) {
					$oldStep = $_SESSION["step"];
					$this->jquery->execAtLast('$("#item-' . $oldStep . '.step").removeClass("active");');
				}
			}
			$this->jquery->execAtLast('$("#item-' . $newStep . '.step").addClass("active");');
			$this->activeStep = $newStep;
			$_SESSION["step"] = $newStep;
		}

		$this->displayAllMessages();

		echo $this->jquery->compile($this->view);
	}

	public function _importFromYuml() {
		$yumlContent = "[User|«pk» id:int(11);name:varchar(11)],[Groupe|«pk» id:int(11);name:varchar(11)],[User]0..*-0..*[Groupe]";
		$bt = $this->jquery->semantic()->htmlButton("bt-gen", "Generate models", "green fluid");
		$bt->postOnClick($this->_getFiles()
			->getAdminBaseRoute() . "/_generateFromYuml", "{code:$('#yuml-code').val()}", "#stepper", [
			"attr" => "",
			"jqueryDone" => "replaceWith"
		]);
		$menu = $this->_yumlMenu("/_updateYumlDiagram", "{refresh:'true',code:$('#yuml-code').val()}", "#diag-class");
		$this->jquery->exec('$("#modelsMessages-success").hide()', true);
		$menu->compile($this->jquery, $this->view);
		$form = $this->jquery->semantic()->htmlForm("frm-yuml-code");
		$textarea = $form->addTextarea("yuml-code", "Yuml code", \str_replace(",", ",\n", $yumlContent . ""));
		$textarea->getField()->setProperty("rows", 20);
		$diagram = $this->_getYumlImage("plain", $yumlContent);
		$this->jquery->execOn("keypress", "#yuml-code", '$("#yuml-code").prop("_changed",true);');
		$this->jquery->execAtLast('$("#yuml-tab .item").tab({onVisible:function(tab){
				if(tab=="diagram" && $("#yuml-code").prop("_changed")==true){
					' . $this->_yumlRefresh("/_updateYumlDiagram", "{refresh:'true',code:$('#yuml-code').val()}", "#diag-class") . '
				}
			}
		});');
		$this->jquery->compile($this->view);
		$this->loadView($this->_getFiles()
			->getViewYumlReverse(), [
			"diagram" => $diagram
		]);
	}

	public function _generateFromYuml() {
		if (URequest::isPost()) {
			$config = Startup::getConfig();
			$yumlGen = new YumlModelsCreator();
			$yumlGen->initYuml($_POST["code"]);
			\ob_start();
			$yumlGen->create($config);
			\ob_get_clean();
			Startup::forward($this->_getFiles()->getAdminBaseRoute() . "/_changeEngineering/completed");
		}
	}

	public function _updateYumlDiagram() {
		if (URequest::isPost()) {
			$type = $_POST["type"];
			$size = $_POST["size"];
			$yumlContent = $_POST["code"];
			$this->jquery->exec('$("#yuml-code").prop("_changed",false);', true);
			echo $this->_getYumlImage($type . $size, $yumlContent);
			echo $this->jquery->compile();
		}
	}

	private function getJsDatabaseTypes($values) {
		$html = '';
		foreach ($values as $v) {
			$html .= '<div class="item" data-value="' . $v . '">' . $v . '</div>';
		}
		return $html;
	}

	private function getAllJsDatabaseTypes($name, $wrappers) {
		$array = [];
		foreach ($wrappers as $wrapperClass) {
			$types = Database::getAvailableDrivers($wrapperClass);
			$array[$wrapperClass] = $this->getJsDatabaseTypes($types);
		}
		return 'var ' . $name . '=' . \json_encode($array) . ';';
	}

	public function _frmAddNewDbConnection() {
		$v = (object) [
			'wrapper' => \Ubiquity\db\providers\pdo\PDOWrapper::class,
			'type' => 'mysql',
			'dbName' => '',
			'serverName' => '127.0.0.1',
			'port' => 3306,
			'options' => [],
			'user' => 'root',
			'password' => '',
			'cache' => false
		];
		$dbForm = $this->_getAdminViewer()->getDatabaseDataForm($v);
		$frm = $this->jquery->semantic()->htmlForm("frm-frmDeConfig");
		$frm->addExtraFieldRule("database-dbName", "empty");
		$frm->addExtraFieldRules("connection-name", [
			"empty",
			[
				"regExp[/^[a-z0-9]{2,}$/]",
				"Please enter a valid name with 2 lower letters minimum and no special characters"
			],
			[
				"checkConnectionName",
				"This connection {value} already exists!"
			]
		]);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkConnectionName", $this->_getFiles()
			->getAdminBaseRoute() . "/_checkConnectionName", "{}", "result=data.result;", "postForm", [
			"form" => "frm-frmDeConfig"
		]), true);

		$frm->setValidationParams([
			"on" => "blur",
			"inline" => true
		]);
		$frm->setSubmitParams($this->_getFiles()
			->getAdminBaseRoute() . "/_addDbConnection", "#main-content");

		$this->jquery->click("#validate-btn", '$("#frm-frmDeConfig").form("submit");');
		$this->jquery->execOn("click", "#cancel-btn", '$("#temp-form").html("");$("#models-main").show();');

		$dbForm->compile($this->jquery);
		$this->jquery->execAtLast('$("#models-main").hide();');
		$this->jquery->execAtLast($this->getAllJsDatabaseTypes('wrappers', Database::getAvailableWrappers()));
		$this->jquery->renderView($this->_getFiles()
			->getViewFrmNewDbConnection(), [
			'dbForm' => $dbForm
		]);
	}

	public function _checkConnectionName() {
		if (URequest::isPost()) {
			$result = [];
			\header('Content-type: application/json');
			$name = $_POST['connection-name'];
			$dbs = DAO::getDatabases();
			$dbs[] = 'default';
			$result["result"] = ! \in_array($name, $dbs);
			echo \json_encode($result);
		}
	}

	public function _addDbConnection() {
		if (URequest::isPost()) {
			$result = Startup::getConfig();
			$postValues = $_POST;
			$this->checkConfigDatabaseCache($postValues);
			if (isset($result['database']['dbName'])) {
				$result['database'] = [
					'default' => $result['database']
				];
			}
			$result['database'][$postValues['connection-name']] = [
				'wrapper' => $postValues['database-wrapper'],
				'type' => $postValues['database-type'],
				'dbName' => $postValues['database-dbName'],
				'serverName' => $postValues['database-serverName'],
				'port' => $postValues['database-port'],
				'options' => $postValues['database-options'],
				'user' => $postValues['database-user'],
				'password' => $postValues['database-password'],
				'cache' => $postValues['database-cache']
			];
			if (Startup::saveConfig($result)) {
				$this->config['activeDb'] = $postValues['connection-name'];
				$this->_saveConfig();
				$this->showSimpleMessage("The connection has been successfully created!", "positive", "check square", null, "opMessage");
			} else {
				$this->showSimpleMessage("Impossible to add this connection.", "negative", "warning circle", null, "opMessage");
			}
			$this->reloadConfig();
		}

		$this->models();
	}

	private function getDbInstance(string $offset) {
		try {
			$db = null;
			$config = Startup::$config;
			if (! isset(DAO::$db[$offset])) {
				DAO::startDatabase($config, $offset);
			}
			if (isset(DAO::$db[$offset])) {
				$db = DAO::$db[$offset];
				SqlUtils::$quote = $db->quote;
			} else {
				DAO::updateDatabaseParams($config, [
					'dbName' => 'newbase'
				], $offset);
				DAO::startDatabase($config, $offset);
				$db = DAO::$db[$offset];
			}
		} catch (\Exception $e) {
			$db = DAO::$db[$offset];
		}
		return $db;
	}

	public function _importSQL() {
		$offset = $this->getActiveDb();
		$db = $this->getDbInstance($offset);
		$frm = $this->jquery->semantic()->htmlForm("frm-sql-import");
		$file = $this->jquery->semantic()->htmlInput('sqlFile');
		$file->asFile('Select file...', 'right', 'upload', true);
		$frm->setSubmitParams($this->_getFiles()
			->getAdminBaseRoute() . "/_loadSqlFromFile/" . $db->getDbName(), "#file-div", [
			'contentType' => 'false',
			'processData' => 'false'
		]);
		$this->jquery->execOn('change', '#div-sqlFile input:file', 'if(event.target.files.length){$("#frm-sql-import").form("submit");}');
		$this->jquery->renderView('@admin/config/importSql.html', [
			'dsn' => $db->getDSN()
		]);
	}

	public function _loadSqlFromFile($db = '') {
		if (URequest::isPost()) {
			$target_dir = \sys_get_temp_dir();
			$target_file = $target_dir . \basename($_FILES["div-sqlFile-file"]["name"]);
			if (\move_uploaded_file($_FILES["div-sqlFile-file"]["tmp_name"], $target_file)) {
				$sql = \file_get_contents($target_file);
				$this->jquery->exec("setAceEditor('sqlx');", true);
				\preg_match('/USE\s[`|"|\'](.*?)[`|"|\']/m', $sql, $matches);
				$this->jquery->postFormOnClick('#validate-btn', $this->_getFiles()
					->getAdminBaseRoute() . "/_createDbFromSql", "frm-sql-content", "#main-content");
				$this->jquery->renderView('@admin/config/sqlContent.html', [
					'sql' => $sql,
					'dbName' => $matches[1] ?? $db
				]);
			}
		}
	}

	public function _createDbFromSql() {
		$dbName = URequest::post('dbName');
		$sql = URequest::post('sql');
		$isValid = true;
		if (isset($dbName) && isset($sql)) {
			$sql = preg_replace('/(USE\s[`|"|\'])(.*?)([`|"|\'])/m', '$1' . $dbName . '$3', $sql);
			$sql = preg_replace('/(CREATE\sDATABASE\s(?:IF NOT EXISTS){0,1}\s[`|"|\'])(.*?)([`|"|\'])/m', '$1' . $dbName . '$3', $sql);
			$activeDbOffset = $this->getActiveDb();

			$db = $this->getDbInstance($activeDbOffset);
			if (! $db->isConnected()) {
				$db->setDbName('');
				try {
					$db->connect();
				} catch (\Exception $e) {
					$isValid = false;
					$this->showSimpleMessage($e->getMessage(), 'error', 'Connection to database: SQL file importation', 'warning', null, 'opMessage');
				}
			}
			if ($isValid) {
				if ($db->getDbName() !== $dbName) {
					$config = Startup::$config;
					DAO::updateDatabaseParams($config, [
						'dbName' => $dbName
					], $activeDbOffset);
					Startup::saveConfig($config);
					Startup::reloadConfig();
				}
				try {
					$db->beginTransaction();
					$db->execute($sql);
					$db->commit();
					$this->showSimpleMessage($dbName . ' created with success!', 'success', 'SQL file importation', 'success', null, 'opMessage');
				} catch (\Exception $e) {
					$db->rollBack();
					$this->showSimpleMessage($e->getMessage(), 'error', 'SQL file importation', 'warning', null, 'opMessage');
				}
			}

			$this->models();
		}
	}

	private function _yumlRefresh($url = "/_updateDiagram", $params = "{}", $responseElement = "#diag-class") {
		$params = JsUtils::_implodeParams([
			"$('#frmProperties').serialize()",
			$params
		]);
		return $this->jquery->postDeferred($this->_getFiles()
			->getAdminBaseRoute() . $url, $params, $responseElement, [
			"ajaxTransition" => "random",
			"attr" => ""
		]);
	}

	private function _yumlMenu($url = "/_updateDiagram", $params = "{}", $responseElement = "#diag-class", $type = "plain", $size = ";scale:100") {
		$params = JsUtils::_implodeParams([
			"$('#frmProperties').serialize()",
			$params
		]);
		$menu = new HtmlMenu("menu-diagram");
		$ddScruffy = new HtmlDropdown("ddScruffy", $type, [
			"nofunky" => "Boring",
			"plain" => "Plain",
			"scruffy" => "Scruffy"
		], true);
		$ddScruffy->setValue("plain")->asSelect("type");
		$this->jquery->postOn("change", "[name='type']", $this->_getFiles()
			->getAdminBaseRoute() . $url, $params, $responseElement, [
			"ajaxTransition" => "random",
			"attr" => ""
		]);
		$menu->addItem($ddScruffy);
		$ddSize = new HtmlDropdown("ddSize", $size, [
			";scale:180" => "Huge",
			";scale:120" => "Big",
			";scale:100" => "Normal",
			";scale:80" => "Small",
			";scale:60" => "Tiny"
		], true);
		$ddSize->asSelect("size");
		$this->jquery->postOn("change", "[name='size']", $this->_getFiles()
			->getAdminBaseRoute() . $url, $params, $responseElement, [
			"ajaxTransition" => "random",
			"attr" => ""
		]);
		$menu->wrap("<form id='frmProperties' name='frmProperties'>", "</form>");
		$menu->addItem($ddSize);
		return $menu;
	}

	protected function displayModelsMessages($type, $messagesToDisplay) {
		$step = $this->getActiveModelStep();
		return $this->displayMessages($type, $messagesToDisplay, $step[2], $step[0]);
	}
}
