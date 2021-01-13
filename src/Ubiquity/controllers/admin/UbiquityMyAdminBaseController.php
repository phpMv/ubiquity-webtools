<?php
namespace Ubiquity\controllers\admin;

use Ajax\common\html\BaseWidget;
use Ajax\php\ubiquity\JsUtils;
use Ajax\semantic\components\validation\Rule;
use Ajax\semantic\html\base\constants\Direction;
use Ajax\semantic\html\collections\HtmlMessage;
use Ajax\semantic\html\collections\form\HtmlFormFields;
use Ajax\semantic\html\collections\form\HtmlFormInput;
use Ajax\semantic\html\collections\menus\HtmlMenu;
use Ajax\semantic\html\elements\HtmlButton;
use Ajax\semantic\html\elements\HtmlButtonGroups;
use Ajax\semantic\html\elements\HtmlHeader;
use Ajax\semantic\html\elements\HtmlInput;
use Ajax\semantic\html\elements\HtmlList;
use Ajax\semantic\html\modules\HtmlDropdown;
use Ajax\semantic\html\modules\checkbox\HtmlCheckbox;
use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\Controller;
use Ubiquity\controllers\Router;
use Ubiquity\controllers\Startup;
use Ubiquity\controllers\admin\popo\ControllerAction;
use Ubiquity\controllers\admin\popo\MailerClass;
use Ubiquity\controllers\admin\popo\MailerQueuedClass;
use Ubiquity\controllers\admin\popo\MaintenanceMode;
use Ubiquity\controllers\admin\popo\Route;
use Ubiquity\controllers\admin\traits\CacheTrait;
use Ubiquity\controllers\admin\traits\ComposerTrait;
use Ubiquity\controllers\admin\traits\ConfigPartTrait;
use Ubiquity\controllers\admin\traits\ConfigTrait;
use Ubiquity\controllers\admin\traits\ControllersTrait;
use Ubiquity\controllers\admin\traits\CreateControllersTrait;
use Ubiquity\controllers\admin\traits\DatabaseTrait;
use Ubiquity\controllers\admin\traits\GitTrait;
use Ubiquity\controllers\admin\traits\LogsTrait;
use Ubiquity\controllers\admin\traits\MailerTrait;
use Ubiquity\controllers\admin\traits\MaintenanceTrait;
use Ubiquity\controllers\admin\traits\ModelsConfigTrait;
use Ubiquity\controllers\admin\traits\ModelsTrait;
use Ubiquity\controllers\admin\traits\OAuthTrait;
use Ubiquity\controllers\admin\traits\RestTrait;
use Ubiquity\controllers\admin\traits\RoutesTrait;
use Ubiquity\controllers\admin\traits\SeoTrait;
use Ubiquity\controllers\admin\traits\ThemesTrait;
use Ubiquity\controllers\admin\traits\TranslateTrait;
use Ubiquity\controllers\crud\CRUDDatas;
use Ubiquity\controllers\crud\interfaces\HasModelViewerInterface;
use Ubiquity\controllers\crud\viewers\ModelViewer;
use Ubiquity\controllers\semantic\InsertJqueryTrait;
use Ubiquity\controllers\semantic\MessagesTrait;
use Ubiquity\log\LoggerParams;
use Ubiquity\orm\DAO;
use Ubiquity\orm\OrmUtils;
use Ubiquity\scaffolding\AdminScaffoldController;
use Ubiquity\themes\ThemesManager;
use Ubiquity\translation\TranslatorManager;
use Ubiquity\utils\UbiquityUtils;
use Ubiquity\utils\base\UArray;
use Ubiquity\utils\base\UFileSystem;
use Ubiquity\utils\base\UString;
use Ubiquity\utils\http\URequest;
use Ubiquity\utils\http\UResponse;
use Ubiquity\utils\yuml\ClassToYuml;
use Ubiquity\utils\yuml\ClassesToYuml;
use Ubiquity\client\oauth\OAuthAdmin;
use Ajax\semantic\html\elements\HtmlLabel;
use Ubiquity\controllers\admin\traits\SecurityTrait;
use Ubiquity\controllers\admin\traits\CommandsTrait;
use Ubiquity\controllers\admin\popo\CategoryCommands;
use Ubiquity\security\acl\AclManager;
use Ubiquity\controllers\admin\traits\AclsTrait;
use Ubiquity\controllers\admin\traits\acls\DisplayAcls;
use Ubiquity\security\acl\persistence\AclDAOProvider;
use Ajax\common\html\HtmlDoubleElement;
use Ajax\semantic\html\base\HtmlSemDoubleElement;
use Ajax\semantic\widgets\datatable\DataTable;

/**
 *
 * @author jcheron <myaddressmail@gmail.com>
 */
class UbiquityMyAdminBaseController extends Controller implements HasModelViewerInterface {
	use MessagesTrait,ModelsTrait,ModelsConfigTrait,RestTrait,CacheTrait,ConfigTrait,
	ControllersTrait,RoutesTrait,DatabaseTrait,SeoTrait,GitTrait,CreateControllersTrait,
	LogsTrait,InsertJqueryTrait,ThemesTrait,TranslateTrait,MaintenanceTrait,MailerTrait,
	ComposerTrait,OAuthTrait,ConfigPartTrait,SecurityTrait,CommandsTrait,DisplayAcls,AclsTrait;

	/**
	 *
	 * @var CRUDDatas
	 */
	private $adminData;

	/**
	 *
	 * @var UbiquityMyAdminViewer
	 */
	private $adminViewer;

	/**
	 *
	 * @var UbiquityMyAdminFiles
	 */
	private $adminFiles;

	/**
	 *
	 * @var ModelViewer
	 */
	private $adminModelViewer;

	/**
	 *
	 * @var AdminScaffoldController
	 */
	private $scaffold;

	private $globalMessage;

	protected $config;

	protected $devtoolsPath;
	
	protected static $configFile = ROOT . DS . 'config' . DS . 'adminConfig.php';

	protected $styles=['inverted'=>['bgColor'=>'#0c0d0d','aceBgColor'=>'#fff','tdDefinition'=>'#fff','selectedRow'=>'black'],''=>['bgColor'=>'#fff','aceBgColor'=>'#002B36','selectedRow'=>'positive']];
	
	public const version = '2.4.1+';
	
	public $style;
	
	public function _setStyle($elm){
		if($this->style==='inverted'){
			$elm->setInverted(true);
			if($elm instanceof DataTable){
				$elm->setActiveRowSelector('black');
			}
		}
	}
	
	public function _getStyle($part){
		return $this->styles[$this->style][$part]??'';
	}
	
	public static function _getConfigFile() {
		$defaultConfig = [
			'devtools-path' => 'Ubiquity',
			'info' => [],
			'display-cache-types' => [
				'controllers',
				'models'
			],
			'first-use' => true,
			'maintenance' => [
				'on' => false,
				'modes' => [
					'maintenance' => [
						'excluded' => [
							'urls' => [
								'admin',
								'Admin'
							],
							'ports' => [
								8080,
								8090
							],
							'hosts' => [
								'127.0.0.1'
							]
						],
						'controller' => "\\controllers\\MaintenanceController",
						'action' => 'index',
						'title' => 'Maintenance mode',
						'icon' => 'recycle',
						'message' => 'Our application is currently undergoing sheduled maintenance.<br>Thank you for your understanding.'
					]
				]
			]
		];
		if (\class_exists('\\Cz\\Git\\GitRepository')) {
			$defaultConfig['git-macros'] = [
				"Status" => "git status",
				"commit & push" => "git+add+.%0Agit+commit+-m+%22%3Cyour+message%3E%22%0Agit+push%0A",
				"checkout" => "git+checkout+%3Cbranch-name%3E",
				"remove file from remote repository" => "git+rm+--cached+%3Cfilename%3E%0Agit+commit+-m+%22Removed+file+from+repository%22%0Agit+push",
				"remove folder from remote repository" => "git+rm+--cached+-r+%3Cdir_name%3E%0Agit+commit+-m+%22Removed+folder+from+repository%22%0Agit+push",
				"undo last commit (soft)" => "git+reset+--soft+HEAD%5E",
				"undo last commit (hard)" => "git+reset+--hard+HEAD%5E",
				"unstage file(s) from index" => "git+rm+--cached+%3Cfile-name%3E",
				"stash & pull (overwrite local changes with pull)" => "git+stash%0Agit+pull%0A"
			];
		}
		if (\file_exists(self::$configFile)) {
			unset($defaultConfig['first-use']);
			$config = include (self::$configFile);
			return \array_replace($defaultConfig, $config);
		}
		return $defaultConfig;
	}

	public function __construct() {
		parent::__construct();
		$this->addAdminViewPath();
		DAO::$transformerOp = 'toView';
		$this->_insertJquerySemantic();
		$this->config = self::_getConfigFile();
		$this->devtoolsPath = $this->config['devtools-path'] ?? 'Ubiquity';
	}

	public function initialize() {
		ob_start(array(
			__class__,
			'_error_handler'
		));
		$this->style=$this->config['style']??'';
		if($this->style!=='inverted'){
			$loader="<div class=\"ui {$this->style} loader\"></div>";
		}else{
			$loader="<div class=\"ui active dimmer\"><div class=\"ui loader\"></div></div>";
		}
		$this->jquery->setAjaxLoader ( $loader);
		
		if (URequest::isAjax() === false || ($_GET["_refresh"] ?? false)) {
			$semantic = $this->jquery->semantic();
			$mainMenuElements = $this->_getAdminViewer()->getMainMenuElements();
			$mainMenuElements = $this->getMenuElements($mainMenuElements);
			$elements = [
				"Webtools"
			];
			$dataAjax = [
				"index"
			];
			$siteUrl = \rtrim(Startup::$config['siteUrl'], '/') . '/';
			$baseRoute = \trim($this->_getFiles()->getAdminBaseRoute(), '/');
			$hrefs = [
				$siteUrl . $baseRoute . "/index"
			];
			foreach ($mainMenuElements as $elm => $values) {
				$elements[] = $elm;
				$dataAjax[] = $values[0];
				$hrefs[] = $siteUrl . $baseRoute . "/" . $values[0];
			}
			$mn = $semantic->htmlMenu("mainMenu", $elements);
			$mn->getItem(0)
				->addClass("header")
				->addIcon("home big link");
			$mn->setPropertyValues("data-ajax", $dataAjax);
			$mn->setPropertyValues("href", $hrefs);
			$mn->setActiveItem(0);
			$mn->setSecondary();
			$mn->addClass($this->style);
			$mn->getOnClick($baseRoute, "#main-content", [
				"attr" => "data-ajax",
				"historize" => true
			]);
			$this->jquery->activateLink("#mainMenu");

			$this->jquery->renderView($this->_getFiles()
				->getViewHeader(),$this->styles[$this->style]);
		}
		$this->scaffold = new AdminScaffoldController($this, $this->jquery);
		DAO::start();
		$config = Startup::$config;
		CacheManager::start($config);
	}

	public static function _error_handler($buffer) {
		$e = error_get_last();
		if ($e) {
			if ($e['file'] != 'xdebug://debug-eval' && ! UResponse::isJSON()) {
				$staticName = "msg-" . rand(0, 50);
				$message = new HtmlMessage($staticName);
				$message->addClass("error");
				$message->addHeader("Error");
				$message->addList([
					"<b>Message :</b> " . $e['message'],
					"<b>File :</b> " . $e['file'],
					"<b>Line :</b> " . $e['line']
				]);
				$message->setIcon("bug");
				switch ($e['type']) {
					case E_ERROR:
					case E_PARSE:
						return $message;
					default:
						return str_replace($e['message'], "", $buffer) . $message;
				}
			} else {
				return str_replace($e['message'], "", $buffer);
			}
		}
		return $buffer;
	}

	public function finalize() {
		if (! URequest::isAjax()) {
			$this->loadView("@admin/main/vFooter.html", [
				"js" => $this->initializeJs()
			]);
		}
	}

	protected function addAdminViewPath() {
		Startup::$templateEngine->addPath(implode(\DS, [
			\dirname(__FILE__),
			"views"
		]) . \DS, "admin");
	}

	protected function reloadConfig() {
		$config = Startup::reloadConfig();
		$this->addAdminViewPath();
		return $config;
	}

	protected function _checkModelsUpdates(&$config, $onMainPage) {
		$models = CacheManager::modelsCacheUpdated($config);
		if (\is_array($models) && \count($models) > 0) {
			$this->_smallUpdateMessageCache($onMainPage, 'models', 'sticky note inverted', 'Updated models files (<b>' . count($models) . '</b>)&nbsp;', 'small inverted compact', $onMainPage ? '_initCache/models' : '_initCache/models/models', $onMainPage ? '#models-refresh' : '#main-content');
		}
	}

	protected function _checkRouterUpdates(&$config, $onMainPage) {
		$caches = CacheManager::controllerCacheUpdated($config);
		if (is_array($caches) && sizeof($caches) > 0) {
			if (! $this->hasMaintenance()) {
				$this->_smallUpdateMessageCache($onMainPage, 'router', 'car', 'Updated controller files ', 'small compact', $onMainPage ? '_initCache/controllers' : '_initCacheRouter', $onMainPage ? '#router-refresh' : '#divRoutes');
			}
		}
	}

	protected function _smallUpdateMessageCache($onMainPage, $type, $icon, $message, $messageType, $url, $target) {
		$js = [];
		if (! $onMainPage) {
			$js = [
				'jsCallback' => '$("#' . $type . '-refresh").html("");'
			];
		}
		$bt = $this->jquery->semantic()->htmlButton("bt-mini-init-{$type}-cache", null, 'orange small');
		$bt->setProperty('title', "Re-init {$type} cache");
		$bt->asIcon('refresh');
		echo "<div class='ui container' id='{$type}-refresh' style='display:inline;'>";
		echo $this->showSimpleMessage('<i class="ui icon ' . $icon . '"></i>&nbsp;' . $message . $bt, $messageType, null, null, '');
		echo "&nbsp;</div>";
		$this->jquery->getOnClick("#bt-mini-init-{$type}-cache", $this->_getFiles()
			->getAdminBaseRoute() . "/" . $url, $target, [
			"dataType" => "html",
			"attr" => "",
			"hasLoader" => "internal"
		] + $js);
	}

	protected function initializeJs() {
		$js = 'var setAceEditor=function(elementId,readOnly,mode,maxLines){
			mode=mode || "sql";readOnly=readOnly || false;maxLines=maxLines || 100;
			var editor = ace.edit(elementId);
			editor.setTheme("ace/theme/solarized_dark");
			editor.getSession().setMode({path:"ace/mode/"+mode, inline:true});
			editor.setOptions({
				maxLines: maxLines,
				minLines: 2,
				showInvisibles: true,
				showGutter: !readOnly,
				showPrintMargin: false,
				readOnly: readOnly,
				showLineNumbers: !readOnly,
				highlightActiveLine: !readOnly,
				highlightGutterLine: !readOnly
				});
			var input = $("#"+elementId +" + input");
			if(input.length){
				input.val(editor.getSession().getValue());
				console.log(input.val());
				editor.getSession().on("change", function () {
				input.val(editor.getSession().getValue());
				});
			}
		};';
		return $this->jquery->inline($js);
	}

	public function index() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$array = $this->_getAdminViewer()->getMainMenuElements();
		$this->_getAdminViewer()->getMainIndexItems("part1", $this->getMenuElements($array, 'part1'));
		$this->_getAdminViewer()->getMainIndexItems("part2", $this->getMenuElements($array, 'part2'));
		$config = Startup::getConfig();
		$this->_checkModelsUpdates($config, true);
		$this->_checkRouterUpdates($config, true);
		if ($this->hasMaintenance()) {
			$this->_smallMaintenanceActive(true, MaintenanceMode::getActiveMaintenance($this->config["maintenance"]));
		}
		$this->jquery->getOnClick("#bt-customize", $baseRoute . "/_indexCustomizing", "#dialog", [
			'hasLoader' => 'internal',
			'jsCallback' => '$("#admin-elements").hide();$("#bt-customize").addClass("active");'
		]);
		$this->jquery->mouseenter("#admin-elements .item", '$(this).children("i").addClass("green basic").removeClass("circular '.$this->style.'");$(this).find(".description").css("color","#21ba45");$(this).transition("pulse","400ms");');
		$this->jquery->mouseleave("#admin-elements .item", '$(this).children("i").removeClass("green basic").addClass("circular '.$this->style.'");$(this).find(".description").css("color","");');
		if ($this->config['first-use'] ?? false) {
			echo $this->showSimpleMessage('This is your first use of devtools. You can select the tools you want to display.', 'info', 'Tools displaying', 'info circle', null, 'msgGlobal');
			$this->jquery->trigger('#bt-customize', 'click', true);
		}
		$this->jquery->compile($this->view);
		$this->loadView($this->_getFiles()
			->getViewIndex());
	}

	public function _closeMessage($type) {
		$this->config['info'][] = $type;
		$this->_saveConfig();
	}

	private function getMenuElements($array, $part = null) {
		if (isset($part)) {
			if ($part == 'part1') {
				if (isset($this->config[$part])) {
					return UArray::extractKeys($array, $this->config[$part]);
				} else {
					return \array_slice($array, 0, 6);
				}
			} elseif ($part == 'part2') {
				if (isset($this->config[$part])) {
					return UArray::extractKeys($array, $this->config[$part]);
				} else {
					return \array_slice($array, 6, 5);
				}
			}
		} else {
			if (isset($this->config['part1']) && isset($this->config['part2'])) {
				$keys = array_merge($this->config['part1'], $this->config['part2']);
				return UArray::extractKeys($array, $keys);
			} else {
				return $array;
			}
		}
	}

	public function _indexCustomizing() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$array = $this->_getAdminViewer()->getMainMenuElements();
		$keys = \array_keys($array);

		$selectedElements1 = \array_keys($this->getMenuElements($array, 'part1'));
		$selectedElements2 = \array_keys($this->getMenuElements($array, 'part2'));
		$elements1 = \array_diff($keys, $selectedElements2);
		$elements2 = \array_diff($keys, $selectedElements1);
		$selectedValue1 = \implode(",", $selectedElements1);
		$dd1 = $this->jquery->semantic()->htmlDropdown('part1', $selectedValue1, $this->_preserveArraySort($selectedElements1, $elements1));
		$dd1->asSearch('t-part1', true);
		$dd1->addClass($this->style);

		$selectedValue2 = implode(",", $selectedElements2);
		$dd2 = $this->jquery->semantic()->htmlDropdown('part2', $selectedValue2, $this->_preserveArraySort($selectedElements2, $elements2));
		$dd2->asSearch('t-part2', true);
		$dd2->addClass($this->style);

		$dd1->setOnAdd("$('#" . $dd2->getIdentifier() . " .item[data-value='+addedText+']').remove();");
		$dd1->setOnRemove($dd2->jsAddItem("removedText", "removedText"));
		$dd2->setOnAdd("$('#" . $dd1->getIdentifier() . " .item[data-value='+addedText+']').remove();");
		$dd2->setOnRemove($dd1->jsAddItem("removedText", "removedText"));
		$this->jquery->click('#cancel-btn', '$("#dialog").html("");$("#admin-elements").show();$("#bt-customize").removeClass("active");');
		$this->jquery->getOnClick("#reset-conf-btn", $baseRoute . "/_resetConfigParams", 'body', [
			'hasLoader' => 'internal'
		]);
		$this->jquery->postFormOnClick('#validate-btn', $baseRoute . '/_indexCustomizingSubmit', 'customize-frm', 'body', [
			'hasLoader' => 'internal'
		]);
		$this->jquery->exec('$("._ckTheme").checkbox();',true);
		$this->jquery->renderView($this->_getFiles()
			->getViewIndexCustomizing(),['inverted'=>$this->style]);
	}

	private function _preserveArraySort($model, $array) {
		$result = [];
		foreach ($model as $v) {
			$result[$v] = $v;
			$index = array_search($v, $array);
			if ($index !== false) {
				unset($array[$index]);
			}
		}
		foreach ($array as $v) {
			$result[$v] = $v;
		}
		return $result;
	}

	public function _indexCustomizingSubmit() {
		$part1Str = URequest::post('t-part1', []);
		$part2Str = URequest::post('t-part2', []);
		$this->config["part1"] = explode(',', $part1Str);
		$this->config["part2"] = explode(',', $part2Str);
		$ckTheme=URequest::filled('ck-theme');
		if($ckTheme){
			if($this->style==='inverted'){
				$this->config['style']='';
			}else{
				$this->config['style']='inverted';
			}
		}
		$this->_saveConfig();
		$_GET["_refresh"] = true;
		$_REQUEST["_userInfo"] = true;
		$this->forward(static::class, 'index', [], true, true);
	}

	public function _resetConfigParams() {
		$this->config = [];
		$this->_saveConfig();
		$_GET["_refresh"] = true;
		$this->forward(static::class, 'index', [], true, true);
	}

	protected function getActiveDb() {
		$dbs = DAO::getDatabases();
		$db = $this->config['activeDb'] ?? 'default';
		if (\in_array($db, $dbs)) {
			return $db;
		}
		return 'default';
	}

	public function models($hasHeader = true) {
		$header = "";
		$activeDb = $this->getActiveDb();

		if ($hasHeader === true) {
			$config = Startup::$config;
			$baseRoute = $this->_getFiles()->getAdminBaseRoute();
			$header = $this->getHeader("models");
			echo $header;
			$dbs = DAO::getDatabases();
			$semantic = $this->jquery->semantic();
			$menu = $semantic->htmlMenu('menu-db');
			$menu->addHeader("Databases");
			foreach ($dbs as $db) {
				$item = $menu->addItem($db);
				$item->setProperty('data-ajax', $db);
			}
			if (sizeof($dbs) > 1 || ($config['database']['dbName'] ?? '') != null) {
				$bt = new HtmlButton('btNewConnection', 'Add new connection...', 'teal '.$this->style);
				$menu->addItem($bt);
				$bt->getOnClick($this->_getBaseRoute() . '/_frmAddNewDbConnection/', '#temp-form', [
					'hasLoader' => 'internal'
				]);
			}
			$menu->setSecondary();
			$menu->addClass($this->style);
			$menu->setActiveItem(\array_search($activeDb, $dbs));
			$menu->getOnClick($baseRoute . '/_modelDatabase/true/true/', '#database-container', [
				'attr' => 'data-ajax'
			]);
			echo $menu;
		}

		$this->_modelDatabase($hasHeader, false, $activeDb);
	}

	public function controllers() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$this->getHeader("controllers");
		if (\array_search('controllers', $this->config['info']) === false) {
			$controllersNS = Startup::getNS('controllers');
			$controllersDir = \ROOT . \str_replace("\\", \DS, $controllersNS);
			$this->showSimpleMessage("Controllers directory is <b>" . UFileSystem::cleanPathname($controllersDir) . "</b>", "info", null, "info circle", null, "msgControllers", "controllers");
		}
		$frm = $this->jquery->semantic()->htmlForm("frmCtrl");
		$frm->setValidationParams([
			"on" => "blur",
			"inline" => true
		]);
		$fields = $frm->addFields();
		$fields->setInline();
		$input = $fields->addInput("name", null, "text", "", "Controller name")
			->addRules([
			[
				"empty",
				"Controller name must have a value"
			],
			"regExp[/^[A-Za-z]\w*$/]"
		])
			->setWidth(8);
		$input->labeledCheckbox(Direction::LEFT, "View", "v", "slider")->addClass($this->style);
		$input->addAction("Create controller", true, "plus", true)
			->addClass("teal ".$this->style)
			->asSubmit();
		$input->addClass($this->style);
		$frm->setSubmitParams($baseRoute . "/createController", "#main-content");
		$activeTheme = ThemesManager::getActiveTheme();

		$bt = $fields->addDropdown("crud-bt", [
			"_frmAddCrudController" => "CRUD controller",
			"_frmAddAuthController" => "Auth controller"
		], "Create special controller");
		$bt->getField()->addClass($this->style);
		$bt->asButton();
		
		$bt->addIcon("plus");
		if ($activeTheme == null) {
			$this->jquery->getOnClick("#dropdown-crud-bt [data-value]", $baseRoute, "#frm", [
				"attr" => "data-value"
			]);
		} else {
			$bt->setDisabled(true);
			$bt->addPopup("Scaffolding", "No scaffolding with an active theme!");
		}

		$bt = $fields->addButton("filter-bt", "Filter controllers");
		$bt->getOnClick($baseRoute . '/_frmFilterControllers', '#frm', [
			'attr' => '',
			'hasLoader'=>'internal'
		]);
		$bt->addClass($this->style);
		$bt->addIcon("filter");
		$this->_refreshControllers();
		$this->jquery->renderView($this->_getFiles()
			->getViewControllersIndex());
	}

	public function _refreshControllers($refresh = false) {
		$dt = $this->_getAdminViewer()->getControllersDataTable(ControllerAction::init());
		$this->jquery->postOnClick("._route[data-ajax]", $this->_getFiles()
			->getAdminBaseRoute() . "/routes", "{filter:$(this).attr('data-ajax')}", "#main-content");
		$this->jquery->postOnClick("._create-view", $this->_getFiles()
			->getAdminBaseRoute() . "/_createView", "{action:$(this).attr('data-action'),controller:$(this).attr('data-controller'),controllerFullname:$(this).attr('data-controllerFullname')}", '$(self).closest("._views-container")', [
			'hasLoader' => false
		]);
		$this->jquery->execAtLast("$('#bt-0-controllersAdmin._clickFirst').click();");
		$this->jquery->exec('$("._popup").popup();',true);
		$this->jquery->postOnClick("._add-new-action", $this->_getFiles()
			->getAdminBaseRoute() . "/_newActionFrm", "{controller:$(this).attr('data-controller')}", "#modal", [
			"hasLoader" => false
		]);
		$this->addNavigationTesting();
		if ($refresh === "refresh") {
			echo $dt;
			echo $this->jquery->compile($this->view);
		}
	}

	public function routes() {
		$this->getHeader("routes");
		if (\array_search('routes', $this->config['info']) === false) {
			$this->showSimpleMessage("Router cache entry is <b>" . CacheManager::$cache->getEntryKey("controllers\\routes.default") . "</b>", "info", null, "info circle", null, "msgRoutes", 'routes');
		}
		$routes = CacheManager::getRoutes();
		$this->_getAdminViewer()->getRoutesDataTable(Route::init($routes));
		$this->jquery->getOnClick("#bt-init-cache", $this->_getFiles()
			->getAdminBaseRoute() . "/_initCacheRouter", "#divRoutes", [
			"dataType" => "html",
			"attr" => "",
			"hasLoader" => "internal"
		]);
		$this->jquery->postOnClick("#bt-filter-routes", $this->_getFiles()
			->getAdminBaseRoute() . "/_filterRoutes", "{filter:$('#filter-routes').val()}", "#divRoutes", [
			"ajaxTransition" => "random"
		]);
		if (isset($_POST["filter"]))
			$this->jquery->exec("$(\"tr:contains('" . $_POST["filter"] . "')\").addClass('warning');", true);
		$this->addNavigationTesting();
		$config = Startup::getConfig();
		$this->_checkRouterUpdates($config, false);

		$this->jquery->renderView($this->_getFiles()
			->getViewRoutesIndex(), [
			"inverted"=>$this->style,
			"url" => Startup::getConfig()["siteUrl"]
		]);
	}

	protected function addNavigationTesting() {
		$this->jquery->postOnClick("._get", $this->_getFiles()
			->getAdminBaseRoute() . "/_runAction", "{method:'get',url:$(this).attr('data-url')}", "#modal", [
			"hasLoader" => false
		]);
		$this->jquery->postOnClick("._post", $this->_getFiles()
			->getAdminBaseRoute() . "/_runAction", "{method:'post',url:$(this).attr('data-url')}", "#modal", [
			"hasLoader" => false
		]);
		$this->jquery->postOnClick("._postWithParams", $this->_getFiles()
			->getAdminBaseRoute() . "/_runPostWithParams", "{url:$(this).attr('data-url')}", "#modal", [
			"attr" => "",
			"hasLoader" => false
		]);
	}

	public function cache() {
		$this->getHeader("cache");
		if (\array_search('cache', $this->config['info']) === false) {
			$this->showSimpleMessage(CacheManager::$cache->getCacheInfo(), "info", null, "info circle", null, "msgCache", 'cache');
		}
		$cacheFiles = $this->getCacheFiles($this->config['display-cache-types']);
		$form = $this->jquery->semantic()->htmlForm('frmCache');
		$form->addClass($this->style);
		$cacheTypes = [
			'controllers' => 'Controllers',
			'models' => 'Models',
			'views' => 'Views',
			'queries' => 'Queries',
			'annotations' => 'Annotations',
			'seo' => 'SEO',
			'contents' => 'Contents',
			'translations' => 'Translations'
		];
		$radios = HtmlFormFields::checkeds('ctvv', 'cacheTypes[]', $cacheTypes, 'Display cache types: ', $this->config['display-cache-types']);

		$this->jquery->postFormOn('change', '#ctvv .checkbox', $this->_getFiles()
			->getAdminBaseRoute() . "/_setCacheTypes", "frmCache", "#dtCacheFiles tbody", [
			"jqueryDone" => "replaceWith",
			"preventDefault" => false,
			"hasLoader" => false
		]);
		$fields = $form->addField($radios)->setInline();
		$fields->setProperty('style', 'margin:0;');
		$this->_getAdminViewer()->getCacheDataTable($cacheFiles);
		$this->jquery->renderView($this->_getFiles()
			->getViewCacheIndex(),['inverted'=>$this->style]);
	}

	public function rest() {
		$this->getHeader("rest");
		if (\array_search('rest', $this->config['info']) === false) {
			$this->showSimpleMessage("Router Rest cache entry is <b>" . CacheManager::$cache->getEntryKey("controllers\\routes.rest") . "</b>", "info", "Rest service", "info circle", null, "msgRest", 'rest');
		}
		$this->_refreshRest();
		$this->jquery->getOnClick("#bt-init-rest-cache", $this->_getFiles()
			->getAdminBaseRoute() . "/_initRestCache", "#divRest", [
			'attr' => '',
			'dataType' => 'html',
			'hasLoader'=>'internal'
		]);
		$this->jquery->postOn("change", "#access-token", $this->_getFiles()
			->getAdminBaseRoute() . "/_saveToken", "{_token:$(this).val()}");
		$token = "";
		if (isset($_SESSION["_token"])) {
			$token = $_SESSION["_token"];
		}
		$this->jquery->getOnClick("#bt-new-resource", $this->_getFiles()
			->getAdminBaseRoute() . "/_frmNewResource", "#div-new-resource", [
				'attr' => '',
				'hasLoader'=>'internal'
		]);
		$this->jquery->renderView($this->_getFiles()
			->getViewRestIndex(), [
			'token' => $token,
			'inverted'=>$this->style
		]);
	}

	public function config($hasHeader = true) {
		$config = Startup::getConfig();
		if ($hasHeader === true)
			$this->getHeader("config");
		$this->_getAdminViewer()->getConfigDataElement($config);
		$this->jquery->getOnClick("#edit-config-btn", $this->_getFiles()
			->getAdminBaseRoute() . "/_formConfig/ajax", "#action-response", [
			"hasLoader" => "internal",
			"jsCallback" => '$("#config-div").hide();'
		]);
		$this->jquery->renderView($this->_getFiles()
			->getViewConfigIndex(),['inverted'=>$this->style]);
	}

	public function logs() {
		$config = Startup::getConfig();
		$this->getHeader("logs");
		$menu = $this->jquery->semantic()->htmlMenu("menu-logs");
		$ck = $menu->addItem(HtmlCheckbox::toggle("ck-reverse"));
		$ck->postFormOnClick($this->_getFiles()
			->getAdminBaseRoute() . "/_logsRefresh", "frm-logs", "#logs-div");
		$menu->addItem(new HtmlInput("maxLines", "number", 50));
		$dd = new HtmlDropdown("groupBy", "1,2", [
			"1" => "Date",
			"2" => "Context",
			"3" => "Part"
		]);
		$dd->setDefaultText("Group by...");
		$dd->asSelect("group-by", true);
		$menu->addItem($dd);
		$dd = new HtmlDropdown("dd-contexts", "", array_combine(LoggerParams::$contexts, LoggerParams::$contexts));
		$dd->setDefaultText("Select contexts...");
		$dd->asSelect("contexts", true);
		$menu->addItem($dd);
		$this->_setStyle($menu);

		if (! $config["debug"]) {
			$this->showSimpleMessage("Debug mode is not active in config.php file. <br><br><a class='_activateLogs ui blue button'><i class='ui toggle on icon'></i> Activate logging</a>", "info", "Debug", "info circle", null, "logs-message");
			$this->jquery->getOnClick("._activateLogs", $this->_getFiles()
				->getAdminBaseRoute() . "/_activateLog", "#main-content");
		} else {
			$item = $menu->addItem($bts = new HtmlButtonGroups("bt-apply", [
				"",
				"Clear all",
				"Apply"
			]));
			$item->addClass("right aligned");
			$bts->postFormOnClick($this->_getFiles()
				->getAdminBaseRoute() . "/", "frm-logs", "$('#'+$(self).attr('data-target'))", [
				"attr" => "data-url"
			]);
			$bts->addPropertyValues("class", [
				"".$this->style,
				"red ".$this->style,
				"black ".$this->style
			]);
			$bts->setPropertyValues("data-url", [
				"_deActivateLog",
				"_deleteAllLogs",
				"_logsRefresh"
			]);
			$bts->setPropertyValues("title", [
				"Stop logging",
				"delete all logs",
				"Apply modifications"
			]);
			$bts->setPropertyValues("data-target", [
				"main-content",
				"logs-div",
				"logs-div"
			]);
			$bts->getItem(0)->asIcon("stop");
		}
		$this->_getAdminViewer()->getLogsDataTable(50);
		$this->jquery->renderView($this->_getFiles()
			->getViewLogsIndex());
	}

	public function seo() {
		$this->getHeader("seo");
		$this->_seo();
		$this->jquery->execOn('click', '#generateRobots', '$("#frm-seoCtrls").form("submit");');
		$this->jquery->getOnClick('.addNewSeo', $this->_getFiles()
			->getAdminBaseRoute() . '/_newSeoController', '#seo-details', [
			'hasLoader' => 'internal'
		]);
		$this->jquery->renderView($this->_getFiles()
			->getViewSeoIndex(),['inverted'=>$this->style]);
	}

	public function translate() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$this->getHeader("translate");
		$loc = TranslatorManager::fixLocale(URequest::getDefaultLanguage());
		$this->jquery->execAtLast("\$.create_UUID=function(){
				var dt = new Date().getTime();
				var uuid = 'xxxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
					var r = (dt + Math.random()*16)%16 | 0;
					dt = Math.floor(dt/16);
					return (c=='x' ? r :(r&0x3|0x8)).toString(16);
				});
					return uuid;
			}");
		$this->_translate($loc, $baseRoute);
	}

	public function git($hasMessage = true) {
		$semantic = $this->jquery->semantic();
		$loader = '<div class="ui active inline centered indeterminate text loader">Waiting for git operation...</div>';
		$this->getHeader("git");
		$gitRepo = $this->_getRepo();
		$initializeBt = "";
		$pushPullBts = "";
		$gitIgnoreBt = "";
		$btRefresh = "";
		$execCmdBt = "";
		if (! $gitRepo->getInitialized()) {
			$initializeBt = $semantic->htmlButton("initialize-bt", "Initialize repository", "orange");
			$initializeBt->addIcon("magic");
			$initializeBt->getOnClick($this->_getFiles()
				->getAdminBaseRoute() . "/_gitInit", "#main-content", [
				"attr" => ""
			]);
			if ($hasMessage)
				$this->showSimpleMessage("<b>{$gitRepo->getName()}</b> respository is not initialized!", "warning", null, "warning circle", null, "init-message");
		} else {
			if ($hasMessage) {
				$this->showSimpleMessage("<b>{$gitRepo->getName()}</b> repository is correctly initialized.", "info", null, "info circle", null, "init-message");
			}
			$pushPullBts = $semantic->htmlButtonGroups("push-pull-bts", [
				"3-Push",
				"1-Pull"
			]);
			$pushPullBts->addIcons([
				"upload",
				"download"
			]);
			$pushPullBts->setPropertyValues("data-ajax", [
				"_gitPush",
				"_gitPull"
			]);
			$pushPullBts->addPropertyValues("class", [
				"blue",
				"black"
			]);
			$pushPullBts->getOnClick($this->_getFiles()
				->getAdminBaseRoute(), "#messages", [
				"attr" => "data-ajax",
				"ajaxLoader" => $loader
			]);
			$pushPullBts->setPropertyValues("style", "width: 220px;");
			$gitIgnoreBt = $semantic->htmlButton("gitIgnore-bt", ".gitignore");
			$gitIgnoreBt->getOnClick($this->_getFiles()
				->getAdminBaseRoute() . "/_gitIgnoreEdit", "#frm", [
				"attr" => ""
			]);
			$btRefresh = $semantic->htmlButton("refresh-bt", "Refresh files", "green");
			$btRefresh->addIcon("sync alternate");
			$btRefresh->getOnClick($this->_getFiles()
				->getAdminBaseRoute() . "/_refreshGitFiles", "#dtGitFiles", [
				'attr' => '',
				'jqueryDone' => 'replaceWith',
				'hasLoader' => false
			]);

			$execCmdBt = $semantic->htmlButton("execCmd-bt", "Git cmd");
			$execCmdBt->getOnClick($this->_getFiles()
				->getAdminBaseRoute() . '/_gitCmdFrm', '#frm', [
				'hasLoader' => 'internal'
			]);
		}

		$this->jquery->getOnClick("#settings-btn", $this->_getFiles()
			->getAdminBaseRoute() . "/_gitFrmSettings", "#frm");

		$this->gitTabs($gitRepo, $loader);
		$this->jquery->renderView($this->_getFiles()
			->getViewGitIndex(), [
			"repo" => $gitRepo,
			"initializeBt" => $initializeBt,
			"gitIgnoreBt" => $gitIgnoreBt,
			"pushPullBts" => $pushPullBts,
			"btRefresh" => $btRefresh,
			"execCmdBt" => $execCmdBt
		]);
	}

	public function themes() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$devtoolsPath = $this->config["devtools-path"] ?? 'Ubiquity';
		$this->getHeader("themes");
		$this->jquery->semantic()->htmlLabel("activeTheme");
		$activeTheme = ThemesManager::getActiveTheme() ?? 'no theme';
		$themes = ThemesManager::getAvailableThemes();
		$notInstalled = ThemesManager::getNotInstalledThemes();
		$refThemes = ThemesManager::getRefThemes();
		$frm = $this->jquery->semantic()->htmlForm('frmNewTheme');
		$frm->addClass($this->style);
		$fields = $frm->addFields();
		$input = $fields->addInput('themeName', null, 'text', '', 'Theme name');
		$input->addRules([
			'empty',
			[
				'checkTheme',
				'Theme {value} already exists!'
			]
		]);
		$dd = $fields->addDropdown('extendTheme', \array_combine($refThemes, $refThemes), '', 'extends...');
		$dd->getField()->setClearable(true);
		$fields->addButton('btNewTheme', 'Create theme', 'positive '.$this->style);

		$this->jquery->exec(Rule::ajax($this->jquery, 'checkTheme', $baseRoute . '/_themeExists/themeName', '{}', 'result=data.result;', 'postForm', [
			'form' => 'frmNewTheme'
		]), true);

		$frm->setValidationParams([
			'on' => 'blur',
			'inline' => true
		]);
		$frm->setSubmitParams($baseRoute . "/_createNewTheme", "#refresh-theme", [
			'hasLoader' => 'internal'
		]);

		$this->jquery->postOnClick('._installTheme', $this->_getFiles()
			->getAdminBaseRoute() . '/_execComposer/_refreshTheme/refresh-theme/html', '{commands: "echo n | ' . $devtoolsPath . ' install-theme "+$(this).attr("data-ajax")}', '#partial', [
			'before' => '$("#response").html(' . $this->getConsoleMessage_('partial', 'Theme installation...') . ');',
			'hasLoader' => false,
			'partial' => "$('#partial').html(response);"
		]);

		$this->jquery->getHref("._setTheme", "#refresh-theme");

		$this->jquery->renderView($this->_getFiles()
			->getViewThemesIndex(), compact('activeTheme', 'themes', 'notInstalled')+['inverted'=>$this->style]);
	}

	protected function getHeader($key) {
		$semantic = $this->jquery->semantic();
		$header = $semantic->htmlHeader("header", 3);
		$e = $this->_getAdminViewer()->getMainMenuElements()[$key];
		$header->asTitle($e[0], $e[2]);
		$header->addIcon($e[1]);
		$header->setBlock()->setInverted();
		return $header;
	}

	public function _showDiagram() {
		if (URequest::isPost()) {
			if (isset($_POST["model"])) {
				$model = $_POST["model"];
				$model = \str_replace("|", "\\", $model);
				$modal = $this->jquery->semantic()->htmlModal("diagram", "Class diagram : " . $model);
				$yuml = $this->_getClassToYuml($model, $_POST);
				$menu = $this->_diagramMenu("/_updateDiagram", "{model:'" . $_POST["model"] . "',refresh:'true'}", "#diag-class");
				$modal->setContent([
					$menu,
					"<div id='diag-class' class='ui center aligned grid' style='margin:10px;'>",
					$this->_getYumlImage("plain", $yuml . ""),
					"</div>"
				]);
				$modal->addAction("Close");
				$this->jquery->exec("$('#diagram').modal('show');", true);
				$modal->onHidden("$('#diagram').remove();");
				echo $modal;
				echo $this->jquery->compile($this->view);
			}
		}
	}

	private function _getClassToYuml($model, $post) {
		if (isset($post["properties"])) {
			$props = \array_flip($post["properties"]);
			$yuml = new ClassToYuml($model, isset($props["displayProperties"]), isset($props["displayAssociations"]), isset($props["displayMethods"]), isset($props["displayMethodsParams"]), isset($props["displayPropertiesTypes"]), isset($props["displayAssociationClassProperties"]));
			if (isset($props["displayAssociations"])) {
				$yuml->init(true, true, true);
			}
		} else {
			$yuml = new ClassToYuml($model, ! isset($_POST["refresh"]));
			$yuml->init(true, true, true);
		}
		return $yuml;
	}

	private function _getClassesToYuml($post) {
		$db = $this->getActiveDb();
		if (isset($post["properties"])) {
			$props = \array_flip($post["properties"]);
			$yuml = new ClassesToYuml($db, isset($props["displayProperties"]), isset($props["displayAssociations"]), isset($props["displayMethods"]), isset($props["displayMethodsParams"]), isset($props["displayPropertiesTypes"]));
		} else {
			$yuml = new ClassesToYuml($db, ! isset($_POST["refresh"]), ! isset($_POST["refresh"]));
		}
		return $yuml;
	}

	public function _updateDiagram() {
		if (URequest::isPost()) {
			if (isset($_POST["model"])) {
				$model = $_POST["model"];
				$model = \str_replace("|", "\\", $model);
				$type = $_POST["type"];
				$size = $_POST["size"];
				$yuml = $this->_getClassToYuml($model, $_POST);
				echo $this->_getYumlImage($type . $size, $yuml . "");
				echo $this->jquery->compile($this->view);
			}
		}
	}

	/**
	 *
	 * @param string $url
	 * @param string $params
	 * @param string $responseElement
	 * @param string $type
	 * @return HtmlMenu
	 */
	private function _diagramMenu($url = "/_updateDiagram", $params = "{}", $responseElement = "#diag-class", $type = "plain", $size = ";scale:100") {
		$params = JsUtils::_implodeParams([
			"$('#frmProperties').serialize()",
			$params
		]);
		$menu = new HtmlMenu("menu-diagram");
		$popup = $menu->addPopupAsItem("Display", "Parameters");
		$list = new HtmlList("lst-checked");
		$list->addCheckedList([
			"displayPropertiesTypes" => "Types"
		], [
			"Properties",
			"displayProperties"
		], [
			"displayPropertiesTypes"
		], true, "properties[]");
		$list->addCheckedList([
			"displayMethodsParams" => "Parameters"
		], [
			"Methods",
			"displayMethods"
		], [], true, "properties[]");
		$list->addCheckedList([
			"displayAssociationClassProperties" => "Associated class members"
		], [
			"Associations",
			"displayAssociations"
		], [
			"displayAssociations"
		], true, "properties[]");
		$btApply = new HtmlButton("bt-apply", "Apply", "green fluid");
		$btApply->postOnClick($this->_getFiles()
			->getAdminBaseRoute() . $url, $params, $responseElement, [
			"ajaxTransition" => "random",
			"params" => $params,
			"attr" => "",
			"jsCallback" => "$('#Parameters').popup('hide');"
		]);
		$list->addItem($btApply);
		$popup->setContent($list);
		$ddScruffy = new HtmlDropdown("ddScruffy", $type, [
			"nofunky" => "Boring",
			"plain" => "Plain",
			"scruffy" => "Scruffy"
		], true);
		$ddScruffy->setValue("plain")->asSelect("type");
		$this->jquery->postOn("change", "#type", $this->_getFiles()
			->getAdminBaseRoute() . $url, $params, $responseElement, [
			"ajaxTransition" => "random",
			"attr" => ""
		]);
		$menu->addItem($ddScruffy);
		$ddSize = new HtmlDropdown("ddSize", $size, [
			";scale:180" => "Huge",
			";scale:120" => "Big",
			";scale:100" => "Normal",
			";scale:80" => "Small",
			";scale:60" => "Tiny"
		], true);
		$ddSize->asSelect("size");
		$this->jquery->postOn("change", "#size", $this->_getFiles()
			->getAdminBaseRoute() . $url, $params, $responseElement, [
			"ajaxTransition" => "random",
			"attr" => ""
		]);
		$menu->wrap("<form id='frmProperties' name='frmProperties'>", "</form>");
		$menu->addItem($ddSize);
		return $menu;
	}

	public function _showAllClassesDiagram() {
		$yumlContent = new ClassesToYuml($this->getActiveDb());
		$menu = $this->_diagramMenu("/_updateAllClassesDiagram", "{refresh:'true'}", "#diag-class");
		$this->jquery->exec('$("#modelsMessages-success").hide()', true);
		$menu->compile($this->jquery, $this->view);
		$form = $this->jquery->semantic()->htmlForm("frm-yuml-code");
		$textarea = $form->addTextarea("yuml-code", "Yuml code", \str_replace(",", ",\n", $yumlContent . ""));
		$textarea->getField()->setProperty("rows", 20);
		$diagram = $this->_getYumlImage("plain", $yumlContent);
		$this->jquery->execAtLast('$("#all-classes-diagram-tab .item").tab();');
		$this->jquery->compile($this->view);
		$this->loadView($this->_getFiles()
			->getViewClassesDiagram(), [
			"diagram" => $diagram
		]);
	}

	public function _updateAllClassesDiagram() {
		if (URequest::isPost()) {
			$type = $_POST["type"];
			$size = $_POST["size"];
			$yumlContent = $this->_getClassesToYuml($_POST);
			$this->jquery->exec('$("#yuml-code").html("' . \htmlentities($yumlContent . "") . '")', true);
			echo $this->_getYumlImage($type . $size, $yumlContent);
			echo $this->jquery->compile();
		}
	}

	protected function _getYumlImage($sizeType, $yumlContent) {
		return "<img src='http://yuml.me/diagram/" . $sizeType . "/class/" . $yumlContent . "'>";
	}

	public function _showDatabaseCreation() {
		$config = Startup::getConfig();
		$models = $this->getModels();
		$dbConfig = DAO::getDbOffset($config, $this->getActiveDb());
		$segment = $this->jquery->semantic()->htmlSegment("menu");
		$segment->setTagName("form");
		$header = new HtmlHeader("", 5, "Database creation");
		$header->addIcon("plus");
		$segment->addContent($header);
		$input = new HtmlFormInput("dbName");
		$input->setValue($dbConfig["dbName"]);
		$input->getField()->setFluid();
		$segment->addContent($input);
		$list = new HtmlList("lst-checked");
		$list->addCheckedList([
			"dbCreation" => "Creation",
			"dbUse" => "Use"
		], [
			"Database",
			"db"
		], [
			"use",
			"creation"
		], false, "dbProperties[]");
		$list->addCheckedList($models, [
			"Models [tables]",
			"modTables"
		], \array_keys($models), false, "tables[]");
		$list->addCheckedList([
			"manyToOne" => "ManyToOne",
			"oneToMany" => "oneToMany"
		], [
			"Associations",
			"displayAssociations"
		], [
			"displayAssociations"
		], false, "associations[]");
		$btApply = new HtmlButton("bt-apply", "Create SQL script", "green fluid");
		$btApply->postFormOnClick($this->_getFiles()
			->getAdminBaseRoute() . "/_createSQLScript", "menu", "#div-create", [
			"ajaxTransition" => "random",
			"attr" => ""
		]);
		$list->addItem($btApply);

		$segment->addContent($list);
		$this->jquery->compile($this->view);
		$this->loadView($this->_getFiles()
			->getViewDatabaseIndex());
	}

	public function _runPostWithParams($method = "post", $type = "parameter", $origine = "routes") {
		if (URequest::isPost()) {
			$model = null;
			$actualParams = [];
			$url = $_POST["url"];
			if (isset($_POST["method"]))
				$method = $_POST["method"];
			if (isset($_POST["model"])) {
				$model = $_POST["model"];
			}

			if ($origine === "routes") {
				$responseElement = "#modal";
				$responseURL = "/_runAction";
				$jqueryDone = "html";
				$toUpdate = "";
			} else {
				$toUpdate = $_POST["toUpdate"];
				$responseElement = "#" . $toUpdate;
				$responseURL = "/_saveRequestParams/" . $type;
				$jqueryDone = "replaceWith";
			}
			if (isset($_POST["actualParams"])) {
				$actualParams = $this->_getActualParamsAsArray($_POST["actualParams"]);
			}
			$modal = $this->jquery->semantic()->htmlModal("response-with-params", "Parameters for the " . \strtoupper($method) . ":" . $url);
			$frm = $this->jquery->semantic()->htmlForm("frmParams");
			$frm->addMessage("msg", "Enter your " . $type . "s.", \ucfirst($method) . " " . $type . "s", "info circle");
			$index = 0;
			foreach ($actualParams as $name => $value) {
				$this->_addNameValueParamFields($frm, $type, $name, $value, $index ++);
			}
			$this->_addNameValueParamFields($frm, $type, "", "", $index ++);

			$fieldsButton = $frm->addFields();
			$fieldsButton->addClass("_notToClone");
			$fieldsButton->addButton("clone", "Add " . $type, "yellow")->setTagName("div");
			if ($model != null) {
				$model = UbiquityUtils::getModelsName(Startup::getConfig(), $model);
				$modelFields = OrmUtils::getSerializableFields($model);
				if (\sizeof($modelFields) > 0) {
					$modelFields = \array_combine($modelFields, $modelFields);
					$ddModel = $fieldsButton->addDropdown("bt-addModel", $modelFields, "Add " . $type . "s from " . $model);
					$ddModel->asButton();
					$this->jquery->click("#dropdown-bt-addModel .item", "
							var text=$(this).text();
							var count=0;
							var empty=null;
							$('#frmParams input[name=\'name[]\']').each(function(){
								if($(this).val()==text) count++;
								if($(this).val()=='') empty=this;
							});
							if(count<1){
								if(empty==null){
									$('#clone').click();
									var inputs=$('.fields:not(._notToClone)').last().find('input');
									inputs.first().val($(this).text());
								}else{
									$(empty).val($(this).text());
								}
							}
							");
				}
			}
			if (isset($_COOKIE[$method]) && \sizeof($_COOKIE[$method]) > 0) {
				$dd = $fieldsButton->addDropdownButton("btMem", "Memorized " . $type . "s", $_COOKIE[$method])
					->getDropdown()
					->setPropertyValues("data-mem", \array_map("addslashes", $_COOKIE[$method]));
				$cookiesIndex = \array_keys($_COOKIE[$method]);
				$dd->each(function ($i, $item) use ($cookiesIndex) {
					$bt = new HtmlButton("bt-" . $item->getIdentifier());
					$bt->asIcon("remove")
						->addClass("basic _deleteParam");
					$bt->getOnClick($this->_getFiles()
						->getAdminBaseRoute() . "/_deleteCookie", null, [
						"attr" => "data-value"
					]);
					$bt->setProperty("data-value", $cookiesIndex[$i]);
					$bt->onClick("$(this).parents('.item').remove();");
					$item->addContent($bt, true);
				});
				$this->jquery->click("[data-mem]", "
						var objects=JSON.parse($(this).text());
						$.each(objects, function(name, value) {
							$('#clone').click();
							var inputs=$('.fields:not(._notToClone)').last().find('input');
							inputs.first().val(name);
							inputs.last().val(value);
						});
						$('.fields:not(._notToClone)').each(function(){
							var inputs=$(this).find('input');
							if(inputs.last().val()=='' && inputs.last().val()=='')
								if($('.fields').length>2)
									$(this).remove();
						});
						");
			}
			$this->jquery->click("._deleteParameter", "
								if($('.fields').length>2)
									$(this).parents('.fields').remove();
					", true, true, true);
			$this->jquery->click("#clone", "
					var cp=$('.fields:not(._notToClone)').last().clone(true);
					var num = parseInt( cp.prop('id').match(/\d+/g), 10 ) +1;
					cp.find( '[id]' ).each( function() {
						var num = $(this).attr('id').replace( /\d+$/, function( strId ) { return parseInt( strId ) + 1; } );
						$(this).attr( 'id', num );
						$(this).val('');
					});
					cp.insertBefore($('#clone').closest('.fields'));");
			$frm->setValidationParams([
				"on" => "blur",
				"inline" => true
			]);
			$frm->setSubmitParams($this->_getFiles()
				->getAdminBaseRoute() . $responseURL, $responseElement, [
				"jqueryDone" => $jqueryDone,
				"params" => "{toUpdate:'" . $toUpdate . "',method:'" . \strtoupper($method) . "',url:'" . $url . "'}"
			]);
			$modal->setContent($frm);
			$modal->addAction("Validate");
			$this->jquery->click("#action-response-with-params-0", "$('#frmParams').form('submit');", false, false, false);

			$modal->addAction("Close");
			$this->jquery->exec("$('.dimmer.modals.page').html('');$('#response-with-params').modal('show');", true);
			echo $modal->compile($this->jquery, $this->view);
			echo $this->jquery->compile($this->view);
		}
	}

	protected function _getActualParamsAsArray($urlEncodedParams) {
		$result = [];
		$params = [];
		\parse_str(urldecode($urlEncodedParams), $params);
		if (isset($params["name"])) {
			$names = $params["name"];
			$values = $params["value"];
			$count = \sizeof($names);
			for ($i = 0; $i < $count; $i ++) {
				$name = $names[$i];
				if (UString::isNotNull($name)) {
					if (isset($values[$i]))
						$result[$name] = $values[$i];
				}
			}
		}
		return $result;
	}

	protected function _addNameValueParamFields($frm, $type, $name, $value, $index) {
		$fields = $frm->addFields();
		$fields->addInput("name[]", \ucfirst($type) . " name")
			->getDataField()
			->setIdentifier("name-" . $index)
			->setProperty("value", $name);
		$input = $fields->addInput("value[]", \ucfirst($type) . " value");
		$input->getDataField()
			->setIdentifier("value-" . $index)
			->setProperty("value", htmlentities($value));
		$input->addAction("", true, "remove")->addClass("icon basic _deleteParameter");
	}

	public function _deleteCookie($index, $type = "post") {
		$name = $type . "[" . $index . "]";
		if (isset($_COOKIE[$type][$index])) {
			\setcookie($name, "", \time() - 3600, "/", "127.0.0.1");
		}
	}

	private function _setPostCookie($content, $method = "post", $index = null) {
		if (isset($_COOKIE[$method])) {
			$cookieValues = \array_values($_COOKIE[$method]);
			if ((\array_search($content, $cookieValues)) === false) {
				if (! isset($index))
					$index = \sizeof($_COOKIE[$method]);
				setcookie($method . "[" . $index . "]", $content, \time() + 36000, "/", "127.0.0.1");
			}
		} else {
			if (! isset($index))
				$index = 0;
			setcookie($method . "[" . $index . "]", $content, \time() + 36000, "/", "127.0.0.1");
		}
	}

	private function _setGetCookie($index, $content) {
		setcookie("get[" . $index . "]", $content, \time() + 36000, "/", "127.0.0.1");
	}

	public function _runAction($frm = null) {
		if (URequest::isPost()) {
			$url = URequest::cleanUrl($_POST["url"]);
			unset($_POST["url"]);
			$method = $_POST["method"] ?? 'GET';
			unset($_POST["method"]);
			$newParams = null;
			$postParams = $_POST;
			if (\sizeof($_POST) > 0) {
				if (\strtoupper($method) === "POST" && $frm !== "frmGetParams") {
					$postParams = [];
					$keys = $_POST["name"];
					$values = $_POST["value"];
					for ($i = 0; $i < \sizeof($values); $i ++) {
						if ($keys[$i] != null)
							$postParams[$keys[$i]] = $values[$i];
					}
					if (\sizeof($postParams) > 0) {
						$this->_setPostCookie(\json_encode($postParams));
					}
				} else {
					$newParams = $_POST;
					$this->_setGetCookie($url, \json_encode($newParams));
				}
			}
			$modal = $this->jquery->semantic()->htmlModal("rModal", \strtoupper($method) . ":" . $url);
			$params = $this->getRequiredRouteParameters($url, $newParams);
			if (\sizeof($params) > 0) {
				$toPost = \array_merge($postParams, [
					"method" => $method,
					"url" => $url
				]);
				$frm = $this->jquery->semantic()->htmlForm("frmGetParams");
				$frm->addMessage("msg", "You must complete the following parameters before continuing navigation testing", "Required URL parameters", "info circle");
				$paramsValues = $this->_getParametersFromCookie($url, $params);
				foreach ($paramsValues as $param => $value) {
					$frm->addInput($param, \ucfirst($param))
						->addRule("empty")
						->setValue($value);
				}
				$frm->setValidationParams([
					"on" => "blur",
					"inline" => true
				]);
				$frm->setSubmitParams($this->_getFiles()
					->getAdminBaseRoute() . "/_runAction/frmGetParams", "#modal", [
					"params" => \json_encode($toPost)
				]);
				$modal->setContent($frm);
				$modal->addAction("Validate");
				$this->jquery->click("#action-rModal-0", "$('#frmGetParams').form('submit');");
			} else {
				$this->jquery->ajax($method, $url, '#content-rModal.content', [
					"params" => \json_encode($postParams)
				]);
			}
			$modal->addAction("Close");
			$this->jquery->exec("$('.dimmer.modals.page').html('');$('#rModal').modal('show');", true);
			echo $modal;
			echo $this->jquery->compile($this->view);
		}
	}

	private function _getParametersFromCookie($url, $params) {
		$result = \array_fill_keys($params, "");
		if (isset($_COOKIE["get"])) {
			if (isset($_COOKIE["get"][$url])) {
				$values = \json_decode($_COOKIE["get"][$url], true);
				foreach ($params as $p) {
					$result[$p] = @$values[$p];
				}
			}
		}
		return $result;
	}

	private function getRequiredRouteParameters(&$url, $newParams = null) {
		$url = stripslashes($url);
		$route = Router::getRouteInfo($url);
		if ($route === false) {
			$ns = Startup::getNS();
			$u = \explode("/", $url);
			$controller = $ns . $u[0];
			if (\sizeof($u) > 1)
				$action = $u[1];
			else
				$action = "index";
			if (isset($newParams) && \sizeof($newParams) > 0) {
				$url = $u[0] . "/" . $action . "/" . \implode("/", \array_values($newParams));
				return [];
			}
		} else {
			if (isset($newParams) && \sizeof($newParams) > 0) {
				$routeParameters = $route["parameters"];
				$i = 0;
				foreach ($newParams as $v) {
					if (isset($routeParameters[$i]))
						$result[(int) $routeParameters[$i ++]] = $v;
				}
				ksort($result);

				$url = vsprintf(\preg_replace('#\([^\)]+\)#', '%s', $url), $result);
				return [];
			}
			$controller = $route["controller"];
			$action = $route["action"];
		}
		if (! is_string($controller)) {
			if (is_callable($controller)) {
				$func = new \ReflectionFunction($controller);
				return \array_map(function ($e) {
					return $e->name;
				}, \array_slice($func->getParameters(), 0, $func->getNumberOfRequiredParameters()));
			}
			return [];
		}
		if (\class_exists($controller)) {
			if (\method_exists($controller, $action)) {
				$method = new \ReflectionMethod($controller, $action);
				return \array_map(function ($e) {
					return $e->name;
				}, \array_slice($method->getParameters(), 0, $method->getNumberOfRequiredParameters()));
			}
		}
		return [];
	}

	protected function loadViewCompo(BaseWidget $elm) {
		$elm->setLibraryId('_compo_');
		$this->jquery->renderView('@framework/main/component.html');
	}

	protected function _createController($controllerName, $variables = [], $ctrlTemplate = 'controller.tpl', $hasView = false, $jsCallback = "") {
		return $this->scaffold->_createController($controllerName, $variables, $ctrlTemplate, $hasView, $jsCallback);
	}

	protected function _addMessageForRouteCreation($path, $jsCallback = "") {
		$msgContent = "<br>Created route : <b>" . $path . "</b>";
		$msgContent .= "<br>You need to re-init Router cache to apply this update:";
		$btReinitCache = new HtmlButton("bt-init-cache", "(Re-)Init router cache", "orange");
		$btReinitCache->addIcon("refresh");
		$msgContent .= "&nbsp;" . $btReinitCache;
		$this->jquery->getOnClick("#bt-init-cache", $this->_getFiles()
			->getAdminBaseRoute() . "/_refreshCacheControllers", "#messages", [
			"attr" => "",
			"hasLoader" => false,
			"dataType" => "html",
			"jsCallback" => $jsCallback
		]);
		return $msgContent;
	}

	protected function getAdminData() {
		return new CRUDDatas();
	}

	protected function getUbiquityMyAdminViewer() {
		return new UbiquityMyAdminViewer($this);
	}

	protected function getUbiquityMyAdminModelViewer() {
		return new ModelViewer($this,$this->style);
	}

	protected function getUbiquityMyAdminFiles() {
		return new UbiquityMyAdminFiles();
	}

	private function getSingleton($value, $method) {
		if (! isset($value)) {
			$value = $this->$method();
		}
		return $value;
	}

	/**
	 *
	 * @return CRUDDatas
	 */
	public function _getAdminData(): CRUDDatas {
		return $this->getSingleton($this->adminData, "getAdminData");
	}

	/**
	 *
	 * @return ModelViewer
	 */
	public function _getModelViewer() {
		return $this->getSingleton($this->adminModelViewer, "getUbiquityMyAdminModelViewer");
	}

	/**
	 *
	 * @return UbiquityMyAdminViewer
	 */
	public function _getAdminViewer() {
		return $this->getSingleton($this->adminViewer, "getUbiquityMyAdminViewer");
	}

	/**
	 *
	 * @return UbiquityMyAdminFiles
	 */
	public function _getFiles() {
		return $this->getSingleton($this->adminFiles, "getUbiquityMyAdminFiles");
	}

	protected function getTableNames($offset = 'default') {
		return $this->_getAdminData()->getTableNames($offset);
	}

	public function _getBaseRoute() {
		return $this->_getFiles()->getAdminBaseRoute();
	}

	public function _getInstancesFilter($model) {
		return "1=1";
	}

	public function _getConfig() {
		return $this->config;
	}

	public function _saveConfig() {
		if (isset($this->config['first-use'])) {
			unset($this->config['first-use']);
		}
		$content = "<?php\nreturn " . UArray::asPhpArray($this->config, 'array', 1, true) . ";";
		return UFileSystem::save(self::$configFile, $content);
	}

	public function maintenance() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$this->getHeader("maintenance");

		$maintenance = $this->config['maintenance'];
		$active = MaintenanceMode::getActiveMaintenance($maintenance);
		$dt = $this->jquery->semantic()->dataTable('dtMaintenance', MaintenanceMode::class, MaintenanceMode::manyFromArray($maintenance));
		$dt->setFields([
			'icon',
			'id',
			'title',
			'active'
		]);
		$dt->fieldAsCheckbox('active', [
			'type' => 'slider'
		]);
		$dt->fieldAsIcon('icon');
		$dt->setIdentifierFunction('getId');
		$dt->setActiveRowSelector();
		$dt->getOnRow('click', $baseRoute . '/_displayMaintenance/', '#maintenance', [
			'attr' => 'data-ajax',
			'hasLoader'=>'internal'
		]);
		$dt->addDeleteButton(true, [
			'hasLoader' => 'internal',
			'jqueryDone' => 'prepend'
		], function ($bt, $instance) {
			if ($instance->getActive()) {
				$bt->setProperty('disabled', 'disabled');
			} else {
				$bt->setClass('ui button visibleover icon _delete red');
				$bt->setProperty('title', 'Delete the maintenance type');
			}
			$bt->addClass($this->style);
		});
		$dt->setTargetSelector([
			'delete' => '#maintenance'
		]);
		$dt->setUrls([
			'delete' => $baseRoute . '/_deleteMaintenanceById'
		]);
		$dt->onPreCompile(function () use (&$dt) {
			$dt->getHtmlComponent()
				->colRightFromRight(0);
		});
		$dt->onNewRow(function ($tr, $instance) {
			if ($instance->getActive()) {
				$tr->addClass('positive');
			}
		});
		$this->_setStyle($dt);
		$display = "";
		if (isset($active)) {
			$display = $this->_displayActiveMaintenance($active);
		}
		$this->jquery->getOnClick('#add-maintenance-btn', $baseRoute . '/_addNewMaintenanceType', '#maintenance', [
			'hasLoader' => 'internal'
		]);
		$this->jquery->renderView($this->_getFiles()
			->getViewMaintenanceIndex(), [
			'active' => $display,
			'inverted'=>$this->style
		]);
	}

	protected function toast($message, $title, $class = 'info', $showIcon = false) {
		$this->jquery->semantic()->toast('body', \compact('message', 'title', 'class', 'showIcon'));
	}

	public function mailer() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$this->getHeader("mailer");
		$this->showSimpleMessage("This part is very recent, feel free to submit your feedback in this <a target='_blank' href='https://github.com/phpMv/ubiquity/issues/56'>github issue [RFC] E-mail module</a> in case of problems.", "info", "Mailer", "info circle", null, "msgGlobal");
		$this->_getAdminViewer()->getMailerDataTable(MailerClass::init());
		$queue = MailerQueuedClass::initQueue();
		$this->_getAdminViewer()->getMailerQueueDataTable($queue);
		$this->activateQueueMenu($queue);

		$this->_getAdminViewer()->getMailerDequeueDataTable(MailerQueuedClass::initQueue(true));
		$this->jquery->execAtLast("$('.menu .item').tab();");

		$this->addMailerBehavior($baseRoute);
		$this->addQueueBehavior($baseRoute, true);
		$this->addDequeueBehavior($baseRoute);

		$this->jquery->getOnClick('#define-period-btn', $baseRoute . '/_definePeriodFrm', '#frm', [
			'hasLoader' => 'internal'
		]);

		$this->jquery->getOnClick('#add-mailer-btn', $baseRoute . '/_addNewMailerFrm', '#frm', [
			'hasLoader' => 'internal'
		]);

		$this->jquery->getOnClick('#edit-config-btn', $baseRoute . '/_mailerConfigFrm', '#frm', [
			'hasLoader' => 'internal',
			'jsCallback' => '$("#mailer-details").hide();$("._menu").addClass("disabled");'
		]);

		$this->jquery->renderView($this->_getFiles()
			->getViewMailerIndex(), [
			'period' => $this->queuePeriodToString($this->config['mailer']['queue-period'] ?? 'now')
		]);
	}

	public function composer() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$this->getHeader("composer");
		$this->getComposerDataTable();

		$this->jquery->postFormOnClick('#submit-composer-bt', $baseRoute . '/_updateComposer', 'composer-frm', '#response', [
			'hasLoader' => 'internal'
		]);

		$this->jquery->postOnClick('#opt-composer-bt', $this->_getFiles()
			->getAdminBaseRoute() . '/_execComposer', "{commands:'composer install --optimize-autoloader --classmap-authoritative'}", null, [
			'before' => '$("#response").html(' . $this->getConsoleMessage_('partial', 'Composer optimization...') . ');',
			'hasLoader' => 'internal',
			'partial' => "$('#partial').html(response);"
		]);

		$this->jquery->getOnClick('#add-dependency-btn', $baseRoute . '/_addDependencyFrm', '#response', [
			'hasLoader' => 'internal'
		]);

		$this->jquery->renderView($this->_getFiles()
			->getViewComposerIndex(),['inverted'=>$this->style]);
	}

	public function oauth($response = '') {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$this->getHeader("oauth");
		$this->getOAuthDataTable($baseRoute);

		$pConfig = OAuthAdmin::loadConfig();
		$url = $pConfig['callback'] ?? null;
		if (isset($url) && $url != null) {
			$callback = new HtmlLabel('_link', $url, 'tags');
			$callback->addClass('large '.$this->style);
			$rRoute = OAuthAdmin::getRedirectRoute();
			$rInfo = Router::getRouteInfo($rRoute . '/Google');
			if (\is_array($rInfo)) {
				$lbl = new HtmlLabel("", "<span style='font-weight: bold;color: #3B83C0;'>" . $rInfo['controller'] . "</span>::<span style='color: #7F0055;'>" . $rInfo['action'] . "</span>", "heartbeat");
				$lbl->addClass('basic large '.$this->style);
				$firstProvider = \array_key_first($pConfig['providers'] ?? []);
				if (isset($firstProvider)) {
					$callback->asLink($url . '/' . $firstProvider, '_blank');
					$this->jquery->postOnClick('#_link', $baseRoute . '/_runAction', "{url: \"{$rRoute}/(.+?)/\"}", '#response');
				}
				$callback .= $lbl . '&nbsp;<i class="ui icon large check green"></i>';
			} else {
				$callback .= (HtmlLabel::tag('', "<i class='ui warning circle icon'></i> no route associated with callback"))->addClass('orange');
			}
		} else {
			$callback = $this->showSimpleMessage('Callback URL is missing in config file!', 'warning', 'Callback', 'warning circle');
		}

		$this->jquery->getOnClick('#config-btn', $baseRoute . '/_globalConfigFrm', '#response', [
			'hasLoader' => 'internal'
		]);
		$this->jquery->getOnClick('#add-provider-btn', $baseRoute . '/_addOAuthProviderFrm', '#response', [
			'hasLoader' => 'internal'
		]);

		$this->jquery->getOnClick('#create-controller-btn', $baseRoute . '/_createOAuthControllerFrm', '#response', [
			'hasLoader' => 'internal'
		]);
		$this->jquery->execAtLast('$(".ui.accordion").accordion({exclusive:false});');

		$this->jquery->renderView($this->_getFiles()
			->getViewOAuthIndex(), [
			'response' => $response,
			'callback' => $callback,
			'inverted'=>$this->style
		]);
	}

	public function security() {
		$this->getHeader('security');
		$this->showSimpleMessage("This part is very recent, do not hesitate to submit your feedback in this <a target='_blank' href='https://github.com/phpMv/ubiquity/issues/110'>github issue</a> in case of problems.", "info", "Security", "info circle", null, "msgGlobal");

		$this->jquery->renderView($this->_getFiles()
			->getViewSecurityIndex(), [
			'sPart' => $this->_refreshSecurity(true)
		]);
	}

	public function acls() {
		$this->getHeader('acls');
		if (AclManager::isStarted()) {
			$providers = AclManager::getAclList()->getProviders();
			if (\count($providers) > 0) {
				$selectedProviders = $this->config['selected-acl-providers'] ?? AclManager::getAclList()->getProviderClasses();
				$cards = $this->jquery->semantic()->htmlCardGroups('providers');

				foreach ($providers as $prov) {
					$bts = new HtmlButtonGroups('bt-providers');
					$r = new \ReflectionClass($prov);
					$sn = $r->getShortName();
					$card = $cards->newItem($sn);
					$list=new HtmlList('', $prov->getDetails());
					$list->addClass($this->style);
					$card->addItemHeaderContent($r->getShortName(), [], $list, []);
					$bt = new HtmlButton('bt-' . $sn, 'active', 'item _activate '.$this->style);
					$bt->setProperty('data-class', \urlencode($r->getName()));
					$bts->addElement($bt);
					if ($prov instanceof \Ubiquity\security\acl\persistence\AclCacheProvider) {
						$bt2 = new HtmlButton('bt-cache-' . $sn, 'Re-init cache', 'orange item _cache');
						$bt2->setProperty('data-class', \urlencode($r->getName()));
						$bt2->addIcon('refresh');
						$bt2->addClass($this->style);
						$bts->addElement($bt2);
					}
					if ($prov instanceof AclDAOProvider) {
						$bt2 = new HtmlDropdown("new-acl-bt", 'Add new...', [
							"_roleAdd" => "Role",
							'_resourceAdd' => 'Resource',
							'_permissionAdd' => 'Permission',
							"_aclElementAdd" => "ACL element"
						]);
						$bt2->asButton();
						$bt2->addIcon("plus");
						$bt2->addClass('_DAO '.$this->style);
						$bts->addElement($bt2);
						$this->jquery->getOnClick('#new-acl-bt a.item', $this->_getFiles()
							->getAdminBaseRoute(), '#form', [
							'hasLoader' => false,
							'attr' => 'data-value',
							'historize' => false
						]);
					}
					$active = '';
					if ($selectedProviders === '*' || \array_search($r->getName(), $selectedProviders) !== false) {
						$active = 'active';
					} else {
						if (isset($bt2)) {
							$bt2->setProperty('style', 'display:none');
						}
					}
					$bt->setToggle($active);
					$card->addExtraContent($bts);
					$cards->addItem($card);
				}
				AclManager::reloadFromSelectedProviders($selectedProviders);
				$this->_getAclTabs();
				$this->_setStyle($cards);
				$this->jquery->getOnClick('._activate', $this->_getFiles()
					->getAdminBaseRoute() . '/_activateProvider', '#aclsPart', [
					'hasLoader' => 'internal',
					'attr' => 'data-class'
				]);
				$this->jquery->getOnClick('.addNewAclController', $this->_getFiles()
					->getAdminBaseRoute() . '/_newAclController', '#response', [
					'hasLoader' => 'internal'
				]);
				$this->jquery->getOnClick('._cache', $this->_getFiles()
					->getAdminBaseRoute() . '/_refreshAclCache', '#aclsPart', [
					'hasLoader' => 'internal',
					'attr' => 'data-class'
				]);
				$this->jquery->renderView($this->_getFiles()
					->getViewAclsIndex(),['inverted'=>$this->style]);
			} else {}
		} else {
			$button = "<div class='ui divider'></div>Or you can do it automatically:<br><div class='ui orange button'><i class='ui icon play'></i>Start AclManager service</div>";
			$msg = $this->showSimpleMessage('AclManager is not started. You have to add <div class="ui inverted segment"><pre>AclManager::start(/*providers*/)</pre></div> in <b>app/config/services.php</b>' . $button, 'warning', 'Acls management', 'warning circle');

			$this->loadViewCompo($msg);
		}
	}

	public function commands() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$this->getHeader('commands');
		$this->loadDevtoolsConfig();

		\Ubiquity\devtools\cmd\Command::preloadCustomCommands($this->devtoolsConfig);
		$commands = CategoryCommands::init([
			'installation' => false,
			'servers' => false
		], \Ubiquity\devtools\cmd\Command::getCommands());
		$myCommands = $this->_myCommands();
		$this->_getAdminViewer()->getCommandsDataTable($commands);
		$this->jquery->getOnClick('._displayCommand', $baseRoute . '/_displayCommand', '#command', [
			'hasLoader' => 'internal',
			'attr' => 'data-cmd'
		]);
		$this->addMyCommandsBehavior($baseRoute);
		$this->jquery->execAtLast("$('.menu .item').tab();");
		$this->jquery->getOnClick('#add-suite-btn', $baseRoute . '/_newCommandSuite', '#command', [
			'hasLoader' => 'internal'
		]);
		$this->jquery->getOnClick('#add-command-btn', $baseRoute . '/_displayCommand/create-command', '#command', [
			'hasLoader' => 'internal',
			'jsCallback' => '$("#save-btn").hide();'
		]);
		$this->jquery->getOnClick('._displayHelp', $baseRoute . '/_displayHelp', '$(self).closest("tr").find("._help")', [
			'hasLoader' => false,
			'attr' => 'data-ajax',
			'jsCallback' => $this->activateHelpLabel()
		]);
		$checkDevtools = $this->_checkDevtoolsPath($this->devtoolsPath);
		$this->jquery->postOnClick("._saveConfig", $baseRoute . "/_setDevtoolsPath", "{path:$('#devtools-path').val()}", "#response", [
			"hasLoader" => "internal"
		]);
		$this->jquery->renderView($this->_getFiles()
			->getViewCommandsIndex(), [
			'myCommands' => $myCommands,
			'devtoolsPath' => $this->devtoolsPath,
			'checkDevtools' => $checkDevtools,
			'inverted'=>$this->style
		]);
	}

	protected function getConsoleMessage_($id = 'partial', $defaultMsg = 'Composer update...') {
		return "\"<div style=\'white-space: pre;white-space: pre-line;\' class=\'ui inverted message\'><i class=\'icon close\'></i><div class=\'header\'>{$defaultMsg}</div><div id=\'" . $id . "\' class=\'content\'><div class=\'ui active slow green double loader\'></div></div></div>\"";
	}

	protected function addCloseToMessage() {
		$this->jquery->execAtLast('$(".message .close").on("click", function() {$(this).closest(".message").transition("fade");});');
	}

	protected function liveExecuteCommand($cmd) {
		$proc = \popen("$cmd 2>&1", 'r');
		$live_output = "";
		while (! \feof($proc)) {
			$live_output = fread($proc, 4096);
			$live_output = mb_convert_encoding($live_output, 'UTF-8', 'UTF-8');
			echo "$live_output";
			flush();
			ob_flush();
		}
		\pclose($proc);
	}
}
