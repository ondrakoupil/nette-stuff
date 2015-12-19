<?php

namespace OndraKoupil\Nette\Controls;

use \Nette\Forms\Form;

/**
* To be used in latte forms:
* 
*  ```
* {form myForm}
*	{control formErrors $form}
*   ... here comes controls, inputs, buttons, whatever
* {/form}
* ```
*/
class FormErrorsControl extends GenericMessagesControl {

	function render(Form $form = null) {
		if ($form) {
			$errors = $form->errors;
			if (count($errors) == 0) {
				return "";
			}

			foreach($form->errors as $error) {
				$this->addMessage($error, "danger");
			}
		}

		return parent::render();
	}

}
