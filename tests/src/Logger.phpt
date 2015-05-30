<?php

include "../bootstrap.php";

use \Tester\Assert;
use \OndraKoupil\Testing\FilesTestCase;
use \OndraKoupil\Nette\Logger;

class LoggerTestCase extends FilesTestCase {

	function testLog() {
		$dir=$this->createTempDir();
		$logFile=$dir."/testlog.txt";
		$l=new Logger($logFile);

		Assert::false(file_exists($logFile));
		$l->log("Ahoj");
		Assert::true(file_exists($logFile));
		$contents=file_get_contents($logFile);
		Assert::truthy(preg_match('~Ahoj~',$contents));
		Assert::falsey(preg_match('~World~',$contents));

		$l->log("World");
		$contents=file_get_contents($logFile);
		Assert::truthy(preg_match('~Ahoj~',$contents));
		Assert::truthy(preg_match('~World~',$contents));

		$l2=new Logger($logFile);
		$l2->log("Second word");
		$contents=file_get_contents($logFile);
		Assert::truthy(preg_match('~Ahoj~',$contents));
		Assert::truthy(preg_match('~World~',$contents));
		Assert::truthy(preg_match('~Second~',$contents));
		Assert::truthy(preg_match('~\n~',$contents));
	}

	function testFormat() {
		$dir=$this->createTempDir();
		$logFile=$dir."/testlog2.txt";
		$l=new Logger($logFile,"Hello %message%");
		$l->log("World");
		$contents=file_get_contents($logFile);
		Assert::same('Hello World'."\n",$contents);
	}
}

$a=new LoggerTestCase();
$a->run();
