<?php

namespace OndraKoupil\Nette;

use \Nette\Security\User;
use \Nette\Security\IAuthorizator;

class SingleResourcePermission {

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @var CommonResource
	 */
	protected $resource;

	/**
	 * @param CommonResource $resource
	 * @param User $user
	 */
	function __construct(CommonResource $resource, User $user) {
		$this->user = $user;
		$this->resource = $resource;
	}

	/**
	 * @param mixed $privilege
	 * @return bool
	 */
	function isAllowed($privilege = IAuthorizator::ALL) {
		return $this->user->isAllowed($this->resource, $privilege);
	}

	/**
	 * @return CommonResource
	 */
	function getResource() {
		return $this->resource;
	}

	/**
	 * @return User
	 */
	function getUser() {
		return $this->user;
	}

}
