<?php

namespace OndraKoupil\Nette;

use \Nette\Utils\Callback;
use \Nette\Neon\Neon;
use \OndraKoupil\Nette\Logger;
use \OndraKoupil\Tools\Files;

/**
 * Správce činností a procesů, které se musí spouštět pravidelně v nějakých intervalech nebo v dopředu naplánovaných časech.
 * <br />
 * V Cronu nebo nějakým jiným způsobem je třeba zajistit, aby se alespoň jednou za hodinu spouštěla metoda run().
 * Task Scheduler se postará o to, aby se poté spustily ty správné úlohy.
 * <br />
 *
 * Každa zaregistrovaná úloha dostává dva argumenty:
 * <br /> - $scheduler = odkaz na scheduler
 * <br /> - $logger = Logger vhodný pro zaznamenávání nějakých výstupů či poznámek o průběhu
 */
class TaskScheduler extends \Nette\Object {

	const DAILY="d";
	const INTERVAL="i";
	const WEEKLY="w";
	const MONTHLY="m";

	private $tasks=array();

	private $timing=array();

	/**
	 * @var array of callable
	 */
	private $initCallbacks=array();

	private $configFile;

	private $lastRun;

	/**
	 * @var Logger
	 */
	private $logger;

	/**
	 * @var Logger
	 */
	private $taskLogger;

	/**
	 * @param string $configFile Cesta k souboru, kde si bude TaskScheduler uchovávat informace o tom, kdy naposledy spustil nějakou činnost
	 * @param Logger $logger Hlavní logger, kam TaskScheduler zapisuje, co dělá
	 * @param Logger $taskLogger Vedlejší logger (nepovinně), kam se zapisují výstupy z jednotlivých spouštěných úloh
	 */
	function __construct($configFile, Logger $logger, Logger $taskLogger = null) {
		Files::create($configFile);
		$this->configFile=$configFile;
		$this->logger=$logger;
		$this->taskLogger=$taskLogger;
	}

	public function getConfigFile() {
		return $this->configFile;
	}

	/**
	 * @return Logger
	 */
	public function getLogger() {
		return $this->logger;
	}

	/**
	 * @return Logger
	 */
	public function getTaskLogger() {
		if ($this->taskLogger) {
			return $this->taskLogger;
		}
		return $this->logger;
	}


	/**
	 * Přidá úlohu spouštěnou vždy jednou za určitý počet hodin
	 * @param string $name
	 * @param callback $task Callback s úlohou nebo string s názvem úlohy, která již byla zaregistrována dříve (pro naplánování na jiný čas)
	 * @param int $intervalHours Počet hodin tvořících interval
	 * @return TaskScheduler
	 * @throws \InvalidArgumentException
	 */
	function addTaskWithInterval($name, $task, $intervalHours) {
		if (!is_numeric($intervalHours) or $intervalHours<1 or $intervalHours>1000) {
			throw new \InvalidArgumentException("Bad \$intervalHours $intervalHours. Should be between 1 and 1000.");
		}
		return $this->addTask($name, $task, array(self::INTERVAL,$intervalHours));
	}

	/**
	 * Přidá úlohu spouštěnou denně v určitou hodinu
	 * @param string $name
	 * @param callback $task Callback s úlohou nebo string s názvem úlohy, která již byla zaregistrována dříve (pro naplánování na jiný čas)
	 * @param int $hour 0 až 23
	 * @return TaskScheduler
	 * @throws \InvalidArgumentException
	 */
	function addTaskDaily($name, $task, $hour) {
		if (!is_numeric($hour) or $hour<0 or $hour>23) {
			throw new \InvalidArgumentException("Bad \$hour $hour. Should be between 0 and 23.");
		}
		return $this->addTask($name, $task, array(self::DAILY,$hour));
	}

	/**
	 * Přidá úlohu spouštěnou určitý den v týdnu
	 * @param string $name
	 * @param callback|string $task Callback s úlohou nebo string s názvem úlohy, která již byla zaregistrována dříve (pro naplánování na jiný čas)
	 * @param int $dayOfWeek 1 (pondělí) až 7 (neděle)
	 * @return TaskScheduler
	 * @throws \InvalidArgumentException
	 */
	function addTaskWeekly($name, $task, $dayOfWeek) {
		if (!is_numeric($dayOfWeek) or $dayOfWeek<1 or $dayOfWeek>7) {
			throw new \InvalidArgumentException("Bad \$dayOfWeek $dayOfWeek. Should be between 1 and 7, where 1 means Monday.");
		}
		return $this->addTask($name, $task, array(self::WEEKLY,$dayOfWeek));
	}

	/**
	 * Přidá úlohu spouštěnou měsíčně v určitý den
	 * @param string $name
	 * @param callback $task Callback s úlohou nebo string s názvem úlohy, která již byla zaregistrována dříve (pro naplánování na jiný čas)
	 * @param int $dayInMonth
	 * @return TaskScheduler
	 * @throws \InvalidArgumentException
	 */
	function addTaskMonthly($name, $task, $dayInMonth) {
		if (!is_numeric($dayInMonth) or $dayInMonth<1 or $dayInMonth>31) {
			throw new \InvalidArgumentException("Bad \$dayInMonth $dayInMonth. Should be between 1 and 31.");
		}
		return $this->addTask($name, $task, array(self::MONTHLY,$dayInMonth));
	}

	/**
	 * Přidat přípravný callback, jehož cílem je zaregistrovat tasky. Tyto callbacky se spustí před každým
	 * spuštěním plánovače. Tímpádem není třeba registrovat tasky při každém requestu, ale jen opravdu
	 * těsně před spuštěním run().
	 *
	 * <br />Init callbackdostává jediný argument, a to tento TaskScheduler.
	 * @param callback $task
	 * @return self
	 */
	function addInitCallback($task) {
		$this->initCallbacks[]=Callback::check($task);
		return $this;
	}

	/**
	 * @ignore
	 */
	protected function addTask($name, $task, $timing) {
		if (!$name) {
			throw new \InvalidArgumentException("You must specify \$name!");
		}

		if (isset($this->tasks[$name])) {
			throw new \Nette\InvalidStateException("Task named \"$name\" was already registered before!");
		}

		if (is_string($task) and isset($this->tasks[$task])) {
			$task=$this->tasks[$task];
		}

		$this->tasks[$name]=Callback::check($task);
		$this->timing[$name]=$timing;
		return $this;
	}

	/**
	 * @ignore
	 */
	protected function loadConfig() {
		$configContent=file_get_contents($this->configFile);
		if (!$configContent) {
			$this->lastRun=array();
		} else {
			$conf = Neon::decode($configContent);
			if (isset($conf["lastRun"])) {
				$this->lastRun=$conf["lastRun"];
			} else {
				$this->lastRun=array();
			}
		}
		return $this;
	}

	/**
	 * @ignore
	 */
	protected function saveConfig() {
		$object=array("lastRun"=>$this->lastRun);
		file_put_contents($this->configFile, Neon::encode($object));
	}

	/**
	 * @ignore
	 */
	protected function runInitCallbacks() {
		foreach ($this->initCallbacks as $cb) {
			Callback::invokeArgs($cb, array($this));
		}
		$this->initCallbacks=array();
		return $this;
	}

	/**
	 * @protected
	 * @param string $message
	 */
	protected function writeToLog($message) {
		$this->logger->log($message);
	}

	/**
	 * Spustí ty ze zaregistrovaných úloh, u kterých je na to vhodná doba.
	 * Tuto metodu je třeba externě (třeba Cronem, nebo při každém requestu) volat přinejmenším za hodinu.
	 */
	function run() {
		$this->writeToLog("run()");
		$this->runInitCallbacks();
		$this->loadConfig();

		foreach($this->tasks as $taskName=>$taskCallback) {
			$canBeRun=$this->isTaskTimedToRun($taskName);
			if ($canBeRun) {
				$this->lastRun[$taskName]=time();
				$this->saveConfig();
				$this->runTask($taskName);
			}
		}

		$this->saveConfig();
		$this->writeToLog("finished run()");
	}

	/**
	 * Vynutí spuštění úlohy i mimo plán.
	 * @param string $taskName Jméno úlohy
	 * @param bool $markLastRun viz forceRunAll()
	 * @throws \InvalidArgumentException
	 */
	function forceRunTask($taskName, $markLastRun=true) {
		$this->runInitCallbacks();
		if (!$this->isTaskRegistered($taskName)) {
			throw new \InvalidArgumentException("Task named $taskName is not registered.");
		}
		$this->forceRunTaskNow($taskName,$markLastRun);
	}

	/**
	 * Vynutí spuštění všech úloh i mimo plán.
	 * @param bool $markLastRun True = poznamenat čas posledního spuštění. U intervalových spouštění to odsune čas dalšího spuštění.
	 */
	function forceRunAll($markLastRun=true) {
		$this->runInitCallbacks();
		foreach($this->tasks as $task=>$cb) {
			$this->forceRunTaskNow($task,$markLastRun);
		}
	}

	/**
	 * @protected
	 */
	protected function forceRunTaskNow($taskName,$markLastRun) {
		if ($markLastRun) {
			$this->lastRun[$taskName]=time();
			$this->saveConfig();
		}
		$this->writeToLog("Force run task $taskName (mark ".($markLastRun?"true":"false").")");
		$this->runTask($taskName);
	}


	/**
	 * Ověří, zda je určitý task zaregistrován
	 * @param string $taskName
	 * @return bool
	 */
	function isTaskRegistered($taskName) {
		return isset($this->tasks[$taskName]);
	}

	/**
	 * Nastal již čas pro spuštění určitého tasku?
	 * @param string $taskName
	 * @return boolean
	 * @throws \InvalidArgumentException
	 */
	function isTaskTimedToRun($taskName) {
		if (!isset($this->tasks[$taskName])) {
			throw new \InvalidArgumentException("Task with name $taskName was not registered!");
		}
		$timing=$this->timing[$taskName];
		$now=time();
		$timingArg=$timing[1];
		$timingMode=$timing[0];
		switch ($timingMode) {
			case self::INTERVAL:
				if (!isset($this->lastRun[$taskName])) return true;
				if (($now-($this->lastRun[$taskName])) > ($timingArg*3600-300)) { // 5 minutes tolerance
					return true;
				}
				break;

			case self::DAILY:
				if (date("G")==$timingArg) {
					if (!isset($this->lastRun[$taskName])) return true;
					if ($now-$this->lastRun[$taskName]>7200) { // 1 hour more
						return true;
					}
				}
				break;

			case self::WEEKLY:
				$thisDay=date("w");
				if (!$thisDay) $thisDay=7; // sunday
				if ($thisDay==$timingArg) {
					if (!isset($this->lastRun[$taskName])) return true;
					if ($now-$this->lastRun[$taskName]>86400*2) {
						return true;
					}
				}
				break;

			case self::MONTHLY:
				$thisDay=date("j");
				if ($thisDay==$timingArg) {
					if (!isset($this->lastRun[$taskName])) return true;
					if ($now-$this->lastRun[$taskName]>86400*2) {
						return true;
					}
				}
		}
		return false;
	}

	/**
	 * @ignore
	 */
	protected function runTask($taskName) {
		$this->writeToLog("Starting task $taskName");
		$callback=$this->tasks[$taskName];
		$logger=$this->taskLogger ? $this->taskLogger : $this->logger;
		Callback::invokeArgs($callback, array($this, $logger));
		$this->writeToLog("Finished task $taskName");
	}
}
