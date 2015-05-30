<?php

include "../bootstrap.php";
$db=require("../db.php");

use \Nette;
use \Tester\Assert;
use \OndraKoupil\Nette\FileGarbageCollector;
use \OndraKoupil\Testing\FilesTestCase;
use \OndraKoupil\Testing\Assert as OKAssert;
use \OndraKoupil\Tools\Files;
use \OndraKoupil\Tools\Strings;
use \OndraKoupil\Nette\Logger;

class FileGarbageCollectorTestCase extends FilesTestCase {

	function testPeriodicity() {
		$dir=$this->createTempDir();
		Files::mkdir($dir."/conf");
		Files::mkdir($dir."/files");

		$gc=new FileGarbageCollector($dir."/files", function() {
			return false;
		}, $dir."/conf/gc.txt");

		Assert::true($gc->checkIfGarbageCollectionIsNeeded());
		Assert::true(file_exists($dir."/conf/gc.txt"));

		file_put_contents($dir."/conf/gc.txt", time()-86400*2);
		Assert::true($gc->checkIfGarbageCollectionIsNeeded());

		$gc->setPeriodicity(86400*5);
		Assert::false($gc->checkIfGarbageCollectionIsNeeded());

		Assert::equal(time()-86400*2, $gc->getLastRunDate());

		$gc2=new FileGarbageCollector($dir."/files", function() {
			return false;
		});

		Assert::false($gc2->checkIfGarbageCollectionIsNeeded());
		Assert::equal(0,$gc2->getLastRunDate());
	}

	function testGetGarbageFiles() {
		$dir=$this->createTempDir();
		$filesDir=$dir."/files";
		Files::mkdir($dir."/conf");
		Files::mkdir($filesDir);

		$arguments=array();

		$gc=new FileGarbageCollector($dir."/files", function($filename,$gc) use (&$arguments) {
			$arguments[]=$filename;
			return (Strings::startsWith(Files::filename($filename),"a"));
		}, $dir."/conf/gc.txt");

		$filelist=array(
			$filesDir."/abc.txt",
			$filesDir."/bcde.txt",
			$filesDir."/aaaa.txt",
			$filesDir."/xyz.txt",
			$filesDir."/sub/abc.txt",
			$filesDir."/sub/def.txt"
		);
		foreach($filelist as $f) {
			Files::create($f);
		}

		$garbage=$gc->getGarbageFiles();
		Assert::equal(6,count($arguments));
		OKAssert::arrayEqual(array(
			$filesDir."/abc.txt",
			$filesDir."/aaaa.txt",
			$filesDir."/sub/abc.txt"
		), $garbage);
	}

	function testProcessGarbageFile() {
		$dir=$this->createTempDir();
		$gc=new FileGarbageCollector($dir, function($file,$gc) {
			return true;
		});

		$gc->setAction(FileGarbageCollector::DELETE);

		Files::create($dir."/abc.txt");
		Assert::true(file_exists($dir."/abc.txt"));
		$gc->processGarbageFile($dir."/abc.txt");
		Assert::false(file_exists($dir."/abc.txt"));

		Files::create($dir."/files/def.txt");
		Files::create($dir."/files/something/somewhere/klm.txt");
		Files::create($dir."/files/something/somewhere/def.txt");
		Files::mkdir($dir."/outs");
		$gc->setDirectory($dir."/files");

		$gc->setAction(FileGarbageCollector::MOVE_WITHOUT_SUBDIRECTORIES, $dir."/outs");
		$gc->processGarbageFile($dir."/files/def.txt");
		$gc->processGarbageFile($dir."/files/something/somewhere/klm.txt");
		$gc->processGarbageFile($dir."/files/something/somewhere/def.txt");
		Assert::false(file_exists($dir."/files/def.txt"));
		Assert::false(file_exists($dir."/files/something/somewhere/klm.txt"));
		Assert::false(file_exists($dir."/files/something/somewhere/def.txt"));
		Assert::true(file_exists($dir."/outs/def.txt"));
		Assert::true(file_exists($dir."/outs/def-2.txt"));
		Assert::true(file_exists($dir."/outs/klm.txt"));

		Files::purgeDir($dir."/outs");

		Files::create($dir."/files/def.txt");
		Files::create($dir."/files/something/somewhere/klm.txt");
		Files::create($dir."/files/something/somewhere/def.txt");
		$gc->setAction(FileGarbageCollector::MOVE, $dir."/outs");
		$gc->processGarbageFile($dir."/files/def.txt");
		$gc->processGarbageFile($dir."/files/something/somewhere/klm.txt");
		$gc->processGarbageFile($dir."/files/something/somewhere/def.txt");
		Assert::false(file_exists($dir."/files/def.txt"));
		Assert::false(file_exists($dir."/files/something/somewhere/klm.txt"));
		Assert::false(file_exists($dir."/files/something/somewhere/def.txt"));
		Assert::true(file_exists($dir."/outs/something/somewhere/def.txt"));
		Assert::true(file_exists($dir."/outs/def.txt"));
		Assert::true(file_exists($dir."/outs/something/somewhere/klm.txt"));

		Files::purgeDir($dir."/outs");

		$arguments=array();
		Files::create($dir."/files/def.txt");
		Files::create($dir."/files/something/somewhere/klm.txt");
		Files::create($dir."/files/something/somewhere/def.txt");
		$gc->setAction(FileGarbageCollector::CALLBACK, function($file, $garbc) use ($gc, &$arguments) {
			Assert::same($gc, $garbc);
			$arguments[]=$file;
		});
		$gc->processGarbageFile($dir."/files/def.txt");
		$gc->processGarbageFile($dir."/files/something/somewhere/klm.txt");
		$gc->processGarbageFile($dir."/files/something/somewhere/def.txt");
		Assert::true(file_exists($dir."/files/def.txt"));
		Assert::true(file_exists($dir."/files/something/somewhere/klm.txt"));
		Assert::true(file_exists($dir."/files/something/somewhere/def.txt"));
		OKAssert::arrayEqual(array(
			$dir."/files/def.txt",
			$dir."/files/something/somewhere/klm.txt",
			$dir."/files/something/somewhere/def.txt"
		), $arguments);

		$gc->setAction(FileGarbageCollector::DELETE);
		$gc->setDataFilename($dir."/gc.last.txt");
		$gc->runNow();
		foreach(Nette\Utils\Finder::findFiles("*")->from($dir."/files") as $f) {
			Assert::fail("$f should not be there.");
		}
		Assert::equal(time(), (int)file_get_contents($dir."/gc.last.txt"));
		Assert::equal(time(), $gc->getLastRunDate());

	}

	function testLogger() {
		$dir=$this->createTempDir();
		Files::create($dir."/files/abc.txt");
		Files::create($dir."/files/def.txt");
		Files::mkdir($dir."/log");

		$gc=new FileGarbageCollector($dir."/files", function($file) {
			return true;
		});

		$l=new Logger($dir."/log/gctest.log");
		$gc->logger=$l;

		$gc->runNow();

		$logContents=file_get_contents($dir."/log/gctest.log");
		Assert::contains($dir."/files/abc.txt", $logContents);
		Assert::contains($dir."/files/def.txt", $logContents);

	}

	function testCallbacks() {
		$dir=$this->createTempDir();
		Files::create($dir."/abc.txt");
		Files::create($dir."/def.txt");
		Files::create($dir."/sub/xyz.txt");

		$gc=new FileGarbageCollector($dir, function($file,$gc) {
			return true;
		});

		$beforeRun=false;
		$afterRun=false;
		$gc->onBeforeRun[]=function($_gc) use (&$beforeRun,$gc) {
			Assert::same($gc,$_gc);
			$beforeRun=true;
			throw new \OndraKoupil\Nette\Exceptions\StopActionException();
		};
		$gc->onAfterRun[]=function($files,$_gc) use (&$afterRun,$gc) {
			Assert::same($gc,$_gc);
			$afterRun=true;
		};

		// Test Before run
		$out=$gc->runNow();
		Assert::equal(array(),$out);
		Assert::true($beforeRun);
		Assert::false($afterRun);
		Assert::true(file_exists($dir."/abc.txt"));

		// Test After run
		$beforeRun=false;
		$gc->onBeforeRun=array();
		$out=$gc->runNow();
		Assert::equal(3,count($out));
		Assert::false($beforeRun);
		Assert::true($afterRun);
		Assert::false(file_exists($dir."/abc.txt"));


		// Test files callback
		$arguments=array();
		Files::create($dir."/abc.txt");
		Files::create($dir."/def.txt");
		Files::create($dir."/sub/xyz.txt");
		$gc->onGarbageFile[]=function($file,$_gc) use ($gc,&$arguments) {
			$arguments[]=$file;
			if (Strings::startsWith(Files::filename($file),"a")) {
				throw new \OndraKoupil\Nette\Exceptions\StopActionException();
			}
		};
		$out=$gc->runNow();
		Assert::equal(2, count($out));
		Assert::equal(3, count($arguments));
		Assert::true(file_exists($dir."/abc.txt"));
		Assert::false(file_exists($dir."/def.txt"));
	}

}

$a=new FileGarbageCollectorTestCase();
$a->run();