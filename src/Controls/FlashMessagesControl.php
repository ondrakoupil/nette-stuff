<?php

namespace OndraKoupil\Nette\Controls;

class FlashMessagesControl extends GenericMessagesControl {

	protected $initialised = false;

	function __construct($parent=null, $name=null) {
		$this->initialised = false;
		parent::__construct($parent, $name, null);
	}

	function render() {
		$parent = $this->getParent();
		if (!$parent) {
			throw new \Nette\InvalidStateException("FlashMessagesControl must be attached to something!");
		}
		if (!$this->initialised) {
			$template = $parent->getTemplate();
			$flashes = isset($template->flashes) ? $template->flashes : array() ;
			$this->addMessages($flashes);
			$this->initialised;
		}

		return parent::render();
	}
}
