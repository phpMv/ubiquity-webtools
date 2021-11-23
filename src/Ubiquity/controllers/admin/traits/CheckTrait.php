<?php
namespace Ubiquity\controllers\admin\traits;

use Ubiquity\controllers\Startup;
use Ubiquity\db\reverse\DbGenerator;
use Ubiquity\domains\DDDManager;
use Ubiquity\orm\DAO;
use Ubiquity\orm\reverse\DatabaseChecker;
use Ubiquity\orm\reverse\DatabaseReversor;
use Ubiquity\utils\base\UString;
use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\admin\popo\InfoMessage;
use Ubiquity\db\Database;
use Ajax\semantic\html\base\HtmlSemDoubleElement;
use Ubiquity\utils\base\UFileSystem;
use Ubiquity\cache\system\ArrayCache;
use Ubiquity\orm\creator\database\DbModelsCreator;
use Ubiquity\controllers\admin\UbiquityMyAdminFiles;
use Ajax\semantic\html\collections\HtmlMessage;
use Ubiquity\utils\base\UArray;
use Ajax\semantic\html\modules\HtmlDropdown;
use Ubiquity\orm\parser\Reflexion;

/**
 *
 * @author jc
 * @property array $steps
 * @property int $activeStep
 * @property string $engineering
 * @property \Ajax\JsUtils $jquery
 */
trait CheckTrait {

	protected $messages = [
		"error" => [],
		"info" => []
	];

	abstract protected function getModelSteps();

	abstract protected function getActiveModelStep();

	abstract protected function getNextModelStep();

	abstract protected function displayModelsMessages($type, $messagesToDisplay);

	abstract public function _showSimpleMessage($content, $type, $title = null, $icon = "info", $timeout = NULL, $staticName = null, $closeAction = null, $toast = false): HtmlMessage;

	abstract public function _isModelsCompleted();

	abstract public function _getConfig();

	/**
	 *
	 * @return UbiquityMyAdminFiles
	 */
	abstract public function _getFiles();

	private $dbNeedsMigration=false;

	public function _createModels($singleTable = null) {
		$config = Startup::getConfig();
		$offset = $this->getActiveDb();
		\ob_start();
		(new DbModelsCreator())->create($config, false, $singleTable, $offset);
		$result = \ob_get_clean();
		$message = $this->_showSimpleMessage("", "success", "Models creation", "check mark", null, "msg-create-models");
		$message->addList(\explode("\n", \str_replace("\n\n", "\n", \trim($result))));
		$this->models(true);
		echo $message;
	}

	protected function _checkStep($niveau = null) {
		$nbChecked = 1;
		$niveauMax = $niveau;
		if (! isset($niveau))
			$niveauMax = $this->activeStep - 1;
		$steps = $this->getModelSteps();
		while ($nbChecked <= $niveauMax && $this->hasNoError() && isset($steps[$nbChecked])) {
			$this->_modelCheckOneNiveau($steps[$nbChecked][1]);
			$nbChecked ++;
		}
		if ($this->hasError() && ! isset($niveau)) {
			$this->activeStep = $nbChecked - 1;
			$this->steps[$this->engineering][$this->activeStep][0] = "warning sign red";
		}
	}

	protected function _modelCheckOneNiveau($name) {
		$activeDb = $this->getActiveDb();
		$config = Startup::getConfig();
		switch ($name) {
			case "Conf":
				if ($this->missingKeyInConfigMessage("Database is not well configured in <b>app/config/config.php</b>", Startup::checkDbConfig($activeDb)) === false) {
					$this->_addInfoMessage("settings", "Database is well configured");
				}
				break;
			case "Database":
				$this->checkForDatabaseMigrations($activeDb);
				break;
			case "Connexion":
				$this->checkDatabase($config, "database", $activeDb);
			break;
			case "Models":
				CacheManager::start($config);
				$this->checkModels($config);
				if ($this->engineering === "forward") {
					$modelsWithoutTable = $this->getModelsWithoutTable($config, $activeDb);
					if (\count($modelsWithoutTable) > 0) {
						foreach ($modelsWithoutTable as $model) {
							$table = Reflexion::getTableName($model);
							$this->_addErrorMessage("warning", "The table <b>" . $table . "</b> does not exists for the model <b>" . $model . "</b>.");
						}
					}
				}
				break;
			case "Cache":
				$this->checkModelsCache($config);
				break;
		}
	}

	protected function checkForDatabaseMigrations($activeDb){
		$checker=new DatabaseChecker($activeDb);
		$dbResults=$checker->checkAll();
		$this->dbNeedsMigration=$checker->hasErrors();
		$checker->displayAll(function($type,$icon,$content){
			$this->_addMessage($type, $icon,$content);
		});
	}

	protected function checkDatabase($config, $infoIcon = "database", $offset = 'default') {
		$db = DAO::getDbOffset($config, $offset);
		if (! DAO::isConnected($offset)) {
			// $this->_addErrorMessage("warning", "connection to the database is not established (probably in <b>app/config/services.php</b> file).");
			try {
				if ($db["dbName"] !== "") {
					$this->_addInfoMessage($infoIcon, "Attempt to connect to the database <b>" . $db["dbName"] . "</b> ...");
					$db = new Database($db['wrapper'] ?? \Ubiquity\db\providers\pdo\PDOWrapper::class, $db["type"], $db["dbName"], @$db["serverName"], @$db["port"], @$db["user"], @$db["password"], @$db["options"], @$db["cache"]);
					$db->connect();
				}
			} catch (\Exception $e) {
				$this->_addErrorMessage("warning", $e->getMessage());
			}
		} else {
			$this->_addInfoMessage($infoIcon, "The connection to the database <b>" . $db["dbName"] . "</b> is established.");
		}
	}

	protected function checkModels($config, $infoIcon = "sticky note") {
		if ($this->missingKeyInConfigMessage("Models namespace is not well configured in <b>app/config/config.php</b>", Startup::checkModelsConfig()) === false) {
			$modelsNS = Startup::getNS('models');
			$this->_addInfoMessage($infoIcon, "Models namespace <b>" . $modelsNS . "</b> is ok.");
			$dir = UFileSystem::cleanPathname(\ROOT . \DS . $modelsNS);
			if (\file_exists($dir) === false) {
				$this->_addErrorMessage("warning", "The directory <b>" . $dir . "</b> does not exist.");
			} else {
				$activeDb = $this->getActiveDb();
				$this->_addInfoMessage($infoIcon, "The directory for models namespace <b>" . $dir . "</b> exists.");
				$models = CacheManager::getModels($config, true, $activeDb);
				if (\sizeof($models) === 0) {
					$this->_addErrorMessage("warning", "No file found in <b>" . $dir . "</b> folder.");
				} else {
					foreach ($models as $model) {
						$r = new \ReflectionClass($model);
						$ns = $r->getNamespaceName();
						if (! UString::startswith($ns.'\\', $modelsNS)) {
							$this->_addErrorMessage("warning", "The namespace <b>" . $ns . "</b> would start with <b>" . $modelsNS . "</b> for the class <b>" . $model . "</b>.");
						} else {
							$this->_addInfoMessage($infoIcon, "The namespace for the class <b>" . $model . "</b> is ok.");
						}
					}
				}
			}
		}
	}

	protected function getTablesWithoutModel($config, $offset = 'default') {
		$models = CacheManager::getModels($config, true, $offset);
		$tables = DAO::getDatabase($offset)->getTablesName();
		$allJoinTables = Reflexion::getAllJoinTables($models);
		$tables = array_diff($tables, $allJoinTables);
		foreach ($models as $model) {
			try {
				$table = Reflexion::getTableName($model);
				$tables = UArray::iRemove($tables, $table);
			} catch (\Exception $e) {}
		}
		return $tables;
	}

	protected function getModelsWithoutTable($config, $offset = 'default') {
		$models = CacheManager::getModels($config, true, $offset);
		$result = $models;
		$tables = DAO::getDatabase($offset)->getTablesName();
		$allJoinTables = Reflexion::getAllJoinTables($models);
		$tables = array_diff($tables, $allJoinTables);
		foreach ($models as $model) {
			if (sizeof(Reflexion::getAnnotationClass($model, "@transient")) == 0) {
				try {
					$table = Reflexion::getTableName($model);
					if ((UArray::iSearch($table, $tables, false)) !== false) {
						$result = UArray::iRemoveOne($result, $model);
					}
				} catch (\Exception $e) {}
			} else {
				$result = UArray::iRemoveOne($result, $model);
			}
		}
		return $result;
	}

	protected function checkModelsCache($config, $infoIcon = "lightning") {
		$instanceCache = CacheManager::$cache;
		if ($instanceCache === null) {
			$this->_addErrorMessage("warning", "Cache instance is not created.");
		} else {
			$this->_addInfoMessage($infoIcon, $instanceCache->getCacheInfo());
			if (CacheManager::$cache instanceof ArrayCache) {
				$this->checkArrayCache($config, $infoIcon);
			} else {
				$this->checkModelsCacheFiles($config, $infoIcon);
			}
		}
	}

	protected function checkArrayCache($config, $infoIcon = "lightning") {
		if (! isset($config["cache"]) || UString::isNull($config["cache"])) {
			self::missingKeyInConfigMessage("Cache directory is not well configured in <b>app/config/config.php</b>", [
				"cache"
			]);
		} else {
			if (! isset($config["cache"]["directory"]) || UString::isNull($config["cache"]["directory"])) {
				self::missingKeyInConfigMessage("Cache directory is not well configured in <b>app/config/config.php</b>", [
					"directory"
				]);
			} else {
				$cacheDir = UFileSystem::cleanPathname(\ROOT . \DS . $config["cache"]["directory"]);
				$this->_addInfoMessage($infoIcon, "Models cache directory is well configured in config file.");
				$cacheDirs = CacheManager::getCacheDirectories($config, true);
				if (\file_exists($cacheDir) === false) {
					$this->_addErrorMessage("warning", "The cache directory <b>" . $cacheDir . "</b> does not exist.");
				} else {
					$modelsCacheDir = UFileSystem::cleanPathname($cacheDirs["models"]);
					$this->_addInfoMessage($infoIcon, "Cache directory <b>" . $cacheDir . "</b> exists.");
					if (\file_exists($modelsCacheDir) === false) {
						$this->_addErrorMessage("warning", "The models cache directory <b>" . $modelsCacheDir . "</b> does not exist.");
					} else {
						$this->_addInfoMessage($infoIcon, "Models cache directory <b>" . $modelsCacheDir . "</b> exists.");
						$this->checkModelsCacheFiles($config, $infoIcon);
					}
				}
			}
		}
	}

	protected function checkModelsCacheFiles($config, $infoIcon = "lightning") {
		$activeDb = $this->getActiveDb();
		CacheManager::startProd($config);
		if($activeDb!='default' && CacheManager::getModelsDatabases()===[]){
			$this->_addErrorMessage("warning", "The <b>_modelsDatabases</b> cache file doesn't exist! Please reinit the models cache.");
			return;
		}
		$models = CacheManager::getModels($config, true, $activeDb);
		foreach ($models as $model) {
			if (! CacheManager::modelCacheExists($model)) {
				$this->_addErrorMessage("warning", "The models cache entry does not exist for the class <b>" . $model . "</b>.");
			} else {
				$this->_addInfoMessage($infoIcon, "The models cache entry for <b>" . $model . "</b> exists.");
			}
		}
	}

	protected function displayAllMessages() {
		$activeDb = $this->getActiveDb();
		if ($this->hasNoError()) {
			$this->_addInfoMessage("checkmark", "everything is fine here");
		}
		if ($this->hasMessages()) {
			$messagesElmInfo = $this->displayModelsMessages($this->hasNoError() ? "success" : "info", $this->messages["info"]);
			echo $messagesElmInfo;
		}

		if ($this->hasWarning()) {
			$messagesElmWarning = $this->displayModelsMessages("warning", $this->messages["warning"]);
			echo $messagesElmWarning;
		}

		if ($this->hasError()) {
			$messagesElmError = $this->displayModelsMessages("error", $this->messages["error"]);
			echo $messagesElmError;
		}
		$this->showActions($activeDb);
	}

	protected function showActions($activeDb) {
		$buttons = $this->jquery->semantic()->htmlButtonGroups("step-actions");
		$step = $this->getActiveModelStep();
		$activeDomain=DDDManager::getActiveDomain();
		$baseRoute=$this->_getFiles()->getAdminBaseRoute();
		switch ($step[1]) {
			case "Conf":
			case "Connexion":
			case "Database":
				if($this->dbNeedsMigration){
					$buttons->addItem('Database migrations')
						->addClass($this->style.' olive')
						->getOnclick($baseRoute.'/_displayMigrations','#action-response',['hasLoader'=>'internal'])
						->addIcon('dove');
				}
				$buttons->addItem("Show config file")
					->addClass($this->style)
					->getOnClick($baseRoute . "/_config", "#action-response", [
					'hasLoader' => 'internal'
				])
					->addIcon("settings");
				$buttons->addItem("Edit config file")
					->addClass($this->style)
					->addClass("orange")
					->getOnClick($baseRoute . "/_formConfig/check", "#action-response", [
					'hasLoader' => 'internal'
				])
					->addIcon("edit");
				$buttons->addItem('Import from SQL file')
					->addClass($this->style)
					->getOnClick($baseRoute . "/_importSQL", "#action-response", [
					'hasLoader' => 'internal'
				])
					->addIcon("file code");
				if ($this->engineering === "reverse")
					$buttons->addItem("Export database")
						->addClass($this->style)
						->getOnClick($baseRoute . "/_showDatabaseCreation", "#main-content", [
							'hasLoader' => 'internal'
						])
						->addIcon("upload");
				break;
			case "Models":
				if ($this->engineering === "forward") {
					if (\count($tables = $this->getTablesWithoutModel(Startup::getConfig(), $activeDb))) {
						$ddBtn = new HtmlDropdown("ddTables", "Create models for new tables", \array_combine($tables, $tables));
						$ddBtn->asButton()->addClass($this->style);
						$ddBtn->getOnClick($baseRoute . "/_createModels", "#main-content", [
							"attr" => "data-value"
						]);
						$buttons->addItem($ddBtn);
					}
					$buttons->addItem("(Re-)Create all models")
						->addClass($this->style)
						->getOnClick($baseRoute . "/_createModels", "#main-content", [
						"attr" => "",
						'hasLoader' => 'internal'
					])
						->addIcon("sticky note");
				} else {
					$buttons->addItem("Import from Yuml")
						->addClass($this->style)
						->getOnClick($baseRoute . "/_importFromYuml", "#models-main", [
						"attr" => "",
						'hasLoader' => 'internal'
					])
						->addIcon("sticky note");
				}
				$bt = $buttons->addItem("Classes diagram")
					->addClass($this->style)
					->getOnClick($baseRoute . "/_showAllClassesDiagram", "#action-response", [
					"attr" => "",
					"ajaxTransition" => "random",
					'hasLoader' => 'internal'
				]);
				$bt->addIcon("sticky note outline");
				if ($this->hasError())
					$bt->addClass("disabled");
				break;
			case "Cache":
				$buttons->addItem("(Re-)Init all models cache")
					->addClass($this->style)
					->getOnClick($baseRoute . "/_initCache/models/models", "#main-content", [
					'hasLoader' => 'internal'
				])
					->addIcon("lightning");
				break;
		}
		$nextStep = $this->getNextModelStep();
		if (isset($nextStep)) {
			$bt = $buttons->addItem($nextStep[1]);
			$bt->addIcon("angle double right", false);
			$bt->addLabel($nextStep[2], true, $nextStep[0]);
			$bt->getContent()[1]->addClass("green " . $this->style);
			if ($this->hasNoError()) {
				$bt->getOnClick($baseRoute . "/_loadModelStep/" . $this->engineering . "/" . ($this->activeStep + 1), "#models-main", [
					'hasLoader' => 'internal'
				]);
			} else {
				$bt->addClass("disabled");
			}
			if($activeDomain=='') {
				$this->jquery->execAtLast('$("#btNewConnection").hide();');
			}
		} else {
			$bt = $buttons->addItem("See datas")->addClass("black " . $this->style);
			$bt->addIcon("unhide");
			if ($this->hasNoError()) {
				$bt->getOnClick($baseRoute . "/models/noHeader/", "#models-main", [
					'hasLoader' => 'internal'
				]);
				$this->jquery->execAtLast('$("#btNewConnection").show();');
			} else {
				$bt->addClass("disabled");
				if($activeDomain=='') {
					$this->jquery->execAtLast('$("#btNewConnection").hide();');
				}
			}
		}
		echo "<div>" . $buttons . "</div><br>";
		echo "<div id='action-response'></div>";
	}

	protected function missingKeyInConfigMessage($message, $keys) {
		if (\count($keys) > 0) {
			$this->_addErrorMessage("warning", $message . " : parameters <b>[" . \Ajax\service\JArray::implode(",", $keys) . "]</b> are required.");
			return true;
		}
		return false;
	}

	protected function _addErrorMessage($type, $content): InfoMessage {
		return $this->_addMessage("error", $type, $content);
	}

	protected function _addInfoMessage($type, $content): InfoMessage {
		return $this->_addMessage("info", $type, $content);
	}

	protected function _addWarningMessage($type, $content): InfoMessage {
		return $this->_addMessage("warning", $type, $content);
	}

	protected function _addMessage($key, $type, $content): InfoMessage {
		return $this->messages[$key][] = new InfoMessage($type, $content);
	}

	protected function hasError() {
		return \count($this->messages["error"]??[]) > 0;
	}

	protected function hasWarning() {
		return \count($this->messages["warning"]??[]) > 0;
	}

	protected function hasNoError() {
		return \count($this->messages["error"]??[]) == 0;
	}

	protected function hasMessages() {
		return \count($this->messages["info"]??[]) > 0;
	}

	protected function displayMessages($type, $messagesToDisplay, $header = "", $icon = "") {
		$messagesElm = $this->jquery->semantic()->htmlMessage("modelsMessages-" . $type);
		$messagesElm->addHeader($header);
		if ($this->hasError() && $type === "info") {
			$messagesElm->setIcon("info circle");
		}
		else {
			$messagesElm->setIcon($icon);
		}
		$messages = [];
		foreach ($messagesToDisplay as $msg) {
			$lines = explode("\n", $msg->getContent());
			foreach ($lines as $line) {
				$elm = new HtmlSemDoubleElement("", "div", "", $line);
				$elm->addIcon($msg->getType());
				$messages[] = $elm;
			}
		}
		$list = $messagesElm->addList($messages);
		$list->addClass('relaxed divided');
		$messagesElm->addClass($type . ' ' . $this->style);
		return $messagesElm;
	}

	public function _displayMigrations(){
		$activeDb = $this->getActiveDb();
		$generator = new DatabaseReversor(new DbGenerator(), $activeDb);
		$generator->migrate();
		
		$this->jquery->exec("setAceEditor('sqlx');$('#modelsMessages-success,#modelsMessages-info').hide();", true);
		$this->jquery->postFormOnClick('#validate-btn', $this->_getFiles()
				->getAdminBaseRoute() . "/_migrateDb", "frm-sql-content", "#models-main",['hasLoader'=>'internal']);
		$this->jquery->renderView($this->_getFiles()
			->getViewDatabaseMigrate(),['inverted'=>$this->style,'sql'=>$generator->__toString()]);
	}
}
