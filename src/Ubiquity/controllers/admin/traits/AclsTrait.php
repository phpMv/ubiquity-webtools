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
use Ubiquity\security\acl\persistence\AclDAOProvider;
use Ubiquity\scaffolding\starter\ServiceStarter;
use Ubiquity\cache\CacheManager;
use Ubiquity\creator\HasUsesTrait;
use Ubiquity\controllers\admin\traits\acls\AclUses;

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
		$tab->getMenu()->addClass($this->style);
		$this->addTab($tab, AclManager::getAcls(), 'Acls', '_getAclElementsDatatable',$this->style);
		$this->addTab($tab, AclManager::getPermissionMap()->getObjectMap(), 'Map', '_getPermissionMapDatatable',$this->style);
		$this->addTab($tab, AclManager::getRoles(), 'Roles', '_getRolesDatatable',$this->style);
		$this->addTab($tab, AclManager::getResources(), 'Resources', '_getResourcesDatatable',$this->style);
		$this->addTab($tab, AclManager::getPermissions(), 'Permissions', '_getPermissionsDatatable',$this->style);
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
			$class = $datas['class'] ?? null;
			$elmId = $datas['id'] ?? null;
			if (isset($elmId) && isset($class)) {
				if (is_subclass_of($class, AclElement::class)) {
					$acl = AclManager::getAclList()->getAclById_($datas['id']);
					if ($acl != null) {
						$permission = $acl->getPermission()->getName();
						$role = $acl->getRole()->getName();
						$resource = $acl->getResource()->getName();
						AclManager::removeAcl($role, $resource, $permission);
						$msg = $this->toast("Permission $permission removed to $role on $resource!", 'ACL Deletion', 'info', true);
					}
				} else {
					if (\is_subclass_of($class, Role::class)) {
						AclManager::removeRole($elmId);
						$msg = $this->toast("Role $elmId removed!", 'Role Deletion', 'info', true);
					} elseif (\is_subclass_of($class, Resource::class)) {
						AclManager::removeResource($elmId);
						$msg = $this->toast("resource $elmId removed!", 'resource Deletion', 'info', true);
					} elseif (\is_subclass_of($class, Permission::class)) {
						AclManager::removePermission($elmId);
						$msg = $this->toast("Permission $elmId removed!", 'Permission Deletion', 'info', true);
					}
				}
				if (isset($msg)) {
					echo $msg;
				}
				$this->jquery->get($baseRoute . '/_refreshAclsFromAjax', '#aclsPart', [
					'hasLoader' => false
				]);
				echo $this->jquery->compile();
			}
		} else {
			$class = \urldecode($_POST["class"]);
			$conf = $this->showConfMessage('Do you want to delete the instance <b>' . \urldecode($_POST['id']) . '</b> of ' . $class, 'error', 'Acl removing confirmation', 'alert', $baseRoute . '/_removeAcl', "#response", \json_encode([
				'id' => \urldecode($_POST['id']),
				'class' => \str_replace('\\', '\\\\', \urldecode($_POST['class']))
			]), [
				'hasLoader' => 'internal'
			]);
			$this->loadViewCompo($conf);
		}
	}

	public function _refreshAcls() {
		$tab = $this->_getAclTabs();
		$this->loadViewCompo($tab);
	}

	public function _startAclService() {
		$sStarter = new ServiceStarter();
		$sStarter->addService('aclManager');
		$sStarter->save();
		Startup::reloadServices();
		$this->showSimpleMessage("Service <b>aclManager</b> successfully started!", "success", "Services", "info circle", null, "msgInfo");
		$this->acls();
	}

	public function _refreshAclsFromAjax() {
		$selectedProviders = $this->config['selected-acl-providers'] ?? AclManager::getAclList()->getProviderClasses();
		AclManager::reloadFromSelectedProviders($selectedProviders);
		$this->_refreshAcls();
	}

	public function _activateProvider($eProviderClass) {
		$providerClass = \urldecode($eProviderClass);
		$eProviderClass = \urlencode($providerClass);
		$selectedProviders = $this->config['selected-acl-providers'] ?? AclManager::getAclList()->getProviderClasses();
		if (($index = \array_search($providerClass, $selectedProviders)) !== false) {
			unset($selectedProviders[$index]);
			$this->jquery->hide('._cache[data-class="' . $eProviderClass . '"]', '', '', true);
		} else {
			$selectedProviders[] = $providerClass;
			$this->jquery->show('._cache[data-class="' . $eProviderClass . '"]', '', '', true);
		}
		AclManager::reloadFromSelectedProviders($selectedProviders);
		$this->_refreshAcls();
		$this->config['selected-acl-providers'] = $selectedProviders;
		$this->_saveConfig();
	}

	public function _refreshAclCache($providerClass) {
		$config = Startup::$config;
		CacheManager::start($config);
		AclManager::initCache($config);
		$selectedProviders = $this->config['selected-acl-providers'] ?? AclManager::getAclList()->getProviderClasses();
		AclManager::reloadFromSelectedProviders($selectedProviders);
		$this->_refreshAcls();
	}

	public function _newAclController() {
		$modal = $this->jquery->semantic()->htmlModal("modalNewAcl", "Creating a new Acl controller");
		$frm = $this->jquery->semantic()->htmlForm("frmNewAcl");
		
		$fc = $frm->addField('controllerName')->addRules([
			'empty',
			[
				"checkController",
				"Controller {value} already exists!"
			]
		]);
		$fc->labeled(Startup::getNS());
		$frm->addCheckbox("ck-add-view", "Add default view");
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
		$this->_setStyle($modal);
		$this->_setStyle($frm);
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
			if (isset($path) && $path != null) {
				$uses=new AclUses();
				$variables["%routePath%"]=$path;
				$variables["%route%"] = CacheManager::getAnnotationsEngineInstance()->getAnnotation($uses, 'route', [
					'path' => $path,
					'automated' => true
				])->asAnnotation();
				$variables['%uses%']=$uses->getUsesStr();
				$this->jquery->getOnClick("#bt-init-cache", $this->_getFiles()
					->getAdminBaseRoute() . "/_initCacheRouter/0", "#response", [
					"dataType" => "html",
					"attr" => "",
					"hasLoader" => "internal"
				]);
			}

			$resp = $this->_createController($_POST["controllerName"], $variables, 'aclController.tpl',isset($_POST['ck-add-view']));

			$this->loadViewCompo($resp);
		}
	}

	public function _aclElementAdd() {
		$form = $this->_aclElementForm();
		$form->fieldAsSubmit('submit', 'green fluid', $this->_getFiles()
			->getAdminBaseRoute() . '/_aclElementSubmit', '#form', [
			'ajax' => [
				'hasLoader' => 'internal'
			]
		]);
		$this->jquery->click('#frm-aclelement-cancel-0', '$("#form").html("");');
		$this->loadViewCompo($form);
	}

	public function _aclElementSubmit() {
		AclManager::reloadFromSelectedProviders([
			AclDAOProvider::class
		]);
		AclManager::filterProviders(AclDAOProvider::class);
		extract($_POST);
		$aclList = AclManager::getAclList();
		$aclList->addAndAllow($role, $resource, $permission);
		echo $this->toast("Permission $permission granted to $role on $resource!", 'ACL Creation', 'info', true);
		$this->jquery->get($this->_getFiles()
			->getAdminBaseRoute() . '/_refreshAclsFromAjax', '#aclsPart', [
			'hasLoader' => false,
			'jsCallback' => '$("#form").html("");'
		]);
		echo $this->jquery->compile($this->view);
	}
}

