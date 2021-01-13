<?php
namespace Ubiquity\controllers\admin\traits;

use Ajax\semantic\html\collections\HtmlMessage;
use Ubiquity\controllers\Startup;
use Ubiquity\utils\http\URequest;
use Ubiquity\utils\http\UResponse;
use Ubiquity\utils\base\UString;
use Ubiquity\db\Database;
use Ubiquity\orm\DAO;

/**
 *
 * @author jc
 * @property \Ajax\JsUtils $jquery
 * @property \Ubiquity\views\View $view
 */
trait ConfigTrait {

	abstract public function _getAdminData();

	abstract public function _getAdminViewer();

	abstract public function _getFiles();

	abstract public function loadView($viewName, $pData = NULL, $asString = false);

	abstract public function config($hasHeader = true);

	abstract public function models($hasHeader = true);

	abstract protected function reloadConfig();

	abstract protected function showConfMessage($content, $type, $title, $icon, $url, $responseElement, $data, $attributes = NULL): HtmlMessage;

	abstract public function showSimpleMessage($content, $type, $title = null, $icon = "info", $timeout = NULL, $staticName = null, $closeAction = null, $toast = false): HtmlMessage;

	public function _formConfig($hasHeader = true) {
		global $config;
		if ($hasHeader === true) {
			$this->getHeader("config");
		}
		$this->_getAdminViewer()->getConfigDataForm($config, $hasHeader);
		$this->jquery->execAtLast($this->getAllJsDatabaseTypes('wrappers', Database::getAvailableWrappers()));
		$this->jquery->renderView($this->_getFiles()
			->getViewConfigForm());
	}

	public function _config() {
		$config = Startup::getConfig();
		echo $this->_getAdminViewer()->getConfigDataElement($config);
		echo $this->jquery->compile($this->view);
	}

	private function checkConfigDatabaseCache(&$postValues, $co = null) {
		$n = "-";
		if (isset($co)) {
			$n = "-" . $co . "-";
		}
		if (isset($postValues["ck" . $n . "cache"])) {
			unset($postValues["ck" . $n . "cache"]);
			if (! (isset($postValues["database" . $n . "cache"]) && UString::isNotNull($postValues["database" . $n . "cache"]))) {
				$postValues["database" . $n . "cache"] = false;
			}
		} else {
			$postValues["database" . $n . "cache"] = false;
		}
	}

	public function _submitConfig($partial = true) {
		$result = Startup::getConfig();
		$postValues = $_POST;
		if ($partial !== true) {
			if (isset($result['database']['dbName'])) {
				$this->checkConfigDatabaseCache($postValues);
			} else {
				$dbs = DAO::getDatabases();
				foreach ($dbs as $db) {
					$this->checkConfigDatabaseCache($postValues, $db);
				}
			}
			$postValues["debug"] = isset($postValues["debug"]);
			$postValues["test"] = isset($postValues["test"]);
			$postValues["templateEngineOptions-cache"] = isset($postValues["templateEngineOptions-cache"]);
		}
		foreach ($postValues as $key => $value) {
			if (strpos($key, "-") === false) {
				$result[$key] = $value;
			} else {
				$keys = explode('-', $key);
				$v = &$result;
				foreach ($keys as $k) {
					if (! isset($v[$k])) {
						$v[$k] = [];
					}
					$v = &$v[$k];
				}
				$v = $value;
			}
		}
		try {
			if (Startup::saveConfig($result)) {
				$this->showSimpleMessage("The configuration file has been successfully modified!", "positive", "check square", null, "msgConfig");
			} else {
				$this->showSimpleMessage("Impossible to write the configuration file.", "negative", "warning circle", null, "msgConfig");
			}
		} catch (\Exception $e) {
			$this->showSimpleMessage("Your configuration contains errors.<br>The configuration file has not been saved.<br>" . $e->getMessage(), "negative", "warning circle", null, "msgConfig");
		}

		$config = $this->reloadConfig();
		if ($partial == "check") {
			if (isset($config["database"]["dbName"])) {
				Startup::reloadServices();
			}
			$this->models(true);
		} else {
			$this->config(false);
		}
	}

	protected function _checkCondition($callback) {
		if (URequest::isPost()) {
			$result = [];
			UResponse::asJSON();
			$value = $_POST["_value"];
			$result["result"] = $callback($value);
			echo json_encode($result);
		}
	}

	public function _checkArray() {
		$this->_checkCondition(function ($value) {
			try {
				$array = eval("return " . $value . ";");
				return is_array($array);
			} catch (\ParseError $e) {
				return false;
			}
		});
	}

	public function _checkDirectory() {
		$folder = URequest::post("_ruleValue");
		$this->_checkCondition(function ($value) use ($folder) {
			if ($value != null) {
				$base = Startup::getApplicationDir();
				return \file_exists($base . \DS . $folder . \DS . $value);
			}
			return true;
		});
	}

	public function _checkAbsoluteDirectory() {
		$this->_checkCondition(function ($value) {
			if ($value != null) {
				return \file_exists($value);
			}
			return true;
		});
	}

	public function _checkUrl() {
		$this->_checkCondition(function ($value) {
			$headers = @get_headers($value);
			if ($value != null) {
				return $headers && strpos($headers[0], '200');
			}
			return true;
		});
	}

	public function _checkClass() {
		$parent = URequest::post("_ruleValue");
		$this->_checkCondition(function ($value) use ($parent) {
			try {
				$class = new \ReflectionClass($value);
				return $class->isSubclassOf($parent);
			} catch (\ReflectionException $e) {
				return false;
			}
		});
	}

	private function convert_smart_quotes($string) {
		$search = array(
			chr(145),
			chr(146),
			chr(147),
			chr(148),
			chr(151)
		);

		$replace = array(
			"'",
			"'",
			'"',
			'"',
			'-'
		);

		return str_replace($search, $replace, $string);
	}

	private function getDbValue($post, $key) {
		foreach ($post as $k => $v) {
			if (UString::endswith($k, $key)) {
				return $v;
			}
		}
		return '';
	}

	public function _checkDbStatus($co = '') {
		$n = '';
		if ($co != null) {
			$n = $co . '-';
		}
		$postValues = $_POST;
		$connected = false;
		$db = new Database($postValues["database-" . $n . "wrapper"] ?? \Ubiquity\db\providers\pdo\PDOWrapper::class, $postValues["database-" . $n . "type"], $postValues["database-" . $n . "dbName"], $postValues["database-" . $n . "serverName"], $postValues["database-" . $n . "port"], $postValues["database-" . $n . "user"], $postValues["database-" . $n . "password"]);
		try {
			$db->_connect();
			$connected = $db->isConnected();
		} catch (\Exception $e) {
			$errorMsg = $e->getMessage();
			$msg = ((mb_detect_encoding($errorMsg, "UTF-8, ISO-8859-1, ISO-8859-15", "CP1252")) !== "UTF-8") ? utf8_encode($this->convert_smart_quotes($errorMsg)) : ($errorMsg);
			$connected = false;
		}
		$icon = "exclamation triangle red";
		if ($connected) {
			$icon = "check square green";
		}
		$icon = $this->jquery->semantic()->htmlIcon("db-" . $n . "status", $icon);
		if (isset($msg)) {
			$icon->addPopup("Error", $msg);
		} else {
			$icon->addPopup("Success", "Connexion is ok!");
		}
		$this->jquery->execAtLast('$("#db-' . $n . 'status").popup("show");');
		echo $icon;
		echo $this->jquery->compile($this->view);
	}
}
