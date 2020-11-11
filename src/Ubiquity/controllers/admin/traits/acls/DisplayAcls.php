<?php
namespace Ubiquity\controllers\admin\traits\acls;

use Ubiquity\security\acl\models\Role;
use Ubiquity\security\acl\models\Permission;
use Ubiquity\security\acl\models\Resource;
use Ubiquity\security\acl\models\AclElement;
use Ajax\semantic\html\modules\HtmlTab;

/**
 *
 * @author jc
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 *
 */
trait DisplayAcls {

	private function getAbstractAclPart($name, $elements, $class, $fields) {
		$dt = $this->jquery->semantic()->dataTable($name, $class, $elements);
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
			$row->setProperty('data-class', \urlencode(\get_class($object)));
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
			'parents',
			'_fromArray'
		]);
		$dt->fieldAsLabel('name', 'user');
		$dt->fieldAsCheckbox('_fromArray');
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
}

