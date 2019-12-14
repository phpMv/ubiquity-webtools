<?php
namespace Ubiquity\controllers\admin\popo;

use Ubiquity\mailer\MailerManager;
use Ubiquity\utils\base\UDateTime;

class MailerQueuedClass extends MailerClass {

	protected $at;

	protected $between;

	protected $and;

	protected $sentAt;

	/**
	 *
	 * @return mixed
	 */
	public function getAt() {
		return $this->at;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getBetween() {
		return $this->between;
	}

	/**
	 *
	 * @return mixed
	 */
	public function getAnd() {
		return $this->and;
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
	 * @param mixed $at
	 */
	public function setAt($at) {
		$this->at = $at;
	}

	/**
	 *
	 * @param mixed $between
	 */
	public function setBetween($between) {
		$this->between = $between;
	}

	/**
	 *
	 * @param mixed $and
	 */
	public function setAnd($and) {
		$this->and = $and;
	}

	public function getStartDate() {
		if ($this->at instanceof \DateTime) {
			return $this->at;
		}
		if ($this->between instanceof \DateTime) {
			return $this->between;
		}
		return null;
	}

	public function startIn() {
		$d = $this->getStartDate();
		if (! isset($d)) {
			return 'now';
		}
		return UDateTime::elapsed($d);
	}

	/**
	 *
	 * @param mixed $sentAt
	 */
	public function setSentAt($sentAt) {
		$this->sentAt = $sentAt;
	}

	public static function initQueue($dec = false) {
		$result = [];
		MailerManager::start();
		if ($dec) {
			$mails = MailerManager::allInDequeue();
		} else {
			$mails = MailerManager::allInQueue();
		}
		foreach ($mails as $mail) {
			if (! $dec) {
				$mailerClass = self::initOne($mail['class']);
			} else {
				$mailerClass = self::initOneFromInfos($mail['class'], $mail);
			}
			$mailerClass->setAt($mail['at'] ?? null);
			$mailerClass->setBetween($mail['between'] ?? null);
			$mailerClass->setAnd($mail['and'] ?? null);
			if ($dec) {
				$mailerClass->setSentAt($mail['sentAt'] ?? null);
			}
			$result[] = $mailerClass;
		}
		return $result;
	}
}

