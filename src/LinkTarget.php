<?php

namespace OndraKoupil\Nette;

class LinkTarget extends \Nette\Object implements \Serializable {

	const TYPE_NETTE = "n";
	const TYPE_HTTP = "h";
	const TYPE_NONE = "";

	protected $type = self::TYPE_NETTE;

	protected $target;

	protected $params = array();

	/**
	 * Konstruktor s několika možnostmi:
	 *
	 * <code>
	 * new LinkTarget(); new LinkTarget(null) // Prázdný link
	 * new LinkTarget("presenter:action") // Nette link
	 * new LinkTarget("presenter:action", array("id"=>1)) // Nette link with array params
	 * new LinkTarget(LinkTarget::TYPE_HTTP, "target") // Specify type and target
	 * new LinkTarget(LinkTarget::TYPE_HTTP, "target", array("id"=>1) // Specify type, target and params
	 * </code>
	 * 
	 * @param mixed $p1
	 * @param mixed $p2
	 * @param mixed $p3
	 */
	function __construct($p1 = null, $p2 = null, $p3 = null) {
		$argc = func_num_args();
		if ($argc == 0 or ($argc == 1 and !$p1)) {
			$this->setType(self::TYPE_NONE);
			$this->setParams();
			$this->setTarget();
		} elseif ($argc == 1) {
			$this->setType(self::TYPE_NETTE);
			$this->setParams(array());
			$this->setTarget($p1);
		} elseif ($argc == 2 and is_array($p2)) {
			$this->setType(self::TYPE_NETTE);
			$this->setParams($p2);
			$this->setTarget($p1);
		} elseif ($argc == 2) {
			$this->setType($p1);
			$this->setTarget($p2);
			$this->setParams(array());
		} else {
			$this->setType($p1);
			$this->setTarget($p2);
			$this->setParams($p3);
		}
	}

	function setType($type) {
		if ($type == self::TYPE_HTTP or $type == self::TYPE_NETTE or $type == self::TYPE_NONE) {
			$this->type = $type;
		}
		return $this;
	}

	function getType() {
		return $this->type;
	}

	public function getTarget() {
		return $this->target;
	}

	public function getParams() {
		return $this->params;
	}

	public function setTarget($target = "") {
		if (!$target) {
			$this->type = self::TYPE_NONE;
		}
		$this->target = $target;
		return $this;
	}

	public function setParams($params = array()) {
		$this->params = $params ? $params : array();
		return $this;
	}

	/**
	 * Vytvoří výsledný link
	 * @param \Nette\Application\UI\Presenter $p Nějaký presenter, kvůli link() metodě
	 */
	function link(\Nette\Application\UI\Presenter $p = null) {
		if ($this->type == self::TYPE_NETTE) {
			if (!$p) {
				throw new \InvalidArgumentException("This is a Nette link, a presenter must be supplied.");
			}
			return $p->link($this->target, $this->params);
		} else {
			return $this->target;
		}
	}

	function isLink() {
		return $this->type != self::TYPE_NONE;
	}

	public function serialize() {
		if ($this->type == self::TYPE_NONE) return "";
		return $this->type."|".$this->target.($this->params?("||".serialize($this->params)):"");
	}

	function string() {
		return $this->serialize();
	}

	public function unserialize($serialized) {
		if (!$serialized) {
			$this->setType(self::TYPE_NONE);
			$this->setTarget();
			$this->setParams();
			return;
		}

		$pipePos = strpos($serialized, "|");
		if ($pipePos !== false) {
			$this->setType(substr($serialized, 0, $pipePos));
			$serialized = substr($serialized, $pipePos + 1);
		} else {
			$this->setType(self::TYPE_NONE);
		}

		$secondPipePos = strpos($serialized, "||");
		if ($secondPipePos !== false) {
			$this->setTarget(substr($serialized, 0, $secondPipePos));
			$this->setParams(unserialize(substr($serialized, $secondPipePos + 2)));
		} else {
			$this->setTarget($serialized);
		}
	}

	public function __toString() {
		return $this->serialize();
	}

	/**
	 * @param string $serialized
	 * @return LinkTarget
	 */
	static function from($serialized) {
		$a = new LinkTarget();
		$a->unserialize($serialized);
		return $a;
	}

	static function httpLink($http) {
		return new LinkTarget(self::TYPE_HTTP, $http);
	}

}
