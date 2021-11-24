<?php
namespace Ubiquity\controllers\admin\traits;

use Ubiquity\controllers\Startup;
use Ubiquity\cache\CacheManager;
use Ubiquity\orm\OrmUtils;
use Ubiquity\cache\ClassUtils;
use Ubiquity\orm\reverse\DatabaseReversor;
use Ubiquity\db\reverse\DbGenerator;
use Ubiquity\utils\http\URequest;
use Ubiquity\orm\DAO;
use Ubiquity\db\export\DbExport;
use Ajax\semantic\html\collections\HtmlMessage;

/**
 *
 * @author jc
 * @property \Ajax\JsUtils $jquery
 */
trait DatabaseTrait {

	abstract public function _getAdminData();

	abstract public function _getAdminViewer();

	abstract public function _getFiles();

	abstract public function loadView($viewName, $pData = NULL, $asString = false);

	protected function getModels() {
		$db = $this->getActiveDb();
		$config = Startup::getConfig();
		$models = CacheManager::getModels($config, true, $db);
		$result = [];
		foreach ($models as $model) {
			$table = OrmUtils::getTableName($model);
			$simpleM = ClassUtils::getClassSimpleName($model);
			if ($simpleM !== $table)
				$result[$table] = $simpleM . "[" . $table . "]";
			else
				$result[$table] = $simpleM;
		}
		return $result;
	}

	public function _createSQLScript() {
		if (URequest::isPost()) {
			$db = $_POST["dbName"];
			$activeDb = $this->getActiveDb();
			if (DAO::isConnected($activeDb)) {
				$actualDb = DAO::$db[$activeDb]->getDbName();
			}
			$generator = new DatabaseReversor(new DbGenerator(), $activeDb);
			$generator->createDatabase($db);
			$frm = $this->jquery->semantic()->htmlForm("form-sql");
			$text = $frm->addElement("sql", $generator->__toString(), "SQL script", "div", "ui segment editor");
			$text->getField()->setProperty("style", "background-color: #002B36;");
			$bts = $this->jquery->semantic()->htmlButtonGroups("buttons");
			$bts->addElement("Generate database")->addClass("green");
			if (isset($actualDb) && $actualDb !== $db) {
				$btExport = $bts->addElement("Export datas script : " . $actualDb . " => " . $db);
				$btExport->addIcon("exchange");
				$btExport->postOnClick($this->_getFiles()
					->getAdminBaseRoute() . "/_exportDatasScript", "{}", "#div-datas", [
					'hasLoader' => 'internal'
				]);
			}
			$frm->addDivider();
			$this->jquery->exec("setAceEditor('sql');", true);
			$this->jquery->compile($this->view);
			$this->loadView($this->_getFiles()
				->getViewDatabaseCreate());
		}
	}

	public function _exportDatasScript() {
		$dbExport = new DbExport();
		$frm = $this->jquery->semantic()->htmlForm("form-sql-datas");
		$text = $frm->addElement("datas-sql", $dbExport->exports(), "Datas export script", "div", "ui segment editor");
		$text->getField()->setProperty("style", "background-color: #002B36;");
		$this->jquery->exec("setAceEditor('datas-sql');", true);
		$this->jquery->compile($this->view);
		$this->loadView($this->_getFiles()
			->getViewDatasExport());
	}

	public function _createDbFromSql() {
		$dbName = URequest::post('dbName');
		$sql = URequest::post('sql');
		if (isset($dbName) && isset($sql)) {
			$sql = preg_replace('/(USE\s[`|"|\'])(.*?)([`|"|\'])/m', '$1' . $dbName . '$3', $sql);
			$sql = preg_replace('/(CREATE\sDATABASE\s(?:IF NOT EXISTS){0,1}\s[`|"|\'])(.*?)([`|"|\'])/m', '$1' . $dbName . '$3', $sql);
			$this->_executeSQLTransaction($sql, 'SQL file importation', $dbName);
			$this->models();
		}
	}

	public function _migrateDb() {
		$sql = URequest::post('sql');
		$this->_executeSQLTransaction($sql, 'Database migrations');
		$this->_loadModelStep('reverse', 3);
	}

	private function _executeSQLTransaction(string $sql, string $title, ?string $dbName = null) {
		$isValid = true;
		if (isset($sql)) {
			$activeDbOffset = $this->getActiveDb();

			$db = $this->getDbInstance($activeDbOffset);
			if (! $db->isConnected()) {
				$db->setDbName('');
				try {
					$db->connect();
				} catch (\Exception $e) {
					$isValid = false;
					$this->_showSimpleMessage($e->getMessage(), 'error', 'Server connexion', 'warning', null, 'opMessage');
				}
			}
			if ($isValid) {
				if ($dbName != null && $db->getDbName() !== $dbName) {
					$config = Startup::$config;
					DAO::updateDatabaseParams($config, [
						'dbName' => $dbName
					], $activeDbOffset);
					Startup::saveConfig($config);
					Startup::reloadConfig();
				}
				if ($db->beginTransaction()) {
					try {
						$db->execute($sql);
						if ($db->inTransaction()) {
							$db->commit();
						}
						$this->_showSimpleMessage($dbName . ' created/updated with success!', 'success', $title, 'success', null, 'opMessage');
					} catch (\Error $e) {
						if ($db->inTransaction()) {
							$db->rollBack();
						}
						$this->_showSimpleMessage($e->getMessage(), 'error', $title, 'warning', null, 'opMessage');
					}
				} else {
					$db->execute($sql);
					$this->_showSimpleMessage($dbName . ' created/updated with success!', 'success', $title, 'success', null, 'opMessage');
				}
			}
		}
	}
}
