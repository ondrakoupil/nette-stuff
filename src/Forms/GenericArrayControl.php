<?php

namespace OndraKoupil\Nette\Forms;

/**
 * Virtual form field made for transporting array values.
 */
class GenericArrayControl extends \Nette\Forms\Controls\HiddenField {

	function setValue($value) {
		$this->value = \OndraKoupil\Tools\Arrays::arrayize($value);
		return $this;
	}

	function loadHttpData() {
		$rawData = \Nette\Utils\Arrays::get( $this->getForm()->getHttpData(), $this->getHtmlName(), null);
		try {
			$this->setValue(\OndraKoupil\Tools\Arrays::arrayize($rawData));
		} catch (\Exception $e) {
			$this->value = false;
		}
		return $this;
	}

	function getControl() {
		return new \Nette\Utils\Html();
	}

}
