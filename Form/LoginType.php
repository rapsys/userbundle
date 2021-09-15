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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class LoginType extends AbstractType {
	/**
	 * {@inheritdoc}
	 */
	public function buildForm(FormBuilderInterface $builder, array $options): FormBuilderInterface {
		//Create form
		$form = $builder;

		//Add extra mail field
		if (!empty($options['mail'])) {
			$form->add('mail', EmailType::class, ['attr' => ['placeholder' => 'Your mail'], 'constraints' => [new NotBlank(['message' => 'Please provide your mail']), new Email(['message' => 'Your mail doesn\'t seems to be valid'])], 'required' => true]);
		}

		//Add extra password field
		if (!empty($options['password'])) {
			//Add password repeated field
			if (!empty($options['password_repeated'])) {
				$form->add('password', RepeatedType::class, ['type' => PasswordType::class, 'invalid_message' => 'The password and confirmation must match', 'first_options' => ['attr' => ['placeholder' => 'Your password'], 'label' => 'Password'], 'second_options' => ['attr' => ['placeholder' => 'Your password confirmation'], 'label' => 'Confirm password'], 'options' => ['constraints' => [new NotBlank(['message' => 'Please provide your password'])]], 'required' => true]);
			//Add password field
			} else {
				$form->add('password', PasswordType::class, ['attr' => ['placeholder' => 'Your password'], 'constraints' => [new NotBlank(['message' => 'Please provide your password'])], 'required' => true]);
			}
		}

		//Add submit
		$form->add('submit', SubmitType::class, ['label' => 'Send', 'attr' => ['class' => 'submit']]);

		//Return form
		return $form;
	}

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver): void {
		//Set defaults
		$resolver->setDefaults(['error_bubbling' => true, 'mail' => true, 'password' => true, 'password_repeated' => true]);

		//Add extra mail option
		$resolver->setAllowedTypes('mail', 'boolean');

		//Add extra password option
		$resolver->setAllowedTypes('password', 'boolean');

		//Add extra password repeated option
		$resolver->setAllowedTypes('password_repeated', 'boolean');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return 'rapsys_user_login';
	}
}
