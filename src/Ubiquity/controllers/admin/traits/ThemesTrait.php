<?php
namespace Ubiquity\controllers\admin\traits;

use Ubiquity\themes\ThemesManager;
use Ajax\semantic\html\collections\HtmlMessage;
use Ubiquity\utils\base\UString;
use Ubiquity\utils\http\URequest;

/**
 *
 * @property \Ajax\JsUtils $jquery
 * @author jcheron <myaddressmail@gmail.com>
 *        
 */
trait ThemesTrait {

	abstract public function _showSimpleMessage($content, $type, $title = null, $icon = "info", $timeout = NULL, $staticName = null, $closeAction = null, $toast = false): HtmlMessage;

	abstract public function _saveConfig();

	public function _refreshTheme($partial = true) {
		$activeTheme = ThemesManager::getActiveTheme() ?? 'no theme';
		$themes = ThemesManager::getAvailableThemes();
		$notInstalled = ThemesManager::getNotInstalledThemes();
		$ubiquityCmd = $this->config["devtools-path"] ?? 'Ubiquity';
		$this->jquery->postOnClick('._installTheme', $this->_getFiles()
			->getAdminBaseRoute() . '/_execComposer/_refreshTheme/refresh-theme/html', '{commands: "echo n | ' . $ubiquityCmd . ' install-theme "+$(this).attr("data-ajax")}', '#partial', [
			'before' => '$("#response").html(' . $this->getConsoleMessage_('partial', 'Theme installation...') . ');',
			'hasLoader' => false,
			'partial' => "$('#partial').html(response);"
		]);

		$this->loadView('@admin/themes/refreshTheme.html', compact('activeTheme', 'themes', 'notInstalled', 'partial'));
	}

	public function _createNewTheme() {
		$themeName = $_POST["themeName"];
		$ubiquityCmd = $this->config["devtools-path"] ?? 'Ubiquity';
		$allThemes = ThemesManager::getRefThemes();
		$extend = "";
		if (array_search($_POST["extendTheme"], $allThemes) !== false) {
			$extend = " -x=" . trim($_POST["extendTheme"]);
		}
		$run = $this->runSilent("echo n | {$ubiquityCmd} create-theme " . $themeName . $extend, $output);
		echo $this->showConsoleMessage($output, "Theme creation", $hasError);
		if (! $hasError) {
			if ($run === false) {
				echo $this->_showSimpleMessage("Command executed with errors", "error", "Theme creation", "warning circle");
			} else {
				$msg = sprintf("Theme <b>%s</b> successfully created !", $themeName);
				if ($extend != null) {
					$msg = sprintf("Theme <b>%s</b> based on <b>%s</b> successfully created !", $themeName, $extend);
				}
				echo $this->_showSimpleMessage($msg, "success", "Theme creation", "check square outline");
			}
		}

		$this->jquery->getHref("._setTheme", "#refresh-theme");
		$this->jquery->compile($this->view);
		$this->_refreshTheme();
	}

	public function _installTheme($themeName) {
		$allThemes = ThemesManager::getRefThemes();
		$ubiquityCmd = $this->config["devtools-path"] ?? 'Ubiquity';

		if (array_search($themeName, $allThemes) !== false) {
			$run = $this->runSilent("echo n | {$ubiquityCmd} install-theme " . $themeName, $output);
			echo $this->showConsoleMessage($output, "Theme installation", $hasError);
			if (! $hasError) {
				if ($run === false) {
					echo $this->_showSimpleMessage("Command executed with errors", "error", "Theme installation", "warning circle");
				} else {
					$msg = sprintf("Theme <b>%s</b> successfully installed !", $themeName);
					echo $this->_showSimpleMessage($msg, "success", "Theme installation", "check square outline");
				}
			}
		}
		$this->jquery->getHref("._setTheme", "#refresh-theme");
		$this->jquery->compile($this->view);
		$this->_refreshTheme();
	}

	public function _setTheme($theme) {
		$allThemes = ThemesManager::getAvailableThemes();
		if (array_search($theme, $allThemes) !== false) {
			ThemesManager::setActiveTheme($theme);
			ThemesManager::saveActiveTheme($theme);
		}
		$this->jquery->getHref("._setTheme", "#refresh-theme");
		$this->jquery->compile($this->view);
		$this->_refreshTheme();
	}

	public function _setDevtoolsPath() {
		$path = $_POST['path'];
		$this->config["devtools-path"] = $path;
		$this->_saveConfig();
		echo $this->_checkDevtoolsPath($path);
		echo $this->jquery->compile();
	}

	public function _checkDevtoolsPath($path) {
		$res = $this->runSilent($path . ' version', $return_var);
		if (UString::contains('Ubiquity devtools', $return_var)) {
			$res = $this->showConsoleMessage(\nl2br(\str_replace("\n\n", "\n", $return_var)), "Ubiquity devtools", $hasError, "success", "check square");
			$this->jquery->exec('$("._checkDevtools").toggleClass("green check square",true);$("._checkDevtools").toggleClass("red warning circle",false);$(".devtools-related").dimmer("hide");', true);
		} else {
			$res = $this->_showSimpleMessage(sprintf("Devtools are not available at %s", $path), "error", 'Devtools command path', 'warning circle');
			$this->jquery->exec('$("._checkDevtools").toggleClass("green check square",false);$("._checkDevtools").toggleClass("red warning circle",true);$(".devtools-related").dimmer("show").dimmer({closable:false});', true);
		}
		return $res;
	}

	private function showConsoleMessage($originalMessage, $title, &$hasError, $type = 'info', $icon = 'info circle') {
		$hasError = false;
		if ($originalMessage != null) {
			if (UString::contains("error", $originalMessage)) {
				$type = "error";
				$icon = "warning circle";
				$hasError = true;
			}
			return $this->_showSimpleMessage($originalMessage, $type, $title, $icon);
		}
	}

	protected function runSilent($command, &$return_var) {
		ob_start();
		$res = system($command);
		$return_var = ob_get_clean();
		return $res;
	}

	public function _themeExists($fieldname) {
		if (URequest::isPost()) {
			$result = [];
			header('Content-type: application/json');
			$theme = $_POST[$fieldname];
			$refThemes = ThemesManager::getRefThemes();
			$allThemes = array_merge($refThemes, ThemesManager::getAvailableThemes());
			$result["result"] = (array_search($theme, $allThemes) === false);
			echo json_encode($result);
		}
	}
}

