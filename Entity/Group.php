<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\UserBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

use Rapsys\UserBundle\Entity\User;

/**
 * Group
 */
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
	 * @var ArrayCollection
	 */
	protected $users;

	/**
	 * Constructor
	 *
	 * @param string $title The group name
	 */
	public function __construct(string $title) {
		$this->title = $title;
		$this->users = new ArrayCollection();
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
	 * Set title
	 *
	 * @param string $title The group name
	 *
	 * @return Group
	 */
	public function setTitle(string $title): Group {
		$this->title = $title;

		return $this;
	}

	/**
	 * Get title
	 *
	 * @return string
	 */
	public function getTitle(): ?string {
		return $this->title;
	}

	/**
	 * Set created
	 *
	 * @param \DateTime $created
	 *
	 * @return Group
	 */
	public function setCreated(\DateTime $created): Group {
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
	 * @return Group
	 */
	public function setUpdated(\DateTime $updated): Group {
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
	 * Add user
	 *
	 * @param User $user
	 *
	 * @return Group
	 */
	public function addUser(User $user) {
		$this->users[] = $user;

		return $this;
	}

	/**
	 * Remove user
	 *
	 * @param User $user
	 */
	public function removeUser(User $user) {
		$this->users->removeElement($user);
	}

	/**
	 * Get users
	 *
	 * @return ArrayCollection
	 */
	public function getUsers(): ArrayCollection {
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
	public function getRole(): string {
		return 'ROLE_'.strtoupper($this->title);
	}
}
