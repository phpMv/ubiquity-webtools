<?php
namespace Ubiquity\controllers\admin\traits\acls;

use Ubiquity\security\acl\models\Role;
use Ubiquity\security\acl\models\Permission;
use Ubiquity\security\acl\models\Resource;
use Ubiquity\security\acl\models\AclElement;
use Ajax\semantic\html\modules\HtmlTab;
use Ubiquity\security\acl\cache\PermissionsMap;
use Ubiquity\security\acl\cache\PermissionMapObject;
use Ajax\semantic\widgets\datatable\DataTable;
use Ubiquity\security\acl\AclManager;
use Ubiquity\security\acl\persistence\AclDAOProvider;

/**
 *
 * @author jc
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 *
 */
trait DisplayAcls {

	private function getAbstractAclPart($name, $elements, $class, $fields) {
		$dt = new DataTable($name, $class, $elements);
		$dt->setFields($fields);
		$dt->setCompact(true);
		$dt->setEdition();
		$dt->addDeleteButton();
		$dt->onPreCompile(function () use (&$dt) {
			$dt->getHtmlComponent()
				->colRightFromRight(0);
		});
		$dt->setIdentifierFunction(function ($index, $elm) {
			return urlencode($elm->getId_());
		});
		$dt->onNewRow(function ($row, $object) {
			$class = \get_class($object);
			$row->setProperty('data-class', \urlencode(\get_class($object)));
			if ($class === AclElement::class || $class === Role::class || $class === Resource::class || $class == Permission::class || $class == PermissionMapObject::class) {
				$i = $row->getColCount() - 1;
				$row->getItem($i)
					->setContent('');
			}
		});
		return $dt;
	}

	private function addTab(HtmlTab $tab, array $array, $title, $method) {
		$caption = "$title <span class='ui tiny circular teal label'>" . \count($array) . "</span>";
		$tab->addTab($caption, $this->$method("dt$title", $array));
	}

	/**
	 *
	 * @param Role[] $elements
	 */
	public function _getRolesDatatable($name, $elements) {
		$dt = $this->getAbstractAclPart($name, $elements, Role::class, [
			'name',
			'parents'
		]);
		$dt->fieldAsLabel('name', 'user');
		return $dt;
	}

	/**
	 *
	 * @param Permission[] $elements
	 */
	public function _getPermissionsDatatable($name, $elements) {
		usort($elements, function ($elm1, $elm2) {
			return $elm1->getLevel() <=> $elm2->getLevel();
		});
		$dt = $this->getAbstractAclPart($name, $elements, Permission::class, [
			'name',
			'level'
		]);
		$dt->fieldAsLabel('name', 'unlock alternate');
		return $dt;
	}

	/**
	 *
	 * @param Resource[] $elements
	 */
	public function _getResourcesDatatable($name, $elements) {
		$dt = $this->getAbstractAclPart($name, $elements, Resource::class, [
			'name',
			'value'
		]);
		$dt->fieldAsLabel('name', 'archive');
		return $dt;
	}

	/**
	 *
	 * @param AclElement[] $elements
	 */
	public function _getAclElementsDatatable($name, $elements) {
		$dt = $this->getAbstractAclPart($name, $elements, AclElement::class, [
			'role',
			'resource',
			'permission'
		]);
		$dt->fieldAsLabel('role', 'user');
		$dt->fieldAsLabel('resource', 'archive');
		$dt->fieldAsLabel('permission', 'unlock alternate');
		$dt->setCaptions([
			'Roles',
			'Resources',
			'Permissions',
			''
		]);
		return $dt;
	}

	/**
	 *
	 * @param PermissionMapObject[] $elements
	 */
	public function _getPermissionMapDatatable($name, $elements) {
		$dt = $this->getAbstractAclPart($name, $elements, PermissionMapObject::class, [
			'controllerAction',
			'resource',
			'permission',
			'roles'
		]);
		$dt->fieldAsLabel('role', 'user');
		$dt->fieldAsLabel('resource', 'archive');
		$dt->fieldAsLabel('permission', 'unlock alternate');
		$dt->setCaptions([
			'Controller.action',
			'Resource',
			'Permission',
			'Roles',
			''
		]);
		return $dt;
	}

	public function _aclElementForm() {
		$providerClass = AclDAOProvider::class;
		$provider = AclManager::getProvider($providerClass);
		$aclClass = $provider->getModelClasses()[AclElement::class];
		$aclElement = new $aclClass();
		$aclElement->title = "Creation";
		$form = $this->jquery->semantic()->dataForm('frm-aclelement', $aclElement);
		$form->setFields([
			"title\n",
			"role",
			'resource',
			"permission",
			'submit',
			'cancel'
		]);
		$form->setCaptions([
			'Create a new ACL element',
			'Role',
			'Resource',
			'Permission',
			'Create ACL',
			'Cancel'
		]);
		$form->addDividerBefore('submit', '');
		$form->fieldAsMessage(0, [
			'icon' => 'users'
		]);
		$form->fieldAsDataList('role', AclManager::getAclList()->getElementsNames('roles'), [
			'rules' => 'empty'
		]);
		$form->fieldAsDataList('permission', AclManager::getAclList()->getElementsNames('permissions'), [
			'rules' => 'empty'
		]);
		$form->fieldAsDataList('resource', AclManager::getAclList()->getElementsNames('resources'), [
			'rules' => 'empty'
		]);

		$form->fieldAsButton('cancel', 'black', [
			'value' => 'Cancel'
		]);
		$form->setValidationParams([
			"on" => "blur",
			"inline" => true
		]);
		return $form;
	}
}

