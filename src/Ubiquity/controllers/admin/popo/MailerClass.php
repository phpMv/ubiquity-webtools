<?php
namespace Ubiquity\controllers\admin\popo;

use Ubiquity\mailer\MailerManager;
use Ubiquity\cache\ClassUtils;

class MailerClass {

	protected $name;

	protected $shortname;

	protected $to;

	protected $cc;

	protected $bcc;

	protected $from;

	protected $subject;

	protected $body;

	protected $bodyText;

	protected $attachments;

	protected $rawAttachments;

	protected $hasRecipients;

	protected $sentAt;

	protected $attachmentsDir;

	/**
	 *
	 * @return mixed
	 */
	public function getAttachmentsDir() {
		return $this->attachmentsDir;
	}

	/**
	 *
	 * @param mixed $attachmentsDir
	 */
	public function setAttachmentsDir($attachmentsDir) {
		$this->attachmentsDir = $attachmentsDir;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getSentAt() {
		return $this->sentAt;
	}

	/**
	 *
	 * @param mixed $sentAt
	 */
	public function setSentAt($sentAt) {
		$this->sentAt = $sentAt;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getBodyText() {
		return $this->bodyText;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getAttachments() {
		return $this->attachments;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getRawAttachments() {
		return $this->rawAttachments;
	}

	/**
	 *
	 * @param mixed $bodyText
	 */
	public function setBodyText($bodyText) {
		$this->bodyText = $bodyText;
	}

	/**
	 *
	 * @param mixed $attachments
	 */
	public function setAttachments($attachments) {
		$this->attachments = $attachments;
	}

	/**
	 *
	 * @param mixed $rawAttachments
	 */
	public function setRawAttachments($rawAttachments) {
		$this->rawAttachments = $rawAttachments;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getHasRecipients() {
		return $this->hasRecipients;
	}

	/**
	 *
	 * @param mixed $hasRecipients
	 */
	public function setHasRecipients($hasRecipients) {
		$this->hasRecipients = $hasRecipients;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getBcc() {
		return $this->bcc;
	}

	/**
	 *
	 * @param mixed $bcc
	 */
	public function setBcc($bcc) {
		$this->bcc = $bcc;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getCc() {
		return $this->cc;
	}

	/**
	 *
	 * @param mixed $cc
	 */
	public function setCc($cc) {
		$this->cc = $cc;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getBody() {
		return $this->body;
	}

	/**
	 *
	 * @param mixed $body
	 */
	public function setBody($body) {
		$this->body = $body;
	}

	/**
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 *
	 * @return string
	 */
	public function getShortname() {
		return $this->shortname;
	}

	/**
	 *
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 *
	 * @param string $shortname
	 */
	public function setShortname($shortname) {
		$this->shortname = $shortname;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getTo() {
		return $this->to;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getFrom() {
		return $this->from;
	}

	/**
	 *
	 * @param mixed $to
	 */
	public function setTo($to) {
		$this->to = $to;
	}

	/**
	 *
	 * @param mixed $from
	 */
	public function setFrom($from) {
		$this->from = $from;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getSubject() {
		return $this->subject;
	}

	/**
	 *
	 * @param mixed $subject
	 */
	public function setSubject($subject) {
		$this->subject = $subject;
	}

	public static function init() {
		$result = [];
		$classes = MailerManager::getMailClasses(true);
		foreach ($classes as $class) {
			$result[] = self::initOne($class);
		}
		return $result;
	}

	public static function initOne($class) {
		$mailclass = new static();
		$mailclass->setName($class);
		$mailclass->setShortname(ClassUtils::getClassSimpleName($class));
		$mail = new $class();
		$mailclass->to = $mail->to;
		$mailclass->from = $mail->from;
		$mailclass->cc = $mail->cc;
		$mailclass->bcc = $mail->bcc;
		$mailclass->hasRecipients = $mail->hasRecipients();
		$mailclass->subject = $mail->getSubject();
		$mailclass->body = $mail->body();
		$mailclass->bodyText = $mail->bodyText();
		$mailclass->attachments = $mail->attachments;
		$mailclass->rawAttachments = $mail->rawAttachments;
		$mailclass->attachmentsDir = $mail->getAttachmentsDir();
		return $mailclass;
	}

	public static function initOneFromInfos($class, $mailInfos) {
		$mailclass = new static();
		$mailclass->setName($class);
		$mailclass->setShortname(ClassUtils::getClassSimpleName($class));
		foreach ($mailInfos as $key => $value) {
			if (\property_exists($mailclass, $key)) {
				$mailclass->$key = $value;
			}
		}
		$mailclass->hasRecipients = false;
		return $mailclass;
	}
}

