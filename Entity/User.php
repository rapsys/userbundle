<?php declare(strict_types=1);

/*
 * this file is part of the rapsys packbundle package.
 *
 * (c) raphaël gertz <symfony@rapsys.eu>
 *
 * for the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

namespace Rapsys\UserBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\Security\Core\User\UserInterface;

use Rapsys\UserBundle\Entity\Civility;
use Rapsys\UserBundle\Entity\Group;

/**
 * User
 */
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
	 * @var bool
	 */
	protected $disabled;

	/**
	 * @var \DateTime
	 */
	protected $created;

	/**
	 * @var \DateTime
	 */
	protected $updated;

	/**
	 * @var Civility
	 */
	protected $civility;

	/**
	 * @var ArrayCollection
	 */
	protected $groups;

	/**
	 * Constructor
	 *
	 * @param string $mail The user mail
	 */
	public function __construct(string $mail) {
		//With mail
		if (!empty($this->mail = $mail)) {
			$this->password = $mail;
		} else {
			$this->password = '';
		}

		//Set defaults
		$this->forename = '';
		$this->surname = '';
		$this->active = false;
		$this->disabled = false;
		$this->created = new \DateTime('now');
		$this->updated = new \DateTime('now');

		//Set collections
		$this->groups = new ArrayCollection();
	}

	/**
	 * Get id
	 *
	 * @return integer
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * Set mail
	 *
	 * @param string $mail
	 *
	 * @return User
	 */
	public function setMail(string $mail): User {
		//With mail
		if (!empty($this->mail = $mail)) {
			//Without password
			if (empty($this->password)) {
				//Set mail as password
				$this->password = $mail;
			}
		}

		return $this;
	}

	/**
	 * Get mail
	 *
	 * @return string
	 */
	public function getMail(): string {
		return $this->mail;
	}

	/**
	 * Set forename
	 *
	 * @param string $forename
	 *
	 * @return User
	 */
	public function setForename(string $forename): User {
		$this->forename = $forename;

		return $this;
	}

	/**
	 * Get forename
	 *
	 * @return string
	 */
	public function getForename(): string {
		return $this->forename;
	}

	/**
	 * Set surname
	 *
	 * @param string $surname
	 *
	 * @return User
	 */
	public function setSurname(string $surname): User {
		$this->surname = $surname;

		return $this;
	}

	/**
	 * Get surname
	 *
	 * @return string
	 */
	public function getSurname(): string {
		return $this->surname;
	}

	/**
	 * Set password
	 *
	 * @param string $password
	 *
	 * @return User
	 */
	public function setPassword(string $password): User {
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
	public function getPassword(): ?string {
		return $this->password;
	}

	/**
	 * Set active
	 *
	 * @param bool $active
	 *
	 * @return User
	 */
	public function setActive(bool $active): User {
		$this->active = $active;

		return $this;
	}

	/**
	 * Get active
	 *
	 * @return bool
	 */
	public function getActive(): bool {
		return $this->active;
	}

	/**
	 * Set disabled
	 *
	 * @param bool $disabled
	 *
	 * @return User
	 */
	public function setDisabled(bool $disabled): User {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Get disabled
	 *
	 * @return bool
	 */
	public function getDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Set created
	 *
	 * @param \DateTime $created
	 *
	 * @return User
	 */
	public function setCreated(\DateTime $created): User {
		$this->created = $created;

		return $this;
	}

	/**
	 * Get created
	 *
	 * @return \DateTime
	 */
	public function getCreated(): \DateTime {
		return $this->created;
	}

	/**
	 * Set updated
	 *
	 * @param \DateTime $updated
	 *
	 * @return User
	 */
	public function setUpdated(\DateTime $updated): User {
		$this->updated = $updated;

		return $this;
	}

	/**
	 * Get updated
	 *
	 * @return \DateTime
	 */
	public function getUpdated(): \DateTime {
		return $this->updated;
	}

	/**
	 * Set civility
	 */
	public function setCivility(Civility $civility): User {
		$this->civility = $civility;

		return $this;
	}

	/**
	 * Get civility
	 */
	public function getCivility(): ?Civility {
		return $this->civility;
	}

	/**
	 * Add group
	 *
	 * @param Group $group
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
	 * @param Group $group
	 */
	public function removeGroup(Group $group) {
		$this->groups->removeElement($group);
	}

	/**
	 * Get groups
	 *
	 * @return ArrayCollection
	 */
	public function getGroups(): ArrayCollection {
		return $this->groups;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRoles(): array {
		//Get the unique roles list by id
		return array_unique(array_reduce(
			//Cast groups as array
			$this->groups->toArray(),
			//Reduce to an array of id => group tuples
			function ($array, $group) {
				$array[$group->getId()] = $group->getRole();
				return $array;
			},
			//Init with empty array
			//XXX: on registration, add each group present in rapsys_user.default.group array to user
			//XXX: see vendor/rapsys/userbundle/Controller/DefaultController.php +450
			[]
		));
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRole(): ?string {
		//Retrieve roles
		$roles = $this->getRoles();

		//With roles array empty
		if ($roles === []) {
			//Return null
			return null;
		}

		//Return the role with max id
		//XXX: should be rewriten if it change in your configuration
		return $roles[array_reduce(
			array_keys($roles),
			function($cur, $id) {
				if ($cur === null || $id > $cur) {
					return $id;
				}
				return $cur;
			},
			null
		)];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSalt(): ?string {
		//No salt required with bcrypt
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUsername(): string {
		return $this->mail;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUserIdentifier(): string {
		return $this->mail;
	}

	/**
	 * {@inheritdoc}
	 */
	public function eraseCredentials(): void {}

	/**
	 * {@inheritdoc}
	 */
	public function serialize(): string {
		return serialize([
			$this->id,
			$this->mail,
			$this->forename,
			$this->surname,
			$this->password,
			$this->active,
			$this->disabled,
			$this->created,
			$this->updated
		]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function unserialize($serialized) {
		list(
			$this->id,
			$this->mail,
			$this->forename,
			$this->surname,
			$this->password,
			$this->active,
			$this->disabled,
			$this->created,
			$this->updated
		) = unserialize($serialized);
	}

	/**
	 * Check if account is activated
	 *
	 * It was from deprecated AdvancedUserInterface, see if it's used anymore
	 *
	 * @see vendor/symfony/security-core/User/AdvancedUserInterface.php
	 */
	public function isActivated(): bool {
		return $this->active;
	}

	/**
	 * Check if account is disabled
	 *
	 * It was from deprecated AdvancedUserInterface, see if it's used anymore
	 *
	 * @see vendor/symfony/security-core/User/AdvancedUserInterface.php
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * {@inheritdoc}
	 */
	public function preUpdate(PreUpdateEventArgs $eventArgs) {
		//Check that we have an user instance
		if (($user = $eventArgs->getEntity()) instanceof User) {
			//Set updated value
			$user->setUpdated(new \DateTime('now'));
		}
	}

	/**
	 * Returns a recipient name of the user
	 *
	 * @return string
	 */
	public function getRecipientName(): string {
		//Without forename and surname
		if (empty($this->forename) && empty($this->surname)) {
			//Return recipient name from mail
			return ucwords(trim(preg_replace('/[^a-zA-Z]+/', ' ', current(explode('@', $this->mail)))));
		}

		//Return recipient name from forename and surname
		return implode(' ', [$this->forename, $this->surname]);
	}

	/**
	 * Returns a string representation of the user
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->civility.' '.$this->forename.' '.$this->surname;
	}
}
