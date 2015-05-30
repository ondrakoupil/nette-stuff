<?php

namespace OndraKoupil\Nette;

use \Nette\Neon\Neon;
use \OndraKoupil\Tools\Files;

/**
 * Úložiště pro uživatelské nastavení
 */
class UserPrefs implements \ArrayAccess {

	/**
	 * Ukládat do souboru
	 */
	const FILE = 1;

	/**
	 * Ukládat do sessionu
	 */
	const SESSION = 2;

	/**
	 * Ukládat do databáze
	 */
	const DB = 3;

	/**
	 * Neukládat nikam, jen zachovat po dobu běhu skriptu
	 */
	const NONE = 4;

	protected $saveMethod;
	protected $saveTarget;
	protected $dbRow;
	protected $dbRowName;

	protected $data=array();

	protected $loaded=false;

	/**
	 * @var \Nette\Http\SessionSection
	 */
	protected $session;

	protected $wasChanged;
	protected $registeredShutdownHandler=false;

	protected $defaults=array();

	/**
	 * @param string $key
	 * @param mixed $default. Místo toho lze používat i setDefault().
	 * @return mixed Null, pokud není definovaný $default
	 */
	function get($key,$default=null) {
		$this->loadIfNeeded();
		if (isset($this->data[$key])) return $this->data[$key];
		if (isset($this->defaults[$key])) return $this->defaults[$key];
		return $default;
	}

	function __get($key) {
		return $this->get($key);
	}

	function __destruct() {
		$this->saveIfNeeded();
	}

	function setDefault($key,$defaultValue) {
		$this->defaults[$key]=$defaultValue;
		return $this;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return \OndraKoupil\UserPrefs
	 */
	function set($key,$value) {
		$this->loadIfNeeded();
		if (!isset($this->data[$key]) or $this->data[$key]!==$value) $this->wasChanged=true;
		$this->data[$key]=$value;
		return $this;
	}

	/**
	 * Vyresetuje zpět na defaultní hodnotu.
	 * @param string $key
	 * @return \OndraKoupil\UserPrefs Fluent interface
	 */
	function reset($key) {
		$this->loadIfNeeded();
		if (isset($this->data[$key])) {
			$this->wasChanged;
			unset($this->data[$key]);
		}
		return $this;
	}


	public function __set($key, $value) {
		return $this->set($key, $value);
	}

	/**
	 * Předpokládá, že pod $key je uloženo array, přidá $value do pole.
	 * @param string $key
	 * @param mixed $value
	 * @return \OndraKoupil\UserPrefs
	 * @throws \InvalidArgumentException Pokud $key vůbec není pole
	 */
	function add($key,$value) {
		$ar=$this->get($key);
		if (!$ar) $ar=array();
		if (!is_array($ar)) {
			throw new \InvalidArgumentException('\$key is not array!');
		}
		$ar[]=$value;
		return $this->set($key,$ar);
	}


	/**
	 * Parametry viz setup()
	 * @param int $saveMethod
	 * @param mixed $saveArg1
	 * @param mixed $saveArg2
	 */
	function __construct($saveMethod=null,$saveArg1=null,$saveArg2=null) {
		if ($saveMethod) {
			$this->setup($saveMethod, $saveArg1, $saveArg2);
		}
	}

	/**
	 * Nastaví UserPrefs pro ukládání na určité místo.
	 * <br /><br />
	 * Ukládání do souboru (FILE):<br />
	 *  - $saveArg1 je jméno souboru, do kterého ukládat<br /><br />
	 * Ukládání do session (SESSION):<br />
	 *  - $saveArg1 je stringový identifikátor v session. Dva různé UserPrefs objekty si nepolezou do zelí, pokud mají odlišný tento argument.<br />
	 *  - $saveArg2 je objekt \Nette\Http\SessionSection<br /><br />
	 * Ukládání do databáze (DB):<br />
	 *  - $saveArg1 je řádek z databáze - \Nette\Database\Table\ActiveRow<br />
	 *  - $saveArg2 je string označující konkrétní sloupec, do kterého se v řádku $saveArg1 má ukládat nastavení<br />
	 * Neukládání (NONE):<br />
	 *  - bez argumentů
	 * @param int $saveMethod Jedna z třídních konstant DB, FILE nebo SESSION
	 * @param mixed $saveArg1
	 * @param mixed $saveArg2
	 * @return \OndraKoupil\UserPrefs
	 * @throws \InvalidArgumentException
	 */
	function setup($saveMethod,$saveArg1=null,$saveArg2=null) {
		switch ($saveMethod) {
			case self::FILE:
				$this->saveMethod=$saveMethod;
				$this->saveTarget=$saveArg1;
				Files::create($this->saveTarget);
				break;

			case self::SESSION:
				if (!$saveArg1) {
					$saveArg1="userPref";
				}
				if ($saveArg2===null or !($saveArg2 instanceof \Nette\Http\SessionSection)) {
					throw new \InvalidArgumentException('SessionSection object is required as \$saveArg2 argument.');
				}
				$this->session=$saveArg2;
				$this->saveTarget=$saveArg1;
				$this->saveMethod=$saveMethod;
				break;

			case self::DB:
				if (!($saveArg1 instanceof \Nette\Database\Table\ActiveRow)) {
					throw new \InvalidArgumentException('Missing ActiveRow record in $saveArg1');;
				}
				if (!isset($saveArg1[$saveArg2]) and $saveArg1[$saveArg2]!==null) {
					throw new \InvalidArgumentException('Missing $saveArg1['.$saveArg2.'] in DB row');;
				}
				$this->dbRow=$saveArg1;
				$this->dbRowName=$saveArg2;
				$this->saveMethod=$saveMethod;
				break;

			case self::NONE:
				$this->saveMethod=$saveMethod;
				break;

			default:
				throw new \InvalidArgumentException("Invalid \$saveMethod: $saveMethod");
		}

		if (!$this->registeredShutdownHandler) {
			register_shutdown_function(callback($this,"shutdownHandler"));
			$this->registeredShutdownHandler=true;
		}

		return $this;
	}

	/**
	 * Pokud je potřeba, načte data z úložiště
	 * @return \OndraKoupil\UserPrefs
	 * @throws \Nette\InvalidStateException
	 * @throws \RuntimeException
	 */
	function loadIfNeeded() {
		if (!$this->loaded) {
			switch ($this->saveMethod) {
				case self::SESSION:
					if (!isset($this->session[$this->saveTarget])) {
						$this->session[$this->saveTarget]=array();
					}
					$this->data=$this->session[$this->saveTarget];
					break;

				case self::FILE:
					$readData=file_get_contents($this->saveTarget);
					if ($readData) {
						$this->data=@unserialize($readData);
						if ($this->data===FALSE) {
							throw new \Nette\InvalidStateException("In file $this->saveTarget is invalid content.");
						}
					} else {
						$this->data=array();
					}
					break;

				case self::DB:
					$data=$this->dbRow[$this->dbRowName];
					if (!$data) { // Empty is valid
						$this->data=array();
					} else {
						$ok=@unserialize($data); //Checking here
						if ($ok===false) { // False is never valid
							throw new \RuntimeException("Could not unserialize User preference.");
						}
						$this->data=$ok;
					}
					break;

				case self::NONE:
					$this->data=array();
					break;

				default:
					throw new \Nette\InvalidStateException("UserPrefs must be initialised using setup() method first!");
			}

			$this->loaded = true;
			$this->wasChanged = false;
		}

		return $this;
	}

	/**
	 * @ignore
	 */
	function shutdownHandler() {
		if ($this->wasChanged) {
			$this->saveData();
		}
	}

	/**
	 * Pokdu je potřeba, uloží data do úložiště
	 * @return \OndraKoupil\UserPrefs
	 */
	function saveIfNeeded() {
		if ($this->wasChanged) {
			$this->saveData();
		}
		return $this;
	}

	protected function saveData() {

		switch ($this->saveMethod) {
			case self::SESSION:
				$this->session[$this->saveTarget]=$this->data;
				break;

			case self::FILE:
				$ok=file_put_contents($this->saveTarget, serialize($this->data));
				if (!$ok) {
					throw new \Exception("Failed writing to file $this->saveTarget");
				}
				break;

			case self::DB:
				$string=serialize($this->data);
				$ok = $this->dbRow->update(array($this->dbRowName=>$string));
				if ($ok===false) {
					throw new \RuntimeException("Failed saving user preference to DB!");
				}
				break;

			case self::NONE:
				break;

			default:
				throw new \Nette\InvalidStateException("UserPrefs must be initialised using setup() method first!");
				break;
		}

		$this->wasChanged=false;
	}

	public function offsetExists($offset) {
		return ($this->get($offset,null) !== null);
	}

	public function offsetGet($offset) {
		return $this->get($offset);
	}

	public function offsetSet($offset, $value) {
		return $this->set($offset, $value);
	}

	public function offsetUnset($offset) {
		return $this->reset($offset);
	}

	public function addDefaultsFromFile($file) {
		if (!file_exists($file)) throw new \Nette\FileNotFoundException("Did not found defaults file $file");
		$fileContents=file_get_contents($file);
		$output=Neon::decode($fileContents);
		foreach($output as $name=>$value) {
			$this->setDefault($name, $value);
		}
		return $this;
	}

}
