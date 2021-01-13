<?php
namespace Ubiquity\controllers\admin\traits;

use Ajax\semantic\components\validation\Rule;
use Ajax\semantic\html\base\HtmlSemDoubleElement;
use Ajax\semantic\html\collections\HtmlMessage;
use Ajax\semantic\html\elements\HtmlButton;
use Ajax\semantic\html\elements\HtmlInput;
use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\Startup;
use Ubiquity\controllers\seo\SeoController;
use Ubiquity\seo\ControllerSeo;
use Ubiquity\seo\UrlParser;
use Ubiquity\utils\base\UFileSystem;
use Ubiquity\utils\base\UString;
use Ubiquity\utils\http\URequest;
use Ubiquity\utils\http\UResponse;
use Ubiquity\utils\http\USession;
use Ubiquity\controllers\admin\traits\acls\AclUses;

/**
 *
 * @author jc
 * @property \Ajax\JsUtils $jquery
 * @property \Ubiquity\views\View $view
 * @property \Ubiquity\scaffolding\AdminScaffoldController $scaffold
 */
trait SeoTrait {

	abstract public function _getAdminData();

	abstract public function _getAdminViewer();

	abstract public function _getFiles();

	abstract public function loadView($viewName, $pData = NULL, $asString = false);

	abstract public function seo();

	abstract protected function showConfMessage($content, $type, $title, $icon, $url, $responseElement, $data, $attributes = NULL): HtmlMessage;

	abstract public function showSimpleMessage($content, $type, $title = null, $icon = "info", $timeout = NULL, $staticName = null, $closeAction = null, $toast = false): HtmlMessage;

	abstract protected function _createController($controllerName, $variables = [], $ctrlTemplate = 'controller.tpl', $hasView = false, $jsCallback = "");

	protected function _seo() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$ctrls = ControllerSeo::init();
		$dtCtrl = $this->jquery->semantic()->dataTable("seoCtrls", "Ubiquity\\seo\\ControllerSeo", $ctrls);
		$dtCtrl->setFields([
			'name',
			'urlsFile',
			'siteMapTemplate',
			'route',
			'inRobots',
			'see'
		]);
		$dtCtrl->setIdentifierFunction('getName');
		$dtCtrl->setCaptions([
			'Controller name',
			'Urls file',
			'SiteMap template',
			'Route',
			'In robots?',
			''
		]);
		$dtCtrl->fieldAsLabel('route', 'car', [
			'jsCallback' => function ($lbl, $instance, $i, $index) {
				if ($instance->getRoute() == "") {
					$lbl->setProperty('style', 'display:none;');
				}
			}
		]);
		$dtCtrl->fieldAsCheckbox('inRobots', [
			'type' => 'toggle',
			'disabled' => true
		]);
		$dtCtrl->setValueFunction('see', function ($value, $instance, $fi, $index) {
			if ($instance->urlExists()) {
				$bt = new HtmlButton('see-' . $index, '', '_see circular basic right floated '.$this->style);
				$bt->setProperty("data-ajax", $instance->getName());
				$bt->asIcon('eye');
				return $bt;
			}
		});
		$dtCtrl->setValueFunction('urlsFile', function ($value, $instance, $fi, $index) {
			if (! $instance->urlExists()) {
				$elm = new HtmlSemDoubleElement('urls-' . $index, 'span', '', $value);
				$elm->addIcon("warning circle red");
				$elm->addPopup("Missing", $value . ' is missing!');
				return $elm;
			}
			return $value;
		});
		$dtCtrl->addDeleteButton(false, [], function ($bt) {
			$bt->setProperty('class', 'ui circular basic red right floated icon button _delete '.$this->style);
		});
		$dtCtrl->setTargetSelector([
			"delete" => "#messages"
		]);
		$dtCtrl->setUrls([
			"delete" => $baseRoute . "/_deleteSeoController"
		]);
		$dtCtrl->getOnRow('click', $baseRoute . '/_displaySiteMap', '#seo-details', [
			'attr' => 'data-ajax',
			'hasLoader' => false
		]);
		$dtCtrl->setHasCheckboxes(true);
		$dtCtrl->setSubmitParams($baseRoute . '/_generateRobots', "#messages", [
			'attr' => '',
			'ajaxTransition' => 'random'
		]);
		$dtCtrl->addErrorMessage();
		$dtCtrl->addExtraFieldRule('selection[]', 'minCount', 'You must select at least one SEO controller!', 1);

		$dtCtrl->setActiveRowSelector('olive');
		$this->jquery->getOnClick("._see", $baseRoute . "/_seeSeoUrl", "#messages", [
			"attr" => "data-ajax"
		]);
		$dtCtrl->setEmptyMessage($this->showSimpleMessage("<p>No SEO controller available!</p><a class='ui teal button addNewSeo'><i class='ui sitemap icon'></i>Add a new one...</a>", "teal", "SEO Controllers", "info circle"));
		$this->_setStyle($dtCtrl);
		return $dtCtrl;
	}

	public function _displaySiteMap(...$params) {
		$controllerClass = \implode("\\", $params);
		if (\class_exists($controllerClass)) {
			$controllerSeo = new $controllerClass();
			USession::set("seo-sitemap", $controllerSeo);
			$array = $controllerSeo->_getArrayUrls();
			$parser = new UrlParser();
			$parser->parseArray($array, true);
			$parser->parse();
			$urls = $parser->getUrls();
			$dt = $this->jquery->semantic()->dataTable('dtSiteMap', 'Ubiquity\seo\Url', $urls);
			$dt->setFields([
				'location',
				'lastModified',
				'changeFrequency',
				'priority'
			]);
			$dt->setCaptions([
				'Location',
				'Last Modified',
				'Change Frequency',
				'Priority'
			]);
			$dt->fieldAsInput('location');
			$dt->setValueFunction('lastModified', function ($v, $o, $fi, $i) {
				$d = date('Y-m-d\TH:i', $v);
				$input = new HtmlInput("date-" . $i, 'datetime-local', $d);
				$input->setName('lastModified[]');
				return $input;
			});
			$freq = UrlParser::$frequencies;
			$dt->fieldAsDropDown('changeFrequency', \array_combine($freq, $freq));
			$dt->setValueFunction('priority', function ($v, $o, $fi, $i) {
				$input = new HtmlInput('priority-' . $i, 'number', $v);
				$f = $input->getDataField();
				$f->setProperty('name', 'priority[]');
				$f->setProperty('max', '1')
					->setProperty('min', '0')
					->setProperty('step', '0.1');
				return $input;
			});
			$dt->onNewRow(function ($row, $instance) {
				if ($instance->getExisting()) {
					$row->addClass('positive');
				} else {
					$row->setProperty('style', 'display: none;')
						->addClass('toToggle');
				}
			});
			$dt->setHasCheckboxes(true);
			$dt->setCheckedCallback(function ($object) {
				return $object->getExisting();
			});
			$dt->asForm();
			$dt->setSubmitParams($this->_getFiles()
				->getAdminBaseRoute() . '/_saveUrls', '#seo-details', [
				'attr' => ''
			]);
			$this->_setStyle($dt);
			$this->jquery->execOn('click', '#saveUrls', '$("#frm-dtSiteMap").form("submit");');
			$this->jquery->exec('$("#displayAllRoutes").checkbox();', true);
			$this->jquery->execOn('change', 'input[name="selection[]"]', '$(this).parents("tr").toggleClass("_checked",$(this).prop("checked"));');
			$this->jquery->click('#displayAllRoutes', '$(".toToggle:not(._checked)").toggle();');
			$this->jquery->execAtLast($this->jquery->execOn('change', '#frm-dtSiteMap input', '$("#saveUrls").show();', [
				'immediatly' => false
			]));
			$this->jquery->renderView($this->_getFiles()->getViewSeoDetails(), [
				'controllerClass' => $controllerClass,
				'urlsFile' => $controllerSeo->_getUrlsFilename(),
				'inverted'=>$this->style
			]);
		} else {
			if ($controllerClass == null) {
				$msg = $this->showSimpleMessage('No controller selected!', 'info', 'SEO controller', 'info circle');
			} else {
				$msg = $this->showSimpleMessage("The controller <b>`{$controllerClass}`</b> does not exists!", "warning", "SEO controller", "warning circle");
			}
			$this->loadViewCompo($msg);
		}
	}

	public function _generateRobots() {
		$templateDir = $this->scaffold->getTemplateDir();
		$config = Startup::getConfig();
		$siteUrl = $config["siteUrl"];
		$content = [];
		if (URequest::isPost()) {
			$template = UFileSystem::load($templateDir . "/robots.tpl");
			$seoCtrls = URequest::post('selection', []);
			foreach ($seoCtrls as $ctrl) {
				if (\class_exists($ctrl)) {
					$controllerSeo = new ControllerSeo($ctrl);
					$content[] = \str_replace("%url%", URequest::cleanUrl($siteUrl . $controllerSeo->getPath()), $template);
				}
			}
			if (\sizeof($content) > 0) {
				$appDir = Startup::getApplicationDir();
				$content = \implode("\n", $content);
				UFileSystem::save($appDir . \DS . 'robots.txt', $content);
				$msg = $this->showSimpleMessage("The file <b>robots.txt</b> has been generated in " . $appDir, "success", "Robots generation", "info circle");
				$this->jquery->get($this->_getFiles()
					->getAdminBaseRoute() . "/_seoRefresh", "#seoCtrls", [
					'hasLoader' => false,
					'jqueryDone' => 'replaceWith'
				]);
			} else {
				$msg = $this->showSimpleMessage("Can not generate <b>robots.txt</b> if no SEO controller is selected.", "warning", "Robots.txt generation", "warning circle");
			}
			$this->loadViewCompo($msg);
		}
	}

	public function _seoRefresh() {
		$this->loadViewCompo($this->_seo());
	}

	public function _newSeoController() {
		$modal = $this->jquery->semantic()->htmlModal("modalNewSeo", "Creating a new Seo controller");
		$modal->setInverted();
		$frm = $this->jquery->semantic()->htmlForm("frmNewSeo");
		$fc = $frm->addField('controllerName')->addRules([
			'empty',
			[
				"checkController",
				"Controller {value} already exists!"
			]
		]);
		$fc->labeled(Startup::getNS());
		$fields = $frm->addFields([
			"urlsFile",
			"sitemapTemplate"
		], "Urls file & sitemap twig template");
		$fields->setFieldsPropertyValues("value", [
			"urls",
			"@framework/Seo/sitemap.xml.html"
		]);

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
			->getAdminBaseRoute() . "/_createSeoController", "#messages", [
			"hasLoader" => false
		]);
		$modal->setContent($frm);
		$modal->addAction("Validate");
		$this->jquery->click("#action-modalNewSeo-0", "$('#frmNewSeo').form('submit');", false, false);
		$modal->addAction("Close");
		$this->jquery->change('#controllerName', 'if($("#ck-add-route").is(":checked")){$("#path").val($(this).val());}');
		$this->jquery->exec("$('.dimmer.modals.page').html('');$('#modalNewSeo').modal('show');", true);
		$this->jquery->jsonOn("change", "#ck-add-route", $this->_getFiles()
			->getAdminBaseRoute() . "/_addRouteWithNewAction", "post", [
			"context" => "$('#frmNewSeo')",
			"params" => "$('#frmNewSeo').serialize()",
			"jsCondition" => "$('#ck-add-route').is(':checked')"
		]);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkRoute", $this->_getFiles()
			->getAdminBaseRoute() . "/_checkRoute", "{}", "result=data.result;", "postForm", [
			"form" => "frmNewSeo"
		]), true);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkController", $this->_getFiles()
			->getAdminBaseRoute() . "/_checkController", "{}", "result=data.result;", "postForm", [
			"form" => "frmNewSeo"
		]), true);
		$this->jquery->change("#ck-add-route", '$("#div-new-route").toggle($(this).is(":checked"));if($(this).is(":checked")){$("#path").val($("#controllerName").val());}');
		$this->loadViewCompo($modal);
	}

	public function _createSeoController($force = null) {
		if (URequest::isPost()) {
			$variables = [];
			$path = URequest::post("path");
			$variables["%path%"] = $path;
			if (isset($path)) {
				$uses=new AclUses();
				$variables["%routePath%"]=$path;
				$variables["%route%"] = CacheManager::getAnnotationsEngineInstance()->getAnnotation($uses, 'route', ['path' => $path])->asAnnotation();
				$variables['%uses%']=$uses->getUsesStr();
			}
			$variables["%urlsFile%"] = URequest::post("urlsFile", "urls");
			$variables["%sitemapTemplate%"] = URequest::post("sitemapTemplate", "@framework/Seo/sitemap.xml.html");

			echo $this->_createController($_POST["controllerName"], $variables, 'seoController.tpl', false, $this->jquery->getDeferred($this->_getFiles()
				->getAdminBaseRoute() . "/_seoRefresh", "#frm-seoCtrls", [
				'hasLoader' => false,
				'jqueryDone' => 'replaceWith',
				'jsCallback' => '$("#seo-details").html("");'
			]));
		}
		$this->jquery->get($this->_getFiles()
			->getAdminBaseRoute() . "/_seoRefresh", "#frm-seoCtrls", [
			'hasLoader' => false,
			'jqueryDone' => 'replaceWith',
			'jsCallback' => '$("#seo-details").html("");'
		]);
		echo $this->jquery->compile($this->view);
	}

	public function _checkController() {
		if (URequest::isPost()) {
			$result = [];
			$controllers = CacheManager::getControllers();
			$ctrlNS = Startup::getNS();
			header('Content-type: application/json');
			$controller = $ctrlNS . $_POST["controllerName"];
			$result["result"] = (\array_search($controller, $controllers) === false);
			echo json_encode($result);
		}
	}

	public function _saveUrls() {
		$result = [];
		$selections = URequest::post("selection", []);
		$locations = URequest::post("location", []);
		$lastModified = URequest::post("lastModified", []);
		$changeFrequency = URequest::post("changeFrequency", []);
		$priority = URequest::post("priority", []);
		foreach ($selections as $index) {
			$result[] = [
				"location" => $locations[$index - 1],
				"lastModified" => \strtotime($lastModified[$index - 1]),
				"changeFrequency" => $changeFrequency[$index - 1],
				"priority" => $priority[$index - 1]
			];
		}
		$seoController = USession::get("seo-sitemap");
		if (isset($seoController) && $seoController instanceof SeoController) {
			try {
				$seoController->_save($result);
				$r = new \ReflectionClass($seoController);
				$this->_displaySiteMap($r->getNamespaceName(), $r->getShortName());
				$filename = $seoController->_getUrlsFilename();
				$message = $this->showSimpleMessage(UString::pluralize(\sizeof($selections), '<b>`' . $filename . '`</b> saved with no url.', '<b>`' . $filename . '`</b> saved with {count} url.', '<b>`' . $filename . '`</b> saved with {count} urls.'), "success", 'URLs saving', "info circle");
			} catch (\Ubiquity\exceptions\CacheException $e) {
				$message = $this->showSimpleMessage("Unable to write urls file `" . $filename . "`", "warning", 'URLs saving', "warning");
			}
			$this->jquery->html("#messages", $message, true);
			echo $this->jquery->compile($this->view);
		}
	}

	public function _deleteSeoController(...$params) {
		$controllerName = \implode("\\", $params);
		if (sizeof($_POST) > 0) {
			$controllerName = \urldecode($_POST["data"]);
			if ($this->_deleteController($controllerName)) {
				$message = $this->showSimpleMessage("Deletion of SEO controller `<b>" . $controllerName . "</b>`", "success", "SEO controller deletion", "remove", 4000);
				$this->jquery->get($this->_getFiles()
					->getAdminBaseRoute() . "/_seoRefresh", "#frm-seoCtrls", [
					'hasLoader' => false,
					'jqueryDone' => 'replaceWith',
					'jsCallback' => '$("#seo-details").html("");'
				]);
			} else {
				$message = $this->showSimpleMessage("Can not delete SEO controller `" . $controllerName . "`", "warning", "warning");
			}
		} else {
			$message = $this->showConfMessage("Do you confirm the deletion of SEO controller `<b>" . $controllerName . "</b>`?", "error", "SEO controller deletion", "remove circle", $this->_getFiles()
				->getAdminBaseRoute() . "/_deleteSeoController/{$params[0]}/{$params[1]}", "#messages", \urlencode($controllerName));
		}
		$this->loadViewCompo($message);
	}

	protected function _deleteController($controllerName) {
		$controllerName = \urldecode($controllerName);
		if (\class_exists($controllerName)) {
			$rClass = new \ReflectionClass($controllerName);
			return UFileSystem::deleteFile($rClass->getFileName());
		}
		return false;
	}

	public function _seeSeoUrl(...$params) {
		$controllerName = \implode("\\", $params);
		$ctrl = new $controllerName();
		\ob_start();
		$ctrl->index();
		$content = \ob_get_clean();
		UResponse::asHtml();
		$modal = $this->jquery->semantic()->htmlModal("seeSeo", "sitemap file for {$ctrl->getPath()} url");
		$modal->setInverted();
		$modal->setContent("<pre><code>" . \htmlentities($content) . "</pre></code>");
		$modal->addAction("Close");
		$this->jquery->exec("$('.dimmer.modals.page').html('');$('#seeSeo').modal('show');", true);
		$this->loadViewCompo($modal);
	}
}
