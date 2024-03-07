<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys UserBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\UserBundle\Form;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * {@inheritdoc}
 */
class LoginType extends RegisterType {
	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver): void {
		//Call parent configure option
		parent::configureOptions($resolver);

		//Set defaults
		$resolver->setDefaults(['civility' => false, 'password' => true, 'password_repeated' => false, 'forename' => false, 'surname' => false]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return 'rapsysuser_login';
	}
}
