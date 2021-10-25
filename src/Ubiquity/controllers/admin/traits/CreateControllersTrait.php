<?php
namespace Ubiquity\controllers\admin\traits;

use Ajax\semantic\html\collections\form\HtmlForm;
use Ubiquity\utils\http\URequest;
use Ubiquity\controllers\Startup;
use Ubiquity\cache\CacheManager;
use Ajax\semantic\components\validation\Rule;
use Ubiquity\utils\base\UString;
use Ubiquity\cache\ClassUtils;
use Ubiquity\utils\http\UResponse;
use Ubiquity\scaffolding\AdminScaffoldController;

/**
 *
 * @author jc
 * @property \Ajax\JsUtils $jquery
 * @property \Ubiquity\views\View $view
 * @property \Ubiquity\scaffolding\AdminScaffoldController $scaffold
 */
trait CreateControllersTrait {

	abstract public function _getFiles();

	public function _frmAddCrudController() {
		$config = Startup::getConfig();
		$resources = CacheManager::getModels($config, true, $this->getActiveDb());
		$resources = \array_combine($resources, $resources);
		$resourcesList = $this->jquery->semantic()->htmlDropdown("resources-list", "", $resources);
		$resourcesList->asSelect("crud-model");
		$resourcesList->addClass($this->style);

		$frm = $this->jquery->semantic()->htmlForm("crud-controller-frm");
		$this->crudFormCommon($frm);
		$frm->addExtraFieldRule("crud-model", "exactCount[1]");

		$frm->setSubmitParams($this->_getFiles()
			->getAdminBaseRoute() . "/_addCrudController", "#frm");
		$this->jquery->change("#ck-add-route", "$('#div-new-route').toggle($(this).is(':checked'));");
		$this->jquery->jsonOn("change", "#ck-add-route", $this->_getFiles()
			->getAdminBaseRoute() . "/_addCtrlRoute/crud", "post", [
			"context" => "$('#crud-controller-frm')",
			"params" => "$('#crud-controller-frm').serialize()",
			"jsCondition" => "$('#ck-add-route').is(':checked')"
		]);

		$this->jquery->renderView($this->_getFiles()
			->getViewAddCrudController(), [
			'controllerNS' => Startup::getNS("controllers"),
			'inverted' => $this->style
		]);
	}

	protected function crudFormCommon(HtmlForm $frm, $crudPart = 'CRUD') {
		$viewList = $this->jquery->semantic()->htmlDropdown("view-list", "", AdminScaffoldController::$views[$crudPart]);
		$viewList->asSelect("crud-views", true);
		$viewList->setDefaultText("Select views");
		$viewList->addClass('fluid ' . $this->style);

		$frm->addExtraFieldRules("crud-name", [
			"empty",
			[
				"checkController",
				"Controller {value} already exists!"
			]
		]);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkController", $this->_getFiles()
			->getAdminBaseRoute() . "/_controllerExists/crud-name", "{}", "result=data.result;", "postForm", [
			"form" => "crud-controller-frm"
		]), true);

		$frm->setValidationParams([
			"on" => "blur",
			"inline" => true
		]);
		$this->jquery->click("#validate-btn", '$("#crud-controller-frm").form("submit");');
		$this->jquery->execOn("click", "#cancel-btn", '$("#frm").html("");');
		$this->jquery->exec("$('#crud-datas-ck').checkbox();", true);
		$this->jquery->exec("$('#crud-viewer-ck').checkbox();", true);
		$this->jquery->exec("$('#crud-events-ck').checkbox();", true);
		$this->jquery->exec("$('#ck-add-route').checkbox();", true);

		$this->jquery->exec('$("#crud-files-ck").checkbox({onChange:function(){ $("#div-view-list").toggle($("#crud-files-ck").checkbox("is checked"));}});', true);
	}

	public function _frmAddIndexCrudController() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$frm = $this->jquery->semantic()->htmlForm("crud-controller-frm");
		$this->crudFormCommon($frm, 'indexCRUD');
		$frm->addExtraFieldRules("path", [
			'empty',
			[
				'contains[{resource}]',
				'The route must contain the {resource} part!'
			],
			[
				'checkRoute',
				'Route {value} already exists!'
			]
		]);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkRoute", $baseRoute . "/_checkRoute", "{}", "result=data.result;", "postForm", [
			"form" => "crud-controller-frm"
		]), true);
		$frm->setSubmitParams($baseRoute . "/_addIndexCrudController", "#frm");
		$this->jquery->renderView($this->_getFiles()
			->getViewAddIndexCrudController(), [
			'controllerNS' => Startup::getNS("controllers"),
			'inverted' => $this->style
		]);
	}

	public function _addCtrlRoute($type) {
		if (URequest::isPost()) {
			$result = [];
			UResponse::asJSON();

			$controller = "\\" . $_POST[$type . "-name"];
			$controller = ClassUtils::getClassSimpleName($controller);
			$result["route-path"] = $controller;
			echo \json_encode($result);
		}
	}

	public function _addCrudController() {
		if (URequest::isPost()) {
			$views = null;
			if (isset($_POST["crud-files"])) {
				$views = $_POST["crud-views"] ?? null;
			}
			$route = '';
			if (isset($_POST["ck-add-route"])) {
				$route = $_POST["route-path"] ?? '';
			}
			$this->scaffold->addCrudController(\ucfirst(\trim($_POST["crud-name"])), UString::doubleBackSlashes($_POST["crud-model"]), $_POST["crud-datas"] ?? null, $_POST["crud-viewer"] ?? null, $_POST["crud-events"] ?? null, $views, $route, isset($_POST["ck-use-inheritance"]));
			$this->jquery->get($this->_getFiles()
				->getAdminBaseRoute() . "/_refreshControllers/refresh", "#dtControllers", [
				"jqueryDone" => "replaceWith",
				"hasLoader" => false,
				"dataType" => "html"
			]);
			echo $this->jquery->compile($this->view);
		}
	}

	public function _addIndexCrudController() {
		if (URequest::isPost()) {
			$views = null;
			if (isset($_POST["crud-files"])) {
				$views = $_POST["crud-views"] ?? null;
			}
			$route = $_POST["path"] ?? '';
			$this->scaffold->setActiveDb($this->getActiveDb());
			$this->scaffold->addIndexCrudController(\ucfirst(\trim($_POST["crud-name"])), $_POST["crud-datas"] ?? null, $_POST["crud-viewer"] ?? null, $_POST["crud-events"] ?? null, $views, $route, isset($_POST["ck-use-inheritance"]));
			$this->jquery->get($this->_getFiles()
				->getAdminBaseRoute() . "/_refreshControllers/refresh", "#dtControllers", [
				"jqueryDone" => "replaceWith",
				"hasLoader" => false,
				"dataType" => "html"
			]);
			echo $this->jquery->compile($this->view);
		}
	}

	public function _frmAddAuthController() {
		$viewList = $this->jquery->semantic()->htmlDropdown("view-list", "", AdminScaffoldController::$views["auth"]);
		$viewList->asSelect("auth-views", true);
		$viewList->setDefaultText("Select views");
		$viewList->addClass('fluid ' . $this->style);
		$authControllers = CacheManager::getControllers("Ubiquity\\controllers\\auth\\AuthController", false, true);
		$authControllers = array_combine($authControllers, $authControllers);
		$ctrlList = $this->jquery->semantic()->htmlDropdown("ctrl-list", "Ubiquity\\controllers\\auth\\AuthController", $authControllers);
		$ctrlList->addClass($this->style);
		$ctrlList->asSelect("baseClass");
		$ctrlList->setDefaultText("Select base class");

		$frm = $this->jquery->semantic()->htmlForm("auth-controller-frm");
		$frm->addExtraFieldRules("auth-name", [
			"empty",
			[
				"checkController",
				"Controller {value} already exists!"
			]
		]);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkController", $this->_getFiles()
			->getAdminBaseRoute() . "/_controllerExists/auth-name", "{}", "result=data.result;", "postForm", [
			"form" => "auth-controller-frm"
		]), true);

		$frm->setValidationParams([
			"on" => "blur",
			"inline" => true
		]);
		$frm->setSubmitParams($this->_getFiles()
			->getAdminBaseRoute() . "/_addAuthController", "#frm");
		$this->jquery->change("#ck-add-route", "$('#div-new-route').toggle($(this).is(':checked'));");
		$this->jquery->jsonOn("change", "#ck-add-route", $this->_getFiles()
			->getAdminBaseRoute() . "/_addCtrlRoute/auth", "post", [
			"context" => "$('#auth-controller-frm')",
			"params" => "$('#auth-controller-frm').serialize()",
			"jsCondition" => "$('#ck-add-route').is(':checked')"
		]);

		$this->jquery->click("#validate-btn", '$("#auth-controller-frm").form("submit");');
		$this->jquery->execOn("click", "#cancel-btn", '$("#frm").html("");');
		$this->jquery->exec("$('#ck-add-route').checkbox();", true);
		$this->jquery->exec('$("#auth-files-ck").checkbox({onChange:function(){ $("#div-view-list").toggle($("#auth-files-ck").checkbox("is checked"));}});', true);
		$this->jquery->renderView($this->_getFiles()
			->getViewAddAuthController(), [
			'controllerNS' => Startup::getNS("controllers"),
			'inverted' => $this->style
		]);
	}

	public function _addAuthController() {
		if (URequest::isPost()) {
			$views = null;
			if (isset($_POST["auth-files"])) {
				$views = $_POST["auth-views"] ?? null;
			}
			$route = '';
			if (isset($_POST["ck-add-route"])) {
				$route = $_POST["route-path"] ?? '';
			}
			$baseClass = $_POST["baseClass"];
			if (! UString::startswith($baseClass, "\\")) {
				$baseClass = "\\" . $baseClass;
			}
			$this->scaffold->addAuthController(ucfirst($_POST["auth-name"]), $baseClass, $views, $route, isset($_POST["ck-use-inheritance"]));
			$this->jquery->get($this->_getFiles()
				->getAdminBaseRoute() . "/_refreshControllers/refresh", "#dtControllers", [
				"jqueryDone" => "replaceWith",
				"hasLoader" => false,
				"dataType" => "html"
			]);
			echo $this->jquery->compile($this->view);
		}
	}
}
