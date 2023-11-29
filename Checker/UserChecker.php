<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys UserBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\UserBundle\Checker;

use Symfony\Component\Security\Core\User\InMemoryUserChecker;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserInterface;

use Rapsys\UserBundle\Entity\User;
use Rapsys\UserBundle\Exception\UnactivatedException;

/**
 * {@inheritdoc}
 */
class UserChecker extends InMemoryUserChecker {
	/**
	 * {@inheritdoc}
	 */
	public function checkPostAuth(UserInterface $user): void {
		//Without User instance
		if (!$user instanceof User) {
			return;
		}

		//With not activated user
		if (!$user->isActivated()) {
			$ex = new UnactivatedException('User Account is not activated');
			$ex->setUser($user);
			throw $ex;
		}

		//Call parent checkPreAuth
		parent::checkPostAuth($user);
	}
}
