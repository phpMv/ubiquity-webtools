<?php
namespace Ubiquity\controllers\admin\traits;

use Ajax\semantic\components\validation\Rule;
use Ajax\semantic\html\elements\HtmlButton;
use Ajax\semantic\html\elements\HtmlLabel;
use Ubiquity\controllers\Startup;
use Ubiquity\controllers\admin\popo\ComposerDependency;
use Ubiquity\scaffolding\starter\ServiceStarter;
use Ubiquity\utils\http\UCookie;
use Ubiquity\utils\http\URequest;
use Ubiquity\utils\http\USession;
use Ubiquity\controllers\admin\ServicesChecker;
use Ubiquity\security\data\EncryptionManager;

/**
 * Ubiquity\controllers\admin\traits$SecurityTrait
 * This class is part of Ubiquity
 *
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 * @author jc
 * @version 1.0.1
 *
 */
trait SecurityTrait {

	protected function _refreshSecurity($asString = false) {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$hasSecurity = ServicesChecker::hasSecurity();
		$hasShieldon = ServicesChecker::hasShieldon();
		$hasAcl = ServicesChecker::hasAcl();
		$componentsValues = [
			'security' => $hasSecurity,
			'acl' => $hasAcl,
			'shieldon' => $hasShieldon
		];
		$servicesValues = [];
		$servicesNames = [];
		if ($hasSecurity) {
			$servicesValues['encryption'] = \Ubiquity\security\data\EncryptionManager::isStarted();
			$servicesNames[] = 'Encryption manager';

			$hasCsrf = \Ubiquity\security\csrf\CsrfManager::isStarted();
			$servicesNames[] = 'Csrf manager';
			$servicesValues['csrf'] = $hasCsrf;
			if ($hasCsrf) {
				$csrfValues = [
					'selector' => \Ubiquity\security\csrf\CsrfManager::getSelectorClass(),
					'validator' => \Ubiquity\security\csrf\CsrfManager::getValidatorClass(),
					'storage' => \Ubiquity\security\csrf\CsrfManager::getStorageClass()
				];
			}
		}
		if ($hasShieldon) {

			$servicesValues['shieldon'] = (\Shieldon\Container::get('firewall') !== null);
			$servicesNames[] = 'Shieldon firewall';
		}
		if ($hasAcl) {
			$servicesValues['acl'] = (\Ubiquity\security\acl\AclManager::getAclList() !== null);
			$servicesNames[] = 'Acl Manager';
		}
		$sessionValues = [];
		if ($sessionValues['started'] = USession::isStarted()) {
			$sessionValues['class'] = USession::getInstanceClass();
			$sessionValues['protection'] = USession::getCsrfProtectionClass();
			$sessionValues['visitorCount'] = USession::visitorCount();
		}
		$cookieValues = [
			'transformer' => UCookie::getTransformerClass() ?? 'Nothing'
		];
		$deComponents = $this->jquery->semantic()->dataElement('components', $componentsValues);
		$deComponents->setFields(array_keys($componentsValues));
		$deComponents->setCaptions([
			'ubiquity-security',
			'ubiquity-acl',
			'Shieldon'
		]);
		$deComponents->setAttached();
		$dependencies = ComposerDependency::load($this->libraries);
		$deComponents->setValueFunction('security', function ($value) use ($dependencies) {
			return $this->installOrInstalledSecurityCompo($value, 'security', 'phpmv', 'ubiquity-security', $dependencies);
		});

		$deComponents->setValueFunction('acl', function ($value) use ($dependencies) {
			return $this->installOrInstalledSecurityCompo($value, 'acl', 'phpmv', 'ubiquity-acl', $dependencies);
		});

		$deComponents->setValueFunction('shieldon', function ($value) use ($dependencies) {
			return $this->installOrInstalledSecurityCompo($value, 'shieldon', 'shieldon', 'shieldon', $dependencies);
		});
		if (count($servicesValues) > 0) {
			$deServices = $this->jquery->semantic()->dataElement('services', $servicesValues);
			$deServices->setFields(\array_keys($servicesValues));
			$deServices->setCaptions($servicesNames);

			$deServices->setValueFunction('encryption', function ($value) {
				$res = $this->startOrStartedSecurityService($value, 'encryptionManager');
				if ($value) {
					$lbl = new HtmlLabel('', EncryptionManager::getEncryptionInstance()->getCipher());
					$lbl->addClass('circular grey floated right');
					$res->wrap('', $lbl);
				}
				return $res;
			});
			$deServices->setValueFunction('csrf', function ($value) {
				return $this->startOrStartedSecurityService($value, 'csrfManager');
			});

			$deServices->setValueFunction('acl', function ($value) use ($baseRoute) {
				$elm = $this->startOrStartedSecurityService($value, 'aclManager');
				if ($value) {
					$bt = new HtmlButton('bt-acl', 'Manage Acls');
					$bt->addIcon("users");
					$bt->addClass('tiny black right floated ');
					$this->jquery->getOnClick('#bt-acl', $baseRoute . '/acls', '#main-content', [
						'hasLoader' => 'internal'
					]);
					$elm->wrap('', $bt);
				}
				return $elm;
			});
			$deServices->setValueFunction('shieldon', function ($value) use ($baseRoute) {
				$elm = $this->startOrStartedSecurityService($value, 'shieldon');
				$bts = [];
				if ($value) {
					$validUrl = false;
					if (isset($this->config['shieldon-url'])) {
						$bt = new HtmlButton('bt-shieldon-url', 'Shieldon firewall panel');
						if (Startup::isValidUrl($this->config['shieldon-url'])) {
							$bt->asLink('/' . $this->config['shieldon-url'], 'shieldon');
							$bt->addIcon('shield alternate');
							$bt->addClass('blue');
							$validUrl = true;
						} else {
							$bt->addIcon('warning circle');
							$bt->addClass('red disabled');
						}
						$bt->addClass('tiny right floated ');
						$bts[] = $bt;
					}

					if (! $validUrl) {
						$bt = new HtmlButton('bt-shieldon', 'Add shieldon controller');
						$bt->addIcon('plus');
						$bt->addClass('tiny right floated teal');
						$this->jquery->getOnClick('#bt-shieldon', $baseRoute . '/_addSieldonControllerFrm', '#response', [
							'hasLoader' => 'internal'
						]);
						$bts[] = $bt;
					}

					$elm->wrap('', $bts);
					return $elm;
				}
				return $elm;
			});
			$deServices->setAttached();
		}

		$deSession = $this->jquery->semantic()->dataElement('session', $sessionValues);
		$deSession->setFields(array_keys($sessionValues));
		$deSession->setCaptions([
			'Started?',
			'Instance class',
			'Csrf protection',
			'Session count'
		]);
		$deSession->fieldAsCheckbox('started', [
			'type' => 'slider disabled'
		]);
		$deSession->fieldAsLabel('class');
		$deSession->fieldAsLabel('protection', '', [
			'jsCallback' => function ($elm, $value) {
				$call = $value->protection;
				$elm->addIcon(($call::getLevel() > 0) ? 'lock' : 'unlock');
			}
		]);
		$deSession->fieldAsLabel('visitorCount', null, 'circular');
		$deSession->setAttached()->setEdition();

		$deCookies = $this->jquery->semantic()->dataElement('cookies', $cookieValues);
		$deCookies->setFields([
			'transformer'
		]);
		$deCookies->setCaptions([
			'Transformer'
		]);
		$deCookies->fieldAsLabel('transformer');
		$deCookies->setAttached()->setEdition();

		if ($hasSecurity && $hasCsrf) {
			$deCsrf = $this->jquery->semantic()->dataElement('csrf', $csrfValues);
			$deCsrf->setFields(array_keys($csrfValues));
			$deCsrf->setCaptions([
				'Selector',
				'Validator',
				'Storage'
			]);
			$deCsrf->fieldsAs([
				'label',
				'label',
				'label'
			]);
			$deCsrf->setAttached()->setEdition();
			$deCsrf->wrap('<div class="ui top attached orange segment"><i class="ui check double icon"></i> Form Csrf</div>');
		}

		$this->jquery->postOnClick('._installComponent', $baseRoute . '/_execComposer/_refreshComponentSecurity/securityPart', '{commands: "composer require "+$(this).attr("data-composer")}', '#partial', [
			'before' => '$("#response").html(' . $this->getConsoleMessage_('partial', 'Install dependency...') . ');',
			'hasLoader' => false,
			'partial' => "$('#partial').html(response);"
		]);
		$this->jquery->getOnClick('._startService', $baseRoute . '/_startService', '#securityPart', [
			'attr' => 'data-service',
			'hasLoader' => 'internal'
		]);
		return $this->jquery->renderView($this->_getFiles()
			->getViewSecurityPart(), [], $asString);
	}

	protected function installOrInstalledSecurityCompo(bool $value, $idElm, $vendor, $package, $dependencies) {
		if ($value) {
			$dep = ComposerDependency::getDependency($vendor, $package, $dependencies);

			$lbl = new HtmlLabel('lbl-' . $idElm, 'Installed', 'green check');
			if (isset($dep)) {
				$lbl->addDetail($dep->getVersion());
			}
			return $lbl;
		} else {
			$bt = new HtmlButton('install-' . $idElm, 'Install with composer', 'teal _installComponent tiny');
			$bt->addIcon('plus');
			$bt->setProperty('data-composer', $vendor . '/' . $package);
			return $bt;
		}
	}

	protected function startOrStartedSecurityService(bool $value, $service) {
		if ($value) {
			$lbl = new HtmlLabel('lbl-' . $service, 'Started', 'blue toggle on');
			$lbl->addDetail($service);
			return $lbl;
		} else {
			$bt = new HtmlButton('start-' . $service, 'Start', 'green _startService tiny');
			$bt->addIcon('play');
			$bt->setProperty('data-service', $service);
			return $bt;
		}
	}

	public function _refreshComponentSecurity() {
		$this->showSimpleMessage("<b>Composer</b> successfully updated!", "success", "Composer", "info circle", null, "msgInfo");
		$this->_refreshSecurity();
	}

	public function _refreshComponentSecurityForShieldon() {
		$this->showSimpleMessage("<b>Shieldon</b> admin controller successfully created!", "success", "Controller", "info circle", null, "msgInfo");
		$this->_refreshSecurity();
	}

	public function _startService($service) {
		$sStarter = new ServiceStarter();
		$sStarter->addService($service);
		$sStarter->save();
		Startup::reloadServices();
		$this->showSimpleMessage("Service <b>" . ucfirst($service) . "</b> successfully started!", "success", "Services", "info circle", null, "msgInfo");
		$this->_refreshSecurity();
	}

	public function _addSieldonControllerFrm() {
		$modal = $this->jquery->semantic()->htmlModal("modalShieldon", "Creating a controller for Shieldon firewall admin");
		$modal->setInverted();
		$frm = $this->jquery->semantic()->htmlForm("frmShieldon");
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
		$frm->addInput("path", "", "text", "/shieldon")->addRule([
			"checkRoute",
			"Route {value} already exists!"
		]);
		$frm->addContent("</div>");

		$frm->setValidationParams([
			"on" => "blur",
			"inline" => true
		]);
		$frm->setSubmitParams($this->_getFiles()
			->getAdminBaseRoute() . "/_addShieldonController", "#response", [
			"hasLoader" => false
		]);
		$modal->setContent($frm);
		$modal->addAction("Validate");
		$this->jquery->click("#action-modalShieldon-0", "$('#frmShieldon').form('submit');", false, false);
		$modal->addAction("Close");
		$this->jquery->change('#controllerName', 'if($("#ck-add-route").is(":checked")){$("#path").val($(this).val());}');
		$this->jquery->exec("$('.dimmer.modals.page').html('');$('#modalShieldon').modal('show');", true);
		$this->jquery->jsonOn("change", "#ck-add-route", $this->_getFiles()
			->getAdminBaseRoute() . "/_addRouteWithNewAction", "post", [
			"context" => "$('#frmShieldon')",
			"params" => "$('#frmShieldon').serialize()",
			"jsCondition" => "$('#ck-add-route').is(':checked')"
		]);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkRoute", $this->_getFiles()
			->getAdminBaseRoute() . "/_checkRoute", "{}", "result=data.result;", "postForm", [
			"form" => "frmShieldon"
		]), true);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkController", $this->_getFiles()
			->getAdminBaseRoute() . "/_checkController", "{}", "result=data.result;", "postForm", [
			"form" => "frmShieldon"
		]), true);
		$this->jquery->change("#ck-add-route", '$("#div-new-route").toggle($(this).is(":checked"));if($(this).is(":checked")){$("#path").val($("#controllerName").val());}');
		$this->loadViewCompo($modal);
	}

	public function _addShieldonController() {
		if (URequest::isPost()) {
			$variables = [];
			$path = URequest::post("path");
			$variables["%path%"] = $path;
			if (isset($path)) {
				$variables["%route%"] = '@route("' . $path . '")';
				$this->config['shieldon-url'] = $path;
				$this->_saveConfig();
			}
			$csrf = '';
			if (ServicesChecker::isCsrfStarted()) {
				$csrf = "\$controlPanel->csrf('_token', \Ubiquity\security\csrf\CsrfManager::generateValue(32));";
			}
			$variables['%csrf%'] = $csrf;
			echo $this->_createController($_POST["controllerName"], $variables, 'shieldonController.tpl', false, $this->jquery->getDeferred($this->_getFiles()
				->getAdminBaseRoute() . "/_refreshComponentSecurityForShieldon", "#securityPart", [
				'hasLoader' => false,
				'jsCallback' => '$("#response").html("");'
			]));
			echo $this->jquery->compile();
		}
	}
}

