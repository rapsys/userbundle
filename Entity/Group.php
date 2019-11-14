<?php

// src/Rapsys/UserBundle/Entity/Group.php
namespace Rapsys\UserBundle\Entity;

class Group {
	/**
	 * @var integer
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $role;

	/**
	 * @var \DateTime
	 */
	protected $created;

	/**
	 * @var \DateTime
	 */
	protected $updated;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 */
	protected $users;

	/**
	 * Constructor
	 * @param string $role The role name
	 */
	public function __construct(string $role) {
		$this->role = (string) $role;
		$this->users = new \Doctrine\Common\Collections\ArrayCollection();
	}

	/**
	 * Set role
	 *
	 * @param string $role
	 *
	 * @return User
	 */
	public function setRole($role) {
		$this->role = $role;

		return $this;
	}

	/**
	 * Get role
	 *
	 * @return string
	 */
	public function getRole() {
		return $this->role;
	}

	/**
	 * Returns a string representation of the role.
	 *
	 * @xxx Replace the deprecated "extends \Symfony\Component\Security\Core\Role\Role"
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->role;
	}

	/**
	 * Set created
	 *
	 * @param \DateTime $created
	 *
	 * @return User
	 */
	public function setCreated($created) {
		$this->created = $created;

		return $this;
	}

	/**
	 * Get created
	 *
	 * @return \DateTime
	 */
	public function getCreated() {
		return $this->created;
	}

	/**
	 * Set updated
	 *
	 * @param \DateTime $updated
	 *
	 * @return User
	 */
	public function setUpdated($updated) {
		$this->updated = $updated;

		return $this;
	}

	/**
	 * Get updated
	 *
	 * @return \DateTime
	 */
	public function getUpdated() {
		return $this->updated;
	}

	/**
	 * Add user
	 *
	 * @param \Rapsys\UserBundle\Entity\User $user
	 *
	 * @return Group
	 */
	public function addUser(\Rapsys\UserBundle\Entity\User $user) {
		$this->users[] = $user;

		return $this;
	}

	/**
	 * Remove user
	 *
	 * @param \Rapsys\UserBundle\Entity\User $user
	 */
	public function removeUser(\Rapsys\UserBundle\Entity\User $user) {
		$this->users->removeElement($user);
	}

	/**
	 * Get users
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getUsers() {
		return $this->users;
	}
}
