<?php

// src/Rapsys/UserBundle/Entity/User.php
namespace Rapsys\UserBundle\Entity;

use Rapsys\UserBundle\Entity\Group;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Rapsys\UserBundle\Entity\Title;

class User implements UserInterface, \Serializable {
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
	 * Constructor
	 */
	public function __construct() {
		$this->active = false;
		$this->groups = new ArrayCollection();
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
	 * {@inheritdoc}
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
	public function setTitle(Title $title) {
		$this->title = $title;

		return $this;
	}

	/**
	 * Get title
	 */
	public function getTitle(): Title {
		return $this->title;
	}

	/**
	 * Add group
	 *
	 * @param \Rapsys\UserBundle\Entity\Group $group
	 *
	 * @return User
	 */
	public function addGroup(Group $group) {
		$this->groups[] = $group;

		return $this;
	}

	/**
	 * Remove group
	 *
	 * @param \Rapsys\UserBundle\Entity\Group $group
	 */
	public function removeGroup(Group $group) {
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

	/**
	 * {@inheritdoc}
	 */
	public function getRoles() {
		//Get the unique roles list by id
		return array_unique(array_reduce(
			//Cast groups as array
			$this->groups->toArray(),
			//Reduce to an array of id => group tuples
			function ($array, $group) {
				$array[$group->getId()] = $group->getRole();
				return $array;
			},
			//Init with ROLE_USER
			//XXX: we assume that ROLE_USER has id 1 in database
			[ 1 => 'ROLE_USER' ]
		));
	}

	public function getRole() {
		//Retrieve roles
		$roles = $this->getRoles();

		//Return the role with max id
		//XXX: should be rewriten if it change in your configuration
		return $roles[array_reduce(
			array_keys($roles),
			function($cur, $id) {
				if ($id > $cur) {
					return $id;
				}
				return $cur;
			},
			0
		)];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSalt() {
		//No salt required with bcrypt
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUsername() {
		return $this->mail;
	}

	/**
	 * {@inheritdoc}
	 */
	public function eraseCredentials() {}

	public function serialize(): string {
		return serialize([
			$this->id,
			$this->mail,
			$this->password,
			$this->active,
			$this->created,
			$this->updated
		]);
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

	//XXX: was from vendor/symfony/security-core/User/AdvancedUserInterface.php, see if it's used anymore
	public function isEnabled() {
		return $this->active;
	}

	/**
	 * Returns a string representation of the user
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->title.' '.$this->forename.' '.$this->surname;
	}
}
