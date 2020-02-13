<?php
namespace Ubiquity\controllers\admin;

use Ajax\semantic\components\validation\Rule;
use Ajax\semantic\html\base\HtmlSemDoubleElement;
use Ajax\semantic\html\base\constants\TextAlignment;
use Ajax\semantic\html\collections\HtmlMessage;
use Ajax\semantic\html\collections\form\HtmlFormCheckbox;
use Ajax\semantic\html\collections\form\HtmlFormInput;
use Ajax\semantic\html\collections\form\HtmlFormTextarea;
use Ajax\semantic\html\content\view\HtmlItem;
use Ajax\semantic\html\elements\HtmlButton;
use Ajax\semantic\html\elements\HtmlButtonGroups;
use Ajax\semantic\html\elements\HtmlIcon;
use Ajax\semantic\html\elements\HtmlIconGroups;
use Ajax\semantic\html\elements\HtmlLabel;
use Ajax\semantic\html\elements\HtmlLabelGroups;
use Ajax\semantic\html\elements\HtmlList;
use Ajax\semantic\html\elements\html5\HtmlLink;
use Ajax\semantic\html\views\HtmlItems;
use Ajax\semantic\widgets\dataelement\DataElement;
use Ajax\semantic\widgets\datatable\DataTable;
use Ajax\service\JString;
use Ubiquity\annotations\parser\DocParser;
use Ubiquity\cache\CacheManager;
use Ubiquity\cache\ClassUtils;
use Ubiquity\contents\validation\validators\ConstraintViolationViewer;
use Ubiquity\controllers\Startup;
use Ubiquity\controllers\admin\popo\ControllerAction;
use Ubiquity\controllers\admin\popo\InstanceViolations;
use Ubiquity\controllers\admin\popo\RepositoryGit;
use Ubiquity\controllers\admin\popo\Route;
use Ubiquity\db\Database;
use Ubiquity\log\HtmlLogFormatter;
use Ubiquity\log\LogMessage;
use Ubiquity\log\Logger;
use Ubiquity\utils\base\UArray;
use Ubiquity\utils\base\UDateTime;
use Ubiquity\utils\base\UIntrospection;
use Ubiquity\utils\base\UString;
use Ubiquity\utils\git\GitFileStatus;
use Ubiquity\utils\http\USession;
use Ubiquity\controllers\admin\popo\MailerClass;
use Ubiquity\controllers\admin\popo\MailerQueuedClass;
use Ubiquity\mailer\MailerManager;
use Ajax\semantic\widgets\base\InstanceViewer;
use Ajax\semantic\html\modules\HtmlTab;
use Ajax\semantic\html\elements\HtmlSegment;
use Ajax\semantic\html\collections\form\HtmlFormDropdown;
use Ubiquity\controllers\admin\popo\ComposerDependency;
use Ajax\semantic\html\content\table\HtmlTR;
use Ajax\common\html\html5\HtmlInput;

/**
 *
 * @author jc
 *        
 */
class UbiquityMyAdminViewer {

	/**
	 *
	 * @var \Ajax\php\ubiquity\JsUtils
	 */
	private $jquery;

	/**
	 *
	 * @var UbiquityMyAdminBaseController
	 */
	private $controller;

	public function __construct(UbiquityMyAdminBaseController $controller) {
		$this->jquery = $controller->jquery;
		$this->controller = $controller;
	}

	public function getMainMenuElements() {
		$result = [
			"models" => [
				"Models",
				"sticky note",
				"Used to perform CRUD operations on data."
			],
			"routes" => [
				"Routes",
				"car",
				"Displays defined routes with annotations"
			],
			"controllers" => [
				"Controllers",
				"heartbeat",
				"Displays controllers and actions"
			],
			"cache" => [
				"Cache",
				"lightning",
				"Annotations, models, router and controller cache"
			],
			"rest" => [
				"Rest",
				"server",
				"Restfull web service"
			],
			"config" => [
				"Config",
				"settings",
				"Configuration variables"
			],
			"seo" => [
				"Seo",
				"google",
				"Search Engine Optimization"
			],
			"translate" => [
				"Translate",
				"language",
				"Translation module"
			],
			"themes" => [
				"Themes",
				"paint brush",
				"Themes module"
			],
			"maintenance" => [
				"Maintenance",
				"recycle",
				"Manages maintenance modes"
			],
			"composer" => [
				"Composer",
				"box",
				"Manages composer dependencies"
			]
		];
		if (\class_exists('Cz\\Git\\GitRepository')) {
			$result["git"] = [
				"Git",
				"github",
				"Git versioning"
			];
		}
		if (\class_exists('Monolog\\Logger')) {
			$result["logs"] = [
				"Logs",
				"bug",
				"Log files"
			];
		}
		if (\class_exists('Ubiquity\\mailer\\MailerManager')) {
			$result["mailer"] = [
				"Mailer",
				"mail",
				"Mailer module"
			];
		}
		return $result;
	}

	public function getRoutesDataTable($routes, $dtName = "dtRoutes") {
		$errors = [];
		$messages = "";
		foreach ($routes as $route) {
			$errors = \array_merge($errors, $route->getMessages());
		}
		if (\sizeof($errors) > 0) {
			$messages = $this->controller->showSimpleMessage($errors, "error", "Error", "warning");
		}
		$dt = $this->jquery->semantic()->dataTable($dtName, "Ubiquity\controllers\admin\popo\Route", $routes);
		$dt->setIdentifierFunction(function ($i, $instance) {
			return $instance->getId();
		});
		$dt->setFields([
			"path",
			"methods",
			"controller",
			"action",
			"cache",
			"expired",
			"name"
		]);
		$dt->setCaptions([
			"Path",
			"Methods",
			"Controller",
			"Action & parameters",
			"Cache",
			"Expired",
			"Name",
			""
		]);
		$dt->fieldAsLabel("path", "car");
		$dt->setValueFunction("controller", function ($v) {
			if (! is_string($v)) {
				$lbl = new HtmlLabel("", "<span style='font-weight: bold;color: #3B83C0;'>call</span>::<span style='color: #7F0055;'>" . gettype($v) . "</span>", "heartbeat");
			} else {
				$lbl = new HtmlLabel("", "<span style='font-weight: bold;color: #3B83C0;'>" . $v . "</span>::<span style='color: #7F0055;'>class</span>", "heartbeat");
			}
			$lbl->addClass("basic large");
			return $lbl;
		});
		$this->_dtCache($dt);
		$this->_dtMethods($dt);
		$this->_dtAction($dt);
		$this->_dtExpired($dt);
		$dt->setGroupByFields([
			2
		]);
		$dt->onRowClick('if(!$(this).is("[data-group]"))$("#filter-routes").val($(this).find(".ui.label").text());');
		$dt->onPreCompile(function ($dTable) {
			$dTable->getHtmlComponent()
				->colRightFromRight(0);
			$dTable->getHtmlComponent()
				->colCenterFromRight(2);
		});
		$this->addGetPostButtons($dt);
		$dt->setActiveRowSelector("warning");
		$dt->wrap($messages);
		$dt->setEdition()->addClass("compact");
		return $dt;
	}

	public function getControllersDataTable($controllers) {
		$filteredCtrls = USession::init("filtered-controllers", UArray::remove(ControllerAction::$controllers, [
			"controllers\\Admin",
			"controllers\\MaintenanceController"
		]));
		$controllers = array_filter($controllers, function ($item) use ($filteredCtrls) {
			return array_search($item->getController(), $filteredCtrls) !== false;
		});
		$dt = $this->jquery->semantic()->dataTable("dtControllers", ControllerAction::class, $controllers);
		$dt->setFields([
			"controller",
			"action",
			"dValues"
		]);
		$dt->setIdentifierFunction(function ($i, $instance) {
			return \urlencode($instance->getController());
		});
		$dt->setCaptions([
			"Controller",
			"Action [routes]",
			"Default values",
			""
		]);
		$this->addGetPostButtons($dt);
		$dt->setValueFunction("controller", function ($v, $instance, $index) {
			$bts = new HtmlButtonGroups("bt-" . \urlencode($v), [
				$v
			]);
			$bts->addClass("basic");
			$bt = $bts->getItem(0);
			$bt->addClass("_clickFirst")
				->setIdentifier("bt-0-" . $v);
			$bt->addIcon("heartbeat", true, true);
			$bt->setToggle();
			$dd = $bts->addDropdown([
				"Add new action in <b>{$v}</b>..."
			]);
			$dd->setIcon("plus");
			$item = $dd->getItem(0);
			$item->addClass("_add-new-action")
				->setProperty("data-controller", $instance->getController());
			$bt->onClick("$(\"tr[data-ajax='" . \urlencode($instance->getController()) . "'] td:not([rowspan])\").toggle(!$(this).hasClass('active'));");
			return $bts;
		});
		$dt->setValueFunction("action", function ($v, $instance, $index) {
			$action = $v;
			$controller = ClassUtils::getClassSimpleName($instance->getController());
			$r = new \ReflectionMethod($instance->getController(), $action);
			$lines = file($r->getFileName());
			$params = $instance->getParameters();
			\array_walk($params, function (&$item) {
				$item = $item->name;
			});
			$params = " (" . \implode(" , ", $params) . ")";
			$v = new HtmlSemDoubleElement("", "span", "", "<b>" . $v . "</b>");
			$v->setProperty("style", "color: #3B83C0;");
			$v->addIcon("lightning");
			$v .= new HtmlSemDoubleElement("", "span", "", $params);
			$annots = $instance->getAnnots();
			foreach ($annots as $path => $annotDetail) {
				$lbl = new HtmlLabel("", $path, "car");
				$lbl->setProperty("data-ajax", \htmlspecialchars(($path)));
				$lbl->addClass("_route");
				$v .= "&nbsp;" . $lbl;
			}
			$v = \array_merge([
				$v,
				"<span class='_views-container'>"
			], $this->getActionViews($instance->getController(), $controller, $action, $r, $lines));
			$v[] = "</span>";
			return $v;
		});
		$dt->onPreCompile(function ($dt) {
			$dt->setColAlignment(3, TextAlignment::RIGHT);
			$dt->getHtmlComponent()
				->mergeIdentiqualValues(0);
		});
		$dt->setEdition(true);
		$dt->addClass("compact");
		return $dt;
	}

	public function getComposerDataTable($dependencies) {
		$dt = $this->jquery->semantic()->dataTable("dtComposer", ComposerDependency::class, $dependencies);
		$dt->setFields([
			"part",
			"category",
			"name",
			"version",
			"actions"
		]);
		$dt->setCaptions([
			'',
			'',
			'Name',
			'Version',
			''
		]);
		$dt->setValueFunction('category', function ($v) {
			return "<h6 class='ui grey header'><i class='folder outline icon'></i><div class='content'>{$v}</div></h6>";
		});
		$dt->setValueFunction('version', function ($value, $instance) {
			if ($value != null) {
				$lbl = new HtmlLabel('version-' . $instance->getName(), $value);
				if ($instance->getOptional()) {
					$lbl->wrap('<div class="_version _toUpdate" data-version="' . $value . '" data-ajax="' . $instance->getName() . '" data-part="' . $instance->getPart() . '">', '</div>');
				}
				return $lbl;
			}
			return "<div class='_version'><input type='hidden' name='version[]'></div>";
		});
		$dt->fieldAsLabel('part', 'tag', [
			'class' => 'ui large label'
		]);
		$dt->setGroupByFields([
			0,
			1
		]);
		$dt->onPreCompile(function () use (&$dt) {
			$dt->getHtmlComponent()
				->colRightFromRight(0);
		});
		$dt->onNewRow(function (HtmlTR $row, ComposerDependency $instance) {
			if ($instance->getVersion() != null) {
				if ($instance->getLoaded()) {
					$row->addClass('positive');
				} else {
					$row->addClass('warning');
				}
			}
		});
		$dt->setValueFunction('name', function ($value, $instance) {
			$lbl = new HtmlSemDoubleElement('', 'span', 'ui medium text _u', $value);
			$lbl->setProperty('style', 'font-weight:600;margin-left:35px');
			if ($instance->getVersion() != null) {
				if ($instance->getLoaded()) {
					$lbl->addClass('green');
				} else {
					$lbl->addClass('orange');
				}
			}
			return $lbl;
		});
		$dt->setValueFunction('actions', function ($value, $instance) {
			if ($instance->getOptional()) {
				$name = $instance->getName();
				$bt = new HtmlButton('bt-' . $name, '', 'mini basic');
				if ($instance->getVersion() != null) {
					$bt->setValue('Remove')
						->addClass('_remove');
					$bt->addIcon('minus');
					$input = "<input type='hidden' class='_value' name='toRemove[]' id='remove-" . $name . "'><input type='hidden' class='_update' name='toUpdate[]' id='update-" . $name . "'>";
				} else {
					$bt->setValue('Add')
						->addClass('_add');
					$bt->addIcon('plus');
					$input = "<input type='hidden' class='_value' name='toAdd[]' id='add-" . $name . "'>";
				}
				$bt->setToggle();
				$bt->wrap($input);
				$bt->setProperty('data-part', $instance->getPart())
					->setProperty('data-ajax', $name);

				return $bt;
			}
			return '';
		});
		$dt->setCompact(true);
		$dt->setEdition();
		return $dt;
	}

	public function getMailerDataTable($mailClasses) {
		MailerManager::start();
		$dt = $this->jquery->semantic()->dataTable("dtMailer", MailerClass::class, $mailClasses);
		$dt->setFields([
			"shortname",
			"from",
			"to",
			"attachments"
		]);
		$this->initMailerFields($dt);
		$dt->setIdentifierFunction(function ($i, $instance) {
			return \urlencode($instance->getName());
		});
		$dt->setCaptions([
			"Mailer class",
			"From",
			"To",
			"Att."
		]);
		$dt->addFieldButtons([
			"Add to queue",
			"Send now",
			"see"
		], true, function (HtmlButtonGroups $bts, $instance, $index) {
			$class = $instance->getName();
			$disabled = MailerManager::inQueue($class) || ! $instance->getHasRecipients();
			$name = \urlencode($class);
			$bts->setIdentifier("bts-" . $name . "-" . $index);
			$bts->getItem(0)
				->addClass("_add_to_queue" . ($disabled ? ' disabled' : ''))
				->setProperty("data-class", $name)
				->addIcon("plus");
			$bts->getItem(1)
				->addClass("positive _send_now" . ($disabled ? ' disabled' : ''))
				->setProperty("data-class", $name)
				->addIcon('play');
			$bts->getItem(2)
				->addClass("_see")
				->setProperty("data-class", $name)
				->asIcon("eye");
		});
		$dt->onPreCompile(function ($dt) {
			$dt->setColAlignment(4, TextAlignment::RIGHT);
		});

		$dt->setEdition(true);
		$dt->addClass("compact");
		return $dt;
	}

	private function initMailerFields($dt) {
		$dt->fieldAsLabel("shortname", "envelope outline", [
			"class" => 'ui large basic label',
			'jsCallback' => function ($item, $instance) {
				$item->addPopupHtml($instance->getSubject());
			}
		]);
		$dt->setValueFunction('from', function ($value, $instance) {
			if (\is_array($value)) {
				$value = \current($value);
				$v = (isset($value['name']) ? "<{$value['name']}>" : "") . ($value['address'] ?? $value);
				$lbl = new HtmlLabel('', \htmlentities($v), 'user');
				return $lbl;
			}
		});
		$dt->setValueFunction('to', function ($value, $instance) use ($dt) {
			return $this->multipleRecipientField($dt, $value);
		});
		$dt->setValueFunction('cc', function ($value, $instance) use ($dt) {
			return $this->multipleRecipientField($dt, $value);
		});
		$dt->setValueFunction('bcc', function ($value, $instance) use ($dt) {
			return $this->multipleRecipientField($dt, $value);
		});
		$dt->setValueFunction('attachments', function ($v, $instance) use ($dt) {
			return $this->attachmentsField($dt, $v);
		});
	}

	private function attachmentsField($dt, $value) {
		$v = 0;
		if (\is_array($value)) {
			$v = \count($value);
		}
		if ($v != 0) {
			$lbl = new HtmlLabel('lbl-files-' . $dt->getIdentifier() . InstanceViewer::$index, \htmlentities($v), 'paperclip');
			$lst = new HtmlList('');
			$lst->fromDatabaseObjects($value, function ($item) {
				return \basename($item['file']);
			});
			$lst->setBulleted();
			$lbl->addPopupHtml($lst);
			return $lbl;
		}
		return "";
	}

	private function multipleRecipientField($dt, $value) {
		$v = 0;
		if (\is_array($value)) {
			$v = \count($value);
		}
		if ($v != 0) {
			$lbl = new HtmlLabel('lbl-' . $dt->getIdentifier() . InstanceViewer::$index, \htmlentities($v), 'users');
			$lst = new HtmlList('');
			$lst->fromDatabaseObjects($value, function ($item) {
				return $item['address'];
			});
			$lst->setBulleted();
			$lbl->addPopupHtml($lst);
			return $lbl;
		}
		$lbl = new HtmlLabel('lbl-' . $dt->getIdentifier() . InstanceViewer::$index, 'No recipient', 'ban');
		return $lbl->addClass('basic');
	}

	public function getMailerQueueDataTable($mailClasses) {
		InstanceViewer::$index = 0;
		$dt = $this->jquery->semantic()->dataTable("dtQueue", MailerQueuedClass::class, $mailClasses);
		$dt->setFields([
			"shortname",
			"from",
			"to",
			"attachments",
			"delay"
		]);
		$this->initMailerFields($dt);
		$dt->setCaptions([
			"Mailer class",
			"From",
			"To",
			"Att.",
			"Delay"
		]);
		$dt->setValueFunction('delay', function ($value, $instance) {
			if ($instance->getAt() == null && $instance->getBetween() == null) {
				return 'now';
			} else {
				$d = $instance->getAt();
				if ($d instanceof \DateTime) {
					return $instance->startIn();
				}
			}
		});
		$dt->addFieldButtons([
			"send",
			"remove"
		], true, function (HtmlButtonGroups $bts, $instance) {
			$index = InstanceViewer::$index;
			$class = $instance->getName();
			$name = \urlencode($class);
			$bts->setIdentifier("bts-queue-" . $name . "-" . $index);
			$bts->getItem(0)
				->addClass("positive _send")
				->setProperty("data-class", $name)
				->setProperty("data-index", $index)
				->asIcon('play');
			$bts->getItem(1)
				->addClass("red _remove_from_queue")
				->setProperty("data-class", $name)
				->setProperty("data-index", $index)
				->asIcon('delete');
		});
		$dt->onPreCompile(function ($dt) {
			$dt->setColAlignment(5, TextAlignment::RIGHT);
		});

		$dt->setEdition(true);
		$dt->addClass("compact");
		return $dt;
	}

	public function getMailerDequeueDataTable($mailClasses) {
		InstanceViewer::$index = 0;
		$dt = $this->jquery->semantic()->dataTable("dtDequeue", MailerQueuedClass::class, $mailClasses);
		$dt->setFields([
			"shortname",
			"from",
			"to",
			"attachments",
			"sentAt"
		]);
		$this->initMailerFields($dt);
		$dt->setCaptions([
			"Mailer class",
			"From",
			"To",
			"Att.",
			"Sent at"
		]);
		$dt->setValueFunction('sentAt', function ($value, $instance) {
			if ($instance->getSentAt() != null) {
				return UDateTime::elapsed($instance->getSentAt());
			}
		});
		$dt->addFieldButtons([
			"remove",
			"see"
		], true, function (HtmlButtonGroups $bts, $instance) {
			$index = InstanceViewer::$index;
			$class = $instance->getName();
			$name = \urlencode($class);
			$bts->setIdentifier("bts-queue-" . $name . "-" . $index);
			$bts->getItem(0)
				->addClass("red _remove_from_dequeue")
				->setProperty("data-class", $name)
				->setProperty("data-index", $index)
				->asIcon('delete');
			$bts->getItem(1)
				->addClass("_see_dequeue")
				->setProperty("data-index", $index)
				->asIcon("eye");
		});
		$dt->onPreCompile(function ($dt) {
			$dt->setColAlignment(5, TextAlignment::RIGHT);
		});

		$dt->setEdition(true);
		$dt->addClass("compact");
		return $dt;
	}

	public function getSeeMailDataElement($mailerClass) {
		$de = $this->jquery->semantic()->dataElement('seeMail', $mailerClass);
		$de->setFields([
			'from',
			'to',
			'cc',
			'bcc',
			'subject',
			'body',
			'attachments'
		]);
		$de->setCaptions([
			$this->getSeeMailCaption('From', $mailerClass),
			$this->getSeeMailCaption('To', $mailerClass),
			$this->getSeeMailCaption('Cc', $mailerClass),
			$this->getSeeMailCaption('Bcc', $mailerClass),
			$this->getSeeMailCaption('Subject', $mailerClass),
			$this->getSeeMailCaption('Body', $mailerClass),
			$this->getSeeMailCaption('Attachments', $mailerClass)
		]);
		$this->initMailerFields($de);
		$de->setValueFunction('body', function ($value, $instance) {
			$tab = new HtmlTab('body');
			$tab->addTab('Body', $value);
			$src = new HtmlSegment('', '<pre>' . \htmlentities($value) . '</pre>');
			$src->addClass('blue');
			$tab->addTab('Source', $src);
			$text = $instance->getBodyText();
			if ($text != null) {
				$tab->addTab('BodyText', '<pre>' . $text . '</pre>');
			}
			return $tab;
		});
		$de->fieldAsHeader('subject', 4);
		$de->setAttached(true);
	}

	public function getSeeMailDataElementForm($mailerClass) {
		$de = $this->jquery->semantic()->dataElement('seeMailForm', $mailerClass);
		$de->setFields([
			'from',
			'to',
			'cc',
			'bcc',
			'subject',
			'body',
			'attachments',
			'attachmentsDir'
		]);
		$de->setCaptions([
			$this->getSeeMailCaption('From', $mailerClass),
			$this->getSeeMailCaption('To', $mailerClass),
			$this->getSeeMailCaption('Cc', $mailerClass),
			$this->getSeeMailCaption('Bcc', $mailerClass),
			$this->getSeeMailCaption('Subject', $mailerClass),
			$this->getSeeMailCaption('Body', $mailerClass),
			$this->getSeeMailCaption('Attachments', $mailerClass)
		]);
		$this->initMailerFieldAddress($de, 'to');
		$this->initMailerFieldAddress($de, 'cc');
		$this->initMailerFieldAddress($de, 'bcc');
		$de->setValueFunction('body', function ($value, $instance) {
			$tab = new HtmlTab('tab-body');
			$tab->addTab('Body', new HtmlFormTextarea('body', null, $value));
			$tab->addTab('Preview', $value);
			$text = $instance->getBodyText();
			if ($text != null) {
				$tab->addTab('BodyText', new HtmlFormTextarea('bodyText', null, $text));
			}
			return $tab;
		});
		$de->fieldAsInput('subject');
		$de->fieldAsHidden('attachmentsDir');
		$de->setValueFunction('from', function ($value) {
			if (\is_array($value)) {
				$value = \current($value);
			}
			return new HtmlFormInput('from', null, 'text', $this->getMailAddress($value)['text']);
		});
		$this->initMailerFieldAttachments($de, 'attachments');
		$de->setAttached(true);
		$de->asForm();
		return $de;
	}

	private function initMailerFieldAttachments(DataElement $dt, $name) {
		$dt->setValueFunction($name, function ($values, $instance) use ($name) {
			$dd = new HtmlFormDropdown($name, [], null, '', true, false);
			$files = [];
			if (! isset($values['file'])) {
				if (\is_array($values)) {
					foreach ($values as $value) {
						$file = $value['file'];
						$dd->addItem(\basename($file), $file);
						$files[] = $file;
					}
				}
			} else {
				$dd->addItem($values, $values);
				$files[] = $values;
			}
			$dd->getField()
				->setValue(\implode(',', $files));
			$dd->getField()
				->asSearch($name, true, true);
			return $dd;
		});
	}

	private function initMailerFieldAddress(DataElement $dt, $name) {
		$dt->setValueFunction($name, function ($values, $instance) use ($name) {
			$dd = new HtmlFormDropdown($name, [], null, '', true, false);
			$addresses = [];
			if (! isset($values['address'])) {
				if (\is_array($values)) {
					foreach ($values as $value) {
						$address = $this->getMailAddress($value);
						$dd->addItem($address['text'], $address['val']);
						$addresses[] = $address['val'];
					}
				}
			} else {
				$dd->addItem($values, $values);
				$addresses[] = $values;
			}

			$textValue = \implode(',', $addresses);
			$dd->getField()
				->setValue($textValue);
			$dd->getField()
				->asSearch($name, true, true)
				->setAllowAdditions(true);
			return $dd;
		});
	}

	private function getMailAddress($value) {
		$text = '';
		$val = '';
		if (isset($value['name'])) {
			$text = "<{$value['name']}>";
			$val = "({$value['name']})";
		}

		$val .= ($value['address'] ?? '');
		$text .= ($value['address'] ?? '');
		$text = \htmlentities($text);
		return \compact('text', 'val');
	}

	private function getSeeMailCaption($caption, $mailerClass) {
		if (isset($mailerClass->original)) {
			$member = 'get' . \ucfirst($caption);
			if ($mailerClass->original->{$member}() !== $mailerClass->{$member}()) {
				return $caption . '&nbsp;<span class="ui basic tag label">Updated</span>';
			}
		}
		return $caption;
	}

	public function getFilterControllers($controllers) {
		$selecteds = USession::init("filtered-controllers", UArray::remove($controllers, "controllers\Admin"));
		$list = $this->jquery->semantic()->htmlList("lst-filter");
		$list->addCheckedList(array_combine($controllers, $controllers), "<i class='heartbeat icon'></i>&nbsp;Controllers", $selecteds, false, "filtered-controllers[]");
		return $list;
	}

	public function getActionViews($controllerFullname, $controller, $action, \ReflectionMethod $r, $lines) {
		$result = [];
		$loadedViews = UIntrospection::getLoadedViews($r, $lines);
		$templateEngine = Startup::getTempateEngineInstance();
		foreach ($loadedViews as $view) {
			if ($templateEngine->exists($view)) {
				$lbl = new HtmlLabel("lbl-view-" . $controller . $action . $view, null, "browser", "span");
				$lbl->addClass("violet tag");
				$lbl->addPopupHtml("<i class='icon info circle green'></i>&nbsp;<b>" . $view . "</b> is ok.", "wide");
			} else {
				$lbl = new HtmlLabel("lbl-view-" . $controller . $action . $view, null, "warning", "span");
				$lbl->addClass("orange tag");
				$lbl->addPopupHtml("<i class='icon red warning circle'></i>&nbsp;<b>" . $view . "</b> file is missing.", 'very wide');
			}
			$result[] = $lbl;
		}
		$viewname = $controller . "/" . $action . ".html";
		if (! \file_exists(\ROOT . \DS . "views" . \DS . $viewname)) {
			$bt = new HtmlButton("");
			$bt->setProperty("data-action", $action);
			$bt->setProperty("data-controller", $controller);
			$bt->setProperty("data-controllerFullname", $controllerFullname);
			$bt->addClass("_create-view visibleover circular violet mini")
				->setProperty("style", "visibility: hidden;")
				->asIcon("plus");
			$bt->setProperty('title', 'Create view ' . $viewname);
			$result[] = $bt;
		} elseif (\array_search($viewname, $loadedViews) === false) {
			$lbl = new HtmlLabel("lbl-view-" . $controller . $action . $viewname, null, "browser", "span");
			$lbl->addClass('tag');
			$lbl->addPopupHtml("<i class='icon orange warning circle'></i>&nbsp;<b>" . $viewname . "</b> exists but is never loaded in action <b>" . $action . "</b>.", 'very wide');
			$result[] = $lbl;
		}
		return $result;
	}

	protected function addGetPostButtons(DataTable $dt) {
		$dt->addFieldButtons([
			"GET",
			"POST"
		], true, function (HtmlButtonGroups $bts, $instance, $index) {
			$path = $instance->getPath();
			$path = \str_replace("(.*?)", "", $path);
			$path = \str_replace("(index/)?", "", $path);
			$bts->setIdentifier("bts-" . $instance->getId() . "-" . $index);
			$bts->getItem(0)
				->addClass("_get")
				->setProperty("data-url", $path);
			$bts->getItem(1)
				->addClass("_post")
				->setProperty("data-url", $path);
			$item = $bts->addDropdown([
				"Post with parameters..."
			])
				->getItem(0);
			$item->addClass("_postWithParams")
				->setProperty("data-url", $path);
		});
	}

	public function getCacheDataTable($cacheFiles) {
		$dt = $this->jquery->semantic()->dataTable("dtCacheFiles", "Ubiquity\cache\CacheFile", $cacheFiles);
		$dt->setFields([
			"type",
			"name",
			"timestamp",
			"size"
		]);
		$dt->setCaptions([
			"Type",
			"Name",
			"Timestamp",
			"Size",
			""
		]);
		$dt->setValueFunction("type", function ($v, $instance, $index) {
			$item = $this->jquery->semantic()
				->htmlDropdown("dd-type-" . $v, $v);
			$item->addItems([
				"Delete all",
				"(Re-)Init cache"
			]);
			$item->setPropertyValues("data-ajax", $v);
			$item->getItem(0)
				->addClass("_delete-all");
			if ($instance->getFile() === "")
				$item->getItem(0)
					->setDisabled();
			$item->getItem(1)
				->addClass("_init");
			if ($instance->getType() !== "Models" && $instance->getType() !== "Controllers" && $instance->getType() !== "Contents")
				$item->getItem(1)
					->setDisabled();
			$item->asButton()
				->addIcon("folder", true, true);
			return $item;
		});
		$dt->addDeleteButton(true, [], function ($o, $instance) {
			if ($instance->getFile() == "")
				$o->setDisabled();
			$type = $instance->getType();
			$o->setProperty("data-type", $type);
			$type = \strtolower($type);
			if ($type == 'models' || $type == 'controllers') {
				$o->setProperty("data-key", $instance->getName());
			} else {
				$o->setProperty("data-key", $instance->getFile());
			}
		});
		$dt->setIdentifierFunction("getFile");
		$dt->setValueFunction("timestamp", function ($v) {
			if ($v !== "")
				return date(DATE_RFC2822, $v);
		});
		$dt->setValueFunction("size", function ($v) {
			if ($v !== "")
				return self::formatBytes($v);
		});
		$dt->setValueFunction("name", function ($name, $instance, $i) {
			if ($name != null) {
				$link = new HtmlLink("lnl-" . $i);
				$link->setContent($name);
				$link->addIcon("edit");
				$link->addClass("_lnk");
				$link->setProperty("data-type", $instance->getType());
				$link->setProperty("data-ajax", $instance->getFile());
				$link->setProperty("data-key", $instance->getName());
				return $link;
			}
		});
		$dt->onPreCompile(function ($dt) {
			$dt->getHtmlComponent()
				->mergeIdentiqualValues(0);
		});
		$this->jquery->postOnClick("._lnk", $this->controller->_getFiles()
			->getAdminBaseRoute() . "/_showFileContent", "{key:$(this).attr('data-key'),type:$(this).attr('data-type'),filename:$(this).attr('data-ajax')}", "#modal", [
			"hasLoader" => false
		]);
		$this->jquery->postFormOnClick("._delete", $this->controller->_getFiles()
			->getAdminBaseRoute() . "/_deleteCacheFile", "frmCache", "#dtCacheFiles tbody", [
			"jqueryDone" => "replaceWith",
			"params" => "{type:$(this).attr('data-type'),toDelete:$(this).attr('data-key')}"
		]);
		$this->jquery->postFormOnClick("._delete-all", $this->controller->_getFiles()
			->getAdminBaseRoute() . "/_deleteAllCacheFiles", "frmCache", "#dtCacheFiles tbody", [
			"jqueryDone" => "replaceWith",
			"params" => "{type:$(this).attr('data-ajax')}"
		]);
		$this->jquery->postFormOnClick("._init", $this->controller->_getFiles()
			->getAdminBaseRoute() . "/_initCacheType", "frmCache", "#dtCacheFiles tbody", [
			"jqueryDone" => "replaceWith",
			"params" => "{type:$(this).attr('data-ajax')}"
		]);
		return $dt;
	}

	public function getModelsStructureDataTable($datas, $name = "dtStructure") {
		$de = $this->jquery->semantic()->dataElement($name, $datas);
		$fields = \array_keys($datas);
		$de->setFields($fields);
		$de->setCaptions($fields);
		foreach ($fields as $key) {
			$de->setValueFunction($key, function ($value) {
				if ($value instanceof \stdClass) {
					$value = $this->parseArray($value);
				} elseif (is_array($value)) {
					$value = $this->parseInlineArray($value);
				}
				return $value;
			});
		}
		return $de;
	}

	protected function parseArray($value) {
		$values = (array) $value;
		$de = new DataElement("", $value);
		$fields = \array_keys($values);
		$de->setFields($fields);
		$de->setCaptions($fields);
		foreach ($fields as $key) {
			$de->setValueFunction($key, function ($value) {
				if ($value instanceof \stdClass) {
					$value = $this->parseInlineArray((array) $value);
				} elseif (is_array($value)) {
					$value = $this->parseInlineArray($value);
				} else {
					$value = var_export($value, true);
				}
				return new HtmlLabel("", $value);
			});
		}
		return $de;
	}

	protected function parseInlineArray($value) {
		$result = [];
		foreach ($value as $k => $v) {
			$prefix = "";
			if (! is_int($k)) {
				$prefix = $k . ": ";
			}
			if (is_array($v)) {
				$v = $this->parseInlineArray($v);
			} elseif ($v instanceof \stdClass) {
				$v = $this->parseInlineArray((array) $v);
			} else {
				$v = var_export($v, true);
				$v = '<span style="color: teal">' . $v . '</span>';
			}
			$result[] = '<b>' . $prefix . '</b>' . $v;
		}
		return '[' . implode(", ", $result) . ']';
	}

	public function getRestRoutesTab($datas) {
		$tabs = $this->jquery->semantic()->htmlTab("tabsRest");

		foreach ($datas as $controller => $restAttributes) {
			$doc = "";
			$list = new HtmlList("attributes", [
				[
					"heartbeat",
					"Controller",
					$controller
				],
				[
					"car",
					"Route",
					$restAttributes["restAttributes"]["route"]
				]
			]);
			$list->setHorizontal();
			if (\class_exists($controller)) {
				$parser = DocParser::docClassParser($controller);
				$desc = $parser->getDescriptionAsHtml();
				if (isset($desc)) {
					$doc = new HtmlMessage("msg-doc-controller-" . $controller, $desc);
					$doc->setIcon("help blue circle")
						->setDismissable()
						->addClass("transition hidden");
				}
			}
			$routes = Route::init($restAttributes["routes"]);
			$errors = [];
			foreach ($routes as $route) {
				$errors = \array_merge($errors, $route->getMessages());
			}
			$resource = $restAttributes["restAttributes"]["resource"];
			$title = $resource;
			if ($resource == null) {
				if (class_exists($controller)) {
					$title = call_user_func([
						$controller,
						'_getApiVersion'
					]);
				}
			}
			$tab = $tabs->addTab($title, [
				$doc,
				$list,
				$this->_getRestRoutesDataTable($routes, "dtRest", $resource, $restAttributes["restAttributes"]["authorizations"])
			]);
			if (\sizeof($errors) > 0) {
				$tab->menuTab->addLabel("error")
					->setColor("red")
					->addIcon("warning sign");
				$tab->addContent($this->controller->showSimpleMessage(\array_values($errors), "error", null, "warning"), true);
			}
			if ($doc !== "") {
				$tab->menuTab->addIcon("help circle blue")->onClick("$('#" . $doc->getIdentifier() . "').transition('horizontal flip');");
			}
		}
		return $tabs;
	}

	protected function _getRestRoutesDataTable($routes, $dtName, $resource, $authorizations) {
		$dt = $this->jquery->semantic()->dataTable($dtName, "Ubiquity\controllers\admin\popo\Route", $routes);
		$dt->setIdentifierFunction(function ($i, $instance) {
			return $instance->getPath();
		});
		$dt->setFields([
			"path",
			"methods",
			"action",
			"cache",
			"expired"
		]);
		$dt->setCaptions([
			"Path",
			"Methods",
			"Action & Parameters",
			"Cache",
			"Exp?",
			""
		]);
		$dt->fieldAsLabel("path", "car");
		$this->_dtCache($dt);
		$this->_dtMethods($dt);
		$dt->setValueFunction("action", function ($v, $instance) use ($authorizations) {
			$auth = "";
			if (\array_search($v, $authorizations) !== false) {
				$auth = new HtmlIcon("lock-" . $instance->getController() . $v, "lock alternate");
				$auth->addPopup("Authorization", "This route require a valid access token");
			}
			$result = [
				"<span style=\"color: #3B83C0;\">" . $v . "</span>" . $instance->getCompiledParams() . "<i class='ui icon help circle blue hidden transition _showMsgHelp' id='" . JString::cleanIdentifier("help-" . $instance->getAction() . $instance->getController()) . "' data-show='" . JString::cleanIdentifier("msg-help-" . $instance->getAction() . $instance->getController()) . "'></i>",
				$auth
			];
			return $result;
		});
		$this->_dtExpired($dt);
		$dt->addFieldButton("Test", true, function ($bt, $instance) use ($resource) {
			$bt->addClass("toggle _toTest basic circular")
				->setProperty("data-resource", ClassUtils::cleanClassname($resource));
			$bt->setProperty("data-action", $instance->getAction())
				->setProperty("data-controller", \urlencode($instance->getController()));
		});
		$dt->onPreCompile(function ($dTable) {
			$dTable->setColAlignment(5, TextAlignment::RIGHT);
			$dTable->setColAlignment(4, TextAlignment::CENTER);
		});
		$dt->setEdition()->addClass("compact");
		return $dt;
	}

	protected function _dtMethods(DataTable $dt) {
		$dt->setValueFunction("methods", function ($v) {
			$result = "";
			if (UString::isNotNull($v)) {
				if (! \is_array($v)) {
					$v = [
						$v
					];
				}
				$result = new HtmlLabelGroups("lbls-method", $v, [
					"color" => "grey"
				]);
			}
			return $result;
		});
	}

	protected function _dtCache(DataTable $dt) {
		$dt->setValueFunction("cache", function ($v, $instance) {
			$ck = new HtmlFormCheckbox("ck-" . $instance->getPath(), $instance->getDuration() . "");
			$ck->setChecked(UString::isBooleanTrue($v));
			$ck->setDisabled();
			return $ck;
		});
	}

	protected function _dtExpired(DataTable $dt) {
		$dt->setValueFunction("expired", function ($v, $instance, $index) {
			$icon = null;
			$expired = null;
			if ($instance->getCache()) {
				if (\sizeof($instance->getParameters()) === 0 || $instance->getParameters() === null)
					$expired = CacheManager::isExpired($instance->getPath(), $instance->getDuration());
				if ($expired === false) {
					$icon = "hourglass full";
				} elseif ($expired === true) {
					$icon = "hourglass empty orange";
				} else {
					$icon = "help";
				}
			}
			return new HtmlIcon("", $icon);
		});
	}

	protected function _dtAction(DataTable $dt) {
		$dt->setValueFunction("action", function ($v, $instance) {
			$result = "<span style=\"font-weight: bold;color: #3B83C0;\">" . $v . "</span>";
			$result .= $instance->getCompiledParams();
			if (! \method_exists($instance->getController(), $v)) {
				$errorLbl = new HtmlIcon("error-" . $v, "warning sign red");
				$errorLbl->addPopup("", "Missing method!");
				return [
					$result,
					$errorLbl
				];
			}
			return $result;
		});
	}

	public function getConfigDataElement($config) {
		$de = $this->jquery->semantic()->dataElement("deConfig", $config);
		$fields = \array_keys($config);
		$de->setFields($fields);
		$de->setCaptions($fields);
		$de->setValueFunction("database", function ($v, $instance, $index) use ($config) {
			$dbDe = new DataElement("", $v);
			if (isset($config['database']['dbName'])) {
				$dbDe->setFields([
					"wrapper",
					"type",
					"dbName",
					"serverName",
					"port",
					"user",
					"password",
					"options",
					"cache"
				]);
				$dbDe->setCaptions([
					"DbWrapper",
					"Type",
					"dbName",
					"serverName",
					"port",
					"user",
					"password",
					"options",
					"cache"
				]);
			} else {
				$cos = \array_keys($config['database']);
				$dbDe->setFields($cos);
				foreach ($cos as $co) {
					$dbDe->setValueFunction($co, function ($v, $instance) {
						return $this->_getDSNFromStdClass($v);
					});
				}
			}
			return $dbDe;
		});
		$de->setValueFunction("cache", function ($v, $instance, $index) {
			$dbDe = new DataElement("", $v);
			$dbDe->setFields([
				"directory",
				"system",
				"params"
			]);
			$dbDe->setCaptions([
				"directory",
				"system",
				"params"
			]);
			return $dbDe;
		});
		$de->setValueFunction("templateEngineOptions", function ($v, $instance, $index) {
			$teoDe = new DataElement("", $v);
			$teoDe->setFields([
				"cache"
			]);
			$teoDe->setCaptions([
				"cache"
			]);
			$teoDe->fieldAsCheckbox("cache", [
				"class" => "ui checkbox slider"
			]);
			return $teoDe;
		});
		$de->setValueFunction("mvcNS", function ($v, $instance, $index) {
			$mvcDe = new DataElement("", $v);
			$mvcDe->setFields([
				"models",
				"controllers",
				"rest"
			]);
			$mvcDe->setCaptions([
				"Models",
				"Controllers",
				"Rest"
			]);
			return $mvcDe;
		});
		if (isset($config["di"]) && sizeof($config["di"]) > 0) {
			$de->setValueFunction("di", function ($v, $instance, $index) use ($config) {
				$diDe = new DataElement("", $v);
				$keys = \array_keys($config["di"]);
				$diDe->setFields($keys);
				foreach ($keys as $key) {
					$diDe->setValueFunction($key, function ($value) use ($config, $key) {
						$r = $config['di'][$key];
						if (\is_callable($r))
							return \nl2br(\htmlentities(UIntrospection::closure_dump($r)));
						return $value;
					});
				}
				return $diDe;
			});
		}
		$de->setValueFunction("isRest", function ($v) use ($config) {
			$r = $config["isRest"];
			if (\is_callable($r))
				return \nl2br(\htmlentities(UIntrospection::closure_dump($r)));
			return $v;
		});
		$de->fieldAsCheckbox("test", [
			"class" => "ui checkbox slider"
		]);
		$de->fieldAsCheckbox("debug", [
			"class" => "ui checkbox slider"
		]);
		return $de;
	}

	private function getCaptionToggleButton($id, $caption, $active = "") {
		$bt = (new HtmlButton($id, $caption))->setToggle($active)->setTagName("a");
		$bt->addIcon("caret square down", false, true);
		return $bt;
	}

	private function labeledInput($input, $value) {
		$lbl = "[empty]";
		if (UString::isNotNull($value))
			$lbl = $value;
		$input->getField()->labeled($lbl);
		return $input;
	}

	private function _cleanStdClassValue($value) {
		if ($value instanceof \stdClass) {
			$value = (array) $value;
		}
		if (is_array($value)) {
			$value = UArray::asPhpArray($value, "array");
		}
		$value = str_replace('"', "'", $value);
		return $value;
	}

	private function _getDSNFromStdClass($v) {
		return $v->type . "://" . $v->user . ":" . $v->password . "@" . $v->serverName . ":" . $v->port . "/" . $v->dbName;
	}

	public function getDatabaseDataForm($v, $dbOffset = '') {
		$n = ($dbOffset != null) ? $dbOffset . '-' : '';
		$v->wrapper = $v->wrapper ?? \Ubiquity\db\providers\pdo\PDOWrapper::class;
		$drivers = Database::getAvailableDrivers($v->wrapper);
		$wrappers = Database::getAvailableWrappers();
		$dbDe = new DataElement("de-database" . $dbOffset, $v);
		$dbDe->setDefaultValueFunction(function ($name, $value) use ($n) {
			$value = $this->_cleanStdClassValue($value);
			$input = new HtmlFormInput("database-" . $n . $name, null, "text", $value);
			return $this->labeledInput($input, $value);
		});
		$dbDe->setFields([
			"wrapper",
			"type",
			"dbName",
			"serverName",
			"port",
			"user",
			"password",
			"options",
			"cache"
		]);
		$dbDe->setCaptions([
			"Provider",
			"Type",
			"dbName",
			"serverName",
			"port",
			"user",
			"password",
			"options",
			"cache"
		]);
		$dbDe->fieldAsInput("password", [
			"inputType" => "password",
			"name" => "database-" . $n . "password"
		]);
		$dbDe->fieldAsInput("port", [
			"name" => "database-" . $n . "port",
			"inputType" => "number",
			"jsCallback" => function ($elm) {
				$elm->getDataField()
					->setProperty("min", 0);
				$elm->getDataField()
					->setProperty("max", 3306);
			}
		]);
		$dbDe->fieldAsDropDown("wrapper", array_flip($wrappers), false, [
			"name" => "database-" . $n . "wrapper",
			"jsCallback" => function ($elm) use ($n) {
				$nn = ($n == '') ? '-' : $n;
				$elm->on('change', '$("#dropdown-de-database' . $nn . 'dd-type .menu").html(wrappers[$("[name=\'database-' . $n . 'wrapper\']").val()]);$("#dropdown-de-database' . $nn . 'dd-type").dropdown("refresh").dropdown("set selected", $("#dropdown-de-database' . $nn . 'dd-type .menu .item:first").attr("data-value")||"");');
			}
		]);
		$dbDe->fieldAsDropDown("type", \array_combine($drivers, $drivers), false, [
			"name" => "database-" . $n . "type"
		]);
		$dbDe->fieldAsInput("cache", [
			"name" => "database-" . $n . "cache",
			"jsCallback" => function ($elm, $object) use ($n) {
				$ck = $elm->labeledCheckbox();
				$ck->getField()
					->setIdentifier("ck-" . $n . "cache");
				$ck->on("click", '$("[name=database-' . $n . 'cache]").prop("disabled",$(this).checkbox("is unchecked"));');
				if ($object->cache !== false) {
					$ck->setChecked(true);
				}
			}
		]);
		$dbDe->setValueFunction("dbName", function ($value) use ($n, $dbOffset) {
			$input = new HtmlFormInput("database-" . $n . "dbName", null, "text", $value);
			$bt = $input->addAction("Test");
			$bt->addClass("black");
			$bt->postFormOnClick($this->controller->_getFiles()
				->getAdminBaseRoute() . "/_checkDbStatus/" . $dbOffset . "/", "frm-frmDeConfig", "#db-" . $n . "status", [
				"jqueryDone" => "replaceWith",
				"hasLoader" => "internal"
			]);
			return $this->labeledInput($input, '<i id="db-' . $n . 'status" class="ui question icon"></i>&nbsp;' . $value);
		});
		$dbDe->setEdition();
		return $dbDe;
	}

	private function getDatabaseForm($v, $instance, $index, $dbOffset = '') {
		$dbDe = $this->getDatabaseDataForm($v, $dbOffset);
		$dbDe->wrap('<div class="ui pointing below black label" style="display:none;">' . $dbOffset . '</div>');
		$dbDe->setStyle("display: none;");
		$caption = "<div class='toggle-caption'>" . $this->_getDSNFromStdClass($v) . "</div>";
		return [
			$dbDe,
			$caption
		];
	}

	public function getConfigDataForm($config, $origin = "all") {
		$de = $this->jquery->semantic()->dataElement("frmDeConfig", $config);
		$keys = array_keys($config);

		$de->setDefaultValueFunction(function ($name, $value) {
			if (is_array($value))
				$value = UArray::asPhpArray($value, "array");
			$input = new HtmlFormInput($name, null, "text", $value);
			return $this->labeledInput($input, $value);
		});
		$de->setFields($keys);
		$de->setCaptions($keys);
		$de->setCaptionCallback(function (&$captions, $instance) use ($keys) {
			$dbBt = $this->getCaptionToggleButton("database-bt", "Database...");
			$dbBt->on("toggled", 'if(!event.active) {
													var text=$("[name=database-type]").val()+"://"+$("[name=database-user]").val()+":"+$("[name=database-password]").val()+"@"+$("[name=database-serverName]").val()+":"+$("[name=database-port]").val()+"/"+$("[name=database-dbName]").val();
													event.caption.html(text);
													}');
			$captions[array_search("database", $keys)] = $dbBt;
			$captions[array_search("cache", $keys)] = $this->getCaptionToggleButton("cache-bt", "Cache...");
			$captions[array_search("mvcNS", $keys)] = $this->getCaptionToggleButton("ns-bt", "MVC namespaces...");
			$captions[array_search("di", $keys)] = $this->getCaptionToggleButton("di-bt", "Dependency injection", "active");
			$captions[array_search("isRest", $keys)] = $this->getCaptionToggleButton("isrest-bt", "Rest", "active");
		});
		$de->setValueFunction("database", function ($v, $instance, $index) use ($config) {
			if (isset($config['database']['dbName'])) {
				return $this->getDatabaseForm($v, $instance, $index);
			} else {
				$cos = \array_keys($config['database']);
				$res = [];
				foreach ($cos as $co) {
					$res[] = $this->getDatabaseForm($v->{$co}, $instance, $index, $co);
				}
				return $res;
			}
		});
		$de->setValueFunction("cache", function ($v, $instance, $index) {
			$dbDe = new DataElement("de-cache", $v);
			$dbDe->setDefaultValueFunction(function ($name, $value) {
				$value = $this->_cleanStdClassValue($value);
				$input = new HtmlFormInput("cache-" . $name, null, "text", $value);
				return $this->labeledInput($input, $value);
			});
			$dbDe->setFields([
				"directory",
				"system",
				"params"
			]);
			$dbDe->setCaptions([
				"directory",
				"system",
				"params"
			]);
			$dbDe->setStyle("display: none;");
			return $dbDe;
		});
		$de->setValueFunction("templateEngineOptions", function ($v, $instance, $index) {
			$teoDe = new DataElement("de-template-engine", $v);
			$teoDe->setFields([
				"cache"
			]);
			$teoDe->setCaptions([
				"cache"
			]);
			$teoDe->fieldAsCheckbox("cache", [
				"class" => "ui checkbox slider",
				"name" => "templateEngineOptions-cache"
			]);
			return $teoDe;
		});
		$de->setValueFunction("mvcNS", function ($v, $instance, $index) {
			$mvcDe = new DataElement("deMvcNS", $v);
			$mvcDe->setDefaultValueFunction(function ($name, $value) {
				return new HtmlFormInput("mvcNS-" . $name, null, "text", $value);
			});
			$mvcDe->setFields([
				"models",
				"controllers",
				"rest"
			]);
			$mvcDe->setCaptions([
				"Models",
				"Controllers",
				"Rest"
			]);
			$mvcDe->setStyle("display: none;");

			return $mvcDe;
		});
		if (isset($config["di"]) && sizeof($config["di"]) > 0) {
			$de->setValueFunction("di", function ($v, $instance, $index) use ($config) {
				$diDe = new DataElement("di", $v);
				$diDe->setDefaultValueFunction(function ($name, $value) {
					return new HtmlFormInput("di-" . $name, null, "text", $value);
				});
				$keys = \array_keys($config["di"]);
				$diDe->setFields($keys);
				foreach ($keys as $key) {
					$diDe->setValueFunction($key, function ($value) use ($config, $key) {
						$input = new HtmlFormTextarea("di-" . $key);
						$df = $input->getDataField();
						$df->setProperty("rows", "5");
						$df->setProperty("data-editor", "true");
						$r = $config['di'][$key];
						if (\is_callable($r)) {
							$value = \htmlentities(UIntrospection::closure_dump($r));
						} elseif (is_array($r)) {
							$value = UArray::asPhpArray($r, 'array');
						}
						$input->setValue($value);
						return $input;
					});
				}
				$diDe->onPreCompile(function () use (&$diDe) {
					$diDe->getHtmlComponent()
						->setColWidth(0, 1);
				});
				return $diDe;
			});
		}
		$de->setValueFunction("isRest", function ($v) use ($config) {
			$r = $config["isRest"];
			$input = new HtmlFormTextarea("isRest");
			$df = $input->getDataField();
			$df->setProperty("rows", "3");
			$df->setProperty("data-editor", "true");
			if (\is_callable($r)) {
				$value = \htmlentities(UIntrospection::closure_dump($r));
			}
			$input->setValue($value);
			return $input;
		});

		$de->setValueFunction("logger", function ($v) use ($config) {
			$r = $config["logger"];
			$input = new HtmlFormTextarea("logger");
			$df = $input->getDataField();
			$df->setProperty("rows", "3");
			$df->setProperty("data-editor", "true");
			if (\is_callable($r)) {
				$value = \htmlentities(UIntrospection::closure_dump($r));
			}
			$input->setValue($value);
			return $input;
		});
		$de->fieldAsCheckbox("test", [
			"class" => "ui checkbox slider"
		]);
		$de->fieldAsCheckbox("debug", [
			"class" => "ui checkbox slider"
		]);

		$this->insertAce();

		$form = $de->getForm();
		$form->setValidationParams([
			"inline" => true,
			"on" => "blur"
		]);
		$responseElement = "#action-response";
		if ($origin == "check") {
			$responseElement = "#main-content";
		}
		$de->addSubmitInToolbar("save-config-btn", "<i class='icon check circle'></i>Save configuration", "positive", $this->controller->_getFiles()
			->getAdminBaseRoute() . "/_submitConfig/" . $origin, $responseElement);
		$de->addButtonInToolbar("<i class='icon remove circle outline'></i>Cancel edition")->onClick('$("#config-div").show();$("#action-response").html("");');
		$de->getToolbar()
			->setSecondary()
			->wrap('<div class="ui top attached segment">', '</div>');
		$de->setAttached();

		$form->addExtraFieldRules("siteUrl", [
			"empty",
			"url"
		]);
		$form->addExtraFieldRule("siteUrl", "regExp", "siteUrl must ends with /", "/^.*?\/$/");
		$form->addExtraFieldRule("database-options", "regExp", "Expression must be an array", "/^array\(.*?\)$/");
		$form->addExtraFieldRule("database-options", "checkArray", "Expression is not a valid php array");
		$form->addExtraFieldRule("database-cache", "checkClass[Ubiquity\\cache\\database\\DbCache]", "Class {value} does not exists or is not a subclass of {ruleValue}");
		$form->setOptional("database-cache");

		$form->addExtraFieldRule("cache-directory", "checkDirectory[app]", "{value} directory does not exists");
		$form->addExtraFieldRule("templateEngine", "checkClass[Ubiquity\\views\\engine\\TemplateEngine]", "Class {value} does not exists or is not a subclass of {ruleValue}");
		$form->addExtraFieldRule("cache-system", "checkClass[Ubiquity\\cache\\system\\AbstractDataCache]", "Class {value} does not exists or is not a subclass of {ruleValue}");
		$form->addExtraFieldRule("cache-params", "checkArray", "Expression is not a valid php array");

		$form->addExtraFieldRule("mvcNS-models", "checkDirectory[app]", "{value} directory does not exists");
		$form->addExtraFieldRule("mvcNS-controllers", "checkDirectory[app]", "{value} directory does not exists");
		$controllersNS = \trim(Startup::getNS(), '\\');
		$form->addExtraFieldRule("mvcNS-rest", "checkDirectory[app/" . $controllersNS . "]", $controllersNS . "/{value} directory does not exists");

		$this->jquery->exec(Rule::ajax($this->jquery, "checkArray", $this->controller->_getFiles()
			->getAdminBaseRoute() . "/_checkArray", "{_value:value}", "result=data.result;", "post"), true);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkDirectory", $this->controller->_getFiles()
			->getAdminBaseRoute() . "/_checkDirectory", "{_value:value,_ruleValue:ruleValue}", "result=data.result;", "post"), true);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkClass", $this->controller->_getFiles()
			->getAdminBaseRoute() . "/_checkClass", "{_value:value,_ruleValue:ruleValue}", "result=data.result;", "post"), true);

		return $de->asForm();
	}

	public function insertAce() {
		$js = '
		$(function() {
		  $("textarea[data-editor]").each(function() {
		    var textarea = $(this);
		    var mode = textarea.data("editor");
		    var editDiv = $("<div>", {
		      position: "absolute",
		      width: "100%",
		      height: textarea.height(),
		      "class": textarea.attr("class")
		    }).insertBefore(textarea);
		    textarea.css("display", "none");
		    var editor = ace.edit(editDiv[0]);
		    editDiv.css("border-radius","4px");
		    editor.$blockScrolling = Infinity ;
		    editor.renderer.setShowGutter(textarea.data("gutter"));
		    editor.getSession().setValue(textarea.val());
		    editor.getSession().setMode({path:"ace/mode/php", inline:true});
		    editor.setTheme("ace/theme/solarized_dark");
		    $("textarea[data-editor]").closest("form").on("ajaxSubmit",function() {
		      textarea.val(editor.getSession().getValue());
		    });
		  });
		});
		';
		$this->jquery->exec($js, true);
	}

	public function getConfigMailerDataForm($config) {
		$fields = [
			'types' => [
				'password' => 'password',
				'port' => 'number'
			]
		];
		$de = $this->jquery->semantic()->dataElement("frmMailerConfig", $config);
		$keys = \array_keys($config);

		$de->setDefaultValueFunction(function ($name, $value) use ($fields) {
			if (\is_object($value) && $value != null)
				return $this->getArrayDataForm($name, \json_decode(\json_encode($value), true), $fields);
			if (UString::isBoolean($value)) {
				$input = new HtmlFormCheckbox($name, '', '', 'slider');
				$input->setChecked($value);
				$input->getField()
					->forceValue();
				return $input;
			}
			$input = new HtmlFormInput($name, null, $fields['types'][$name] ?? 'text', $value);
			return $this->labeledInput($input, $value);
		});
		$de->setFields($keys);
		$de->addField('_toDelete');
		$de->fieldAsDropDown('_toDelete', [], true, [
			'id' => 'toDelete',
			'jsCallback' => function ($elm) {
				$elm->getField()
					->setAllowAdditions(true)
					->setOnAdd("let self=$('[data-name='+addedValue+']');let table=self.closest('table tbody');self.closest('tr').hide();while(table && table.children(':visible').length==0){let next=table.closest('tr').closest('table tbody');table.closest('tr').hide();table=next;}")
					->setOnRemove("let self=$('[data-name='+removedValue+']');let tr=self.closest('tr');tr.show();tr.parents('tr').show();");
			}
		]);

		\array_walk($keys, function (&$item) {
			$item = $item . '<i title="Remove this key." class="close link red icon _see _delete" data-name="' . $item . '" style="visibility: hidden;"></i>';
		});
		$de->setCaptions($keys);
		$de->setCaption('_toDelete', '<div class="ui icon button"><i class="remove icon"></i> Cancel deletions</span>');
		$de->setLibraryId('_compo_');
		$de->setEdition(true);
		return $de;
	}

	private function getArrayDataForm($id, $array, $fields) {
		$dbDe = new DataElement('de-' . $id, $array);
		$keys = \array_keys($array);

		$dbDe->setDefaultValueFunction(function ($name, $value) use ($id, $fields) {
			if (\is_object($value) && $value != null) {
				return $this->getArrayDataForm($id . '-' . $name, \json_decode(\json_encode($value), true), $fields);
			}
			if (UString::isBoolean($value)) {
				$input = new HtmlFormCheckbox($id . '-' . $name, '', 'true', 'slider');
				$input->setChecked($value);
				$input->getField()
					->forceValue();
				return $input;
			}
			$input = new HtmlFormInput($id . '-' . $name, null, $fields['types'][$name] ?? 'text', $value);
			return $this->labeledInput($input, $value);
		});
		$dbDe->setFields($keys);
		\array_walk($keys, function (&$item) use ($id) {
			$item = $item . '<i title="Remove this key." class="close link red icon _see _delete" data-name="' . $id . '-' . $item . '" style="visibility: hidden;"></i>';
		});

		$dbDe->setCaptions($keys);
		return $dbDe;
	}

	private static function formatBytes($size, $precision = 2) {
		$base = log($size, 1024);
		$suffixes = array(
			'o',
			'Ko',
			'Mo',
			'Go',
			'To'
		);
		return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
	}

	public function getMainIndexItems($identifier, $array): HtmlItems {
		$items = $this->jquery->semantic()->htmlItems($identifier);

		$items->fromDatabaseObjects($array, function ($e) {
			$item = new HtmlItem("item-" . $e[0]);
			$item->addIcon($e[1] . " bordered circular")
				->setSize("big");
			$item->addItemHeaderContent($e[0], [], $e[2]);
			$item->setProperty("data-ajax", $e[0]);
			return $item;
		});
		$items->getOnClick($this->controller->_getFiles()
			->getAdminBaseRoute(), "#main-content", [
			"preventDefault" => false,
			"attr" => "data-ajax",
			"historize" => true,
			"jsCallback" => '$("#mainMenu [href]").removeClass("active");$("#mainMenu [data-ajax=\'"+$(self).attr("data-ajax")+"\']").addClass("active");'
		]);
		return $items->addClass("divided relaxed link");
	}

	public function getGitFilesDataTable($files) {
		$list = $this->jquery->semantic()->htmlList("dtGitFiles");
		$elements = array_map(function ($element) {
			return "<i class='" . GitFileStatus::getIcon($element->getStatus()) . " icon'></i>&nbsp;" . $element->getName();
		}, $files);
		$list->addCheckedList($elements, "<i class='file icon'></i>&nbsp;Files", array_keys($elements), false, "files-to-commit[]");
		$this->jquery->getOnClick("#dtGitFiles label[data-value]", $this->controller->_getFiles()
			->getAdminBaseRoute() . "/_gitChangesInfiles", "#changesInFiles-div", [
			"attr" => "data-value",
			"preventDefault" => false,
			"stopPropagation" => true
		]);
		return $list;
	}

	public function getGitCommitsDataTable($commits) {
		$notPushed = false;
		$dt = $this->jquery->semantic()->dataTable("dtCommits", "Ubiquity\utils\git\GitCommit", $commits);
		foreach ($commits as $commit) {
			if (! $commit->getPushed()) {
				$notPushed = true;
				break;
			}
		}
		$dt->setColor("green");
		$dt->setIdentifierFunction("getLHash");
		$dt->setFields([
			"cHash",
			"author",
			"cDate",
			"summary"
		]);
		$dt->setCaptions([
			"Hash",
			"Author",
			"Date",
			"Summary"
		]);
		$dt->setActiveRowSelector();
		$dt->onRowClick($this->jquery->getDeferred($this->controller->_getFiles()
			->getAdminBaseRoute() . "/_gitChangesInCommit", "#changesInCommit-div", [
			"attr" => "data-ajax"
		]));
		$dt->setValueFunction(0, function ($value, $instance) {
			if ($instance->getPushed()) {
				return "<i class='ui green check square icon'></i>" . $value;
			}
			return "<i class='ui external square alternate icon'></i>" . $value;
		});
		$dt->onNewRow(function ($row, $object) {
			if ($object->getPushed())
				$row->addClass("positive");
		});
		$this->jquery->exec('$("#htmlbuttongroups-push-pull-bts-0").prop("disabled",' . ($notPushed ? "false" : "true") . ');', true);
		return $dt;
	}

	public function gitFrmSettings(RepositoryGit $gitRepo) {
		$frm = $this->jquery->semantic()->dataForm("frmGitSettings", $gitRepo);
		$frm->setFields([
			"name\n",
			"name",
			"remoteUrl",
			"user",
			"password"
		]);
		$frm->setCaptions([
			"&nbsp;Git repository settings",
			"Repository name",
			"Remote URL",
			"User name",
			"password"
		]);
		$frm->fieldAsMessage(0, [
			"icon" => HtmlIconGroups::corner("git", "settings")
		]);
		$frm->setSubmitParams($this->controller->_getFiles()
			->getAdminBaseRoute() . "/_updateGitParams", "#main-content");
		$frm->fieldAsInput(1);
		$frm->fieldAsInput(3, [
			"rules" => [
				"empty"
			]
		]);
		$frm->fieldAsInput(4, [
			"inputType" => "password"
		]);
		$frm->addDividerBefore("user", "gitHub");
		return $frm;
	}

	public function getLogsDataTable($maxLines = null, $reverse = true, $groupBy = [
		1,
		2
	], $contexts = null) {
		$os = Logger::asObjects($reverse, $maxLines, $contexts);
		$dt = $this->jquery->semantic()->dataTable("dt-logs", LogMessage::class, $os);
		$gbSize = 0;
		if (is_array($groupBy)) {
			$gbSize = sizeof($groupBy);
		}
		$dt->setFields([
			"level",
			"datetime",
			"context",
			"part",
			"message",
			"extra"
		]);
		$dt->setCaptions([
			"Level",
			"When?",
			"Context",
			"Part",
			"Message",
			"Extra"
		]);
		$dt->setValueFunction(1, function ($value, $instance) {
			$lbl = new HtmlLabel(uniqid("datetime-"), UDateTime::elapsed($value), "clock");
			$lbl->addPopup("", UDateTime::longDatetime($value, "fr"));
			return $lbl;
		});
		$dt->setValueFunction(0, function ($value, $instance) {
			return new HtmlIcon("", HtmlLogFormatter::getIcon($instance));
		});
		$dt->setValueFunction(3, function ($value, $instance) {
			if (($count = $instance->getCount()) > 1) {
				$lbl = new HtmlLabel(uniqid("count-"), "x" . $count);
				$lbl->addClass("circular");
				return $value . "&nbsp;" . $lbl;
			} else {
				return $value;
			}
		});

		$dt->setValueFunction(5, function ($value, $instance) {
			if (isset($value)) {
				$lbl = new HtmlLabel(uniqid("count-"), sizeof($value), "database");
				$lbl->addClass("circular");
				$lbls = new HtmlLabelGroups("", $value, [
					"circular"
				]);
				$lbl->addPopupHtml("<h4>Datas</h4>" . $lbls, null, [
					"on" => "click"
				]);
				return $lbl;
			}
		});

		$dt->onNewRow(function ($row, $instance) {
			$row->addClass(HtmlLogFormatter::getFormat($instance));
		});
		$dt->setHasCheckboxes(true);
		$dt->onPreCompile(function () use (&$dt, $gbSize) {
			$body = $dt->getHtmlComponent()
				->getBody();
			$body->addPropertyCol(6 - $gbSize, "style", "max-width: 300px;word-break:break-all;");
			$body->addPropertyCol(5 - $gbSize, "style", "max-width: 500px;word-break:break-all;");
		});
		if (is_array($groupBy)) {
			$dt->setGroupByFields($groupBy);
		}
		$dt->setCompact(true)->setSelectable();
		return $dt;
	}

	public function displayViolations($instancesViolations) {
		if (sizeof($instancesViolations) == 0) {
			echo $this->controller->showSimpleMessage("No violations!", "success", 'Instances validation', 'check');
		} else {
			$nb = sizeof($instancesViolations);
			echo $this->controller->showSimpleMessage($nb . " instance(s) with violations!", "warning", 'Instances validation', 'exclamation triangle');
			$dt = new DataTable("dtInstancesViolations", InstanceViolations::class, InstanceViolations::initFromArray($instancesViolations));
			$dt->setFields([
				'instance',
				'violations'
			]);
			$dt->fieldAsLabel('instance');
			$dt->setValueFunction('violations', function ($violations) {
				$result = [];
				$header = "";
				foreach ($violations as $violation) {
					$msg = new HtmlMessage("");
					$severity = $violation->getSeverity();
					$msg->addClass("tiny " . ConstraintViolationViewer::getType($severity));
					$msg->addHeader($violation->getMember());
					$msg->setIcon(ConstraintViolationViewer::getIcon($severity));
					$message = str_replace($violation->getValue(), '<span style="color:teal">' . $violation->getValue() . '</span>', $violation->getMessage());
					$msg->addList([
						'<b>' . ClassUtils::getClassSimpleName($violation->getValidatorType()) . '</b> : ' . $message
					]);
					$result[] = $msg;
				}
				return $result;
			});
			echo $dt;
		}
	}
}
