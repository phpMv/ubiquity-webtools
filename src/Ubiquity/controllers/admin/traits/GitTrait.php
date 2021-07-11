<?php
namespace Ubiquity\controllers\admin\traits;

use Ubiquity\controllers\Startup;
use Ubiquity\controllers\admin\popo\RepositoryGit;
use Ubiquity\utils\http\URequest;
use Ubiquity\utils\base\UString;
use Ubiquity\utils\git\GitFileStatus;
use Ajax\semantic\html\collections\HtmlMessage;
use Ubiquity\utils\base\UFileSystem;
use Cz\Git\GitException;
use Ubiquity\cache\CacheManager;
use Ubiquity\utils\base\UArray;
use Ubiquity\utils\git\UGitRepository;

/**
 *
 * @author jc
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 * @property \Ubiquity\views\View $view
 * @property \Ubiquity\scaffolding\AdminScaffoldController $scaffold
 */
trait GitTrait {

	abstract public function _getAdminData();

	abstract public function _getAdminViewer();

	abstract public function _getFiles();

	abstract public function loadView($viewName, $pData = NULL, $asString = false);

	abstract public function git();

	abstract public function _showSimpleMessage($content, $type, $title = null, $icon = "info", $timeout = NULL, $staticName = null, $closeAction = null, $toast = false): HtmlMessage;

	protected function _getRepo($getfiles = true) {
		$gitRepo = RepositoryGit::init($getfiles);
		return $gitRepo;
	}

	protected function gitTabs(\Ubiquity\controllers\admin\popo\RepositoryGit $gitRepo, string $loader) {
		$files = $gitRepo->getFiles();
		$this->_getAdminViewer()->getGitFilesDataTable($files);
		$this->_getAdminViewer()->getGitCommitsDataTable($gitRepo->getCommits());

		$this->jquery->exec('$("#lbl-changed").toggle(' . ((sizeof($files) > 0) ? "true" : "false") . ');', true);

		$this->jquery->exec('$("#commit-frm").form({"fields":{"summary":{"rules":[{"type":"empty"}]},"files-to-commit[]":{"rules":[{"type":"minCount[1]","prompt":"You must select at least 1 file!"}]}},"on":"blur","onSuccess":function(event,fields){' . $this->jquery->postFormDeferred($this->_getFiles()
			->getAdminBaseRoute() . "/_gitCommit", "commit-frm", "#messages", [
			"preventDefault" => true,
			"stopPropagation" => true,
			"ajaxLoader" => $loader
		]) . ';return false;}});', true);
		$this->jquery->exec('$("#git-tabs .item").tab();', true);
	}

	public function _gitTabsRefresh() {
		$this->_showSimpleMessage("<b>Git command</b> successfully executed!", "success", "Git commands", "info circle", null, "msgInfo");
		$gitRepo = $this->_getRepo();
		$this->gitTabs($gitRepo, '<div class="ui active inline centered indeterminate text loader">Waiting for git operation...</div>');
		echo $this->jquery->compile($this->view);
		$this->jquery->renderView($this->_getFiles()
			->getViewGitTabsRefresh());
	}

	public function _gitInit() {
		$this->_getRepo();
		$appDir = Startup::getApplicationDir();
		try {
			UGitRepository::init(Startup::getApplicationDir());
			$gitignoreFile = $appDir . \DS . ".gitignore";
			if (! file_exists($gitignoreFile)) {
				UFileSystem::openReplaceWriteFromTemplateFile($this->scaffold->getTemplateDir() . "/gitignore.tpl", $gitignoreFile, []);
			}
			$this->git();
		} catch (GitException $ge) {
			echo $this->_showSimpleMessage($ge->getMessage(), "negative", "Push", "upload", null, "init-message");
			echo $this->jquery->compile($this->view);
		}
	}

	public function _gitFrmSettings() {
		$gitRepo = $this->_getRepo();
		$this->_getAdminViewer()->gitFrmSettings($gitRepo);
		$this->jquery->execOn("click", "#validate-btn", '$("#frmGitSettings").form("submit");');
		$this->jquery->execOn("click", "#cancel-btn", '$("#frm").html("");');
		$this->jquery->renderView($this->_getFiles()
			->getViewGitSettings(), [
			'inverted' => $this->style
		]);
	}

	public function _updateGitParams() {
		CacheManager::$cache->store(RepositoryGit::$GIT_SETTINGS, $_POST);
		$gitRepo = $this->_getRepo(false);
		$activeRemoteUrl = $gitRepo->getRemoteUrl();
		$newRemoteUrl = URequest::post("remoteUrl");
		if (UString::isNull($activeRemoteUrl)) {
			$gitRepo->getRepository()->addRemote("origin", $newRemoteUrl);
		} elseif ($activeRemoteUrl != $newRemoteUrl) {
			$gitRepo->getRepository()->setRemoteUrl("origin", $newRemoteUrl);
		}
		$this->git();
	}

	public function _gitCommit() {
		$filesToCommit = URequest::post("files-to-commit", []);
		if (sizeof($filesToCommit) > 0) {
			$messages = [];
			$countFilesToAdd = 0;
			$countFilesUpdated = 0;
			$countFilesIgnored = 0;
			$gitRepo = $this->_getRepo(true);
			$repo = $gitRepo->getRepository();
			$filesToAdd = [];
			$allFiles = $gitRepo->getFiles();
			foreach ($allFiles as $filename => $uFile) {
				if (in_array($filename, $filesToCommit)) {
					$filesToAdd[] = $filename;
					if ($uFile->getStatus() == GitFileStatus::$UNTRACKED) {
						$countFilesToAdd ++;
					} else {
						$countFilesUpdated ++;
					}
				} else {
					if ($uFile->getStatus() != GitFileStatus::$UNTRACKED) {
						$countFilesIgnored ++;
					}
				}
			}

			$repo->addFile($filesToAdd);
			if ($countFilesToAdd > 0) {
				$messages[] = $countFilesToAdd . " new file(s) added";
			}
			if ($countFilesIgnored > 0) {
				$messages[] = $countFilesIgnored . " ignored file(s).";
			}
			if ($countFilesUpdated > 0) {
				$messages[] = $countFilesUpdated . " updated file(s).";
			}

			$message = URequest::post("summary", "No summary");
			if (UString::isNotNull(URequest::post("description", "")))
				$message = [
					$message,
					URequest::post("description")
				];
			$repo->commit($message);
			$msg = $this->_showSimpleMessage("Commit successfully completed!", "positive", "Commit", "check square", null, "init-message");
			$msg->addList($messages);
			$this->_refreshParts();
		} else {
			$msg = $this->_showSimpleMessage("Nothing to commit!", "", "Commit", "warning circle", null, "init-message");
		}
		$this->loadViewCompo($msg);
	}

	protected function _refreshParts() {
		$this->jquery->exec('$(".to-clear").html("");$(".to-clear-value").val("");', true);
		$this->jquery->get($this->_getFiles()
			->getAdminBaseRoute() . "/_refreshGitFiles", "#dtGitFiles", [
			"attr" => "",
			"jqueryDone" => "replaceWith",
			"hasLoader" => false
		]);
		$this->jquery->get($this->_getFiles()
			->getAdminBaseRoute() . "/_refreshGitCommits", "#dtCommits", [
			"attr" => "",
			"jqueryDone" => "replaceWith",
			"hasLoader" => false
		]);
	}

	public function _gitPush() {
		$gitRepo = $this->_getRepo(false);
		try {
			if ($gitRepo->setRepoRemoteUrl()) {
				$repo = $gitRepo->getRepository();
				$repo->push("origin master", [
					"--set-upstream"
				]);
				$msg = $this->_showSimpleMessage("Push successfully completed!", "positive", "Push", "upload", null, "init-message");
				$this->_refreshParts();
			} else {
				$msg = $this->_showSimpleMessage("Check your github settings before pushing! (user name, password or remote url)", "negative", "Push", "upload", null, "init-message");
			}
		} catch (GitException $ge) {
			$msg = $this->_showSimpleMessage($ge->getMessage(), "negative", "Push", "upload", null, "init-message");
		}
		$this->loadViewCompo($msg);
	}

	public function _gitPull() {
		$gitRepo = $this->_getRepo(false);
		$repo = $gitRepo->getRepository();
		$repo->pull();
		$msg = $this->_showSimpleMessage("Pull successfully completed!", "positive", "Pull", "download", null, "init-message");
		$this->_refreshParts();
		$this->loadViewCompo($msg);
	}

	public function _gitIgnoreEdit() {
		$this->jquery->postFormOnClick("#validate-btn", $this->_getFiles()
			->getAdminBaseRoute() . "/_gitIgnoreValidate", "gitignore-frm", "#frm");
		$this->jquery->execOn("click", "#cancel-btn", '$("#frm").html("");');
		$gitRepo = $this->_getRepo(false);
		$content = UFileSystem::load($gitRepo->getBaseFolder() . \DS . ".gitignore");
		if ($content === false) {
			$content = "#gitignorefile\n";
		}
		$this->jquery->renderView($this->_getFiles()
			->getViewGitIgnore(), [
			'content' => $content,
			'inverted' => $this->style
		]);
	}

	public function _gitIgnoreValidate() {
		if (URequest::isPost()) {
			$content = URequest::post("content");
			$gitRepo = $this->_getRepo(false);
			if (UFileSystem::save($gitRepo->getBaseFolder() . \DS . ".gitignore", $content)) {
				$this->jquery->get($this->_getFiles()
					->getAdminBaseRoute() . "/_refreshGitFiles", "#dtGitFiles", [
					"attr" => "",
					"jqueryDone" => "replaceWith",
					"hasLoader" => false
				]);
				$message = $this->_showSimpleMessage("<b>.gitignore</b> file saved !", "positive", "gitignore", "git");
			} else {
				$message = $this->_showSimpleMessage("<b>.gitignore</b> file not saved !", "warning", "gitignore", "git");
			}
		}
		$this->loadViewCompo($message);
	}

	public function _refreshGitFiles() {
		$gitRepo = $this->_getRepo();
		$files = $gitRepo->getFiles();
		echo $this->_getAdminViewer()->getGitFilesDataTable($files);
		$this->jquery->exec('$("#lbl-changed").toggle(' . ((sizeof($files) > 0) ? "true" : "false") . ');', true);
		echo $this->jquery->compile($this->view);
	}

	public function _refreshGitCommits() {
		$gitRepo = $this->_getRepo(false);
		$dt = $this->_getAdminViewer()->getGitCommitsDataTable($gitRepo->getCommits());
		$this->loadViewCompo($dt);
	}

	public function _gitChangesInfiles(...$filenameParts) {
		$filename = implode(\DS, $filenameParts);
		$gitRepo = $this->_getRepo(false);
		$changes = $gitRepo->getRepository()->getChangesInFile($filename);
		if (UString::isNull($changes)) {
			$changes = str_replace(PHP_EOL, " ", UFileSystem::load(Startup::getApplicationDir() . \DS . $filename));
		}
		$this->jquery->exec('var value=\'' . htmlentities(str_replace("'", "\\'", str_replace("\u", "\\\u", $changes))) . '\';$("#changes-in-file").html(Diff2Html.getPrettyHtml($("<div/>").html(value).text()),{inputFormat: "diff", showFiles: true, matching: "lines"});', true);
		echo '<div id="changes-in-file"></div>';
		echo $this->jquery->compile($this->view);
	}

	public function _gitChangesInCommit($commitHash) {
		$gitRepo = $this->_getRepo(false);
		$changes = $gitRepo->getRepository()->getChangesInCommit($commitHash);
		if (UString::isNull($changes)) {
			$changes = "No change";
		}
		$this->jquery->exec('var value=\'' . htmlentities(str_replace("'", "\\'", str_replace("\u", "\\\u", $changes))) . '\';var diff2htmlUi = new Diff2HtmlUI({diff: $("<div/>").html(value).text()});diff2htmlUi.draw("#changes-in-commit", {inputFormat: "diff", showFiles: true, matching: "lines"});diff2htmlUi.fileListCloseable("#changes-in-commit", true);', true);
		echo '<div id="changes-in-commit"></div>';
		echo $this->jquery->compile($this->view);
	}

	public function _gitCmdFrm() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$this->getGitMacrosDropdown();
		$this->jquery->postFormOnClick('#validate-btn', $baseRoute . '/_gitCmdExec', 'git-frm', '#partial', [
			'before' => '$("#frm").html("");$("#messages").html(' . $this->getConsoleMessage_('partial', "Git commands...") . ');',
			'hasLoader' => false,
			'partial' => "$('#partial').html(response);"
		]);
		$this->jquery->postOnClick('#add-macro-btn', $baseRoute . '/_saveGitMacro', '{name:$("#macro-name").val(),commands:$("#commands").val()}', '#dd-git-macros', [
			'jqueryDone' => 'replaceWith',
			'hasLoader' => false,
			'jsCondition' => '$("#macro-name").val()'
		]);
		$this->jquery->click('#cancel-btn', '$("#frm").html("");');
		$this->jquery->renderView($this->_getFiles()
			->getViewGitCmdFrm(), [
			'commands' => 'git status',
			'inverted' => $this->style
		]);
	}

	public function _gitCmdExec() {
		header('Content-type: text/html; charset=utf-8');
		header('Cache-Control: no-cache');
		$this->addCloseToMessage();

		$gitRepo = $this->_getRepo(false);
		$dir = $gitRepo->getBaseFolder();
		chdir($dir);

		if (\ob_get_length())
			\ob_end_clean();
		ob_end_flush();

		$cmd = URequest::post('commands');
		$commands = preg_split("/\r\n|\n|\r/", $cmd);
		foreach ($commands as $cmd) {
			echo "<span class='ui teal text'>$cmd</span>\n";
			flush();
			ob_flush();
			$this->liveExecuteCommand($cmd);
		}

		$this->jquery->get($this->_getFiles()
			->getAdminBaseRoute() . '/_gitTabsRefresh', '#git-main', [
			'hasLoader' => false
		]);

		echo $this->jquery->compile($this->view);
	}

	private function getGitMacrosDropdown($selected = '') {
		$macros = array_flip($this->config['git-macros'] ?? []);
		$dd = $this->jquery->semantic()->htmlDropdown('dd-git-macros', $selected, $macros);
		$dd->addClass($this->style);
		$dd->asSearch('add-macro');
		$dd->setFluid();
		$this->jquery->change('#input-dd-git-macros', "var start = $('#commands').prop('selectionStart');
														var end = $('#commands').prop('selectionEnd');
    													var v = $('#commands').val();
    													var textBefore = v.substring(0,  start);
    													var textAfter  = v.substring(end, v.length);
														var newVal=(textBefore + '\\n'+  decodeURIComponent($(this).val()).replace(/\+/g, ' ') + '\\n' + textAfter).replace(/(^[ \t]*\\n)/gm, '');
														$('#commands').val(newVal);
														$('#commands')[0].setSelectionRange(newVal.indexOf('<'), newVal.indexOf('>')+1);
														$('#commands').focus();
    													");
		return $dd;
	}

	public function _saveGitMacro() {
		$name = URequest::post('name');
		$commands = URequest::post('commands');
		$this->config['git-macros'][$name] = urlencode($commands);
		$this->_saveConfig();
		$this->loadViewCompo($this->getGitMacrosDropdown($name));
	}
}
