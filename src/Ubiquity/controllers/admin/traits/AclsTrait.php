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

	abstract protected function _showConfMessage($content, $type, $title, $icon, $url, $responseElement, $data, $attributes = NULL): HtmlMessage;

	abstract protected function _createController($controllerName, $variables = [], $ctrlTemplate = 'controller.tpl', $hasView = false, $jsCallback = "");

	protected function _getAclTabs() {
		$tab = $this->jquery->semantic()->htmlTab('acls');
		$tab->getMenu()->addClass($this->style);
		$this->addTab($tab, AclManager::getAcls(), 'Acls', '_getAclElementsDatatable', $this->style);
		$this->addTab($tab, AclManager::getPermissionMap()->getObjectMap(), 'Map', '_getPermissionMapDatatable', $this->style);
		$this->addTab($tab, AclManager::getRoles(), 'Roles', '_getRolesDatatable', $this->style);
		$this->addTab($tab, AclManager::getResources(), 'Resources', '_getResourcesDatatable', $this->style);
		$this->addTab($tab, AclManager::getPermissions(), 'Permissions', '_getPermissionsDatatable', $this->style);
		$this->jquery->postOnClick('._delete', $this->_getFiles()
			->getAdminBaseRoute() . '/_removeAcl', '{class: $(event.target).closest("tr").attr("data-class"),id:$(event.target).closest("button").attr("data-ajax")}', '#response', [
			'hasLoader' => 'internal'
		]);
		$this->jquery->postOnClick('._edit', $this->_getFiles()
				->getAdminBaseRoute() . '/_editAcl', '{class: $(event.target).closest("tr").attr("data-class"),id:$(event.target).closest("button").attr("data-ajax")}', '#form', [
			'hasLoader' => 'internal'
		]);
		return $tab;
	}

	public function _editAcl() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		if (isset($_POST['id'])) {
			$class = \urldecode($_POST['class']);
			$elmId = \urldecode($_POST['id']);
			if (isset($elmId) && isset($class)) {
				if (is_subclass_of($class, AclElement::class)|| $class===AclElement::class) {
					$acl = AclManager::getAclList()->getAclById_($elmId);
					if ($acl != null) {
						$this->elementAdd(function() use($acl) {return $this->_aclElementForm($acl);},'aclelement');
					}
				} else {
					if (\is_subclass_of($class, Role::class) || $class===Role::class) {
						$role=AclManager::getAclList()->getRoleByName($elmId);
						if ($role!==null) {
							$this->elementAdd(function() use($role) {return $this->_roleForm($role);},'role');
						}
					} elseif (\is_subclass_of($class, Resource::class) || $class===Resource::class) {
						$resource=AclManager::getAclList()->getResourceByName($elmId);
						if ($resource!==null) {
							$this->elementAdd(function() use($resource) {return $this->_resourceForm($resource);},'resource');
						}
					} elseif (\is_subclass_of($class, Permission::class) || $class===Permission::class) {
						$permission=AclManager::getAclList()->getPermissionByName($elmId);
						if ($permission!=null) {
							$this->elementAdd(function() use($permission) {return $this->_permissionForm($permission);},'permission');
						}
					}
				}
			}
		}
	}

	public function _removeAcl() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		if (isset($_POST['data'])) {
			$datas = \json_decode($_POST['data'], true);
			$class = $datas['class'] ?? null;
			$elmId = $datas['id'] ?? null;
			if (isset($elmId) && isset($class)) {
				if (is_subclass_of($class, AclElement::class) || $class==AclElement::class) {
					$acl = AclManager::getAclList()->getAclById_($datas['id']);
					if ($acl != null) {
						$permission = $acl->getPermission()->getName();
						$role = $acl->getRole()->getName();
						$resource = $acl->getResource()->getName();
						AclManager::removeAcl($role, $resource, $permission);
						$msg = $this->toast("ACL with Permission $permission removed to $role on $resource!", 'ACL Deletion', 'info', true);
					}
				} else {
					if (\is_subclass_of($class, Role::class) || $class==Role::class) {
						AclManager::removeRole($elmId);
						$msg = $this->toast("Role $elmId removed!", 'Role Deletion', 'info', true);
					} elseif (\is_subclass_of($class, Resource::class) || $class==Resource::class) {
						AclManager::removeResource($elmId);
						$msg = $this->toast("resource $elmId removed!", 'resource Deletion', 'info', true);
					} elseif (\is_subclass_of($class, Permission::class) || $class==Permission::class) {
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
			$conf = $this->_showConfMessage('Do you want to delete the instance <b>' . \urldecode($_POST['id']) . '</b> of ' . $class, 'error', 'Acl removing confirmation', 'alert', $baseRoute . '/_removeAcl', "#response", \json_encode([
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
		$this->_showSimpleMessage("Service <b>aclManager</b> successfully started!", "success", "Services", "info circle", null, "msgInfo");
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
		AclManager::initCache($config,true);
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
		$this->jquery->execAtLast("$('#modalNewAcl').modal('show');");
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
				$uses = new AclUses();
				$variables["%routePath%"] = $path;
				$variables["%route%"] = CacheManager::getAnnotationsEngineInstance()->getAnnotation($uses, 'route', [
					'path' => $path,
					'automated' => true
				])->asAnnotation();
				$variables['%uses%'] = $uses->getUsesStr();
				$this->jquery->getOnClick("#bt-init-cache", $this->_getFiles()
					->getAdminBaseRoute() . "/_initCacheRouter/0", "#response", [
					"dataType" => "html",
					"attr" => "",
					"hasLoader" => "internal"
				]);
			}

			$resp = $this->_createController($_POST["controllerName"], $variables, 'aclController.tpl', isset($_POST['ck-add-view']));

			$this->loadViewCompo($resp);
		}
	}

	private function elementAdd($callbackForm,$caption){
		$form = $callbackForm();
		$form->fieldAsSubmit('submit', 'green '.$this->style, $this->_getFiles()
				->getAdminBaseRoute() . "/_{$caption}Submit", '#form', [
			'ajax' => [
				'hasLoader' => 'internal'
			]
		]);
		$form->wrap("<div class='ui segment ".$this->style."'>",'</div>');
		$this->jquery->click("#frm-{$caption}-cancel-0", '$("#form").html("");');
		$this->loadViewCompo($form);
	}

	private function elementSubmit($insertCallback,$message,$title) {
		AclManager::reloadFromSelectedProviders([
			AclDAOProvider::class
		]);
		AclManager::filterProviders(AclDAOProvider::class);
		extract($_POST);
		$aclList = AclManager::getAclList();
		$insertCallback($aclList);
		echo $this->toast($message, $title, 'info', true);
		$this->jquery->get($this->_getFiles()
				->getAdminBaseRoute() . '/_refreshAclsFromAjax', '#aclsPart', [
			'hasLoader' => false,
			'jsCallback' => '$("#form").html("");'
		]);
		echo $this->jquery->compile($this->view);
	}

	public function _aclElementAdd() {
		$this->elementAdd(function(){return $this->_aclElementForm();},'aclelement');
	}

	public function _aclElementSubmit() {
		extract($_POST);
		$this->elementSubmit(function($aclList){
			extract($_POST);
			$aclList->addAndAllow($role, $resource, $permission,$_POST['id']??null);
		},"ACL element with Permission $permission granted to $role on $resource!", 'ACL Creation');
	}

	public function _roleAdd() {
		$this->elementAdd(function(){return $this->_roleForm();},'role');
	}

	public function _roleSubmit() {
		extract($_POST);
		$isNew=$_POST['id_']!=null;
		if($isNew){
			$msg="Role $name added!";
			$title='Role creation';
		} else {
			$msg="Role $name updated!";
			$title='Role update';
		}
		$this->elementSubmit(function($aclList) use ($isNew) {
			extract($_POST);
			$role=new Role($name, $parents ?? '');
			if($isNew){
				$id=$_POST['id_'];
				$aclList->updateRole($id,$role);
			}else {
				$aclList->addRole($role);
			}
		},$msg, $title);
	}

	public function _permissionAdd() {
		$this->elementAdd(function(){return $this->_permissionForm();},'permission');
	}

	public function _permissionSubmit() {
		extract($_POST);
		$isNew=$_POST['id_']!=null;
		if($isNew){
			$msg="Permission $name added!";
			$title='Permission creation';
		} else {
			$msg="Permission $name updated!";
			$title='Permission update';
		}
		$this->elementSubmit(function($aclList) use ($isNew) {
			extract($_POST);
			$permission=new Permission($name,$level??0);
			if($isNew){
				$id=$_POST['id_'];
				$aclList->updatePermission($id,$permission);
			}else {
				$aclList->addPermission($permission);
			}
		},$msg, $title);
	}

	public function _resourceAdd() {
		$this->elementAdd(function(){return $this->_resourceForm();},'resource');
	}

	public function _resourceSubmit() {
		extract($_POST);
		$isNew=$_POST['id_']!=null;
		if($isNew){
			$msg="Resource $name added!";
			$title='Resource creation';
		} else {
			$msg="Resource $name updated!";
			$title='Resource update';
		}
		$this->elementSubmit(function($aclList) use ($isNew) {
			extract($_POST);
			$resource=new Resource($name, $value ?? '');
			if ($isNew) {
				$id=$_POST['id_'];
				$aclList->updateResource($id,$resource);
			}else {
				$aclList->addResource($resource);
			}
		},$msg, $title);
	}
}

