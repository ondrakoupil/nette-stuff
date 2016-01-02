<?php

namespace OndraKoupil\Nette\Forms;

/**
 * Univerzální formulářový prvek pro tlačítka s možností nastavit třídu a typ a případně ikonu.
 *
 * Při použití $labelIcon je nutné přidat styly z http://www.jasny.net/bootstrap/css/#buttons
 */

class Button extends \Nette\Forms\Controls\Button {

	protected $labelIcon;
	protected $labelIconLeft = true;

	function __construct($caption = NULL, $type = "button", $class = "btn", $labelIcon = null, $labelIconOnLeft = true) {
		parent::__construct($caption);
		$proto = $this->getControlPrototype();
		$proto->setName("button");
		$proto->type($type);
		$this->labelIcon = $labelIcon;
		$this->labelIconLeft = $labelIconOnLeft ? true : false;
		if ($class) $proto->addClass($class);
	}

	function getControl($caption = NULL) {
		$control = parent::getControl($caption);

		if ($control->value) unset($control->value);
		if ($caption === null and $this->caption) $caption = $this->caption;

		if ($this->labelIcon) {
			if ($this->labelIconLeft) {
				$caption = "<span class='btn-label'><i class='fa fa-".$this->labelIcon."'></i></span>" . $caption;
			} else {
				$caption .= "<span class='btn-label btn-label-right'><i class='fa fa-".$this->labelIcon."'></i></span>";
			}
			$control->class[] = "btn-labeled";
		}

		if (!$control->getHtml() and $caption) {
			$control->setHtml($caption);
		}

		return $control;
	}

}
