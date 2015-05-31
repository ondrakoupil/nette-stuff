<?php

namespace OndraKoupil\Images;

use Nette\Templating\Helpers;

/**
* @deprecated Will use Palette somedays
*/
class UniversalImage extends \Nette\Application\UI\Control {

	protected $path;

	/**
	 * @property
	 * @var string
	 */
	protected $alt;

	/**
	 * @property-read
	 * @var ImagesManager
	 */
	protected $imagesManager;

	/**
	 * @property
	 * @var string
	 */
	protected $htmlClass="";

	/**
	 * @property
	 * @var string
	 */
	protected $defaultTransformation="";

	/**
	 * @property
	 * @var string
	 */
	protected $css="";

	protected $attrs=array();


	function __construct($path, ImagesManager $manager, $alt="", $defaultTransformation="") {
		$this->path=$path;
		$this->imagesManager=$manager;
		$this->alt=$alt;

		if (!file_exists($path)) {
			throw new \Nette\FileNotFoundException("File not found: $path");
		}
	}

	public function getAlt() {
		return $this->alt;
	}

	public function getClass() {
		return $this->htmlClass;
	}

	public function getAttr($attrName) {
		return isset($this->attrs[$attrName]) ? $this->attrs[$attrName] : null;
	}

	public function setAlt($alt) {
		$this->alt = $alt;
		return $this;
	}

	public function setClass($htmlClass) {
		$this->htmlClass = $htmlClass;
		return $this;
	}

	public function addClass($htmlClass) {
		if ($this->htmlClass) $this->htmlClass.=" ".$htmlClass;
		else $this->htmlClass=$htmlClass;
		return $this;
	}

	public function setAttr($attrName,$attrValue) {
		$this->attrs[$attrName] = $attrValue;
		return $this;
	}

	public function getBasePath() {
		return $this->path;
	}

	public function getImagesManager() {
		return $this->imagesManager;
	}

	public function getDefaultTransformation() {
		return $this->defaultTransformation;
	}

	public function setDefaultTransformation($defaultTransformation) {
		$this->defaultTransformation = $defaultTransformation;
		return $this;
	}



	function html($transformation=false,$linkTransformation=false,$effect=true) {
		$path=$this->getHref($transformation);
		if ($linkTransformation) {
			$linkedPath=$this->getHref($linkTransformation);
			return $this->tagWithLink($path,$linkedPath,$effect);
		}
		return $this->tag($path);
	}

	function getPath($transformation="") {
		if ($transformation===false) $transformation=$this->defaultTransformation;
		return $this->getImagesManager()->getTransformedImage($this->path, $transformation);
	}

	function getHref($transformation="") {
		if ($transformation===false) $transformation=$this->defaultTransformation;
		return $this->getImagesManager()->getTransformedHref($this->path, $transformation);
	}

	/**
	 * Ekvivalent pro $this->html()
	 * @return string
	 */
	function __toString() {
		return $this->html(false);
	}

	function tag($path) {
		$moreHtml="";
		if ($this->htmlClass) $moreHtml.="class='".Helpers::escapeHtml($this->htmlClass)."' ";
		if ($this->css) $moreHtml.="style='".Helpers::escapeHtml($this->css)."' ";
		if ($this->attrs) {
			foreach ($this->attrs as $at=>$atv) {
				$moreHtml.="$at='".  Helpers::escapeHtml($atv)."' ";
			}
		}
		return "<img src='".Helpers::escapeHtml($path)."' alt='".Helpers::escapeHtml($this->alt)."' title='".Helpers::escapeHtml($this->alt)."' $moreHtml/>";
	}

	function tagWithLink($path,$linkPath,$effect=true) {
		if ($effect===true) $effect="std";

		$a="";
		if ($effect) {
			$a.=" onclick='return hs.expand(this,{slideshowGroup:\"$effect\"})' ";
		}

		return "<a href='".Helpers::escapeHtml($linkPath)."' $a>".$this->tag($path)."</a>";
	}

	function render($transformation="",$linkTransformation="", $effect=true) {
		echo $this->html($transformation, $linkTransformation, $effect);
	}

//
//	public function serialize() {
//		;
//	}
//
//	public function unserialize($serialized) {
//		;
//	}
//

}
