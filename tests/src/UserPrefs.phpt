<?php

namespace OndraKoupil;

include "../bootstrap.php";

use \Nette;
use \Tester\Assert;
use \OndraKoupil\Testing\FilesTestCase;
use \OndraKoupil\Testing\NetteDatabaseTestCase;
use \OndraKoupil\Nette\UserPrefs;
use \OndraKoupil\Tools\Files;

class UserPrefsTestCase extends FilesTestCase {

	function tearDown() {
		parent::tearDown();
	}

	function testBasicsAndFile() {
		$up=new UserPrefs();
		$filename=__DIR__."/testdata/userprefs-file.txt";
		$up->setup(UserPrefs::FILE, $filename);

		// reading
		Assert::equal("A", $up->get("letter","B"));
		Assert::equal("B", $up->get("nonexistent","B"));
		Assert::equal(10, $up->get("number",15));
		Assert::equal("yes", $up->get("long key"));
		Assert::contains(12, $up->get("array"));
		Assert::equal("A", $up->letter);
		Assert::equal(10, $up->number);
		Assert::equal(null, $up->nothing);
		Assert::equal(10, $up["number"]);
		Assert::equal(null, $up["blah blah"]);
		Assert::equal("yes", $up["long key"]);

		// writing
		$tempDir=$this->createTempDir();
		$copiedFilename=$tempDir."/copied-userprefs-file.txt";
		Files::remove($copiedFilename);
		copy($filename,$copiedFilename);
		clearstatcache();
		$sizeBefore=filesize($copiedFilename);

		$up2=new UserPrefs(UserPrefs::FILE, $copiedFilename);
		Assert::equal("A", $up2["letter"]);
		$up2->letter="B";
		$up2["number"]=200;
		$up2["totally new thing"]="Quite a long string";

		Assert::equal(4, count($up2["array"]));
		$up2->add("array", 1022);
		Assert::equal(5, count($up2["array"]));

		clearstatcache();
		Assert::equal($sizeBefore, filesize($copiedFilename));
		Assert::equal($up2["number"], 200);
		$up2->saveIfNeeded();
		clearstatcache();
		Assert::notEqual($sizeBefore, filesize($copiedFilename));

		$up3=new UserPrefs(UserPrefs::FILE, $copiedFilename);
		Assert::equal("B", $up3["letter"]);

		unlink($copiedFilename);

		// None
		$up=new UserPrefs(UserPrefs::NONE);
		$up["a"]=10;
		$up["B"]=20;
		$up->saveIfNeeded();

		$up=new UserPrefs(UserPrefs::NONE);
		Assert::null($up["a"]);
		Assert::null($up["b"]);
	}

	function testDefaults() {
		$up=new UserPrefs(UserPrefs::NONE);

		Assert::equal(100, $up->get("a",100));
		Assert::null($up->get("a"));

		$up->setDefault("a", 10);

		Assert::equal(10, $up->get("a"));
		Assert::equal(10, $up->get("a",100));

		Assert::null($up["b"]);
		Assert::equal(10, $up->get("b",10));
		Assert::null($up["b"]);
		$up["b"]=20;
		Assert::equal(20, $up->get("b"));
		Assert::equal(20, $up->get("b",10));
		$up->reset("b");
		Assert::null($up["b"]);
		Assert::equal(10, $up->get("b",10));
	}

	function testSessionsAndSetupExceptions() {
		$up=new UserPrefs();

		Assert::exception(function() use ($up) {
			$up["letter"];
		},'\Nette\InvalidStateException');

		Assert::exception(function() use ($up) {
			$up->setup(UserPrefs::SESSION, "xxx", "aaa");
		},'\InvalidArgumentException');

		Assert::exception(function() use ($up) {
			$up->get("letter");
		},'\Nette\InvalidStateException');

		$random=rand(10000,99999);

		Assert::exception(function() use ($up) {
			$up->setup(UserPrefs::SESSION, "testPrefs", null);
		}, '\InvalidArgumentException');

		// Možná docela prasárna... :-/
		$req = new \Nette\Http\Request(new Nette\Http\UrlScript("http://localhost"));
		$resp = new Nette\Http\Response();

		$session = new \Nette\Http\Session($req, $resp);
		$sessionSection = $session->getSection("testPrefs");

		$up->setup(UserPrefs::SESSION, "testPrefs", $sessionSection);
		$up["letter"]=$random;
		$up->saveIfNeeded(); //emulates shutdown
		unset($up);

		$up2=new UserPrefs(UserPrefs::SESSION, "testPrefs", $sessionSection);
		Assert::equal($random,$up2["letter"]);

		$up3=new UserPrefs(UserPrefs::SESSION, "testPrefsOther", $sessionSection);
		Assert::notEqual($random,$up3["letter"]);
	}

	function testDefaultsFromFile() {
		$up=new UserPrefs(UserPrefs::NONE);
		$up->addDefaultsFromFile("testdata/userprefs-default.neon");
		Assert::null($up->get("nesmysl"));
		Assert::equal(1,$up->get("page"));
		Assert::equal(10,$up->get("perpage"));
		Assert::equal("unknown",$up->get("name"));
		Assert::equal(array("a","d","x"),$up->get("some-array"));
		Assert::equal(array("width"=>100,"height"=>200),$up->get("some-object"));
	}
}

class UserPrefsDatabaseTestCase extends NetteDatabaseTestCase {

	function testDb() {
		$row=$this->db->table("userprefs")->where("id",1)->fetch();

		$up=new UserPrefs(UserPrefs::DB, $row, "text");
		Assert::equal("A",$up["letter"]);
		Assert::equal(10,$up["number"]);
		Assert::contains(13,$up["array"]);
		Assert::null($up["missing key"]);

		$up["letter"]="X";
		$up["number"]=123;

		$rowByHand=$this->db->table("userprefs")->where("id",1)->fetch();
		$dataByHand=$rowByHand["text"];
		$a=unserialize($dataByHand);
		Assert::equal($a["letter"], "A");

		$up->saveIfNeeded();

		$rowByHand=$this->db->table("userprefs")->where("id",1)->fetch();
		$dataByHand=$rowByHand["text"];
		$a=unserialize($dataByHand);
		Assert::equal($a["letter"], "X");
	}

}


$a=new UserPrefsTestCase();
$a->run();

$b=new UserPrefsDatabaseTestCase(require("../db.php"), "UserPrefs.sql");
$b->run();
