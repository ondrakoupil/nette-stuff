<?php

namespace OndraKoupil\Nette;

use \OndraKoupil\Tools\Strings;
use \OndraKoupil\Tools\Files;
use \OndraKoupil\Tools\Arrays;
use \OndraKoupil\Nette\Logger;
use \OndraKoupil\Nette\FileGarbageCollector;
use \OndraKoupil\Tools\Exceptions\FileException;
use \OndraKoupil\Nette\Exceptions\StopActionException;

class UserFileManager extends \Nette\Object {

	/**
	 * Cesta k základnímu adresáři
	 * @var string
	 * @property
	 */
	protected $basePath;

	/**
	 * Odkaz k základnímu adresáři
	 * @var string
	 * @property
	 */
	protected $baseHref;

	/**
	 * @property
	 * @var integer
	 */
	protected $directoryLevels=1;

	/**
	 * @var Logger
	 * @property
	 */
	protected $logger;

	/**
	 * @var \Nette\Database\Context
	 */
	protected $db;

	/**
	 *
	 * @var bool
	 * @property
	 */
	protected $checkFilenames=true;

	/**
	 * Přípony, které jsou považovány za nebezpečné. Null = defaultní.
	 * @var array
	 * @property
	 */
	protected $unsafeExtensions=null;

	/**
	 * @see $onBeforeAdd
	 * @var callback $onBeforeAdd($target,$newFilename,$context,$path):
	 */
	public $onAdd=array();

	/**
	 * Pokud vyhodí StopActionException, přidávání nového souboru se zablokuje
	 * @var callback $onBeforeAdd($target,$newFilename,$context,$path):
	 * <br />$target = Plná cesta k cílovému souboru
	 * <br />$newFilename = Jméno nového souboru (po ošetření)
	 * <br />$context
	 * <br />$path = Cesta ke zdrojovému souboru
	 */
	public $onBeforeAdd=array();

	/**
	 * @see $onBeforeDelete
	 * @var callback($path,$filename,$context)
	 */
	public $onDelete=array();

	/**
	 * Pokud vyhodí StopActionException, mazání souboru neproběhne.
	 * @var callback($path,$filename,$context)
	 * <br />$path = Plná cesta k mazanému souboru
	 * <br />$filename
	 * <br />$context
	 */
	public $onBeforeDelete=array();

	/**
	 * True = v $basePath nesmí být žádné soubory, které nelze zařadit a ověřit v DB nebo pomocí callbacku, všechny ostatní sejme GarbageCollector.
	 * False = soubory, které jsou v neobvyklých adresářích nebo mimo očekávanou cestu, nechat být.
	 * @var bool
	 */
	protected $strictMode=true;

	/**
	 * @param string $basePath Cesta k základnímu adresáři (bez / na konci)
	 * @param string $baseHref Veřejná cestak základnímu úložišti (bez / na konci)
	 * @param int $directoryLevels Počet adresářů pro rozdělování. 0 až 5.
	 * @param Logger $logger Logovat operace?
	 * @param bool $checkFilenames Mají se ověřovat potenciálně nebezpeční jména?
	 */
	function __construct($basePath,$baseHref=null,$directoryLevels=0,Logger $logger=null, $checkFilenames=true) {
		$this->basePath=$basePath;
		Files::mkdir($basePath);

		if ($baseHref) $this->baseHref=$baseHref;
		else $this->baseHref=$basePath;

		$this->setDirectoryLevels($directoryLevels);
		$this->setLogger($logger);
		$this->checkFilenames=$checkFilenames;
		$this->setStrictMode(true);
	}

	/**
	 * @param array $exts
	 */
	function setUnsafeExtensions($exts) {
		if (!$exts) {
			$this->unsafeExtensions=null;
		} else {
			$this->unsafeExtensions=Arrays::arrayize($exts);
		}
		return $this;
	}

	function setLogger($logger) {
		if ($logger!==null and !($logger instanceof Logger)) {
			throw new \InvalidArgumentException("Argument must be instance of OndraKoupil\Logger or null");
		}
		$this->logger=$logger;
		return $this;
	}

	function getLogger() {
		return $this->logger;
	}

	/**
	 * @param array $exts
	 * @return array
	 */
	function getUnsafeExtensions() {
		return $this->unsafeExtensions;
	}

	/**
	 * Přidá nový soubor do úložiště. Pokud již takový soubor existuje, nový bude přejmenován a původní ponechán beze změny.
	 * @param string $path Cesta k zdrojovému souboru pro přidání
	 * @param string $newFilename Null pro ponechání beze změny
	 * @param string $context Dodatečné info pro rozdělení
	 * @return string Cesta k výslednému souboru (ten se nemusí jmenovat stejně, jako zdrojový).
	 */
	function add($path,$newFilename=null,$context=null) {
		return $this->doImporting($path, $context, $newFilename, false);
	}

	/**
	 * Přidá nový soubor do úložiště.  Pokud již takový soubor existuje, bude přepsán novým.
	 * @param string $path Cesta ke zdrojovému souboru.
	 * @param string $newFilename Null pro ponechání beze změny
	 * @param string $context Dodatečné info pro rozdělování
	 * @return string Cesta k výslednému souboru (ten se nemusí jmenovat stejně, jako zdrojový).
	 */
	function import($path,$newFilename=null,$context=null) {
		return $this->doImporting($path, $context, $newFilename, true);
	}

	protected function doImporting($path,$context=null,$newFilename=null,$overwrite=true) {
		if (!file_exists($path) or !is_readable($path)) {
			throw new FileException("Missing on unreadable file $path");
		}
		if (!$newFilename) {
			$newFilename=Files::filename($path);
		}
		$newFilename=$this->processFilename($newFilename);
		$target=$this->getPath($newFilename,$context);

		$this->buildDirectories($newFilename,$context);
		$targetDirectory=Files::dir($target);

		$needsDeleting="";
		if (file_exists($target)) {
			if ($overwrite) {
				$needsDeleting=$target;
			} else {
				$betterFilename=Files::freeFilename($targetDirectory, $newFilename);
				$newFilename=$betterFilename;
				$target=$this->getPath($newFilename,$context);
			}
		}
		try {
			$this->onBeforeAdd($target,$newFilename,$context,$path);
		} catch (StopActionException $e) {
			$this->writeToLog("Adding to \"$target\" from \"$path\" stopped by onBeforeAdd");
			return;
		}

		if ($needsDeleting) {
			$u=unlink($needsDeleting);
			if (!$u) {
				$this->writeToLog("Could not delete file $needsDeleting to overwrite it with new one.");
				throw new \RuntimeException("Could not delete file $needsDeleting");
			}
		}

		$u=copy($path,$target);
		if (!$u) {
			$this->writeToLog("Failed copying from $path to $target");
			throw new \RuntimeException("Failed copying from $path to $target");
		} else {
			$this->writeToLog("Added \"$target\" from \"$path\"");
		}
		Files::perms($target);
		$this->onAdd($target,$newFilename,$context,$path);
		return $target;
	}

	/**
	 * Smaže soubor
	 * @param string $filename
	 * @param string $context
	 * @return boolean Dle úspěchu.
	 */
	function delete($filename,$context=null) {
		if ($this->exists($filename,$context)) {
			$path=$this->getPath($filename, $context);
			try {
				$this->onBeforeDelete($path,$filename,$context);
			} catch (StopActionException $e) {
				$this->writeToLog("Deleting \"$path\" was stopped by onBeforeDelete");
				return false;
			}
			$ok=Files::remove($path, true);
			if ($ok) {
				$this->writeToLog("Deleted \"$path\"");
				$this->onDelete($path,$filename,$context);
			}
			return $ok;
		}
		return false;
	}

	/**
	 * Ověří, zda soubor existuje.
	 * @param string $filename
	 * @param string $context
	 * @return bool
	 */
	function exists($filename,$context=null) {
		return file_exists($this->getPath($filename, $context));
	}

	function getBasePath() {
		return $this->basePath;
	}

	function getBaseHref() {
		return $this->baseHref;
	}

	/**
	 * Vygeneruje cestu k určitému souboru
	 * @param string $filename Jméno souboru (jen souboru, bez adresářů)
	 * @param string $context
	 * @throws \InvalidArgumentException
	 * @return string
	 */
	function getPath($filename,$context=null) {
		if (!$filename or strpos($filename,"/")!==false) {
			throw new \InvalidArgumentException("Invalid \$filename: $filename");
		}
		$path=$this->basePath;
		if ($context) $path.="/".$context;
		for ($i=0;$i<$this->directoryLevels;$i++) {
			$dir=$this->getLevelPartOfFilename($filename, $i+1);
			$path.="/".$dir;
		}
		$path.="/".$filename;
		return $path;
	}

	/**
	 * Vygeneruje odkaz k určitému souboru
	 * @param string $filename Jméno souboru (jen souboru, bez adresářů)
	 * @param string $context
	 * @throws \InvalidArgumentException
	 * @return string
	 */
	function getHref($filename,$context=null) {
		if (!$filename or strpos($filename,"/")!==false) {
			throw new \InvalidArgumentException("Invalid \$filename: $filename");
		}
		$href=$this->baseHref;
		if ($context) $href.="/".$context;
		for ($i=0;$i<$this->directoryLevels;$i++) {
			$dir=$this->getLevelPartOfFilename($filename, $i+1);
			$href.="/".$dir;
		}
		$href.="/".$filename;
		return $href;
	}

	function setCheckFilenames($check) {
		$this->checkFilenames=$check?true:false;
	}

	function getCheckFilenames() {
		return $this->checkFilenames;
	}

	function setStrictMode($mode) {
		$this->strictMode=$mode?true:false;
	}

	function getStrictMode() {
		return $this->strictMode;
	}

	protected function buildDirectories($filename,$context=null) {
		$path=$this->basePath;
		if ($context) {
			Files::mkdir($path."/".$context);
			$path=$path."/".$context;
		}
		for ($i=0;$i<$this->directoryLevels;$i++) {
			$dir=$this->getLevelPartOfFilename($filename, $i+1);
			$path.="/".$dir;
			Files::mkdir($path);
		}
	}

	function setDirectoryLevels($dirs) {
		if (!is_numeric($dirs) or $dirs<0 or $dirs>5) {
			throw new \InvalidArgumentException("\$dirs should be between 0 and 5.");
		}
		$this->directoryLevels=$dirs;
	}

	function getDirectoryLevels() {
		return $this->directoryLevels;
	}

	/**
	 * Pokusí se rozparsovat zadanou cestu zpět na $filename a příapdně i $context
	 * @param string $path Musí jít o soubor (ne adresář) uvnitř $this->basePath
	 * @return boolean|array Buď false (tj. soubor je na nestandardní cestě a nelze rozpoznat, co je zač, tj. teoreticky nemusí jít o garbage), nebo array($filename,$context).
	 * @throws \InvalidArgumentException Pokud je $path mimo $this->basePath
	 * @throws \Nette\FileNotFoundException Pokud soubor $path je adresář nebo neexistuje vůbec
	 */
	function pathToFileData($path) {
		$basePathLength=Strings::length($this->basePath);
		$beginning=Strings::substring($path,0,$basePathLength);
		if ($beginning!=$this->basePath) {
			throw new \InvalidArgumentException("\"$path\" is not in \"$this->basePath\"");
		};
		if (!file_exists($path) or is_dir($path)) {
			throw new FileException("\"$path\" is not a valid file - either it is a directory, or that file is not found.");
		}
		$nextPath=Strings::substring($path,$basePathLength);
		if ($nextPath[0]=="/") $nextPath=Strings::substring($nextPath,1);
		$parts=explode("/",$nextPath);
		$filename=$parts[count($parts)-1];
		if (count($parts)==1+$this->directoryLevels) {
			$context=null;
			for ($i=1;$i<=$this->directoryLevels;$i++) {
				$piece=$this->getLevelPartOfFilename($filename,$i);
				if ($parts[$i-1]!=$piece) return false;
			}
			return array($filename,$context);
		}
		else if (count($parts)==2+$this->directoryLevels) {
			$context=$parts[0];
			for ($i=1;$i<=$this->directoryLevels;$i++) {
				$piece=$this->getLevelPartOfFilename($filename,$i);
				if ($parts[$i]!=$piece) return false;
			}
			return array($filename,$context);
		} else {
			return false;
		}
	}

	protected function processFilename($filename) {
		return Files::safeName($filename,$this->unsafeExtensions);
	}

	function getLevelPartOfFilename($filename,$level=1) {
		$filename=Files::filenameWithoutExtension($filename);
		if (Strings::length($filename)>=$level) {
			$part=Strings::substring($filename, $level-1, 1);
		} else {
			$part="0";
		}
		$part=Strings::lower(Strings::toAscii($part));
		if (!preg_match('~[a-z0-9]~', $part)) {
			$part="0";
		}

		return $part;
	}

	protected function writeToLog($message) {
		if ($this->logger) {
			$this->logger->log($message);
		}
	}



	/*  ------ Garbage collection ------ */

	protected $garbageCollectorDb;

	protected $garbageCollectorTableName;

	protected $garbageCollectorColumnFilename;

	protected $garbageCollectorColumnContext;

	protected $garbageCollectorFile;

	protected $garbageCollectorPeriodicity;

	protected $garbageCollectorData;

	protected $garbageCollectorAction,$garbageCollectorActionTarget;

	/**
	 * Nastavení pro GarbageCollector. Nic nedělá, lze volat při konfiguraci.
	 * @param \Nette\Database\Context $db
	 * @param string $tableName
	 * @param string $columnFilename
	 * @param string|null $columnContext null = bez kontextů
	 * @param string|null $dataFile null = bez automatické správy periodicity
	 * @param int|null $periodicity
	 */
	function setupGarbageCollector(\Nette\Database\Context $db,$tableName,$columnFilename,$columnContext=null,$dataFile=null,$periodicity=86400,$action=FileGarbageCollector::DELETE,$actionTarget=NULL) {
		$this->garbageCollectorDb=$db;
		$this->garbageCollectorTableName=$tableName;
		$this->garbageCollectorColumnFilename=$columnFilename;
		$this->garbageCollectorColumnContext=$columnContext;
		$this->garbageCollectorFile=$dataFile;
		$this->garbageCollectorPeriodicity=$periodicity;
		$this->garbageCollectorAction=$action;
		$this->garbageCollectorActionTarget=$actionTarget;
	}

	/**
	 * Vytvoří Garbage Collector
	 * @return FileGarbageCollector
	 * @throws \Nette\InvalidStateException Předtím musí být zavolíno setupGarbageCollector
	 */
	function createGarbageCollector() {
		if (!$this->garbageCollectorDb) {
			throw new \Nette\InvalidStateException("Call setupGarbageCollector first!");
		}

		$gc=new FileGarbageCollector(
			$this->basePath,
			array($this,"verifyFileInGarbageCollection"),
			$this->garbageCollectorFile,
			$this->garbageCollectorPeriodicity,
			$this->logger
		);

		$gc->setAction($this->garbageCollectorAction, $this->garbageCollectorActionTarget);

		$gc->onBeforeRun[]= array($this,"prepareGarbageCollection");

		return $gc;
	}

	/**
	 * Loadne do interní cache z databáze, jaké soubory jsou povoleny.
	 * Volá se automaticky v rámci FileGarnageCollector->onBeforeRun(). Pokud chceš jen
	 * použít getGarbageFiles(), pak je třeba tuto metodu zavolat ručně.
	 * @param FileGarbageCollector $gc
	 */
	function prepareGarbageCollection($gc=null) {
		$this->garbageCollectorData=array();
		$table=$this->garbageCollectorDb->table($this->garbageCollectorTableName);
		$table->select($this->garbageCollectorColumnFilename);
		if ($this->garbageCollectorColumnContext) $table->select($this->garbageCollectorColumnContext);

		if ($this->garbageCollectorColumnContext) {
			foreach($table as $row) {
				$this->garbageCollectorData
					[$row[$this->garbageCollectorColumnContext]]
					[$row[$this->garbageCollectorColumnFilename]]
						=true;
			}
		} else {
			foreach($table as $row) {
				$this->garbageCollectorData
					[$row[$this->garbageCollectorColumnFilename]]
						=true;
			}
		}
	}

	/**
	 * Vyčistí interní cache legitimních souborů vytvořenou během prepareGarbageCollector.
	 * Volá se automaticky v rámci FileGarbageCollector->onAfterRun().
	 * @param array $files Not requied if called manually. Provided by GC callback.
	 * @param FileGarbgeCollector $gc Not requied if called manually. Provided by GC callback.
	 */
	function afterGarbageCollection($files=null,$gc=null) {
		$this->garbageCollectorData=null;
	}

	/**
	 * Callback pro FileGarbageCollector. Lze použít k ověření, zda soubor $filename je
	 * legitimní nebo zda jde o smetí.
	 * @param string $filename
	 * @param FileGarbageCollector $gc
	 * @return bool
	 */
	function verifyFileInGarbageCollection($filename,$gc = null) {
		if ($this->garbageCollectorData===null) {
			throw new \Nette\InvalidStateException("prepareGarbageCollection() must not called before. This should be, however, done automatically...");
		}
		$fileData=$this->pathToFileData($filename);
		if ($fileData===false) {
			return $this->strictMode;
		}
		list($file,$context)=$fileData;
		if ($this->garbageCollectorColumnContext) {
			return !isset(
				$this->garbageCollectorData
					[$context]
					[$file]
			);
		} else {
			return !isset(
				$this->garbageCollectorData
					[$file]
			);
		}
	}
}
