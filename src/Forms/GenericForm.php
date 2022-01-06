<?php

namespace OndraKoupil\Nette\Forms;

/**
 * Univerzální komponenta zabalující nějaký formulář s možností templatování.
 * <br /><br />
 * V templatu je formulář dostupný pod jménem form či v proměnné $form: {form form} nebo {control form}
 * a pak je tam také komponenta errors (jde o GenericMessagesControl}.
 * <br /><br />
 * <code>
 * // PHP class
 *
 * $form = new Form();
 * // $form->addXYZ(); ....
 *
 * $formComponent = new GenericForm($form, "template.latte");
 * </code>
 *
 * <code>
 * // LATTE template of form
 *
 * {form form}
 *		{control errors}
 *		{input someInput /}
 *		{input someAnotherInput /}
 * {/form}
 * </code>
 */
class GenericForm extends \Nette\Application\UI\Control {

	/**
	 * @var \Nette\Forms\Form
	 */
	protected $form;

	protected $templatePath;

	protected $renderCallbacks=array();

	function __construct(\Nette\Forms\Form $form, $templatePath, \Nette\ComponentModel\IContainer $parent = NULL, $name = NULL) {
		$this->form=$form;
		$this->templatePath=$templatePath;
	}

	/**
	 * @param callback $callback receives this $genericForm, $form, $template
	 */
	function addRenderCallback($callback) {
		$this->renderCallbacks[]=  \Nette\Utils\Callback::check($callback);
	}

	function render($templatePath=false) {

		if (!$templatePath) {
			$this->template->setFile($this->templatePath);
		} else {
			$this->template->setFile($templatePath);
		}

		$this->template->form=$this->form;

		foreach ($this->renderCallbacks as $cb) {
			$cb($this,$this->form,$this->template);
			//\Nette\Utils\Callback::invokeArgs($cb, array($this,$this->form,$this->template));
		}

		$this->template->render();
	}

	function createComponentForm() {
		return $this->form;
	}

	/**
	 * @return \Nette\Forms\Form
	 */
	function getForm() {
		return $this->form;
	}

	function createComponentErrors() {
		$messages = new \OndraKoupil\Nette\Controls\GenericMessagesControl();
		foreach($this->form->errors as $error) {
			$messages->addMessage($error, "danger");
		}
		return $messages;
	}

	static function createErrorMessagesControl(\Nette\Forms\Form $form) {
		$messages = new \OndraKoupil\Nette\Controls\GenericMessagesControl();
		$messages->addCallback(function(\OndraKoupil\Nette\Controls\GenericMessagesControl $messagesControl) use ($form) {
			$errs = $form->getErrors();
			if ($errs) {
				foreach($errs as $e) {
					$messagesControl->addMessage($e, "danger");
				}
			}
		});

		return $messages;
	}
}
