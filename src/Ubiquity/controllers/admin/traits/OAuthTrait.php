<?php
namespace Ubiquity\controllers\admin\traits;

use Ubiquity\client\oauth\OAuthManager;
use Ajax\semantic\html\elements\HtmlLabel;
use Ubiquity\client\oauth\OAuthAdmin;
use Ajax\semantic\html\collections\HtmlMessage;
use Ubiquity\cache\CacheManager;
use Ajax\semantic\components\validation\Rule;
use Ubiquity\controllers\Startup;
use Ubiquity\utils\http\URequest;

/**
 * Ubiquity\controllers\admin\traits$OAuthTrait
 * This class is part of Ubiquity
 *
 * @author jc
 * @version 1.0.0
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 *
 */
trait OAuthTrait {

	abstract protected function showConfMessage($content, $type, $title, $icon, $url, $responseElement, $data, $attributes = NULL): HtmlMessage;

	abstract public function showSimpleMessage($content, $type, $title = null, $icon = "info", $timeout = NULL, $staticName = null, $closeAction = null, $toast = false): HtmlMessage;

	abstract protected function _createController($controllerName, $variables = [], $ctrlTemplate = 'controller.tpl', $hasView = false, $jsCallback = "");

	protected function getOAuthDataTable($baseRoute) {
		$this->jquery->getOnClick("._delete", $baseRoute . '/_deleteOAuthProviderConf', '#response', [
			'hasLoader' => 'internal',
			'attr' => 'data-name'
		]);

		$this->jquery->getOnClick("._edit", $baseRoute . '/_oauthProviderFrm', '#response', [
			'hasLoader' => 'internal',
			'attr' => 'data-name'
		]);

		$this->jquery->getOn('change', "._activate", $baseRoute . '/_toggleOAuthProvider', '#dtOAuth', [
			'hasLoader' => false,
			'attr' => 'data-ajax',
			'jqueryDone' => 'replaceWith'
		]);

		$providers = \Ubiquity\client\oauth\OAuthAdmin::loadProvidersConfig();

		return $this->_getAdminViewer()->getOAuthDataTable($providers, $baseRoute, $this->config['oauth-providers'] ?? []);
	}

	private function getOauthUserArray($user) {
		$result = [];
		foreach ($user as $k => $v) {
			$lbl = new HtmlLabel('', $k);
			$lbl->addDetail($v);
			$lbl->setBasic();
			if ($v != null && is_string($v)) {
				if ($k == 'photoURL') {
					$lbl->setContent('photoURL');
					$lbl->setProperty('title', $v);
					$lbl->addImage($v, 'photoURL', false);
				}
				$result[$k] = $lbl;
			}
		}
		return $result;
	}

	public function _testOauth($name) {
		$baseRoute = rtrim($GLOBALS["config"]["siteUrl"], '/') . '/' . ltrim($this->_getFiles()->getAdminBaseRoute(), '/');
		$this->config['oauth-providers'][$name] = false;
		$this->_saveConfig();
		$adapter = OAuthManager::startAdapter($name, $baseRoute . '/_testOauth/' . $name);

		if ($adapter) {
			$user = $adapter->getUserProfile();
			$this->config['oauth-providers'][$name] = true;
			$this->_saveConfig();
			$response = $this->loadView($this->_getFiles()
				->getViewOAuthTest(), [
				'provider' => strtolower($name),
				'values' => $this->getOauthUserArray($user)
			], true);
		}
		$this->jquery->click('._close', '$("#response").html("");');

		$this->oauth($response);
	}

	public function _oauthProviderFrm($name) {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$config = OAuthAdmin::getProviderConfig($name);
		$this->_getAdminViewer()->getConfigPartDataForm($config, 'frmProviderConfig');
		$this->addConfigBehavior();

		$this->addSubmitConfigBehavior([
			'form' => '#frmProviderConfig',
			'response' => '#main-content'
		], [
			'submit' => $baseRoute . "/_submitProviderConfig/{$name}",
			'source' => $baseRoute . "/_getProviderConfigSource/{$name}",
			'form' => $baseRoute . "/_refreshConfigFrmProvider/{$name}"
		], [
			'submit' => '$("#response").html("");',
			'cancel' => '$("#response").html("");'
		]);

		$this->jquery->renderView($this->_getFiles()
			->getProviderFrm(), [
			'provider' => $name,
			'icon' => strtolower($name)
		]);
	}

	public function _newOAuthProviderFrm($name) {
		$type = OAuthAdmin::getProviderType($name);
		if ($type === 'OAuth2' || $type === 'OAuth1') {
			$this->showSimpleMessage("You need to create an application on your <u>{$name}</u> account and specify the <b>id</b> and <b>secret</b> credentials of the provider.", "info", "Provider creation", "info circle", null, 'msg-new-provider');
		}
		$this->_oauthProviderFrm($name);
	}

	public function _getProviderConfigSource($provider) {
		$this->getConfigSourcePart(OAuthAdmin::getProviderConfig($provider), $provider, strtolower($provider));
	}

	public function _refreshConfigFrmProvider($provider) {
		$this->refreshConfigFrmPart(OAuthAdmin::getProviderConfig($provider), 'frmProviderConfig');
	}

	public function _submitProviderConfig($provider) {
		$result = $this->getConfigPartFromPost(OAuthAdmin::getProviderConfig($provider));
		$toDelete = $_POST['_toDelete'] ?? '';
		unset($_POST['_toDelete']);
		$toDeletes = \explode(',', $toDelete);
		$this->removeDeletedsFromArray($result, $toDeletes);
		$this->removeEmpty($result);
		try {
			if (OAuthAdmin::addAndSaveProvider($provider, $result)) {
				$msg = $this->showSimpleMessage("The configuration file has been successfully modified!", "positive", "Configuration", "check square", null, "msgConfig");
				if (isset($this->config['oauth-providers'][$provider])) {
					unset($this->config['oauth-providers'][$provider]);
					$this->_saveConfig();
				}
			} else {
				$msg = $this->showSimpleMessage("Impossible to write the configuration file.", "negative", "Configuration", "warning circle", null, "msgConfig");
			}
		} catch (\Exception $e) {
			$msg = $this->showSimpleMessage("Your configuration contains errors.<br>The configuration file has not been saved.<br>" . $e->getMessage(), "negative", "Configuration", "warning circle", null, "msgConfig");
		}
		$this->oauth($msg);
	}

	public function _toggleOAuthProvider($name) {
		OAuthAdmin::toggleAndSaveProvider($name);
		$dt = $this->getOAuthDataTable($this->_getFiles()
			->getAdminBaseRoute(), $this->config['oauth-providers'] ?? []);
		$this->loadViewCompo($dt);
	}

	public function _globalConfigFrm() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$config = OAuthAdmin::getGlobalConfig();
		$this->_getAdminViewer()->getConfigPartDataForm($config, 'frmGlobalConfig');
		$this->addConfigBehavior();
		$this->addSubmitConfigBehavior([
			'form' => '#frmGlobalConfig',
			'response' => '#main-content'
		], [
			'submit' => $baseRoute . "/_submitGlobalOAuthConfig",
			'source' => $baseRoute . "/_getGlobalConfigSource",
			'form' => $baseRoute . "/_refreshConfigFrmGlobal"
		], [
			'submit' => '$("#response").html("");',
			'cancel' => '$("#response").html("");'
		]);

		$this->jquery->renderView($this->_getFiles()
			->getOAuthConfigFrm());
	}

	public function _getGlobalConfigSource() {
		$this->getConfigSourcePart(OAuthAdmin::getGlobalConfig(), 'Global configuration', 'cogs');
	}

	public function _refreshConfigFrmGlobal() {
		$this->refreshConfigFrmPart(OAuthAdmin::getGlobalConfig(), 'frmGlobalConfig');
	}

	public function _submitGlobalOAuthConfig() {
		$result = $this->getConfigPartFromPost(OAuthAdmin::loadConfig());
		$toDelete = $_POST['_toDelete'] ?? '';
		unset($_POST['_toDelete']);
		$toDeletes = \explode(',', $toDelete);
		$this->removeDeletedsFromArray($result, $toDeletes);
		$this->removeEmpty($result);
		try {
			if (OAuthAdmin::saveConfig($result)) {
				$msg = $this->showSimpleMessage("The configuration file has been successfully modified!", "positive", "Configuration", "check square", null, "msgConfig");
			} else {
				$msg = $this->showSimpleMessage("Impossible to write the configuration file.", "negative", "Configuration", "warning circle", null, "msgConfig");
			}
		} catch (\Exception $e) {
			$msg = $this->showSimpleMessage("Your configuration contains errors.<br>The configuration file has not been saved.<br>" . $e->getMessage(), "negative", "Configuration", "warning circle", null, "msgConfig");
		}
		$this->oauth($msg);
	}

	public function _addOAuthProviderFrm() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$providers = OAuthAdmin::getAvailableProviders();
		$frm = $this->jquery->semantic()->htmlForm('frm-provider');
		$frm->addDropdown('providers', $providers, 'Select a Provider...', 'provider...');
		$frm->wrap('<div class="ui olive segment">', '</div>');
		$this->jquery->getOn('change', '#input-dropdown-providers', $baseRoute . '/_newOAuthProviderFrm/', '#response', [
			'attr' => 'value'
		]);
		$this->loadViewCompo($frm);
	}

	public function _deleteOAuthProviderConf($name) {
		$message = $this->showConfMessage("Do you confirm the deletion of `<b>{$name}</b>`?", "error", "Remove confirmation", "question circle", $this->_getFiles()
			->getAdminBaseRoute() . "/_deleteOAuthProvider", "#main-content", $name);
		$this->loadViewCompo($message);
	}

	public function _deleteOAuthProvider() {
		$provider = $_POST['data'];
		if (OAuthAdmin::removeAndSaveProvider($provider)) {
			$msg = $this->showSimpleMessage("The provider <b>{$provider}</b> has been successfully removed!", "positive", "Provider", "times circle", null, "msgConfig");
		} else {
			$msg = $this->showSimpleMessage("Impossible to remove the provider <b>{$provider}</b>.", "negative", "Provider", "warning circle", null, "msgConfig");
		}
		$this->oauth($msg);
	}

	public function _createOAuthControllerFrm() {
		$authControllers = CacheManager::getControllers("Ubiquity\\controllers\\auth\\AbstractOAuthController", false, true);
		$authControllers = array_combine($authControllers, $authControllers);
		$ctrlList = $this->jquery->semantic()->htmlDropdown("ctrl-list", "Ubiquity\\controllers\\auth\\AbstractOAuthController", $authControllers);
		$ctrlList->asSelect("baseClass");
		$ctrlList->setDefaultText("Select base class");

		$frm = $this->jquery->semantic()->htmlForm("oauth-controller-frm");
		$frm->addExtraFieldRules("auth-name", [
			"empty",
			[
				"checkController",
				"Controller {value} already exists!"
			]
		]);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkController", $this->_getFiles()
			->getAdminBaseRoute() . "/_controllerExists/auth-name", "{}", "result=data.result;", "postForm", [
			"form" => "oauth-controller-frm"
		]), true);

		$frm->setValidationParams([
			"on" => "blur",
			"inline" => true
		]);
		$frm->setSubmitParams($this->_getFiles()
			->getAdminBaseRoute() . "/_addOAuthController", "#response");

		$this->jquery->click("#validate-btn", '$("#oauth-controller-frm").form("submit");');
		$this->jquery->execOn("click", "#cancel-btn", '$("#response").html("");');
		$this->jquery->renderView($this->_getFiles()
			->getViewAddOAuthController(), [
			"controllerNS" => Startup::getNS("controllers"),
			"route" => OAuthAdmin::getRedirectRoute($GLOBALS["config"]["siteUrl"])
		]);
	}

	public function _addOAuthController() {
		if (URequest::isPost()) {
			$msg = $this->_createController($_POST['auth-name'], [
				"%baseClass%" => '\\' . ltrim($_POST['baseClass'], '\\'),
				'%route%' => $_POST['route-path']
			], 'oauthController.tpl', false);
			$this->loadViewCompo($msg);
		}
	}
}

