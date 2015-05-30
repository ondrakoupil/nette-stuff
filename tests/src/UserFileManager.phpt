<?php


include "../bootstrap.php";
$db=require("../db.php");

use \Tester\Assert;
use \OndraKoupil\Testing\FilesTestCase;
use \OndraKoupil\Testing\NetteDatabaseTestCase;
use OndraKoupil\Testing\Assert as OKAssert;
use \OndraKoupil\Nette\UserFileManager;
use \OndraKoupil\Nette\Logger;
use \OndraKoupil\Tools\Files;
use \OndraKoupil\Nette\Exceptions\StopActionException;
use \OndraKoupil\Nette\FileGarbageCollector;

class UserFileManagerTestCase extends FilesTestCase {

	function testLevelPart() {
		$dir=$this->createTempDir();
		$um=new UserFileManager($dir);

		Assert::equal("a",$um->getLevelPartOfFilename("abcde.txt", 1));
		Assert::equal("b",$um->getLevelPartOfFilename("aBCde.txt", 2));
		Assert::equal("c",$um->getLevelPartOfFilename("abcde.txt", 3));
		Assert::equal("0",$um->getLevelPartOfFilename("a.txt", 2));
		Assert::equal("0",$um->getLevelPartOfFilename("a-cde.txt", 2));
		Assert::equal("z",$um->getLevelPartOfFilename("žába.txt", 1));
	}

	function testPathAndHref() {
		$dir=$this->createTempDir();
		$hrefPrefix="http://localhost/files/";
		$um=new UserFileManager($dir,$hrefPrefix);

		Assert::true(file_exists($dir));
		Assert::equal($dir,$um->basePath);
		Assert::equal($hrefPrefix,$um->baseHref);

		Assert::exception(function() use ($um) {
			$um->getPath("../big/evil/path.txt");
		}, '\InvalidArgumentException');
		Assert::exception(function() use ($um) {
			$um->getHref("../big/evil/path.txt");
		}, '\InvalidArgumentException');

		$path1=$um->getPath("abc.txt");
		$href1=$um->getHref("abc.txt");
		Assert::equal($dir."/abc.txt", $path1);
		Assert::equal($hrefPrefix."/abc.txt", $href1);

		$path2=$um->getPath("bbb.txt","a102");
		$href2=$um->getHref("bbb.txt","a102");
		Assert::equal($dir."/a102/bbb.txt", $path2);
		Assert::equal($hrefPrefix."/a102/bbb.txt", $href2);

		$um->setDirectoryLevels(1);
		$path3=$um->getPath("cde.txt");
		$href3=$um->getHref("cde.txt");
		Assert::equal($dir."/c/cde.txt", $path3);
		Assert::equal($hrefPrefix."/c/cde.txt", $href3);

		$path4=$um->getPath("dxa.txt","r101");
		$href4=$um->getHref("dxa.txt","r101");
		Assert::equal($dir."/r101/d/dxa.txt", $path4);
		Assert::equal($hrefPrefix."/r101/d/dxa.txt", $href4);

		$um->setDirectoryLevels(3);
		$path3=$um->getPath("cdefg.txt");
		$href3=$um->getHref("cdefg.txt");
		Assert::equal($dir."/c/d/e/cdefg.txt", $path3);
		Assert::equal($hrefPrefix."/c/d/e/cdefg.txt", $href3);

		$path4=$um->getPath("dxaac.txt","r101");
		$href4=$um->getHref("dxaac.txt","r101");
		Assert::equal($dir."/r101/d/x/a/dxaac.txt", $path4);
		Assert::equal($hrefPrefix."/r101/d/x/a/dxaac.txt", $href4);

	}

	function testOperations() {
		$dir=$this->createTempDir();
		$um=new UserFileManager($dir,null,1);

		Files::mkdir($dir."/src");
		$srcfile=Files::create($dir."/src/test.txt");
		file_put_contents($srcfile,"hello");

		// basics
		Assert::false($um->exists("test.txt"));
		$um->add($srcfile);
		Assert::equal($dir."/t/test.txt",$um->getPath("test.txt"));
		Assert::true(file_exists($um->getPath("test.txt")));
		Assert::equal(5,filesize($um->getPath("test.txt")));
		Assert::true($um->exists("test.txt"));

		$um->add($srcfile,"second-test.txt");
		Assert::true(file_exists($um->getPath("second-test.txt")));
		Assert::equal(5,filesize($um->getPath("second-test.txt")));

		// not overwriting
		$out=$um->add($srcfile,"second-test.txt");
		Assert::equal($dir.'/s/second-test-2.txt',$out);
		Assert::notEqual($um->getPath("second-test.txt"), $out);
		Assert::true(file_exists($out));
		Assert::equal(5,filesize($out));

		// overwriting
		file_put_contents($srcfile,"nazdar!");
		$out=$um->import($srcfile,"second-test.txt");
		Assert::equal($dir.'/s/second-test.txt',$out);
		Assert::equal($um->getPath("second-test.txt"), $out);
		Assert::equal(7,filesize($out));

		// deleting
		Assert::true($um->delete("second-test.txt"));
		Assert::false($um->delete("nothing.txt"));
		Assert::true($um->exists("test.txt"));
		Assert::false(file_exists($dir."/s/second-test.txt"));
		Assert::true(file_exists($dir."/t/test.txt"));
		Assert::true($um->delete("test.txt"));
		Assert::false($um->exists("test.txt"));
		Assert::false(file_exists($dir."/t/test.txt"));
	}

	function testLogging() {
		$dir=$this->createTempDir();
		$logfile=$dir."/log.txt";
		$logger=new Logger($logfile);

		$um=new UserFileManager($dir);
		$um->logger=$logger;

		Assert::false(file_exists($logfile));

		$srcfile=$dir."/src.txt";
		file_put_contents($srcfile, "ahoj");
		$um->add($srcfile,"out.txt");

		Assert::notEqual(0, filesize($logfile));
		$logContents=file_get_contents($logfile);

		Assert::truthy(preg_match('~out.txt~',$logContents));
		Assert::truthy(preg_match('~src.txt~',$logContents));

	}

	function testUnsafeExtensions() {
		$dir=$this->createTempDir();
		$um=new UserFileManager($dir);
		Files::mkdir($dir."/src");
		$srcfile=Files::create($dir."/src/test.php");
		file_put_contents($srcfile,"hello");

		$out=$um->add($srcfile);
		Assert::equal("test.txt", Files::filename($out));
		Assert::false(file_exists($dir."/test.php"));
		Assert::true(file_exists($out));
		Assert::equal("hello",file_get_contents($out));

		$out=$um->add($srcfile,"second.PHP");
		Assert::equal("second.txt", Files::filename($out));
		Assert::false(file_exists($dir."/second.PHP"));
		Assert::true(file_exists($out));
		Assert::equal("hello",file_get_contents($out));

		$um->setUnsafeExtensions(array("php","doc"));
		$out=$um->add($srcfile,"third.doc");
		Assert::equal("third.txt", Files::filename($out));
		Assert::false(file_exists($dir."/third.doc"));
		Assert::true(file_exists($out));
		Assert::equal("hello",file_get_contents($out));
	}

	function testCallbacks() {
		$dir=$this->getTempDir();
		$um=new UserFileManager($dir);
		Files::mkdir($dir."/src");
		$srcfile=Files::create($dir."/src/test.txt");
		file_put_contents($srcfile,"hello");

		$addCalled=0;
		$deleteCalled=0;
		$arguments=array();

		$um->onAdd[]=function($a,$b,$c,$d) use (&$addCalled,&$arguments) {
			$addCalled++;
			$args=func_get_args();
			$arguments[]=$args;
		};

		$um->onDelete[]=function($a,$b,$c) use (&$deleteCalled,&$arguments) {
			$deleteCalled++;
			$args=func_get_args();
			$arguments[]=$args;
		};

		$um->import($srcfile);
		$um->import($srcfile,"second.txt");
		Assert::equal(2, $addCalled);
		Assert::equal(0, $deleteCalled);
		Assert::true(file_exists($um->getPath("test.txt")));
		Assert::equal($arguments[1][0], $um->getPath("second.txt"));
		Assert::equal($arguments[1][1], "second.txt");
		Assert::equal($arguments[1][2], null);
		Assert::equal($arguments[1][3], $srcfile);

		$um->delete(Files::filename($srcfile));
		Assert::equal(2, $addCalled);
		Assert::equal(1, $deleteCalled);
		Assert::equal($arguments[2][0], $um->getPath("test.txt"));
		Assert::equal($arguments[2][1], "test.txt");
		Assert::equal($arguments[2][2], null);

		$um->onBeforeAdd[]=function($a,$b,$c,$d) use (&$arguments) {
			$args=func_get_args();
			$arguments[]=$args;
			throw new StopActionException();
		};

		$um->onBeforeDelete[]=function($a,$b,$c) use (&$arguments) {
			$args=func_get_args();
			$arguments[]=$args;
			throw new StopActionException();
		};

		$um->delete("second.txt");
		Assert::equal(2, $addCalled);
		Assert::equal(1, $deleteCalled);
		Assert::true(file_exists($um->getPath("second.txt")));
		Assert::equal($arguments[3][0], $um->getPath("second.txt"));
		Assert::equal($arguments[3][1], "second.txt");
		Assert::equal($arguments[3][2], null);

		$um->add($srcfile,"third.txt");
		Assert::equal(2, $addCalled);
		Assert::equal(1, $deleteCalled);
		Assert::false(file_exists($um->getPath("third.txt")));
		Assert::equal($arguments[4][0], $um->getPath("third.txt"));
		Assert::equal($arguments[4][1], "third.txt");
		Assert::equal($arguments[4][2], null);
		Assert::equal($arguments[4][3], $srcfile);
	}

	function testPathToFileData() {
		$dir=$this->createTempDir();
		$ufm=new UserFileManager($dir,null,0);

		Assert::exception(function() use ($ufm,$dir) {
			$ufm->pathToFileData("../../somepath/somewhere");
		}, '\InvalidArgumentException');

		Assert::exception(function() use ($ufm,$dir) {
			$ufm->pathToFileData($dir."/abcde");
		}, '\OndraKoupil\Tools\Exceptions\FileException');

		Assert::exception(function() use ($ufm,$dir) {
			$ufm->pathToFileData($dir);
		}, '\OndraKoupil\Tools\Exceptions\FileException');

		Files::create($dir."/abcde.txt");
		Files::create($dir."/102/abcde.txt");
		Files::create($dir."/102/a/b/abcde.txt");
		Files::create($dir."/106/a.txt");

		Assert::equal(array("abcde.txt",null),$ufm->pathToFileData($dir."/abcde.txt"));
		Assert::equal(array("abcde.txt","102"),$ufm->pathToFileData($dir."/102/abcde.txt"));
		Assert::equal(false,$ufm->pathToFileData($dir."/102/a/b/abcde.txt"));
		Assert::equal(array("a.txt","106"),$ufm->pathToFileData($dir."/106/a.txt"));

		$ufm->setDirectoryLevels(1);
		Files::create($dir."/102/6/6-test.txt");
		Assert::equal(array("6-test.txt","102"),$ufm->pathToFileData($dir."/102/6/6-test.txt"));
		Files::create($dir."/8/8-test.txt");
		Assert::equal(array("8-test.txt",null),$ufm->pathToFileData($dir."/8/8-test.txt"));
		Files::create($dir."/missed.txt");
		Assert::equal(false,$ufm->pathToFileData($dir."/missed.txt"));
		Files::create($dir."/a/bat.sh");
		Assert::equal(false,$ufm->pathToFileData($dir."/a/bat.sh"));


		$ufm->setDirectoryLevels(3);
		Files::create($dir."/102/6/0/t/6-test.txt");
		Assert::equal(array("6-test.txt","102"),$ufm->pathToFileData($dir."/102/6/0/t/6-test.txt"));
		Files::create($dir."/102/6/0/x/6-test.txt");
		Assert::equal(false,$ufm->pathToFileData($dir."/102/6/0/x/6-test.txt"));
	}
}

class UserFileManagerDbTestCase extends NetteDatabaseTestCase {

	function setUp() {
		parent::setUp();
		Files::mkdir(TMP_TEST_DIR."/UserFileManagerDbTestCase");
	}

	function tearDown() {
		parent::tearDown();
		Files::removeDir(TMP_TEST_DIR."/UserFileManagerDbTestCase");
	}


	function testGetDatabaseGarbageDataWithoutContext() {
		$dir=TMP_TEST_DIR."/UserFileManagerDbTestCase";
		$ufm=new UserFileManager($dir);
		Files::create($dir."/a.txt");
		Files::create($dir."/abc.txt");
		Files::create($dir."/d.txt");
		Files::create($dir."/c.txt");
		Files::create($dir."/def.txt");
		Files::create($dir."/some/invalid/path/abc.txt");

		$ufm->setupGarbageCollector($this->db, "userfilemanager", "filename");
		$gc=$ufm->createGarbageCollector();
		$ufm->setStrictMode(false);

		Assert::exception(function() use ($gc) {
			$gc->getGarbageFiles();
		}, '\Nette\InvalidStateException');

		$garbage=$gc->runNow();
		
		OKAssert::arrayEqual(array($dir."/c.txt",$dir."/d.txt"), $garbage);
		Assert::false(file_exists($dir."/c.txt"));
		Assert::false(file_exists($dir."/d.txt"));
		Assert::true(file_exists($dir."/def.txt"));
		Assert::true(file_exists($dir."/abc.txt"));
		Assert::true(file_exists($dir."/some/invalid/path/abc.txt"));

		$ufm->setStrictMode(true);
		$garbage=$gc->runNow();
		OKAssert::arrayEqual(array($dir."/some/invalid/path/abc.txt"), $garbage);
		Assert::false(file_exists($dir."/some/invalid/path/abc.txt"));
	}

	function testGetDatabaseGarbageDataWithContext() {
		$dir=TMP_TEST_DIR."/UserFileManagerDbTestCase";
		$ufm=new UserFileManager($dir);
		Files::create($dir."/100/abc.txt");
		Files::create($dir."/100/jkl.txt");
		Files::create($dir."/abc.txt");
		Files::create($dir."/xyz.txt");
		Files::create($dir."/a.txt");
		Files::create($dir."/b.txt");
		Files::create($dir."/100/xyz.txt");

		$arguments=array();
		// Doučasně otestujeme i předávání $action
		$ufm->setupGarbageCollector($this->db, "userfilemanager", "filename", "context", null, null, FileGarbageCollector::CALLBACK, function($file) use (&$arguments) {
			$arguments[]=$file;
		});
		$gc=$ufm->createGarbageCollector();
		$garbage=$gc->runNow();
		OKAssert::arrayEqual(array($dir."/abc.txt",$dir."/b.txt",$dir."/100/xyz.txt"), $garbage);
		OKAssert::arrayEqual(array($dir."/abc.txt",$dir."/b.txt",$dir."/100/xyz.txt"), $arguments);
		Assert::true(file_exists($dir."/abc.txt"));
	}

	function testGetDatabaseGarbageDataWithContextAndDirs() {
		$dir=TMP_TEST_DIR."/UserFileManagerDbTestCase";
		$ufm=new UserFileManager($dir);
		$ufm->setDirectoryLevels(2);

		$ufm->setStrictMode(false);
		Files::create($dir."/100/a/b/abc.txt");
		Files::create($dir."/100/a/b/xyz.txt");
		Files::create($dir."/x/y/xyz.txt");
		Files::create($dir."/x/x/y/xyx.txt");
		Files::create($dir."/x/y/xyx.txt");

		$ufm->setupGarbageCollector($this->db, "userfilemanager", "filename", "context");
		$gc=$ufm->createGarbageCollector();
		$garbage=$gc->runNow();
		OKAssert::arrayEqual(array($dir."/x/x/y/xyx.txt",$dir."/x/y/xyx.txt"), $garbage);

		$ufm->setStrictMode(true);
		$garbage=$gc->runNow();
		OKAssert::arrayEqual(array($dir."/100/a/b/xyz.txt"), $garbage);
	}
}

$a=new UserFileManagerTestCase();
$a->run();
$a=new UserFileManagerDbTestCase($db, __DIR__."/UserFileManager.sql");
$a->run();
