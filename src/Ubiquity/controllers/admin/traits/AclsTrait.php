<?php
namespace Ubiquity\controllers\admin\traits;

use Ajax\semantic\components\validation\Rule;
use Ajax\semantic\html\collections\HtmlMessage;
use Ubiquity\controllers\Startup;
use Ubiquity\security\acl\AclManager;
use Ubiquity\security\acl\models\AclElement;
use Ubiquity\security\acl\models\Permission;
use Ubiquity\security\acl\models\Resource;
use Ubiquity\security\acl\models\Role;
use Ubiquity\utils\http\URequest;

/**
 *
 * @author jc
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 *
 */
trait AclsTrait {

	abstract public function showConfMessage($content, $type, $title, $icon, $url, $responseElement, $data, $attributes = NULL): HtmlMessage;

	abstract protected function _createController($controllerName, $variables = [], $ctrlTemplate = 'controller.tpl', $hasView = false, $jsCallback = "");

	protected function _getAclTabs() {
		$tab = $this->jquery->semantic()->htmlTab('acls');
		$this->addTab($tab, AclManager::getAcls(), 'Acls', '_getAclElementsDatatable');
		$this->addTab($tab, AclManager::getRoles(), 'Roles', '_getRolesDatatable');
		$this->addTab($tab, AclManager::getResources(), 'Resources', '_getResourcesDatatable');
		$this->addTab($tab, AclManager::getPermissions(), 'Permissions', '_getPermissionsDatatable');
		$this->jquery->postOnClick('._delete', $this->_getFiles()
			->getAdminBaseRoute() . '/_removeAcl', '{class: $(event.target).closest("tr").attr("data-class"),id:$(event.target).closest("button").attr("data-ajax")}', '#response', [
			'hasLoader' => 'internal'
		]);
		return $tab;
	}

	public function _removeAcl() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		if (isset($_POST['data'])) {
			$datas = \json_decode($_POST['data'], true);
			$class = urldecode($datas['class']) ?? null;
			$elmId = $datas['id'] ?? null;
			if (isset($elmId)) {
				if (is_subclass_of($class, AclElement::class)) {
					$acl = AclManager::getAclList()->getAclById_($datas['id']);
					if ($acl != null) {
						AclManager::removeAcl($acl->getRole()->getName(), $acl->getResource()->getName(), $acl->getPermission()->getName());
					}
				} else {
					if (is_subclass_of($class, Role::class)) {
						AclManager::removeRole($elmId);
					} elseif (is_subclass_of($class, Resource::class)) {
						AclManager::removeResource($elmId);
					} elseif (is_subclass_of($class, Permission::class)) {
						AclManager::removePermission($elmId);
					}
				}
				$this->jquery->get($baseRoute . '/_refreshAcls', '#aclsPart');
				echo $this->jquery->compile();
			}
		} else {
			$class = urldecode($_POST["class"]);
			$conf = $this->showConfMessage('Do you want to delete the instance <b>' . $_POST['id'] . '</b> of ' . $class, 'error', 'Acl removing confirmation', 'alert', $baseRoute . '/_removeAcl', "#response", \json_encode($_POST));
			$this->loadViewCompo($conf);
		}
	}

	public function _refreshAcls() {
		$tab = $this->_getAclTabs();
		$this->loadViewCompo($tab);
	}

	public function _activateProvider($providerClass) {
		$providerClass = urldecode($providerClass);
		$selectedProviders = $this->config['selected-acl-providers'] ?? AclManager::getAclList()->getProviderClasses();
		if (($index = \array_search($providerClass, $selectedProviders)) !== false) {
			unset($selectedProviders[$index]);
		} else {
			$selectedProviders[] = $providerClass;
		}
		AclManager::reloadFromSelectedProviders($selectedProviders);
		$this->_refreshAcls();
		$this->config['selected-acl-providers'] = $selectedProviders;
		$this->_saveConfig();
	}

	public function _newAclController() {
		$modal = $this->jquery->semantic()->htmlModal("modalNewAcl", "Creating a new Acl controller");
		$modal->setInverted();
		$frm = $this->jquery->semantic()->htmlForm("frmNewAcl");
		$fc = $frm->addField('controllerName')->addRules([
			'empty',
			[
				"checkController",
				"Controller {value} already exists!"
			]
		]);
		$fc->labeled(Startup::getNS());

		$frm->addCheckbox("ck-add-route", "Add route...");

		$frm->addContent("<div id='div-new-route' style='display: none;'>");
		$frm->addDivider();
		$frm->addInput("path", "", "text", "")->addRule([
			"checkRoute",
			"Route {value} already exists!"
		]);
		$frm->addContent("</div>");

		$frm->setValidationParams([
			"on" => "blur",
			"inline" => true
		]);
		$frm->setSubmitParams($this->_getFiles()
			->getAdminBaseRoute() . "/_createAclController", "#response", [
			"hasLoader" => false
		]);
		$modal->setContent($frm);
		$modal->addAction("Validate");
		$this->jquery->click("#action-modalNewAcl-0", "$('#frmNewAcl').form('submit');", false, false);
		$modal->addAction("Close");
		$this->jquery->change('#controllerName', 'if($("#ck-add-route").is(":checked")){$("#path").val($(this).val());}');
		$this->jquery->exec("$('.dimmer.modals.page').html('');$('#modalNewAcl').modal('show');", true);
		$this->jquery->jsonOn("change", "#ck-add-route", $this->_getFiles()
			->getAdminBaseRoute() . "/_addRouteWithNewAction", "post", [
			"context" => "$('#frmNewAcl')",
			"params" => "$('#frmNewAcl').serialize()",
			"jsCondition" => "$('#ck-add-route').is(':checked')"
		]);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkRoute", $this->_getFiles()
			->getAdminBaseRoute() . "/_checkRoute", "{}", "result=data.result;", "postForm", [
			"form" => "frmNewAcl"
		]), true);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkController", $this->_getFiles()
			->getAdminBaseRoute() . "/_checkController", "{}", "result=data.result;", "postForm", [
			"form" => "frmNewAcl"
		]), true);
		$this->jquery->change("#ck-add-route", '$("#div-new-route").toggle($(this).is(":checked"));if($(this).is(":checked")){$("#path").val($("#controllerName").val());}');
		$this->loadViewCompo($modal);
	}

	public function _createAclController() {
		if (URequest::isPost()) {
			$variables = [];
			$path = URequest::post("path", null);
			$variables["%path%"] = $path;
			if (isset($path)) {
				$variables["%route%"] = '@route("' . $path . '","automated"=>true)';
				$this->jquery->getOnClick("#bt-init-cache", $this->_getFiles()
					->getAdminBaseRoute() . "/_initCacheRouter/0", "#response", [
					"dataType" => "html",
					"attr" => "",
					"hasLoader" => "internal"
				]);
			}

			$resp = $this->_createController($_POST["controllerName"], $variables, 'aclController.tpl');

			$this->loadViewCompo($resp);
		}
	}
}

