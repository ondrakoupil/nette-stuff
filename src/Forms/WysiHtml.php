<?php

namespace OndraKoupil\Nette\Forms;

use \Nette\Utils\Html;


/**
 * Wysiwyg editor využívající pro Bootstrap 3
 *
 * Více viz https://github.com/bootstrap-wysiwyg/bootstrap3-wysiwyg
 *
 * Je potřeba připojit do stránky bootstrap3-wysihtml5.custom.js a bootstrap3-wysihtml5.custom.css
 * a pro správné fungování barviček i nahrát do adresáře css soubor wysihtml5-inner.css
 */
class WysiHtml extends \Nette\Forms\Controls\TextArea implements \ArrayAccess {


	protected $parameters = array(
		"toolbar" => array(
			"emphasis"=>array(
				"small" => false
			),
			"font-styles"=>true,
			"lists"=>true,
			"clearFormatHran"=>true,
			"link"=>true,
			"blockquote" => false,
			"html"=>false,
			"color" => true,
			"image"=>false,
			"fa"=>true
		),
		"customTemplates"=>"hran2.wysiHtml5CustomTemplates",
		"useLineBreaks"=>false,
		"stylesheets"=>["/css/wysihtml5-inner.css"],
		"locale"=>"cs-CZ"
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
		$control->class[]="wysihtml5 form-control";
		if ($this->width) $control->style["width"]=$this->width;
		if ($this->height) $control->style["height"]=$this->height;

		$div = Html::el("div");
		$div->add($control);

		$params = json_encode($this->parameters);
		$params = str_replace("\"hran2.wysiHtml5CustomTemplates\"", "hran2.wysiHtml5CustomTemplates", $params);

		$div->add('<script>$(function() {
			$("#'.$this->getHtmlId().'").wysihtml5('.$params.')
				.prev(".wysihtml5-toolbar").find(".btn").tooltip({container:"body"});
		});</script>');

		// TODO: vyřešit lépe

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


	function setToolbarParam($paramName, $paramValue) {
		$this->parameters["toolbar"][$paramName] = $paramValue;
		return $this;
	}

	function getToolbarParam($paramName) {
		if (isset($this->parameters["toolbar"][$paramName])) {
			return $this->parameters["toolbar"][$paramName];
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
