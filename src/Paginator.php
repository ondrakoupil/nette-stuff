<?php

namespace OndraKoupil\Nette;

class Paginator extends \Nette\Object {

	/**
	 * Celkový počet položek (ne stránek)
	 * @property
	 * @var int
	 */
	protected $totalItems;

	/**
	 * Aktuální stránka
	 * @property
	 * @var int
	 */
	protected $current;

	/**
	 * Počet položek na jednu stránku
	 * @property
	 * @var int
	 */
	protected $itemsPerPage;

	private $validateAutomatically=true;

	/**
	 * Konstruktor.
	 * @param int $currentPage Aktuální stránka.
	 * @param int $totalItems Celkový počet položek (ne stránek).
	 * @param int $itemsPerPage Počet položek na stránku.
	 */
	function __construct($currentPage=1,$totalItems=0,$itemsPerPage=10) {
		if (!is_numeric($itemsPerPage) or $itemsPerPage<1) throw new \InvalidArgumentException("\$itemsPerPage must be greater than 0.");
		$this->validateAutomatically=false;
		$this->current=$currentPage;
		$this->totalItems=$totalItems;
		$this->itemsPerPage=$itemsPerPage;
		$this->validateAutomatically=true;
		$this->validate();
	}

	function setCurrent($current) {
		if ($current<1) $current=1;
		$this->current=$current;
		if ($this->validateAutomatically) $this->validate();
		return $this;
	}

	function getCurrent() {
		return $this->current;
	}

	function setTotalItems($total) {
		if ($total<0) $total=0;
		$this->totalItems=$total;
		if ($this->validateAutomatically) $this->validate();
		return $this;
	}

	function getTotalItems() {
		return $this->totalItems;
	}

	function getItemsPerPage() {
		return $this->itemsPerPage;
	}

	function setItemsPerPage($itemsPerPage) {
		if ($itemsPerPage<1) $itemsPerPage=20;
		$this->itemsPerPage=$itemsPerPage;
		if ($this->validateAutomatically) $this->validate();
		return $this;
	}

	function isValid($page) {
		if (!is_numeric($page)) return false;
		if ($page<1) return false;
		if ($page>$this->getTotalPages()) {
			return false;
		}
		return true;
	}

	private function validate() {
		if (($this->current-1)*$this->itemsPerPage >= $this->totalItems) {
			$this->current=$this->getLastPage();
		}
		if ($this->current<1) $this->current=1;
	}

	/**
	 * Vrátí číslo první stránky nebo false.
	 * @param bool $falseIfSameAsCurrent Má se vrátit false, pokud by číslo první stránky bylo stejné jako moje aktuální stránka?
	 * @param bool $falseIfSameAsPreviousPage Má se vrátit false, pokud by číslo první stránky bylo stejné jako předchozí stránka?
	 * @return boolean|int
	 */
	function getFirstPage($falseIfSameAsCurrent=true,$falseIfSameAsPreviousPage=false) {
		if ($this->current==1 and $falseIfSameAsCurrent) return false;
		if ($this->current==2 and $falseIfSameAsPreviousPage) return false;
		return 1;
	}

	/**
	 * Vrátí číslo poslední stránky nebo false.
	 * @param bool $falseIfSameAsCurrent Má se vrátit false, pokud by číslo poslední stránky bylo stejné jako moje aktuální stránka?
	 * @param bool $falseIfSameAsNextPage Má se vrátit false, pokud by číslo poslední stránky bylo stejné jako následující stránka?
	 * @return boolean|int
	 */
	function getLastPage($falseIfSameAsCurrent=true,$falseIfSameAsNextPage=false) {
		$last=$this->getTotalPages();
		if ($this->current==$last and $falseIfSameAsCurrent) return false;
		if ($this->current+1==$last and $falseIfSameAsNextPage) return false;
		return $last;
	}

	/**
	 * Číslo předchozí stránky nebo false
	 * @param bool $falseIfImpossible Má se vrátit false, pokud už na předchozí stránku jít nelze (jsem-li na první stránce)
	 * @return boolean|int
	 */
	function getPreviousPage($falseIfImpossible=true) {
		if ($this->current>1) return $this->current-1;
		if ($falseIfImpossible) {
			return false;
		} else {
			return 1;
		}
	}

	/**
	 * @return bool
	 */
	function isFirstPage() {
		return ($this->current==1);
	}

	/**
	 * @return bool
	 */
	function isLastPage() {
		return ($this->current==$this->getTotalPages());
	}

	/**
	 * Vrátí kus SQL kódu do klauzule LIMIT jako řetězec "offset, limit"
	 * @return string X,Y
	 */
	function getSqlLimit() {
		return ($this->getShowingItemsFrom()-1).", ".$this->itemsPerPage;
	}

	/**
	 * Vrátí kus SQL kódu do klauzule OFFSET
	 * @return number
	 */
	function getSqlOffset() {
		return ($this->getShowingItemsFrom()-1);
	}

	/**
	 * Vrátí kus kódu do klauzule LIMIT, pouze jedno číslo.
	 * Vhodné do Nette Database, kde je potřeba to rozdělit na dvě hodnoty.
	 * @return number
	 */
	function getSqlLimitNumber() {
		return $this->itemsPerPage;
	}

	/**
	 * Číslo následující stránky nebo false
	 * @param bool $falseIfImpossible Má se vrátit false, pokud už na následující stránku jít nelze (jsem-li na poslední stránce)
	 * @return boolean|int
	 */
	function getNextPage($falseIfImpossible=true) {
		$maxPages=ceil($this->totalItems/$this->itemsPerPage);
		if ($this->current<$maxPages) return $this->current+1;

		if ($falseIfImpossible) {
			return false;
		} else {
			return $this->current;
		}
	}

	/**
	 * Celkový počet stran
	 * @return int
	 */
	function getTotalPages() {
		return ceil($this->totalItems/$this->itemsPerPage);
	}

	/**
	 * Číslo pořadí první položky na zadané stránce. Vhodné pro výpisu typu "Zobrazuji produkty X až Y".
	 * @param int|bool $page False = aktuální.
	 * @return int
	 */
	function getShowingItemsFrom($page=false) {
		if (!$page) $page=$this->current;
		return (($page-1)*$this->itemsPerPage)+1;
	}

	/**
	 * Číslo pořadí poslední položky na zadané stránce. Vhodné pro výpisu typu "Zobrazuji produkty X až Y".
	 * @param int|bool $page False = aktuální.
	 * @return int
	 */
	function getShowingItemsTo() {
		return (($this->current)*$this->itemsPerPage);
	}

	/**
	 * Vygeneruje sekvenci vhodných čísel stránek pro vytvoření navigace.
	 * @param int $number Maximální počet vygenerovaných čísel. Musí být alespoň 5. Doporučuji lichá čísla, výsledek je pak symetrický a hezčí.
	 * @param bool $emptySeparators Pokud se dá true, tak se na určitá místa v sekvenci vloží ještě
	 * hodnota false, která naznačuje, že na tomto místě byly nějaké stránky vynechány.
	 * @return array
	 */
	function getSequence($number,$emptySeparators=false) {

		if ($number<5) return array();

		$totalPages=$this->getTotalPages();
		if (!$totalPages) return array();
		if ($totalPages<=$number) return range(1,$totalPages,1);

		$output=array();
		$lastAdded=0;
		$current=$this->getCurrent();
		$maxPage=$this->getTotalPages();
		$minPage=1;
		$output[$current]=true;
		if ($current!=$maxPage) $output[$current+1]=true;
		if ($current>1) $output[$current-1]=true;
		$output[$maxPage]=true;
		$output[$minPage]=true;
		$i=2;
		while (count($output)!=$number) {
			if ($current+$i<$maxPage) $output[$current+$i]=true;
			if (count($output)>=$number) break;

			if ($current-$i>1) $output[$current-$i]=true;
			if (count($output)>=$number) break;

			if ($i%2==0) {
				$stepFromExtreme=floor($i/2); // O kolik od min/max
				$output[$stepFromExtreme+1]=true;
				if (count($output)>=$number) break;

				$output[$maxPage-$stepFromExtreme]=true;
				if (count($output)>=$number) break;
			}

			$i++;
		}

		$numbers=array_keys($output);
		$output=array();
		sort($numbers);
		$previous=0;
		if ($emptySeparators) {
			foreach($numbers as $n) {
				if ($n-1!=$previous) $output[]=false;
				$output[]=$n;
				$previous=$n;
			}
		} else {
			$output=$numbers;
		}

		return $output;
	}

	/**
	 * Vrátí číslo stránky, která obsahuje prvek s pořadovým číslem $itemOrderNumber. Číslování je 1-based.
	 * @param int $itemOrderNumber
	 * @return int
	 */
	function getPageForItem($itemOrderNumber) {
		if ($itemOrderNumber>$this->totalItems) $itemOrderNumber=$this->totalItems;
		if ($itemOrderNumber<1) $itemOrderNumber=1;
		return ceil($itemOrderNumber/$this->itemsPerPage);
	}
}
