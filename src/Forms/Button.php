<?php

namespace OndraKoupil\Nette\Forms;

/**
 * Univerzální formulářový prvek pro tlačítka s možností nastavit třídu a typ
 */

class Button extends \Nette\Forms\Controls\Button {

	function __construct($caption = NULL, $type = "button", $class = "btn") {
		parent::__construct($caption);
		$proto = $this->getControlPrototype();
		$proto->setName("button");
		$proto->type($type);
		if ($class) $proto->addClass($class);
	}

	function getControl($caption = NULL) {
		$control = parent::getControl($caption);

		if ($control->value) unset($control->value);

		if ($caption === null and $this->caption) $caption = $this->caption;
		if (!$control->getHtml() and $caption) {
			$control->setHtml($caption);
		}
		return $control;
	}

}
