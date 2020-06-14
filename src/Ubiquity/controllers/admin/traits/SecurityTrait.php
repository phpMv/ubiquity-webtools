<?php
namespace Ubiquity\controllers\admin\traits;

use Ajax\semantic\html\elements\HtmlButton;
use Ajax\semantic\html\elements\HtmlLabel;
use Ubiquity\utils\http\UCookie;
use Ubiquity\utils\http\USession;
use Ubiquity\controllers\admin\popo\ComposerDependency;
use Ubiquity\scaffolding\starter\ServiceStarter;
use Ubiquity\controllers\Startup;

/**
 * Ubiquity\controllers\admin\traits$SecurityTrait
 * This class is part of Ubiquity
 *
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 * @author jc
 * @version 1.0.0
 *
 */
trait SecurityTrait {

	protected function _refreshSecurity($asString = false) {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$hasSecurity = \class_exists('\\Ubiquity\\security\\csrf\\CsrfManager');
		$hasShieldon = \class_exists('\Shieldon\Container');
		$componentsValues = [
			'security' => $hasSecurity,
			'shieldon' => $hasShieldon
		];
		$servicesValues = [];
		if ($hasSecurity) {
			$servicesValues['encryption'] = \Ubiquity\security\data\EncryptionManager::isStarted();
			$hasCsrf = \Ubiquity\security\csrf\CsrfManager::isStarted();
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
			$servicesValues['shieldon'] = \Shieldon\Container::get('firewall') !== null;
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
			'Shieldon'
		]);
		$deComponents->setAttached();
		$dependencies = ComposerDependency::load($this->libraries);
		$deComponents->setValueFunction('security', function ($value) use ($dependencies) {
			return $this->installOrInstalledSecurityCompo($value, 'security', 'phpmv', 'ubiquity-security', $dependencies);
		});

		$deComponents->setValueFunction('shieldon', function ($value) use ($dependencies) {
			return $this->installOrInstalledSecurityCompo($value, 'shieldon', 'shieldon', 'shieldon', $dependencies);
		});

		$deServices = $this->jquery->semantic()->dataElement('services', $servicesValues);
		$deServices->setFields(array_keys($servicesValues));
		$deServices->setCaptions([
			'Encryption manager',
			'Csrf manager',
			'Shieldon firewall'
		]);

		$deServices->setValueFunction('encryption', function ($value) {
			return $this->startOrStartedSecurityService($value, 'encryptionManager');
		});
		$deServices->setValueFunction('csrf', function ($value) {
			return $this->startOrStartedSecurityService($value, 'csrfManager');
		});
		$deServices->setValueFunction('shieldon', function ($value) {
			$elm = $this->startOrStartedSecurityService($value, 'shieldon');
			if ($value) {
				$bt = new HtmlButton('bt-shieldon');
				if (isset($this->config['shieldon-url'])) {
					$bt->setValue('Shieldon firewall panel');
					$bt->asLink($this->config['shieldon-url']);
					$bt->addIcon('shield alternate');
					$bt->addClass('blue');
				} else {
					$bt->setValue('Add shieldon admin controller');
					$bt->addIcon('plus');
					$bt->addClass('teal');
				}
				$bt->addClass('tiny right floated');
				$elm->wrap('', $bt);
				return $elm;
			}
			return $elm;
		});
		$deServices->setAttached()->setEdition();

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
			$bt = new HtmlButton('install-' . $idElm, 'Install with composer', 'teal _installComponent');
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
			$bt = new HtmlButton('start-' . $service, 'start the service', 'green _startService');
			$bt->addIcon('play');
			$bt->setProperty('data-service', $service);
			return $bt;
		}
	}

	public function _refreshComponentSecurity() {
		$this->showSimpleMessage("<b>Composer</b> successfully updated!", "success", "Composer", "info circle", null, "msgInfo");
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

	public function _addShieldonController($name, $route) {}
}

