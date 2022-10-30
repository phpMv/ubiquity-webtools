<?php
namespace Ubiquity\controllers\admin\traits;

use Ajax\semantic\components\validation\Rule;
use Ajax\semantic\html\collections\HtmlMessage;
use Ubiquity\cache\CacheManager;
use Ubiquity\config\Configuration;
use Ubiquity\config\EnvFile;
use Ubiquity\controllers\Startup;
use Ubiquity\domains\DDDManager;
use Ubiquity\utils\base\UArray;
use Ubiquity\utils\http\URequest;
use Ubiquity\utils\http\UResponse;
use Ubiquity\utils\base\UString;
use Ubiquity\db\Database;
use Ubiquity\orm\DAO;

/**
 *
 * @author jc
 * @property \Ajax\php\Ubiquity\JsUtils $jquery
 * @property \Ubiquity\views\View $view
 */
trait ConfigTrait {

	abstract public function _getAdminData();

	abstract public function _getAdminViewer();

	abstract public function _getFiles();

	abstract public function loadView(string $viewName, $pData = NULL, bool $asString = false);

	abstract public function config($hasHeader = true);

	abstract public function models($hasHeader = true);

	abstract protected function reloadConfig($originalConfig);

	abstract public function _showSimpleMessage($content, $type, $title = null, $icon = "info", $timeout = NULL, $staticName = null, $closeAction = null, $toast = false): HtmlMessage;

	public function _formConfig(string $filename='config') {
		$config = Configuration::loadConfigWithoutEval($filename);
		if($filename==='config') {
			$df = $this->_getAdminViewer()->getConfigDataForm($config, 'all', $filename);
			$this->jquery->execAtLast($this->getAllJsDatabaseTypes('wrappers', Database::getAvailableWrappers()));
			$this->jquery->renderView($this->_getFiles()->getViewConfigForm());
		}else {
			$baseRoute=$this->_getBaseRoute();
			$df = $this->_getAdminViewer()->getConfigPartDataForm($config, 'frmDeConfig',false);
			$this->_getAdminViewer()->addConfigToolbar($df,$baseRoute,$filename);
			$this->sourcePartBehavior([
				'form' => '#frmDeConfig'
			], [
				'source' => $baseRoute . '/_getConfigSourcePartial/'.$filename,
				'form' => $baseRoute . '/_refreshConfigFrmPartial/'.$filename
			],'frm-frmDeConfig','frm-source');
			$this->addConfigBehavior();
			$this->jquery->renderView($this->_getFiles()->getViewConfigFormPartial(),['inverted'=>$this->style]);
		}
	}

	public function _getConfigSourcePartial($filename) {
		unset($_POST['config-filename']);
		$this->getConfigSourcePart(Configuration::loadConfigWithoutEval($filename), 'Configuration', 'cogs');
	}

	public function _refreshConfigFrmPartial($filename){
		$this->refreshConfigFrmPart(Configuration::loadConfigWithoutEval($filename), 'frmDeConfig');
	}

	public function _formEnv(string $filename='.env'){
		$content=EnvFile::loadContent(EnvFile::$ENV_ROOT,$filename);
		$frm=$this->jquery->semantic()->htmlForm('frm-env');
		$this->_setStyle($frm);
		$frm->addTextarea('content','',$content);
		$frm->setSubmitParams($this->_getBaseRoute().'/_submitEnv/'.$filename,'#main-content',['hasLoader'=>'internal']);
		$this->jquery->click('#validate-btn','$("#frm-env").form("submit");');
		$this->jquery->click('#cancel-btn','$("#config-div").show();$("#action-response").html("");');
		$this->jquery->renderView($this->_getFiles()->getViewEnvForm(),['filename'=>$filename,'inverted'=>$this->style]);
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

	private function startWithInArray($search, $toDelete){
		foreach ($toDelete as $toDeleteKey){
			if($search===$toDeleteKey || UString::startswith($search,"$toDeleteKey-")){
				return true;
			}
		}
		return false;
	}

	private function startWithInArray_($search,$toDelete){
		foreach ($toDelete as $keyTodelete){
			if($search===$keyTodelete || UString::startswith($keyTodelete,"$search")){
				return true;
			}
		}
		return false;
	}

	private function removeDeletedKeys(&$result,$toRemove){
		foreach ($result as $key=>$_){
			if($this->startWithInArray_($key,$toRemove)){
				unset($result[$key]);
			}
		}
	}

	public function _submitConfig($partial = true) {
		$originalConfig = Startup::$config;
		$filename=URequest::post('config-filename','config');
		$result = Configuration::loadConfigWithoutEval($filename);
		$toDelete = URequest::post('_toDelete','');
		$toRemove = \explode(',', $toDelete);
		$this->removeDeletedKeys($result,$toRemove);

		$postValues = $_POST;
		unset($postValues['config-filename']);
		unset($postValues[$filename]);
		unset($postValues['_toDelete']);

		if ($partial !== true && $filename==='config') {
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
			if(!$this->startWithInArray($key,$toRemove)) {
				if (\strpos($key, "-") === false) {
					$result[$key] = $value;
				} else {
					$keys = \explode('-', $key);
					$v = &$result;
					foreach ($keys as $k) {
						if (!isset($v[$k])) {
							$v[$k] = [];
						}
						$v = &$v[$k];
					}
					$v = $value;
				}
			}
		}

		try {
			if (Startup::saveConfig($result,$filename)) {
				$this->_showSimpleMessage("The configuration file <b>$filename</b> has been successfully modified!", "positive", "check square", null, "msgConfig");
			} else {
				$this->_showSimpleMessage("Impossible to write the configuration file <b>$filename</b>.", "negative", "warning circle", null, "msgConfig");
			}
		} catch (\Exception $e) {
			$this->_showSimpleMessage("Your configuration contains errors.<br>The configuration file <b>$filename</b> has not been saved.<br>" . $e->getMessage(), "negative", "warning circle", null, "msgConfig");
		}

		$config = $this->reloadConfig($originalConfig);
		if ($partial === 'check') {
			if (isset($config['database']['dbName'])) {
				Startup::reloadServices();
			}
			$this->models(true);
		} else {
			$this->config(false);
		}
	}

	public function _submitEnv(string $filename){
		$content=URequest::post('content','');
		EnvFile::saveText($content,EnvFile::$ENV_ROOT,$filename);
		$this->toast("$filename updated",'Env file updated','info',true);
		$this->config(true);
	}

	protected function _checkCondition($callback) {
		if (URequest::isPost()) {
			$result = [];
			UResponse::asJSON();
			$value = $_POST['_value'];
			$result['result'] = $callback($value);
			echo \json_encode($result);
		}
	}

	public function _checkArray() {
		$this->_checkCondition(function ($value) {
			try {
				$array = eval("return " . $value . ";");
				return \is_array($array);
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
		$parent = URequest::post('_ruleValue');
		$this->_checkCondition(function ($value) use ($parent) {
			try {
				$class = new \ReflectionClass($value);
				return $class->isSubclassOf($parent);
			} catch (\ReflectionException $e) {
				return false;
			}
		});
	}

	public function _checkStringUrl(){
		$this->_checkCondition(function ($value) {
			if ($value != null && !UString::startswith($value,'getenv(')) {
				return \filter_var($value, FILTER_VALIDATE_URL) && UString::endswith($value,'/');
			}
			return true;
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
		Configuration::loadActiveEnv();
		$postValues = array_map(function($elm){
			if(UString::startswith($elm,'getenv(')){
				return eval("return $elm;");
			}
			return $elm;
		},$_POST);
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

	public function _domainFrm() {
		$frm = $this->jquery->semantic()->htmlForm('frm-domain');
		$fields = $frm->addFields();
		$base = DDDManager::getBase();
		$input = $fields->addInput('base', 'Base for all domains', 'text', 'domains', 'Enter a folder ');
		$input->labeled('app\\', 'left', 'tree');
		if (\count(DDDManager::getDomains()) > 0) {
			$input->setDisabled(true);
		}
		$input = $fields->addInput('domains', 'Domain name', 'text', '', 'Enter a new name for your domain');
		$input->addRules([
			[
				'type' => 'checkDomain',
				'prompt' => 'The domain {value} already exists!'
			],
			'empty'
		]);
		$input->setFluid();
		$this->jquery->exec(Rule::ajax($this->jquery, "checkDomain", $this->_getFiles()
			->getAdminBaseRoute() . "/_domainExists/domains", "{}", "result=data.result;", "postForm", [
			"form" => "frm-domain"
		]), true);
		$frm->setValidationParams([
			'on' => 'blur',
			'inline' => true
		]);
		$frm->addClass($this->style);
		$this->jquery->click('#cancel-btn', '$("#frm-domain-container").html("");');
		$this->jquery->click('#validate-btn', '$("#frm-domain").submit();');
		$frm->setSubmitParams($this->_getFiles()
			->getAdminBaseRoute() . '/_addDomainBased', 'body', [
			'hasLoader' => 'internal',
			'params' => \json_encode([
				'action' => Startup::getAction(),
				'params' => Startup::getActionParams()
			])
		]);
		$this->jquery->renderView($this->_getFiles()
			->getViewDomainForm(), [
			'inverted' => $this->style
		]);
	}

	public function _addDomainBased() {
		DDDManager::setBase($base = $_POST['base'] ?? 'domains');
		DDDManager::start();
		DDDManager::createDomain($_POST['domains']);
		$this->updateDomain();
	}

	public function _domainExists($fieldname) {
		if (URequest::isPost()) {
			$result = [];
			header('Content-type: application/json');
			$domain = $_POST[$fieldname];
			$domains = DDDManager::getDomains();
			$result["result"] = (\array_search($domain, $domains) === false);
			echo \json_encode($result);
		}
	}

	public function configRead() {
		$config = Startup::getConfig();
		$this->_getAdminViewer()->getConfigDataElement($config);
		$this->jquery->click('#close-button','$("#configRead-div").hide();$("#config-div").show();');
		$this->jquery->renderView($this->_getFiles()
			->getViewConfigRead(), [
			'inverted' => $this->style
		]);
	}
}
