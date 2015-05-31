<?php

namespace OndraKoupil\Images;

use \OndraKoupil\Tools\Files;
use Nette\Utils\Strings;

/**
* @deprecated Will use Palette somedays
*/
class SimpleFileFinder implements IFileFinder {

	protected $basePath=array();
	protected $baseHref=array();
	protected $baseOutputPath=array();
	protected $baseOutputHref=array();


	function __construct($basePath=null, $baseOutputPath=null, $baseOutputHref=null, $baseHref=null) {
		if ($basePath and $baseOutputPath and $baseOutputHref) {
			$this->addDirectoryPair($basePath, $baseOutputPath, $baseOutputHref, $baseHref);
		}
	}

	function addDirectoryPair($basePath,$baseOutputPath,$baseOutputHref, $baseHref=null) {
		if (!file_exists($basePath) or !is_dir($basePath)) {
			throw new \InvalidArgumentException("\$basePath does not exist or is not directory: \"$basePath\"");
		}
		if (file_exists($baseOutputPath) and !is_dir($baseOutputPath)) {
			throw new \InvalidArgumentException("\$baseOutputPath is normal file, must be directory: \"$baseOutputPath\"");
		}
		$this->basePath[] = $basePath;
		$this->baseHref[] = $baseHref;
		$this->baseOutputPath[] = $baseOutputPath;
		$this->baseOutputHref[] = $baseOutputHref;
		Files::mkdir($baseOutputPath);
	}

	function getPath($source,$signature,$freeFilename=true) {
		foreach($this->basePath as $i=>$basePath) {
			if (Strings::startsWith($source, $basePath)) {
				$path=Files::rebasedFilename($source, $basePath, $this->baseOutputPath[$i]."/".substr($signature,0,4));
				$dir=Files::dir($path);
				if (!file_exists($dir))	Files::mkdir($dir);
				if ($freeFilename) {
					$filename=Files::filename($path);
					$path=$dir."/".Files::freeFilename($dir, $filename);
				}
				return $path;
			}
		}
		throw new \Nette\InvalidStateException("File $source is not in any of defined basepaths!");
	}

	public function getHrefFromPath($path) {
		foreach($this->baseOutputPath as $i=>$baseOutputPath) {
			if (Strings::startsWith($path, $baseOutputPath)) {
				return Files::rebasedFilename($path, $baseOutputPath, $this->baseOutputHref[$i]);
			}
		}
		foreach($this->basePath as $i=>$basePath) {
			if (Strings::startsWith($path, $basePath)) {
				$href=$this->baseHref[$i];
				if ($href) {
					return Files::rebasedFilename($path, $basePath, $href);
				}
			}
		}

		throw new \Nette\InvalidStateException("File $path is not in any of defined output paths!");

	}



}
