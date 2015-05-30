<?php

namespace OndraKoupil\Nette;

use \OndraKoupil\Tools\Files;
use \OndraKoupil\Nette\Logger;
use \OndraKoupil\Nette\Exceptions\StopActionException;
use \Nette\Utils\Finder;
use \Nette\Utils\Callback;

class FileGarbageCollector extends \Nette\Object {

	// TODO: Odstraňování prázdných adresářů
	// TODO: Zpracovat FileRotator do Nette

	const DELETE = 1;
	const MOVE = 2;
	const MOVE_WITHOUT_SUBDIRECTORIES = 3;
	const CALLBACK=4;

	/**
	 * Soubor, do něhož ukládat čas posledního spuštění.
	 * @property
	 * @var string
	 */
	protected $dataFilename=null;

	/**
	 * Četnost spouštění (v sekundách)
	 * @property
	 * @var int
	 */
	protected $periodicity=0;

	/**
	 * Adresář, ve kterém hlídat smetí
	 * @property
	 * @var string
	 */
	protected $directory;

	/**
	 * Co se smetím udělat?
	 * Nastavovat pomocí setAction()
	 * @see setAction()
	 * @property-read
	 * @var int
	 */
	protected $action;

	/**
	 * @property-read
	 * @var callback|string Adresář nebo callback
	 */
	protected $actionTarget;

	/**
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Callback pro ověřování, zda soubor je legitimní nebo smetí.
	 * @var Callback callback($filepath,$this)
	 */
	protected $callback;

	/**
	 * Před spuštěním garbage collectoru. Může vyhodit StopActionException = žádná garbage collection neproběhne.
	 * @var array function($garbageCollector)
	 */
	public $onBeforeRun=array();

	/**
	 * Pro každý garbage soubor. Může vyhodit StopActionException = daný soubor mimořádně nebude považován za garbage.
	 * @var array function($filepath,$garbageCollector)
	 */
	public $onGarbageFile=array();

	/**
	 * Po proběhnuté garbage kolekci.
	 * @var array function($processedFiles,$garbageCollector)
	 */
	public $onAfterRun=array();

	/**
	 * @param string $directory Cesta ke hládanému adresáři, jehož obsah je uklízen
	 * @param callable|Callback $callback Funkce, která rozhoduje, zda určitý soubor je smetí nebo není
	 * @param string $dataFilename Cesta k souboru (neměl by být v hlídaném adresáři!), do kterého si GC ukládá, kdy byl naposledy spuštěn
	 * @param int $periodicity Počet sekund
	 * @param Logger $logger
	 */
	function __construct($directory,$callback,$dataFilename=null,$periodicity=86400,$logger = null) {
		$this->setDirectory($directory);
		$this->setCallback($callback);
		$this->setDataFilename($dataFilename);
		$this->setPeriodicity($periodicity);
		$this->setLogger($logger);
		$this->setAction(self::DELETE);
	}

	function getGarbageCollectionPeriodicity() {
		return $this->garbageCollectionPeriodicity;
	}

	function getGarbageCollectionFilename() {
		return $this->garbageCollectionFile;
	}

	function getDirectory() {
		return $this->directory;
	}

	function getLogger() {
		return $this->logger;
	}

	function getCallback() {
		return $this->callback;
	}

	function setCallback($callback) {
		$this->callback = Callback::check($callback);
		return $this;
	}

	function setLogger($logger) {
		if ($logger!==null and !($logger instanceof Logger)) {
			throw new \InvalidArgumentException("\$logger must be an instance of Logger.");
		}
		$this->logger=$logger;
	}

	function setDirectory($directory) {
		if (!file_exists($directory) or !is_dir($directory)) {
			throw new \OndraKoupil\Tools\Exceptions\FileException("Directory $directory does not exist.");;
		}
		$this->directory=$directory;
	}

	function setDataFilename($filename) {
		if ($filename) {
			Files::create($filename);
		}
		$this->dataFilename=$filename;
	}

	function setPeriodicity($periodicity) {
		if ($periodicity===null) $periodicity=86400;
		if (!is_numeric($periodicity)) {
			throw new \InvalidArgumentException("\$periodicity must be integer in seconds.");
		}
		$this->periodicity=$periodicity;
	}

	function getAction() {
		return $this->action;
	}

	function getActionTarget() {
		return $this->actionTarget;
	}

	/**
	 * Co se má dělat se smetím? Použij třídní konstanty.
	 * @param int $action
	 * @param string|callable $actionTarget Callback nebo cest k adresáři pro přesun smetí
	 * @throws \InvalidArgumentException
	 */
	function setAction($action,$actionTarget=null) {
		if ($action==self::DELETE) {
			$this->action=self::DELETE;
			$this->actionTarget=null;
			return;
		} elseif ($action==self::MOVE or $action==self::MOVE_WITHOUT_SUBDIRECTORIES) {
			Files::mkdir($actionTarget);
			$this->action=$action;
			$this->actionTarget=$actionTarget;
			return;
		} elseif ($action==self::CALLBACK) {
			$this->action=$action;
			$this->actionTarget=Callback::check($actionTarget);
			return;
		}
		throw new \InvalidArgumentException("Invalid \$action: $action");
	}


	/**
	 * Kdy bežel garbage collector naposledy?
	 * @return int Timestamp
	 */
	function getLastRunDate() {
		if (!$this->dataFilename) return 0;
		$u=file_get_contents($this->dataFilename);
		if (is_numeric($u)) return (int)$u;
		return 0;
	}

	protected function writeLastRunDate() {
		if (!$this->dataFilename) return;
		file_put_contents($this->dataFilename,time());
	}

	/**
	 * Ověří, zda již uplynula $periodicity od posledního spuštění.
	 * @return boolean
	 */
	function checkIfGarbageCollectionIsNeeded() {

		if (!$this->dataFilename) return false;

		$lastRun=$this->getLastRunDate();

		if (time()-$lastRun > $this->periodicity) {
			return true;
		}

		return false;
	}

	/**
	 * Spustí úklid nezávisle na tom, kolik času uplynulo od posledního spuštění.
	 * @return array Array souborů smetí
	 */
	function runNow() {
		return $this->run();
	}

	/**
	 * Pokud již uplynula dostatečná doba, spustí úklid
	 * @return boolean|array False, pokud nic neudělá (protože nebylo třeba), anebo array souborů smetí
	 */
	function runIfNeeded() {
		if ($this->checkIfGarbageCollectionIsNeeded()) {
			return $this->run();
		}
		return false;
	}

	protected function run() {
		$this->writeToLog("Starting garbage collection in $this->directory");

		try {
			$this->onBeforeRun($this);
		} catch (StopActionException $e) {
			$this->writeToLog("Stopped by StopActionException: ".$e->getMessage());
			return array();
		}

		$garbage=$this->getGarbageFiles();
		if (!$garbage) {
			$this->writeToLog("No garbage found.");
		} else {
			$this->writeToLog("Garbage found (".count($garbage)." files):\n".implode("\n",$garbage));
		}

		foreach($garbage as $i=>$file) {
			try {
				$this->onGarbageFile($file,$this);
			} catch (StopActionException $e) {
				$this->writeToLog("File $file stopped by StopActionException: ".$e->getMessage());
				unset($garbage[$i]);
				continue;
			}

			$this->processGarbageFile($file);
		}

		$this->writeToLog("Finished garbage collection in $this->directory");
		$this->writeLastRunDate();

		$this->onAfterRun($garbage,$this);

		return array_values($garbage);
	}

	/**
	 * Najde veškeré smetí v hlídaném adresáři
	 * @return array Pole cest ke smeťózním souborům
	 */
	function getGarbageFiles() {
		$allFiles=array();
		foreach(Finder::findFiles("*")->from($this->directory) as $file) {
			$allFiles[]=$file->__toString();
		}
		$_this=$this;

		$garbage=array_filter($allFiles, function($file) use ($_this) {
			return $_this->isFileGarbage($file);
		});
		return $garbage;
	}

	/**
	 * Ověří, zda zadaný soubor je smetí.
	 * @param string $filename Cesta k souboru
	 * @return bool True = jde o garbage.
	 * @throws \Nette\InvalidStateException Když nebyl nastaven callback
	 * @throws \OndraKoupil\Tools\Exceptions\FileException $filename nebyl nalezen
	 */
	function isFileGarbage($filename) {
		if (!$this->callback) {
			throw new \Nette\InvalidStateException("Can't run garbage collection when callback is not set.");
		}
		if (!file_exists($filename) or is_dir($filename)) {
			throw new \OndraKoupil\Tools\Exceptions\FileException("$filename not found or is a directory.");
		}

		return Callback::invokeArgs($this->callback, array($filename,$this));
	}

	/**
	 * Smaže/přesune soubor
	 * @param string $filepath Soubor v $this->directory
	 * @return boolean Dle úspěchu
	 * @throws \Nette\FileNotFoundException $filepath nebyl nalezen.
	 * @throws \Nette\IOException Selhalo kopírování/přesun
	 * @throws \Nette\InvalidStateException Nebyla dosud definována žádná akce, použij setAction()
	 */
	function processGarbageFile($filepath) {
		if (!file_exists($filepath) or is_dir($filepath)) {
			throw new \OndraKoupil\Tools\Exceptions\FileException("File \"$filepath\" is not found or is a directory.");
		}
		switch ($this->action) {
			case self::DELETE:
				$ret=Files::remove($filepath);
				$this->writeToLog("Deleted: $filepath");
				return $ret;

			case self::MOVE_WITHOUT_SUBDIRECTORIES:
				Files::mkdir($this->actionTarget);
				$newName=Files::freeFilename($this->actionTarget, Files::filename($filepath));
				$target=$this->actionTarget."/".$newName;
				$ok=rename($filepath,$target);
				if (!$ok) {
					throw new \OndraKoupil\Tools\Exceptions\FileAccessException("Failed renaming $filepath to $target");
				}
				$this->writeToLog("Moved: \"$filepath\" to \"$target\"");
				return true;

			case self::MOVE:
				$ret=Files::rebaseFile($filepath, $this->directory, $this->actionTarget, false);
				$this->writeToLog("Moved: \"$filepath\" to \"$ret\"");
				return $ret?true:false;

			case self::CALLBACK:
				$this->writeToLog("Callback: $filepath");
				return Callback::invokeArgs($this->actionTarget, array($filepath,$this) );

			default:
				throw new \Nette\InvalidStateException("GarbageCollector can't process file \"$filepath\", no action was defined.");
		}
	}

	/**
	 * Zapíše do logu, pokud je nastaven logger, jinak nic.
	 * @param string $message
	 */
	function writeToLog($message) {
		if ($this->logger) $this->logger->log($message);
	}

}
