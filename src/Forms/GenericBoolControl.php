<?php

namespace OndraKoupil\Nette\Forms;

/**
 * Virtual form field made for transporting boolean values.
 */
class GenericArrayControl extends \Nette\Forms\Controls\HiddenField {

	function setValue($value) {
		$this->value = Tools::arrayize($value);
		return $this;
	}

	function loadHttpData() {
		$rawData = \Nette\Utils\Arrays::get( $this->getForm()->getHttpData(), $this->getHtmlName(), null);
		try {
			$this->setValue($rawData);
		} catch (\Exception $e) {
			$this->value = array();
		}
		return $this;
	}

	function getControl() {
		return null;
	}

}
