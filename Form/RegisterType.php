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

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

use Rapsys\UserBundle\Entity\Civility;

class RegisterType extends AbstractType {
	/**
	 * {@inheritdoc}
	 */
	public function buildForm(FormBuilderInterface $builder, array $options): FormBuilderInterface {
		//Create form
		$form = $builder;

		//Add extra mail field
		if (!empty($options['mail'])) {
			$form->add('mail', EmailType::class, ['attr' => ['placeholder' => 'Your mail'], 'constraints' => [new NotBlank(['message' => 'Please provide your mail']), new Email(['message' => 'Your mail doesn\'t seems to be valid'])]]);
		}

		//Add extra civility field
		if (!empty($options['civility'])) {
			$form->add('civility', EntityType::class, ['class' => $options['civility_class'], 'attr' => ['placeholder' => 'Your civility'], 'constraints' => [new NotBlank(['message' => 'Please provide your civility'])], 'choice_translation_domain' => true, 'empty_data' => $options['civility_default']]);
		}

		//Add extra forename field
		if (!empty($options['forename'])) {
			$form->add('forename', TextType::class, ['attr' => ['placeholder' => 'Your forename'], 'constraints' => [new NotBlank(['message' => 'Please provide your forename'])]]);
		}

		//Add extra surname field
		if (!empty($options['surname'])) {
			$form->add('surname', TextType::class, ['attr' => ['placeholder' => 'Your surname'], 'constraints' => [new NotBlank(['message' => 'Please provide your surname'])]]);
		}

		//Add extra password field
		if (!empty($options['password'])) {
			$form->add('password', RepeatedType::class, ['type' => PasswordType::class, 'invalid_message' => 'The password and confirmation must match', 'first_options' => ['attr' => ['placeholder' => 'Your password'], 'label' => 'Password'], 'second_options' => ['attr' => ['placeholder' => 'Your password confirmation'], 'label' => 'Confirm password'], 'options' => ['constraints' => [new NotBlank(['message' => 'Please provide your password'])]]]);
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
		$resolver->setDefaults(['error_bubbling' => true, 'civility_class' => 'RapsysUserBundle:Civility', 'civility_default' => null, 'mail' => true, 'civility' => true, 'forename' => true, 'surname' => true, 'password' => true]);

		//Add civility class
		$resolver->setAllowedTypes('civility_class', 'string');

		//Add civility default
		//XXX: trigger strange error about table not existing is not specified in form create
		$resolver->setAllowedTypes('civility_default', [Civility::class, 'null']);

		//Add extra mail option
		$resolver->setAllowedTypes('mail', 'boolean');

		//Add extra civility option
		$resolver->setAllowedTypes('civility', 'boolean');

		//Add extra forename option
		$resolver->setAllowedTypes('forename', 'boolean');

		//Add extra surname option
		$resolver->setAllowedTypes('surname', 'boolean');

		//Add extra password option
		$resolver->setAllowedTypes('password', 'boolean');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return 'rapsys_user_register';
	}
}
