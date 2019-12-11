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
	protected $title;

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
	 *
	 * @param string $title The group name
	 */
	public function __construct(string $title) {
		$this->title = (string) $title;
		$this->users = new \Doctrine\Common\Collections\ArrayCollection();
	}

	/**
	 * Get id
	 *
	 * @return integer
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set title
	 *
	 * @param string $title The group name
	 *
	 * @return User
	 */
	public function setTitle($title) {
		$this->title = $title;

		return $this;
	}

	/**
	 * Get title
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
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

	/**
	 * Returns a string representation of the group
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->title;
	}

	/**
	 * Get role
	 *
	 * @return string
	 */
	public function getRole() {
		return 'ROLE_'.strtoupper($this->title);
	}
}
