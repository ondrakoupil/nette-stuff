<?php

namespace OndraKoupil\Nette;

class CommonResource implements \Nette\Security\IResource, \ArrayAccess {

	protected $id;
	protected $type;
	protected $owner;
	protected $data;
	protected $user;
	protected $dataManager;

	/**
	 * @param mixed $type
	 * @param mixed $id
	 * @param \Nette\Security\User $user
	 * @param array|int $ownerOrData
	 * @param array $data
	 * @param \OndraKoupil\Models\DataManager $dataManager
	 */
	function __construct($type, $id, \Nette\Security\User $user, $ownerOrData=null, $data=null, $dataManager = null) {
		$this->type=$type;
		$this->id=$id;
		$this->user=$user;
		if ($data and is_array($ownerOrData)) $this->data=$ownerOrData;
		else $this->owner=$ownerOrData;
		if ($data and is_array($data)) {
			$this->data=$data;
		}
		$this->dataManager=$dataManager;
	}

	/**
	 * @return SingleResourcePermission
	 */
	function createSinglePermission() {
		return new SingleResourcePermission($this, $this->user);
	}

	public function getResourceId() {
		return $this->type;
	}

	public function getId() {
		return $this->id;
	}

	public function getOwner() {
		return $this->owner;
	}

	public function getData() {
		return $this->data;
	}

	/**
	 * Je zadaný nebo aktuálně přihlášený uživatel vlastníkem tohoto resource?
	 * @param int|null $idUser Null = aktuálně přihlášený, jinak zadej UserId
	 * @return bool
	 */
	public function isOwner($idUser = null) {
		if ($idUser === null) {
			return ($this->user->id == $this->owner and $this->user->id);
		}
		return ($idUser == $this->owner);
	}

	/**
	 * @return \OndraKoupil\Models\DataManager
	 */
	public function getDataManager() {
		return $this->dataManager;
	}

	/**
	 * @return \Nette\Security\User
	 */
	public function getUser() {
		return $this->user;
	}

	public function __get($name) {
		if (isset($this->$name)) return $this->$name;
		if ($name=="user") return $this->getUser();
		if (isset($this->data[$name])) return $this->data[$name];
		return NULL;
	}

	public function __set($name,$value) {
		if ($name=="owner" or $name=="type" or $name=="id") $this->$name = $value;
		$this->data[$name]=$value;
	}

	public function offsetExists($offset) {
		return isset($this->data[$offset]);
	}

	public function offsetGet($offset) {
		if (isset($this->data[$offset])) {
			return $this->data[$offset];
		}
		return null;
	}

	public function offsetSet($offset, $value) {
		$this->data[$offset]=$value;
	}

	public function offsetUnset($offset) {
		if (isset($this->data[$offset])) unset($this->data[$offset]);
	}

	function __toString() {
		return "(CommonResource type ".$this->type." ID ".$this->id.($this->owner?" owned by $this->owner":"").")";
	}

}
