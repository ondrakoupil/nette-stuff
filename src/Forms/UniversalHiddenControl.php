<?php

namespace OndraKoupil\Nette\Forms;

/**
 * Hidden form field that is capable of working with non-scalar values (arrays, objects, null)
 */
class UniversalHiddenControl extends \Nette\Forms\Controls\HiddenField {

	const NULL_ENTITY = "{!NULL}";

	function setValue($value) {
		$this->value = $value;
		return $this;
	}

	function convertValue($value) {
		if (is_scalar($value)) {
			return (string)$value;
		}
		if (is_array($value)) {
			return json_encode($value);
		}
		if (is_object($value)) {
			return "object:".serialize($value);
		}
		if ($value===NULL) {
			return self::NULL_ENTITY;
		}
		throw new \InvalidArgumentException("Could not serialize control's value into a string.");
	}

	function restoreValue($value) {

		// null
		if ($value === self::NULL_ENTITY) {
			return null;
		}

		// empty
		if ($value === "") {
			return "";
		}

		// array
		if ($value[0] == "{" or $value[0] == "[") {
			$decoded = @json_decode($value, true); // failing is legal scenario
			if ($decoded !== null) {
				return $decoded;
			}
		}

		// object
		if (substr($value,0,7) === "object:") {
			$objString = substr($value, 0, 7);
			$decoded = @unserialize($objString); // failing is legal scenario
			if ($decoded !== false) {
				return $decoded;
			}
		}

		// scalar
		return $value;
	}

	function loadHttpData() {
		$rawData = \Nette\Utils\Arrays::get( $this->getForm()->getHttpData(), $this->getHtmlName(), null);
		try {
			$this->value = $this->restoreValue($rawData);
		} catch (\Exception $e) {
			$this->value = null;
		}
		return $this;
	}

	function getControl() {
		$control = parent::getControl();
		$control->value($this->convertValue($this->getValue()));
		return $control;
	}

}
