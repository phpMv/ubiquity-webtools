<?php
namespace Ubiquity\controllers\admin\traits;

use Ajax\semantic\components\validation\Rule;
use Ajax\semantic\html\collections\HtmlMessage;
use Ubiquity\cache\ClassUtils;
use Ubiquity\controllers\admin\popo\MailerClass;
use Ubiquity\controllers\admin\popo\MailerQueuedClass;
use Ubiquity\mailer\MailerManager;
use Ubiquity\utils\base\UArray;
use Ubiquity\utils\base\UDateTime;
use Ubiquity\utils\http\URequest;
use Ubiquity\utils\http\UResponse;

/**
 * Ubiquity\controllers\admin\traits$MailerTrait
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.0
 * @property \Ajax\php\ubiquity\JsUtils $jquery
 *
 */
trait MailerTrait {

	abstract public function _getAdminData();

	abstract public function _getAdminViewer();

	abstract public function _getFiles();

	abstract public function loadView($viewName, $pData = NULL, $asString = false);

	abstract protected function showConfMessage($content, $type, $title, $icon, $url, $responseElement, $data, $attributes = NULL): HtmlMessage;

	abstract public function showSimpleMessage($content, $type, $title = null, $icon = "info", $timeout = NULL, $staticName = null, $closeAction = null, $toast = false): HtmlMessage;

	protected $defaultMailerConfigs = [
		'Google' => [
			'host' => 'smtp.gmail.com',
			'port' => 587,
			'auth' => true,
			'user' => '@gmail.com',
			'password' => '',
			'protocol' => 'smtp',
			'_params' => [
				'force' => [
					'host',
					'port',
					'auth',
					'protocol'
				],
				'icon' => 'google'
			]
		],
		'SmtpOptions (unsecure!)' => [
			'SMTPOptions' => [
				'ssl' => [
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true
				]
			],
			'_params' => [
				'force' => [
					'ssl'
				],
				'icon' => 'exclamation triangle'
			]
		],
		'SmtpOptions (secure)' => [
			'SMTPOptions' => [
				'ssl' => [
					'verify_peer' => true,
					'verify_depth' => 3,
					'allow_self_signed' => true,
					'peer_name' => 'smtp.example.com',
					'cafile' => '/etc/ssl/ca_cert.pem'
				]
			],
			'_params' => [
				'force' => [
					'ssl'
				],
				'icon' => 'check square'
			]
		]
	];

	protected function mixDefaultMailerConfig($key, $originalConfig) {
		if (isset($this->defaultMailerConfigs[$key])) {
			$config = $this->defaultMailerConfigs[$key];
			$force = $config['_params']['force'] ?? [];
			unset($config['_params']);
			$result = $originalConfig;
			foreach ($config as $k => $v) {
				if ((isset($result[$k]) && \array_search($k, $force) !== false) || ! isset($result[$k])) {
					$result[$k] = $v;
				}
			}
			return $result;
		}
	}

	protected function getDefaultMailerConfigIcons() {
		$result = [];
		foreach ($this->defaultMailerConfigs as $v) {
			$result[] = $v['_params']['icon'] ?? '';
		}
		return $result;
	}

	public function _seeMail($class) {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$mailerClass = MailerClass::initOne($class);
		$encodedClass = \urlencode($class);
		$this->_getAdminViewer()->getSeeMailDataElement($mailerClass);
		$disabled = ! $mailerClass->getHasRecipients();
		$jsClose = '$("#see-mail").html("");$("#mailer-all").show();';

		$this->jquery->click('#closeViewerBtn', $jsClose);

		$this->jquery->getOnClick('._add_to_queue', $baseRoute . '/_addToQueue/' . $encodedClass, '#dtQueue', [
			'hasLoader' => 'internal',
			'jqueryDone' => 'replaceWith',
			'jsCallback' => $jsClose
		]);

		$this->jquery->getOnClick('._send_now', $baseRoute . '/_sendMailNow/' . $encodedClass, '#dtQueue', [
			'hasLoader' => 'internal',
			'jqueryDone' => 'replaceWith',
			'jsCallback' => $jsClose
		]);

		$this->jquery->getOnClick('#edit-mail-btn', $baseRoute . '/_editMail/' . $encodedClass, '#see-mail', [
			'hasLoader' => 'internal'
		]);

		$this->jquery->renderView($this->_getFiles()
			->getViewSeeMail(), [
			'disabled' => $disabled ? 'disabled' : '',
			'class' => $mailerClass,
			'queue' => true
		]);
	}

	public function _editMail($class) {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$mailerClass = MailerClass::initOne($class);
		$this->_getAdminViewer()->getSeeMailDataElementForm($mailerClass);
		$this->jquery->execAtLast("$('#body').change(function(){\$('#item-tab-body-2').html($(this).val());});");
		$jsClose = '$("#see-mail").html("");$("#mailer-all").show();';
		$this->jquery->click('#closeViewerBtn', $jsClose);
		$this->jquery->postFormOnClick("#send-mail-btn", $baseRoute . '/_sendEditMail/' . \urlencode($class), "frm-seeMailForm", "#dtDequeue", [
			'hasLoader' => 'internal',
			'jsCallback' => $jsClose
		]);
		$this->jquery->renderView($this->_getFiles()
			->getViewSeeMailForm());
	}

	public function _editSentMail($index) {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$mailInstance = $this->getSentMailByIndex($index);
		$this->_getAdminViewer()->getSeeMailDataElementForm($mailInstance);
		$this->jquery->execAtLast("$('#body').change(function(){\$('#item-tab-body-2').html($(this).val());});");
		$jsClose = '$("#see-mail").html("");$("#mailer-all").show();';
		$this->jquery->click('#closeViewerBtn', $jsClose);
		$this->jquery->postFormOnClick("#send-mail-btn", $baseRoute . '/_sendEditMail/' . \urlencode($mailInstance->getName()), "frm-seeMailForm", "#dtDequeue", [
			'hasLoader' => 'internal',
			'jsCallback' => $jsClose
		]);
		$this->jquery->renderView($this->_getFiles()
			->getViewSeeMailForm());
	}

	public function _sendEditMail($class) {
		MailerManager::start();
		$values = $_POST;
		$values['to'] = $this->getMailAddressesFromPost(($_POST['to']));
		$values['cc'] = $this->getMailAddressesFromPost(($_POST['cc']));
		$values['bcc'] = $this->getMailAddressesFromPost(($_POST['bcc']));
		$values['from'] = [
			$this->getMailAddress(\html_entity_decode($_POST['from']))
		];
		$values['attachments'] = $this->getAttachmentsFromPost($_POST['attachments']);
		$values['class'] = $class . '[*]';
		if (MailerManager::sendArray($values)) {
			MailerManager::saveQueue();
			$this->toast('Email sent with success!', 'Send updated mail', 'success', 'mail');
		} else {
			$this->toast(MailerManager::getErrorInfo(), 'Send updated mail', 'error', 'mail');
		}
		$this->_refreshDequeue();
	}

	private function getMailAddressesFromPost(string $strAddresses): ?array {
		if ($strAddresses != null) {
			$addresses = \explode(',', $strAddresses);
			$ret = [];
			foreach ($addresses as $strAddress) {
				$ret[] = $this->getMailAddress($strAddress);
			}
			return $ret;
		}
		return null;
	}

	private function getAttachmentsFromPost(string $strAttachments): array {
		$attachments = \explode(',', $strAttachments);
		$ret = [];
		foreach ($attachments as $att) {
			$ret[] = [
				'file' => $att
			];
		}
		return $ret;
	}

	private function getMailAddress(string $strAddress): array {
		$ret = [];
		$strAddress = \preg_replace('#\((.*?)\)#', '<$1>', $strAddress);
		if (($start = \strpos($strAddress, '<')) !== false) {
			if (($end = \strpos($strAddress, '>', $start + 1)) !== false) {
				$length = $end - $start;
				$ret['name'] = \substr($strAddress, $start + 1, $length - 1);
				$strAddress = \substr($strAddress, $end + 1);
			}
		}
		$ret['address'] = $strAddress;
		return $ret;
	}

	private function getSentMailByIndex($index): MailerClass {
		MailerManager::start();
		$mailInfos = MailerManager::allInDequeue()[$index];
		$class = $mailInfos['class'];
		$class = preg_replace('/\[[\s\S]+?\]/', '', $class);
		$original = MailerClass::initOne($class);
		$mailInstance = MailerClass::initOneFromInfos($class, $mailInfos);
		$mailInstance->original = $original;
		return $mailInstance;
	}

	public function _seeSentMail($index) {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$mailInstance = $this->getSentMailByIndex(-- $index);
		$this->_getAdminViewer()->getSeeMailDataElement($mailInstance);
		$disabled = ! $mailInstance->getHasRecipients();
		$jsClose = '$("#see-mail").html("");$("#mailer-all").show();';

		$this->jquery->click('#closeViewerBtn', $jsClose);
		$this->jquery->getOnClick('#re-send-btn', $baseRoute . '/_sendMailAgain/' . $index, '#dtDequeue', [
			'hasLoader' => 'internal',
			'jsCallback' => $jsClose
		]);

		$this->jquery->getOnClick('#edit-mail-btn', $baseRoute . '/_editSentMail/' . $index, '#see-mail', [
			'hasLoader' => 'internal'
		]);

		$this->jquery->renderView($this->_getFiles()
			->getViewSeeMail(), [
			'disabled' => $disabled ? 'disabled' : '',
			'class' => $mailInstance,
			'queue' => false,
			'sentAgo' => UDateTime::elapsed($mailInstance->getSentAt()),
			'sentDate' => $mailInstance->getSentAt()
				->format('c')
		]);
	}

	public function _sendMailNow($class) {
		MailerManager::start();
		if (MailerManager::addToQueue($class)) {
			MailerManager::saveQueue();
			$this->jquery->semantic()->toast('body', [
				'message' => "$class added to queue",
				'showIcon' => 'mail',
				'title' => 'Queue'
			]);
			$this->_refreshQueue();
			return;
		}
		UResponse::setResponseCode(404);
	}

	public function _addToQueue($class) {
		$qp = $this->config['mailer']['queue-period'] ?? 'now';
		MailerManager::start();
		$result = true;
		if ($qp == 'now') {
			$result = MailerManager::addToQueue($class);
		} elseif ($qp instanceof \DateTime) {
			MailerManager::sendAt($class, $qp);
		} else {
			MailerManager::sendBetween($class, $qp[0], $qp[1]);
		}
		if ($result) {
			MailerManager::saveQueue();
			$this->jquery->semantic()->toast('body', [
				'message' => "$class added to queue",
				'showIcon' => 'mail',
				'title' => 'Queue'
			]);
			$this->_refreshQueue();
			return;
		}
		UResponse::setResponseCode(404);
	}

	public function _refreshMailer() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$dt = $this->_getAdminViewer()->getMailerDataTable(MailerClass::init());
		$dt->setLibraryId("_compo_");
		$this->addMailerBehavior($baseRoute);
		$this->jquery->renderView("@framework/main/component.html");
	}

	public function _refreshQueue($withMailer = true, $withDec = false) {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$queue = MailerQueuedClass::initQueue();
		$this->activateQueueMenu($queue);
		$dt = $this->_getAdminViewer()->getMailerQueueDataTable($queue);
		$dt->setLibraryId("_compo_");
		$this->addQueueBehavior($baseRoute);
		if ($withMailer) {
			$this->jquery->get($baseRoute . '/_refreshMailer', '#dtMailer', [
				'hasLoader' => false,
				'jqueryDone' => 'replaceWith'
			]);
		}
		if ($withDec) {
			$this->jquery->get($baseRoute . '/_refreshDequeue', '#dtDequeue', [
				'hasLoader' => false,
				'jqueryDone' => 'replaceWith'
			]);
		}
		$this->jquery->renderView("@framework/main/component.html");
	}

	public function _refreshDequeue() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$dt = $this->_getAdminViewer()->getMailerDequeueDataTable(MailerQueuedClass::initQueue(true));
		$dt->setLibraryId("_compo_");
		$this->addDequeueBehavior($baseRoute);
		$this->jquery->renderView("@framework/main/component.html");
	}

	private function getQueuePeriodeValues($qp) {
		if ($qp == 'now') {
			$choice = 1;
			$d1 = $d2 = $d3 = (new \DateTime())->format('Y-m-d\TH:i:s');
		} elseif ($qp instanceof \DateTime) {
			$choice = 2;
			$d1 = $qp->format('Y-m-d\TH:i:s');
			$d2 = $d3 = (new \DateTime())->format('Y-m-d\TH:i:s');
		} else {
			$choice = 3;
			$d1 = (new \DateTime())->format('Y-m-d\TH:i:s');
			$d2 = $qp[0]->format('Y-m-d\TH:i:s');
			$d3 = $qp[1]->format('Y-m-d\TH:i:s');
		}
		return \compact('choice', 'd1', 'd2', 'd3');
	}

	public function _definePeriodFrm() {
		$qp = $this->config['mailer']['queue-period'] ?? 'now';
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$this->jquery->click("input[type=radio]", "$('td').removeClass('left marked green');$(this).closest('td').addClass('left marked green').next().addClass('green');", false, false, true);
		$frm = $this->jquery->semantic()->htmlForm('define-period-frm');
		$this->jquery->exec(Rule::custom('dateCompare', "function(value){if($('input[name=choice]:checked'). val()==3){return new Date(value)>=new Date($('#d-between').val());}return true;}"), true);
		$frm->addExtraFieldRule('d-and', 'dateCompare', 'The and date must be greater than between date');
		$frm->setValidationParams([
			"on" => "blur",
			"inline" => false
		]);
		$frm->setSubmitParams($baseRoute . '/_definePeriod', '#frm', [
			'hasLoader' => 'internal'
		]);
		$this->jquery->click('#validate-btn', '$("#define-period-frm").form("submit");', false);
		$this->jquery->click('#cancel-btn', '$("#frm").html("");', false);
		$this->jquery->click('td', '$(this).closest("tr").find("input[type=radio]")[0].click();', false, false, true);
		$this->jquery->renderView($this->_getFiles()
			->getViewMailerDefinePeriod(), $this->getQueuePeriodeValues($qp));
	}

	private function queuePeriodToString($qp) {
		if (\is_string($qp)) {
			return $qp;
		}
		if (\is_array($qp)) {
			return $this->queuePeriodToString($qp[0]) . '->' . $this->queuePeriodToString($qp[1]);
		}
		if ($qp instanceof \DateTime) {
			return $qp->format("d/m/Y H:i");
		}
	}

	public function _definePeriod() {
		$choice = URequest::post('choice');
		switch ($choice) {
			case "1":
				$r = 'now';
				break;
			case "2":
				$r = \DateTime::createFromFormat('Y-m-d\TH:i:s', URequest::post('d-at'));
				break;
			case "3":
				$d1 = \DateTime::createFromFormat('Y-m-d\TH:i:s', URequest::post('d-between'));
				$d2 = \DateTime::createFromFormat('Y-m-d\TH:i:s', URequest::post('d-and'));
				$r = [
					$d1,
					$d2
				];
				break;
		}
		$this->jquery->execAtLast('$("#queue-period").html("' . $this->queuePeriodToString($r) . '");');
		$this->config['mailer']['queue-period'] = $r;
		$this->saveConfig();
		echo $this->jquery->compile();
	}

	private function activateQueueMenu($queue) {
		if (\sizeof($queue) > 0) {
			$this->jquery->doJQuery('._queue, ._queue input', 'removeClass', 'disabled');
		} else {
			$this->jquery->doJQuery('._queue, ._queue input', 'addClass', 'disabled');
		}
	}

	private function addMailerBehavior($baseRoute) {
		$this->jquery->getOnClick('._add_to_queue', $baseRoute . '/_addToQueue', '#dtQueue', [
			'hasLoader' => 'internal',
			'attr' => 'data-class',
			'jqueryDone' => 'replaceWith'
		]);
		$this->jquery->getOnClick('._send_now', $baseRoute . '/_sendMailNow', '#dtQueue', [
			'hasLoader' => 'internal',
			'attr' => 'data-class',
			'jqueryDone' => 'replaceWith'
		]);

		$this->jquery->getOnClick('._see', $baseRoute . '/_seeMail', '#see-mail', [
			'hasLoader' => 'internal',
			'attr' => 'data-class',
			'jsCallback' => '$("#mailer-all").hide();'
		]);
	}

	private function addQueueBehavior($baseRoute, $all = false) {
		if ($all) {
			$this->jquery->getOnClick('#delete-queue-btn', $baseRoute . '/_removeAllMessages', '#dtQueue', [
				'hasLoader' => 'internal',
				'jqueryDone' => 'replaceWith'
			]);
			$this->jquery->getOnClick('#send-queue-btn', $baseRoute . '/_sendQueue', '#dtQueue', [
				'hasLoader' => 'internal',
				'jqueryDone' => 'replaceWith'
			]);
		}
		$this->jquery->getOnClick('._remove_from_queue', $baseRoute . '/_remove_from_queue', '#dtQueue', [
			'hasLoader' => 'internal',
			'attr' => 'data-index',
			'jqueryDone' => 'replaceWith'
		]);
		$this->jquery->getOnClick('._send', $baseRoute . '/_sendMailQueue', '#dtQueue', [
			'hasLoader' => 'internal',
			'attr' => 'data-index',
			'jqueryDone' => 'replaceWith'
		]);

		$this->jquery->click('#auto-refresh', "if($(this).prop('checked')){" . $this->jquery->ajaxInterval('post', $baseRoute . '/_sendQueue', "$('#interval').val()*1000", 'refresh', '#dtQueue', [
			'hasLoader' => false,
			'jqueryDone' => 'replaceWith',
			'jsCondition' => "!$('._queue input').hasClass('disabled')"
		], false) . "}else{" . $this->jquery->clearInterval('refresh', false) . "}", false);
	}

	private function addDequeueBehavior($baseRoute) {
		$this->jquery->getOnClick('._remove_from_dequeue', $baseRoute . '/_remove_from_dequeue', '#dtDequeue', [
			'hasLoader' => 'internal',
			'attr' => 'data-index',
			'jqueryDone' => 'replaceWith'
		]);
		$this->jquery->getOnClick('._see_dequeue', $baseRoute . '/_seeSentMail', '#see-mail', [
			'hasLoader' => 'internal',
			'attr' => 'data-index',
			'jsCallback' => '$("#mailer-all").hide();'
		]);
	}

	public function _removeAllMessages() {
		MailerManager::start();
		MailerManager::clearAllMessages();
		MailerManager::saveQueue();
		$this->_refreshQueue(true);
	}

	public function _remove_from_queue($index) {
		MailerManager::start();
		if (MailerManager::removeFromQueue(-- $index)) {
			MailerManager::saveQueue();
			$this->toast('Email removed from queue!', 'Queue', 'info', 'close');
		}
		$this->_refreshQueue(true);
	}

	public function _remove_from_dequeue($index) {
		MailerManager::start();
		if (MailerManager::removeFromDequeue(-- $index)) {
			MailerManager::saveQueue(false, true);
			$this->toast('Email removed from dequeue!', 'Dequeue', 'info', 'close');
		}
		$this->_refreshDequeue();
	}

	public function _sendQueue() {
		MailerManager::start();
		$count = MailerManager::sendQueue();
		if ($count > 0) {
			MailerManager::saveQueue();
			$this->toast($count . ' email(s) sent with success!', 'Send mails from Queue', 'success', 'mail');
			$this->_refreshQueue(true, true);
		} else {
			$this->toast('No mail sent!', 'Send mails from Queue', 'info', 'mail');
			$this->_refreshQueue(false);
		}
	}

	public function _sendMailQueue($index) {
		MailerManager::start();
		if (MailerManager::sendQueuedMail(-- $index)) {
			MailerManager::saveQueue();
			$this->toast('Email sent with success!', 'Send mail from Queue', 'success', 'mail');
			$this->_refreshQueue(true, true);
		} else {
			$this->toast(MailerManager::getErrorInfo(), 'Send mail from Queue', 'error', 'mail');
			$this->_refreshQueue(false);
		}
	}

	public function _sendMailAgain($index) {
		MailerManager::start();
		if (MailerManager::sendAgain($index)) {
			MailerManager::saveQueue();
			$this->toast('Email sent with success!', 'Re-send mail from DeQueue', 'success', 'mail');
		} else {
			$this->toast(MailerManager::getErrorInfo(), 'Re-send mail from DeQueue', 'error', 'mail');
		}
		$this->_refreshDequeue();
	}

	public function _addNewMailerFrm() {
		$mailerClasses = MailerManager::getMailClasses(true);
		$mailerClasses[] = "Ubiquity\\mailer\\AbstractMail";
		$mailerClasses = array_combine($mailerClasses, $mailerClasses);
		$ctrlList = $this->jquery->semantic()->htmlDropdown("mailer-list", "Ubiquity\\mailer\\AbstractMail", $mailerClasses);
		$ctrlList->asSelect("mailerClass");
		$ctrlList->setDefaultText("Select mailer class");

		$frm = $this->jquery->semantic()->htmlForm("new-mailer-frm");
		$frm->addExtraFieldRules("mailer-name", [
			"empty",
			[
				"checkMailer",
				"Mailer {value} already exists!"
			]
		]);
		$this->jquery->exec(Rule::ajax($this->jquery, "checkMailer", $this->_getFiles()
			->getAdminBaseRoute() . "/_mailerExists/mailer-name", "{}", "result=data.result;", "postForm", [
			"form" => "new-mailer-frm"
		]), true);

		$frm->setValidationParams([
			"on" => "blur",
			"inline" => true
		]);
		$frm->setSubmitParams($this->_getFiles()
			->getAdminBaseRoute() . "/_addNewMailer", "#frm");

		$this->jquery->click("#validate-btn", '$("#new-mailer-frm").form("submit");');
		$this->jquery->execOn("click", "#cancel-btn", '$("#frm").html("");');

		$this->jquery->renderView($this->_getFiles()
			->getViewNewMailerFrm(), [
			"mailerNS" => MailerManager::getNamespace()
		]);
	}

	public function _mailerExists($fieldname) {
		if (URequest::isPost()) {
			$result = [];
			header('Content-type: application/json');
			$mailer = ucfirst($_POST[$fieldname]);
			$mailerNS = MailerManager::getNamespace();
			$result["result"] = ! \class_exists($mailerNS . '\\' . $mailer);
			echo json_encode($result);
		}
	}

	public function _addNewMailer() {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$mailerName = URequest::post('mailer-name');
		$classname = ClassUtils::getClassSimpleName($mailerName);
		$ns = MailerManager::getNamespace() . ClassUtils::getNamespaceFromCompleteClassname($mailerName);
		$msg = $this->scaffold->_createClass('mailer.tpl', $classname, $ns, 'use Ubiquity\\mailer\\MailerManager;', '\\' . URequest::post('mailerClass'), '');
		if (URequest::post('ck-add-view') == 'on') {
			$vName = $this->scaffold->_createViewOp('mailer', $classname);
			$msg->addContent("<br>Associated view created: <b>$vName</b>.");
		}
		$this->jquery->get($baseRoute . '/_refreshMailer', '#dtMailer', [
			'hasLoader' => false,
			'jqueryDone' => 'replaceWith'
		]);
		echo $msg;
		echo $this->jquery->compile();
	}

	public function _mailerConfigFrm() {
		$this->_getMailerConfigFrm(MailerManager::loadConfig());
	}

	private function _getMailerConfigFrm($config) {
		$baseRoute = $this->_getFiles()->getAdminBaseRoute();
		$this->getMailerConfigFrmDataForm($config);
		$configs = \array_keys($this->defaultMailerConfigs);
		$dd = $this->jquery->semantic()->htmlDropdown('btDefaultConfig', 'Add default config', \array_combine($configs, $configs));
		$dd->asButton(true)->setColor('olive');
		$dd->addIcons($this->getDefaultMailerConfigIcons());
		$this->jquery->postFormOnClick('#btDefaultConfig a.item', $baseRoute . '/_applyConfig', 'frmConfig', '#frmMailerConfig', [
			'hasLoader' => false,
			'attr' => 'data-value',
			'jqueryDone' => 'replaceWith'
		]);

		$this->jquery->postFormOnClick("#save-config-btn", $baseRoute . '/submitMailerConfig', 'frmConfig', '#frm', [
			'jsCallback' => '$("#mailer-details").show();'
		]);
		$this->jquery->execOn("click", "#bt-Canceledition", '$("#frm").html("");$("#mailer-details").show();$("._menu").removeClass("disabled");');

		$this->jquery->execAtLast("$('._tabConfig .item').tab();");
		$this->jquery->execAtLast("$('._tabConfig .item').tab({'onVisible':function(value){
			if(value=='source'){
			" . $this->jquery->postFormDeferred($baseRoute . '/_getMailerConfigSource', 'frmConfig', '#tab-source', [
			'hasLoader' => false
		]) . "}else{
			" . $this->jquery->postFormDeferred($baseRoute . '/_refreshConfigFrm', 'frm-source', '#frmMailerConfig', [
			'hasLoader' => false,
			'jqueryDone' => 'replaceWith'
		]) . "
		}
		}});");
		$this->jquery->renderView($this->_getFiles()
			->getViewMailerConfig());
	}

	private function getMailerConfigFrmDataForm($config) {
		$df = $this->_getAdminViewer()->getConfigMailerDataForm($config);
		$this->addConfigBehavior();
		return $df;
	}

	private function addConfigBehavior() {
		$this->jquery->mouseleave('td', '$(this).find("i._see").css({"visibility":"hidden"});');
		$this->jquery->mouseenter('td', '$(this).find("i._see").css({"visibility":"visible"});');
		$this->jquery->click('._delete', 'let tDf=$("[name=_toDelete]");tDf.closest(".ui.dropdown").dropdown("set selected",$(this).attr("data-name"));');
	}

	private function arrayUpdateRecursive(&$original, &$update, &$toRemove, $key = '', $remove = false) {
		foreach ($original as $k => $v) {
			$nKey = ($key == null) ? $k : ($key . '-' . $k);
			if (\array_key_exists($k, $update)) {
				if (\is_array($update[$k]) && \is_array($v)) {
					$this->arrayUpdateRecursive($original[$k], $update[$k], $toRemove, $nKey, $remove);
				} else {
					if (\array_search($nKey, $toRemove) === false) {
						$original[$k] = $update[$k];
					}
				}
			} else {
				if (\array_search($nKey, $toRemove) === false) {
					$toRemove[] = $nKey;
				}
			}
			if ($remove && \array_search($nKey, $toRemove) !== false) {
				unset($original[$k]);
			}
			unset($update[$k]);
		}
		foreach ($update as $k => $v) {
			if (\array_search($k, $toRemove) === false) {
				$original[$k] = $v;
			}
		}
	}

	public function _refreshConfigFrm() {
		$toRemove = [];
		$original = MailerManager::loadConfig();
		$update = eval('return ' . URequest::post('src') . ';');
		$this->arrayUpdateRecursive($original, $update, $toRemove);
		$this->getMailerConfigFrmDataForm($original);
		if (\count($toRemove) > 0) {
			$this->jquery->execAtLast("$('[name=_toDelete]').closest('.ui.dropdown').dropdown('set selected'," . \json_encode($toRemove) . ");");
		}
		$this->jquery->renderView('@framework/main/component.html');
	}

	public function _applyConfig($key) {
		$original = MailerManager::loadConfig();
		$toDelete = URequest::post('_toDelete');
		$toRemove = \explode(',', $toDelete);
		$update = $this->getMailerConfigFromPost();
		$this->arrayUpdateRecursive($original, $update, $toRemove, '', true);
		$original = $this->mixDefaultMailerConfig($key, $original);
		$this->getMailerConfigFrmDataForm($original);
		if (\count($toRemove) > 0) {
			$this->jquery->execAtLast("$('[name=_toDelete]').closest('.ui.dropdown').dropdown('set selected'," . \json_encode($toRemove) . ");");
		}
		$this->jquery->renderView('@framework/main/component.html');
	}

	public function _getMailerConfigSource() {
		$original = MailerManager::loadConfig();
		$toDelete = URequest::post('_toDelete');
		$toRemove = \explode(',', $toDelete);
		$update = $this->getMailerConfigFromPost();
		$this->arrayUpdateRecursive($original, $update, $toRemove, '', true);
		$src = UArray::asPhpArray($original, "array", 1, true);
		$frm = $this->jquery->semantic()->htmlForm('frm-source');
		$textarea = $frm->addTextarea('src', '', $src, null, 20);
		$frm->addInput('toDeleteSrc', null, 'hidden', $toDelete);
		$frm->setLibraryId('_compo_');
		$textarea->getDataField()->setProperty('data-editor', true);
		$this->_getAdminViewer()->insertAce();

		$this->jquery->renderView('@framework/main/component.html');
	}

	public function submitMailerConfig($partial = true) {
		$result = $this->getMailerConfigFromPost();
		$toDelete = $_POST['_toDelete'] ?? '';
		unset($_POST['_toDelete']);
		$toDeletes = \explode(',', $toDelete);
		$this->removeDeletedsFromArray($result, $toDeletes);
		$this->removeEmpty($result);
		try {
			if (MailerManager::saveConfig($result)) {
				$msg = $this->showSimpleMessage("The configuration file has been successfully modified!", "positive", "Configuration", "check square", null, "msgConfig");
			} else {
				$msg = $this->showSimpleMessage("Impossible to write the configuration file.", "negative", "Configuration", "warning circle", null, "msgConfig");
			}
		} catch (\Exception $e) {
			$msg = $this->showSimpleMessage("Your configuration contains errors.<br>The configuration file has not been saved.<br>" . $e->getMessage(), "negative", "Configuration", "warning circle", null, "msgConfig");
		}
		$msg->setLibraryId('_compo_');
		$this->jquery->execAtLast('$("._menu").removeClass("disabled");');
		$this->jquery->renderView('@framework/main/component.html');
	}

	private function getMailerConfigFromPost() {
		$result = MailerManager::loadConfig();
		$postValues = $_POST;
		foreach ($postValues as $key => $value) {
			if ('_toDelete' != $key) {
				if (strpos($key, "-") === false) {
					$result[$key] = $value;
				} else {
					$keys = explode('-', $key);
					$v = &$result;
					foreach ($keys as $k) {
						if (! isset($v[$k])) {
							$v[$k] = [];
						}
						$v = &$v[$k];
					}
					$v = $value;
				}
			}
		}
		return $result;
	}

	private function removeEmpty(&$array) {
		foreach ($array as $k => $value) {
			if ($value == null) {
				unset($array[$k]);
			} elseif (\is_array($value)) {
				if (\count($value) == 0) {
					unset($array[$k]);
				} else {
					$this->removeEmpty($array[$k]);
				}
			}
		}
	}

	private function removeDeletedsFromArray(&$result, $toDeletes) {
		$v = &$result;
		foreach ($toDeletes as $toDeleteOne) {
			if ($toDeleteOne != null) {
				if (strpos($toDeleteOne, "-") === false) {
					unset($result[$toDeleteOne]);
				} else {
					$keys = explode('-', $toDeleteOne);
					$v = &$result;
					$s = \count($keys);
					for ($i = 0; $i < $s - 1; $i ++) {
						$v = &$v[$keys[$i]];
					}
					unset($v[\end($keys)]);
				}
			}
		}
	}
}

