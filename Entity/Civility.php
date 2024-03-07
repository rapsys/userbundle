<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys UserBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\UserBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Event\PreUpdateEventArgs;

use Rapsys\UserBundle\Entity\User;

/**
 * Civility
 */
class Civility {
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
	 * @param string $title The civility name
	 */
	public function __construct(string $title) {
		//Set defaults
		$this->title = $title;
		$this->created = new \DateTime('now');
		$this->updated = new \DateTime('now');
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
	 * @param string $title
	 *
	 * @return Civility
	 */
	public function setTitle(string $title): Civility {
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
	 * @return Civility
	 */
	public function setCreated(\DateTime $created): Civility {
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
	 * @return Civility
	 */
	public function setUpdated(\DateTime $updated): Civility {
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
	 * @return Civility
	 */
	public function addUser(User $user): Civility {
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
	 * {@inheritdoc}
	 */
	public function preUpdate(PreUpdateEventArgs $eventArgs) {
		//Check that we have a civility instance
		if (($user = $eventArgs->getObject()) instanceof Civility) {
			//Set updated value
			$user->setUpdated(new \DateTime('now'));
		}
	}

	/**
	 * Returns a string representation of the title
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->title;
	}
}
