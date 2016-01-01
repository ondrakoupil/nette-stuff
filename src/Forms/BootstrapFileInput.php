<?php

namespace OndraKoupil\Nette\Forms;

use \Nette\Utils\Html;

/**
 * Styled file input.
 * Based on http://www.jasny.net/bootstrap/javascript/#fileinput
 *
 * Requires adding CSS and JS to project.
 *
 */

class BootstrapFileInput extends \Nette\Forms\Controls\UploadControl {

	protected $mode = self::MODE_DEFAULT;

	const MODE_DEFAULT = 1;
	const MODE_BUTTON = 2;
	const MODE_THUMBNAIL = 3;

	function __construct($label = NULL) {
		parent::__construct($label, false);
		$this->control = Html::el("div", array(
			"class" => array("fileinput", "fileinput-new", "input-group"),
			"data-provides" => "fileinput"
		));
	}

	function getMode() {
		return $this->mode;
	}

	/**
	 * @param int $mode Use one of MODE_* constants
	 * @return BootstrapFileInput
	 */
	function setMode($mode) {
		$this->mode = $mode;
		return $this;
	}

	function getControl() {
		$this->setOption("rendered", true);

		$mainDiv = clone $this->control;

		$htmlId = $this->getHtmlId();
		$htmlName = $this->getHtmlName();

		$translator = $this->getTranslator();
		if ($translator) {
			$translate = function($a) use ($translator) {
				return htmlspecialchars($translator->translate($a));
			};
		} else {
			$translate = function($a) {
				return htmlspecialchars($a);
			};
		}

		switch ($this->mode) {
			case self::MODE_DEFAULT:
				$mainDiv->setHtml('
				  <div class="form-control" data-trigger="fileinput"><i class="fa fa-file-image-o fileinput-exists"></i> <span class="fileinput-filename"></span></div>
				  <span class="input-group-addon btn btn-default btn-file">
					<span class="fileinput-new">'.($translate("Vybrat soubor")).'</span>
					<span class="fileinput-exists">'.  ($translate("Změnit")).'</span>
					<input type="file" id="'.$htmlId.'" name="'.$htmlName.'"></span>
					<a href="#" class="input-group-addon btn btn-default fileinput-exists" data-dismiss="fileinput">'.  ($translate("Zrušit")).'</a>
				');
				break;

			case self::MODE_BUTTON:
				$mainDiv->class("input-group", false);
				$mainDiv->setHtml('
					<div class="fileinput fileinput-new" data-provides="fileinput">
					  <span class="btn btn-default btn-file">
						<span class="fileinput-new">'.($translate("Vybrat soubor")).'</span>
						<span class="fileinput-exists">'.($translate("Změnit")).'</span>
						<input type="file" id="'.$htmlId.'" name="'.$htmlName.'">
					  </span>
					  <span class="fileinput-filename"></span>
					  <a href="#" class="close fileinput-exists" data-dismiss="fileinput" style="float: none">&times;</a>
					</div>
				');
				break;

			case self::MODE_THUMBNAIL:
				$mainDiv->class("input-group", false);
				$mainDiv->setHtml('
					<div class="fileinput fileinput-new" data-provides="fileinput">
					  <div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width: 100px; height: 100px;"></div>
					  <div>
						<span class="btn btn-default btn-file">
						  <span class="fileinput-new">'.($translate("Vybrat obrázek")).'</span>
						  <span class="fileinput-exists">'.($translate("Změnit")).'</span>
					      <input type="file" id="'.$htmlId.'" name="'.$htmlName.'">
						  </span>
						  <a href="#" class="btn btn-default fileinput-exists" data-dismiss="fileinput">'.($translate("Zrušit")).'</a>
					  </div>
					</div>
				');
				break;

		}


		return $mainDiv;
	}

}
