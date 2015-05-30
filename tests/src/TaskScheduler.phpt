<?php

include "../bootstrap.php";

use \Tester\Assert;
use \OndraKoupil\Testing\FilesTestCase;
use \OndraKoupil\Nette\Logger;
use \OndraKoupil\Nette\TaskScheduler;

class TaskSchedulerTestCase extends FilesTestCase {

	function createScheduler() {
		$dir=$this->getTempDir();
		$logger = new Logger($dir."/log.log");
		$scheduler=new TaskScheduler($dir."/config.neon", $logger);
		return $scheduler;
	}

	function testCreate() {
		$dir=$this->getTempDir();
		$logger = new Logger($dir."/log.log");

		Assert::exception(function() use ($dir, $logger) {
			$wrongTaskScheduler=new TaskScheduler($dir,$logger);
		}, '\Exception');

		$taskScheduler=new TaskScheduler($dir."/conf.txt",$logger);

		Assert::true(file_exists($dir."/conf.txt"));

		$taskScheduler->run();

		Assert::true(file_exists($dir."/log.log"));
		Assert::truthy( strpos(file_get_contents($dir."/log.log"),"run()") );
	}

	function testSameTaskName() {
		$scheduler=$this->createScheduler();
		$scheduler->addTaskMonthly("same", function() {
			;
		}, 3);
		Assert::true($scheduler->isTaskRegistered("same"));
		Assert::exception(function() use ($scheduler) {
			$scheduler->addTaskMonthly("same", function() {
				;
			}, 3);
		}, '\Nette\InvalidStateException');
	}

	function testAddInitCallbacks() {
		$scheduler = $this->createScheduler();

		$scheduler->addInitCallback(function($sch) {
			$sch->addTaskWithInterval("hokus-pokus", function($scheduler, $logger) {
				$logger->log("Balabambam");
			}, 4);
		});

		$scheduler->addInitCallback(function($sch) {
			$sch->addTaskMonthly("druhy-pokus", function($scheduler, $logger) {
				;
			}, 4);
		});

		Assert::false($scheduler->isTaskRegistered("hokus-pokus"));
		Assert::false($scheduler->isTaskRegistered("druhy-pokus"));

		$scheduler->run();

		Assert::true($scheduler->isTaskRegistered("hokus-pokus"));
		Assert::true($scheduler->isTaskRegistered("druhy-pokus"));

		$scheduler->run();

		Assert::true($scheduler->isTaskRegistered("druhy-pokus"));

		$logContents=file_get_contents($this->getTempDir()."/log.log");

		Assert::truthy(strpos($logContents,"Balabambam"));
	}

	function testIntervals() {
		$scheduler = $this->createScheduler();

		$wasRunNumber=0;

		$scheduler->addTaskWithInterval("pokus", function() use (&$wasRunNumber) {
			$wasRunNumber++;
		}, 3);

		Assert::equal(0, $wasRunNumber);

		$scheduler->run();
		Assert::equal(1, $wasRunNumber);

		$scheduler->run();
		Assert::equal(1, $wasRunNumber);

		$configContent=file_get_contents($scheduler->getConfigFile());
		$configContent=str_replace(time(),time()-7200,$configContent);
		file_put_contents($scheduler->getConfigFile(), $configContent);

		$scheduler->run();
		Assert::equal(1, $wasRunNumber);

		$configContent=file_get_contents($scheduler->getConfigFile());
		$configContent=str_replace(time()-7200,time()-7200*2,$configContent);
		file_put_contents($scheduler->getConfigFile(), $configContent);

		$scheduler->run();
		Assert::equal(2, $wasRunNumber);

		$scheduler->run();
		$scheduler->run();

		Assert::equal(2, $wasRunNumber);

		$configContent=file_get_contents($scheduler->getConfigFile());
		$configContent=str_replace(time(),time()-17200,$configContent);
		file_put_contents($scheduler->getConfigFile(), $configContent);

		$scheduler->run();
		Assert::equal(3, $wasRunNumber);

	}

	function testDaily() {
		$scheduler = $this->createScheduler();

		$wasRunNumber=0;

		$hour=date("H");

		$scheduler->addTaskDaily("pokus-hourly", function() use (&$wasRunNumber) {
			$wasRunNumber++;
		}, $hour);

		$scheduler->addTaskDaily("pokus-hourly-2", function() use (&$wasRunNumber) {
			$wasRunNumber++;
		}, $hour);

		$hourNext=$hour+1;
		if ($hourNext>=24) $hourNext=$hour-1;
		$scheduler->addTaskDaily("pokus-hourly-3", function() use (&$wasRunNumber) {
			$wasRunNumber++;
		}, $hourNext);

		$scheduler->run();

		Assert::equal(2, $wasRunNumber);
	}

	function testAliases() {
		$scheduler = $this->createScheduler();

		$numberOfCalls=0;
		$otherHour=date("H")-3;
		if ($otherHour<0) $otherHour=15;

		$scheduler->addTaskWithInterval("task1", function() use (&$numberOfCalls){
			$numberOfCalls++;
		}, 2);
		$scheduler->addTaskDaily("task2", "task1", date("H"));
		$scheduler->addTaskDaily("task3", "task2", $otherHour);

		Assert::exception(function() use ($scheduler) {
			$scheduler->addTaskMonthly("task2", "non-existent", 15);
		}, '\Exception');

		Assert::false($scheduler->isTaskRegistered("non-existent"));
		Assert::true($scheduler->isTaskRegistered("task2"));
		Assert::true($scheduler->isTaskRegistered("task1"));

		$scheduler->run();

		Assert::equal(2,$numberOfCalls);
	}

	function testTiming() {
		$scheduler = $this->createScheduler();

		$nowHour=date("H");
		$scheduler->addTaskDaily("nowHour", function() {;}, $nowHour);

		$nextHour=date("H")-3;
		if ($nextHour<0) $nextHour=23;
		$scheduler->addTaskDaily("lastHour", function() {;}, $nextHour);

		Assert::true($scheduler->isTaskTimedToRun("nowHour"));
		Assert::false($scheduler->isTaskTimedToRun("lastHour"));

		$thisDay=date("j");
		$nextDay=date("j")+1;
		if ($nextDay>28) $nextDay=5;

		$scheduler->addTaskMonthly("nowMonth", function() {;}, $thisDay);
		$scheduler->addTaskMonthly("nextMonth", function() {;}, $nextDay);
		Assert::true($scheduler->isTaskTimedToRun("nowMonth"));
		Assert::false($scheduler->isTaskTimedToRun("nextMonth"));

		$thisDayOfWeek=date("w");
		if (!$thisDayOfWeek) $thisDayOfWeek=7;
		$lastDayOfWeek=$thisDayOfWeek-1;
		if (!$lastDayOfWeek) $lastDayOfWeek=7;

		$scheduler->addTaskWeekly("nowWeek", function() {;}, $thisDayOfWeek);
		$scheduler->addTaskWeekly("lastWeek", function() {;}, $lastDayOfWeek);
		Assert::true($scheduler->isTaskTimedToRun("nowWeek"));
		Assert::false($scheduler->isTaskTimedToRun("lastWeek"));

	}

	function testForceRun() {
		$scheduler=$this->createScheduler();
		$_this=$this;

		$scheduler->addInitCallback(function($sch) use ($_this) {
			$sch->addTaskWithInterval("intTask", function($scheduler, $logger) use ($_this) {
				$_this->addSomeCounter(1);
				$logger->log("BCDEF");
			}, 6);
		});

		$dayNext=date("j")+4;
		if ($dayNext>28) $dayNext=1;
		$scheduler->addTaskMonthly("monthTask", function($scheduler, $logger) use ($_this) {
			$_this->addSomeCounter(2);
			$logger->log("AAAVVV");
		}, $dayNext);

		$hourNext=date("G")+4;
		if ($hourNext>23) $hourNext=1;
		$scheduler->addTaskMonthly("dayTask", function() use ($_this) {
			$_this->addSomeCounter(3);
		}, $hourNext);

		$scheduler->forceRunAll(false);
		$scheduler->forceRunTask("monthTask",false);
		Assert::equal(8, $this->counter);

		$scheduler->run();
		Assert::equal(9, $this->counter);

		$scheduler->forceRunAll(true);
		Assert::equal(15, $this->counter);

		$scheduler->run();
		Assert::equal(15, $this->counter);

		$scheduler->forceRunTask("monthTask", true);
		Assert::equal(17, $this->counter);

		$logContent=file_get_contents($scheduler->getLogger()->getFile());
		$substrCount=substr_count($logContent, "AAAVVV");
		$substrCount2=substr_count($logContent, "BCDEF");

		Assert::equal(4, $substrCount);
		Assert::equal(3, $substrCount2);
	}

	protected $counter=0;
	function addSomeCounter($number=1) {
		$this->counter+=$number;
	}

	function setUp() {
		parent::setUp();
		$this->counter=0;
	}
}

$a=new TaskSchedulerTestCase();
$a->run();
