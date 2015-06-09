<?php

namespace OndraKoupil\Nette\Controls;

use \Nette\Utils\Callback;
use \OndraKoupil\Tools\Arrays;

class GenericMessagesControl extends \Nette\Application\UI\Control {

	protected $messages = array();

	/**
	 * @var array of callbacks
	 */
	protected $callbacks = array();

	protected $hideIfEmpty = false;

	function __construct($parent=null, $name=null, $messages = array()) {

		if ($messages) {
			foreach($messages as $message) {
				$this->addMessage($message);
			}
		}


		parent::__construct($parent, $name);
	}

	function getHideIfEmpty() {
		return $this->hideIfEmpty;
	}

	function setHideIfEmpty($hideIfEmpty) {
		$this->hideIfEmpty = $hideIfEmpty ? true : false;
		return $this;
	}

	function getMessages() {
		return $this->messages;
	}

	function addMessages($messages) {
		$messages = Arrays::arrayize($messages);
		foreach($messages as $m) {
			$this->addMessage($m);
		}
		return $this;
	}

	function addCallback($callback) {
		$this->callbacks[] = Callback::check($callback);
		return $this;
	}

	function addMessage($message, $type = "", $icon = "") {

		if (is_string($message)) {
			$messageObj = new \stdClass();
			$messageObj->message = $message;
			$messageObj->type = $type;
			$messageObj->icon = $icon;
		} elseif (is_array($message) and isset($message["message"])) {
			if (isset($message["message"]->message)) {
				$messageObj = $message["message"];
			} elseif (is_array($message["message"]) and isset($message["message"]["message"])) {
				$messageObj = Arrays::toObject($message["message"]);
			} else {
				$messageObj = Arrays::toObject($message);
			}
		} elseif (is_object($message) and isset($message->message)) {
			if (isset($message->message->message)) {
				$messageObj = $message->message;
			} elseif (is_array($message->message) and isset($message->message["message"])) {
				$messageObj = Arrays::toObject($message->message);
			} else {
				$messageObj = $message;
			}
		} else {
			throw new \InvalidArgumentException("Give me scalar agruments or object or array with [message] value.");
		}

		if (!isset($messageObj->icon) or !$messageObj->icon) {
			$messageObj->icon = null;
		} else {
			if (substr($messageObj->icon, 0, 5) != "icon-" and substr($messageObj->icon, 0, 3) != "fa-") {
				// $messageObj->icon = "icon-".$messageObj->icon;
				$messageObj->icon = "fa-".$messageObj->icon;
			}
		}

		$this->messages[] = $messageObj;
		return $this;
	}

	protected function evalCallbacks() {
		foreach ($this->callbacks as $cb) {
			Callback::invokeArgs($cb, array($this));
		}

		$this->callbacks = array();
	}

	function render() {

		$this->evalCallbacks();

		if (!$this->messages and $this->hideIfEmpty) {
			return "";
		}

		$this->template->setFile(__DIR__."/templates/GenericMessages.latte");
		$this->template->messages = $this->messages;
		$this->template->render();
	}
}
