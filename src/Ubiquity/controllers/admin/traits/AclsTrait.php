<?php
namespace Ubiquity\controllers\admin\traits;

use Ajax\semantic\html\collections\HtmlMessage;
use Ubiquity\security\acl\models\AclElement;
use Ubiquity\security\acl\AclManager;
use Ubiquity\security\acl\models\Role;
use Ubiquity\security\acl\models\Resource;
use Ubiquity\security\acl\models\Permission;

/**
 *
 * @author jc
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 *
 */
trait AclsTrait {

	abstract public function showConfMessage($content, $type, $title, $icon, $url, $responseElement, $data, $attributes = NULL): HtmlMessage;

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
}

