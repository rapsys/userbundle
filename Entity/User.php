<?php

// src/Rapsys/UserBundle/Entity/User.php
namespace Rapsys\UserBundle\Entity;

class User implements \Symfony\Component\Security\Core\User\AdvancedUserInterface, \Serializable {
	/**
	 * @var integer
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $mail;

	/**
	 * @var string
	 */
	protected $pseudonym;

	/**
	 * @var string
	 */
	protected $forename;

	/**
	 * @var string
	 */
	protected $surname;

	/**
	 * @var string
	 */
	protected $password;

	/**
	 * @var bool
	 */
	protected $active;

	/**
	 * @var \DateTime
	 */
	protected $created;

	/**
	 * @var \DateTime
	 */
	protected $updated;

	/**
	 * @var \Rapsys\UserBundle\Entity\Title
	 */
	protected $title;

	/**
	 * @var \Doctrine\Common\Collections\Collection
	 */
	protected $groups;

	/**
	 * User constructor.
	 */
	public function __construct() {
		$this->active = false;
		$this->groups = new \Doctrine\Common\Collections\ArrayCollection();
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
	 * Set mail
	 *
	 * @param string $mail
	 *
	 * @return User
	 */
	public function setMail($mail) {
		$this->mail = $mail;

		return $this;
	}

	/**
	 * Get mail
	 *
	 * @return string
	 */
	public function getMail() {
		return $this->mail;
	}

	/**
	 * Set pseudonym
	 *
	 * @param string $pseudonym
	 *
	 * @return User
	 */
	public function setPseudonym($pseudonym) {
		$this->pseudonym = $pseudonym;

		return $this;
	}

	/**
	 * Get pseudonym
	 *
	 * @return string
	 */
	public function getPseudonym() {
		return $this->pseudonym;
	}

	/**
	 * Set forename
	 *
	 * @param string $forename
	 *
	 * @return User
	 */
	public function setForename($forename) {
		$this->forename = $forename;

		return $this;
	}

	/**
	 * Get forename
	 *
	 * @return string
	 */
	public function getForename() {
		return $this->forename;
	}

	/**
	 * Set surname
	 *
	 * @param string $surname
	 *
	 * @return User
	 */
	public function setSurname($surname) {
		$this->surname = $surname;

		return $this;
	}

	/**
	 * Get surname
	 *
	 * @return string
	 */
	public function getSurname() {
		return $this->surname;
	}

	/**
	 * Set password
	 *
	 * @param string $password
	 *
	 * @return User
	 */
	public function setPassword($password) {
		$this->password = $password;

		return $this;
	}

	/**
	 * Get password
	 *
	 * @return string
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * Set active
	 *
	 * @param bool $active
	 *
	 * @return User
	 */
	public function setActive($active) {
		$this->active = $active;

		return $this;
	}

	/**
	 * Get active
	 *
	 * @return bool
	 */
	public function getActive() {
		return $this->active;
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
	 * Set title
	 */
	public function setTitle($title) {
		$this->title = $title;

		return $this;
	}

	/**
	 * Get title
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * Add group
	 *
	 * @param \Rapsys\UserBundle\Entity\Group $group
	 *
	 * @return User
	 */
	public function addGroup(\Rapsys\UserBundle\Entity\Group $group) {
		$this->groups[] = $group;

		return $this;
	}

	/**
	 * Remove group
	 *
	 * @param \Rapsys\UserBundle\Entity\Group $group
	 */
	public function removeGroup(\Rapsys\UserBundle\Entity\Group $group) {
		$this->groups->removeElement($group);
	}

	/**
	 * Get groups
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getGroups() {
		return $this->groups;
	}

	public function getRoles() {
		return $this->groups->toArray();
	}

	public function getSalt() {
		//No salt required with bcrypt
		return null;
	}

	public function getUsername() {
		return $this->mail;
	}

	public function eraseCredentials() {
	}

	public function serialize() {
		return serialize(array(
			$this->id,
			$this->mail,
			$this->password,
			$this->active,
			$this->created,
			$this->updated
		));
	}

	public function unserialize($serialized) {
		list(
			$this->id,
			$this->mail,
			$this->password,
			$this->active,
			$this->created,
			$this->updated
		) = unserialize($serialized);
	}

	public function isAccountNonExpired() {
		return true;
	}

	public function isAccountNonLocked() {
		return true;
	}

	public function isCredentialsNonExpired() {
		return true;
	}

	public function isEnabled() {
		return $this->active;
	}
}
