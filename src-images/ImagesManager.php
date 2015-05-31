<?php

namespace OndraKoupil\Images;

use \OndraKoupil\Tools\Files;
use \OndraKoupil\Nette\Logger;
use \OndraKoupil\Tools\Time;

/**
* @deprecated Will use Palette somedays
*/

class ImagesManager extends \Nette\Object {

	/**
	 * @var \Nette\Database\Context
	 */
	private $db;

	/**
	 * @var string
	 */
	private $dbTableName;

	/**
	 * @var ImageQueryParser
	 */
	private $imageQueryParser;

	/**
	 * @var Logger
	 */
	private $logger;

	/**
	 * How long should be thumbnails kept from last access? In seconds.
	 * @property
	 * @var int
	 */
	private $ttl=2592000; // 30 days

	/**
	 * @property-read
	 * @var IFileFinder
	 */
	private $fileFinder;

	// TODO: Batch preloading from DB

	function __construct(\Nette\Database\Context $database, $tableName, IFileFinder $fileFinder, Logger $logger=null) {
		$this->db=$database;
		$this->dbTableName=$tableName;
		$this->fileFinder=$fileFinder;
		$this->logger=$logger;
	}

	function setTTL($ttl=2592000) {
		if (is_numeric($ttl)) {
			$this->ttl=$ttl;
		} else {
			throw new \InvalidArgumentException("TTL must be numeric (in seconds), \"$ttl\" given.");
		}
	}

	function getFileFinder() {
		return $this->fileFinder;
	}

	function getTTL() {
		return $this->ttl;
	}

	function createImageQueryParser() {
		if (!$this->imageQueryParser) {
			$this->imageQueryParser=new ImageQueryParser();
		}
		return $this->imageQueryParser;
	}

	function getTransformedImage($source,$imageQuery) {
		if (!($imageQuery instanceof Transformation)) {
			$transformation=$this->createImageQueryParser()->parse($imageQuery);
		} else {
			$transformation=$imageQuery;
		}

		if (!$transformation) {
			return $source;
		}

		$signature=$transformation->getSignature();

		$row=$this->checkIfImageIsPrepared($source,$signature);
		if ($row) {
			$this->updateLastAccessInDb($row);
			return $row["output"];
		}

		try {
			$transformedResource=$this->transformImage($source,$transformation);
		} catch (\Exception $e) {
			$this->writeToLog("Failed transforming image \"$source\"! Exception: ".$e->getMessage());
			return "";
		}
		$outputPath=$this->fileFinder->getPath($source,$signature,true);
		$this->saveToDb($source,$signature,$outputPath);
		$transformedResource->save($outputPath);
		$this->writeToLog("Transforming image \"$source\" to \"$outputPath\" using IQ \"$imageQuery\"");
		return $outputPath;
	}

	function getTransformedHref($sourceFilePath,$imageQuery) {
		$path=$this->getTransformedImage($sourceFilePath, $imageQuery);
		if ($path) {
			return $this->fileFinder->getHrefFromPath($path);
		} else {
			return "";
		}
	}

	/**
	 *
	 * @param string $source
	 * @param Transformation $transformation
	 * @return ImageResource
	 */
	function transformImage($source, Transformation $transformation) {
		$resource=new ImageResource($source);
		$resource->apply($transformation);
		return $resource;
	}

	/**
	 * @return \Nette\Database\Table\Selection
	 */
	private function getTable() {
		return $this->db->table($this->dbTableName);
	}

	function saveToDb($source,$signature,$output) {
		$data=array(
			"source"=>$source,
			"signature"=>$signature,
			"output"=>$output,
			"created"=>time(),
			"last_access"=>time()
		);
		$this->getTable()->insert($data);
	}

	function updateLastAccessInDb(\Nette\Database\Table\ActiveRow $row) {
		$row->update(array("last_access"=>time()));
	}

	function checkIfImageIsPrepared($source,$signature) {
		$u=$this->getTable()->where("source",$source)->where("signature",$signature)->fetch();
		if (!$u) return false;

		if (!file_exists($source)) return false;
		if (!file_exists($u["output"]) or is_dir($u["output"])) {
			$this->invalidateDatabaseRow($u,"output file \"$u[output]\" does not exist anymore.");
			return false;
		}
		$sourceMTime=filemtime($source);
		$transformedMTime=filemtime($u["output"]);

		if ($sourceMTime>$transformedMTime) {
			$this->invalidateDatabaseRow($u,"original file is newer (see next row in this log)");
			$this->invalidateFile($u["output"], "original file is newer (source ".Time::convert($sourceMTime, Time::MYSQL)." vs. transformed ".Time::convert($transformedMTime, Time::MYSQL).")");
			return false;
		}

		return $u;
	}

	function invalidateFile($filename, $detailsToLog="") {
		$this->writeToLog("Deleting \"$filename\"".($detailsToLog?", $detailsToLog":""));
		if (file_exists($filename) and !is_dir($filename)) {
			Files::remove($filename);
		}
	}

	function invalidateDatabaseRow(\Nette\Database\Table\ActiveRow $row, $detailsToLog="") {
		$this->writeToLog("Deleting from database: source \"$row[source]\", signature \"$row[signature]\"".($detailsToLog?", $detailsToLog":""));
		$row->delete();
	}

	private function writeToLog($message) {
		if ($this->logger) {
			$this->logger->log($message);
		}
	}

	function garbageCollection() {
		$this->writeToLog("Starting garbage collection");
		$this->garbageCollectionDb();
		$this->writeToLog("Finished garbage collection");
	}

	private function garbageCollectionDb() {
		$tooOldFiles=$this->getTable()->where("last_access < ?",time()-$this->ttl);

		foreach($tooOldFiles as $file) {
			$reason="Thumbnail not used too long, probably forgotten (last access ".Time::convert($file["last_access"], Time::MYSQL).")";
			$this->invalidateFile($file["output"], $reason);
			$this->invalidateDatabaseRow($file, $reason);
		}

		// TODO: soubory, u nichž source již není k nalezení
	}

}
