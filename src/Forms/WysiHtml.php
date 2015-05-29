<?php

namespace OndraKoupil\Nette\Forms;

use Nette\Utils\Html;

class WysiHtml extends \Nette\Forms\Controls\TextArea implements \ArrayAccess {

	protected $parameters = array(
		"emphasis"=>true,
		"font-styles"=>true,
		"lists"=>true,
		"clear"=>true,
		"locale"=>"cs-CZ",
		"link"=>true,
		"html"=>false,
		"image"=>false,
		"stylesheets"=>[],
		"useLineBreaks"=>false
	);

	protected $width = "100%";
	protected $height = null;

	function __construct($label = NULL) {
		parent::__construct($label);
	}

	public function getWidth() {
		return $this->width;
	}

	public function getHeight() {
		return $this->height;
	}

	public function setWidth($width) {
		if (is_numeric($width)) $width.="px";
		$this->width = $width;
		return $this;
	}

	public function setHeight($height) {
		if (is_numeric($height)) $height.="px";
		$this->height = $height;
		return $this;
	}


	function getControl() {
		$control = parent::getControl();
		$control->class[]="wysihtml5";
		if ($this->width) $control->style["width"]=$this->width;
		if ($this->height) $control->style["height"]=$this->height;

		$div = Html::el("div");
		$div->add($control);
		$div->add('<script>$(function() {
			$("#'.$this->getHtmlId().'").wysihtml5('.json_encode($this->parameters).');
		});</script>');

		return $div;
	}

	function setParam($paramName, $paramValue) {
		$this->parameters[$paramName] = $paramValue;
		return $this;
	}

	function getParam($paramName) {
		if (isset($this->parameters[$paramName])) {
			return $this->parameters[$paramName];
		}
		return null;
	}

	public function offsetExists($offset) {
		return isset($this->parameters[$offset]);
	}

	public function offsetGet($offset) {
		return ( isset($this->parameters[$offset]) ? $this->parameters[$offset] : null );
	}

	public function offsetSet($offset, $value) {
		$this->parameters[$offset] = $value;
	}

	public function offsetUnset($offset) {
		if (isset($this->parameters[$offset])) {
			unset($this->parameters[$offset]);
		}
	}


}
