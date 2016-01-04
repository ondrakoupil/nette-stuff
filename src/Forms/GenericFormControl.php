<?php

namespace OndraKoupil\Nette\Forms;

/**
 * A form control that can hold any type of data,
 * its value is passed "as is".
 */
class GenericFormControl extends \Nette\Forms\Controls\BaseControl {

	function getHttpData($type = null, $htmlTail = null) {
		$formData = $this->getForm()->getHttpData(null, null);
		return \Nette\Utils\Arrays::get( $formData, $this->getHtmlName(), null);
	}

}
