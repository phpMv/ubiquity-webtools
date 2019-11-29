<?php
namespace Ubiquity\controllers\admin\popo;

use Ubiquity\mailer\MailerManager;
use Ubiquity\cache\ClassUtils;

class MailerClass {

	protected $name;

	protected $shortname;

	protected $to;

	protected $from;

	protected $subject;

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
		$mailclass->subject = $mail->getSubject();
		return $mailclass;
	}
}

