<?php

namespace OndraKoupil\Nette\Controls;

use \Nette\Application\UI\Form;
use \OndraKoupil\Nette\Forms\Button;
use \OndraKoupil\Tools\Arrays;

class Fiddle extends \Nette\Application\UI\Control {

	public $evalOutput;
	public $formatOutput;

	protected $context;
	protected $presenter;

	protected $session;

	function __construct(\Nette\DI\Container $context, \Nette\Application\UI\Presenter $presenter, \Nette\ComponentModel\IContainer $parent = NULL, $name = NULL) {
		parent::__construct($parent, $name);
		$this->context = $context;
		$this->presenter = $presenter;
		$this->session = $this->presenter->getSession("fiddle");
	}

	function createComponentForm() {

		$form = new Form();


		$form->addTextArea("source", "Source code")
			->addRule(Form::FILLED, "Vyplň zdroják!");

		$form->addRadioList("output", "Typ výstupu", array("text" => "Text", "html" => "Html"))
			->setDefaultValue("text")
			->getControlPrototype()->style["margin-right"] = "6px";

		$form["output"]->addRule(Form::FILLED, "Vyber typ výstupu!");


		$button = new Button("Vykonat!", "submit", "btn btn-primary btn-large btn-lg");
		$form->addComponent($button, "submit");

		$form->getElementPrototype()->class[] = "ajax";

		$_this = $this;

		$form->onSuccess[] = function(Form $form) use ($_this) {
			$_this->formatOutput = $form["output"]->value;
			$output = $_this->evaluate($form["source"]->value);
			$_this->evalOutput = $output;
			$_this->addToHistory($form["source"]->value);
			$_this->invalidateControl("all");
		};

		return $form;

	}

	function handleHistory($index) {
		$command = $this->getHistory($index);
		if ($command) {
			$form = $this->createComponentForm();
			$this->addComponent($form, "form");
			$form["source"]->value = $command;
		} else {
			$this->evalOutput = "That code snippet is not in history.";
		}

		$this->invalidateControl("all");
	}

	function evaluate($source) {
		$out = "";
		try {
			ob_start();
			$context = $this->context;
			$presenter = $this->presenter;

			$lastError = error_get_last();
			@eval($source.";");

			$currentError = error_get_last();
			if ($currentError or $lastError) {
				if (
					!$lastError
					or $lastError["message"] != $currentError["message"]
					or $lastError["file"] != $currentError["file"]
					or $lastError["line"] != $currentError["line"]
				) {
					$out = "PHP error: $currentError[file], line $currentError[line]\n\n$currentError[message]";
					$this->formatOutput = "text";
				}
			}

			if (!$out) {
				$out = ob_get_clean();
			}

		} catch (\Exception $e) {
			$out = get_class($e)." catched: ".$e->getMessage();
			$out .= "\nFile: " . $e->getFile();
			$out .= "\nLine: " . $e->getLine();
			$out .= "\n\n" . $e->getTraceAsString();
			ob_end_clean();
		}

		return $out;
	}

	function render() {
		$this->template->setFile(__DIR__."/templates/Fiddle.latte");

		$this->template->output = $this->evalOutput;
		$this->template->format = $this->formatOutput;
		$this->template->history = array_reverse($this->getHistory(), true);

		$this->template->render();
	}

	function getHistory($index = null) {

		if ($index !== null) {
			if (isset($this->session->history[$index])) {
				return $this->session->history[$index];
			}
			return "";
		}

		if (!$this->session->history) {
			return array();
		}

		return $this->session->history;
	}

	function addToHistory($code) {
		if (!$this->session->history) {
			$this->session->history = array($code);
			return;
		}

		$this->session->history = Arrays::deleteValue($this->session->history, $code);

		$this->session->history[] = $code;

		if (count($this->session->history) > 12) {
			$this->session->history = array_slice($this->session->history, -12);
		}

		$this->session->history = array_values($this->session->history);
	}

}
