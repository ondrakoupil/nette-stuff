<?php

namespace OndraKoupil\Nette;

class Logger extends \Nette\Object {

	/**
	 * @property-read
	 * @var string
	 */
	protected $file;

	/**
	 * @property-read
	 * @var string
	 */
	protected $format;

	const TIME="%time%";
	const MESSAGE="%message%";

	function __construct($file,$format="%time% %message%") {
		$this->file=$file;
		$this->format=$format;
	}

	function log($message) {
		$this->write($this->prepareMessage($message));
	}

	function getFile() {
		return $this->file;
	}

	function getFormat() {
		return $this->format;
	}

	protected function prepareMessage($message) {
		if (is_array($message)) {
			$message=print_r($message,true);
		}
		if (is_object($message)) {
			$message=(string)$message;
		}

		$message=str_replace(array(
			self::TIME,
			self::MESSAGE
		),array(
			date("Y-m-d H:i:s"),
			$message
		),$this->format);

		return $message;
	}

	protected function write($preparedMessage) {
		file_put_contents($this->file, $preparedMessage."\n", FILE_APPEND);
	}

}
