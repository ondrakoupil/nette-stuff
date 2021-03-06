<?php

namespace OndraKoupil\Nette\Forms;

/**
 * Virtual form field made for transporting boolean values.
 */
class GenericBoolControl extends \Nette\Forms\Controls\HiddenField {

	function setValue($value) {
		$this->value = $value ? true : false;
		return $this;
	}

	function loadHttpData() {
		$rawData = \Nette\Utils\Arrays::get( $this->getForm()->getHttpData(), $this->getHtmlName(), null);
		if ($rawData === "0") $rawData = "";
		try {
			$this->setValue($rawData ? true : false);
		} catch (\Exception $e) {
			$this->value = false;
		}
		return $this;
	}

	function getControl() {
		return new \Nette\Utils\Html();
	}

}
