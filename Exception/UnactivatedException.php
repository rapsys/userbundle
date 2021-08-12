<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys UserBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\UserBundle\Exception;

use Symfony\Component\Security\Core\Exception\AccountStatusException;

/**
 * UnactivatedException is thrown when the user account is unactivated.
 *
 * {@inheritdoc}
 */
class UnactivatedException extends AccountStatusException {
    /**
     * {@inheritdoc}
     */
    public function getMessageKey(): string {
        return 'Account is not activated';
	}
}
