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

use Rapsys\PackBundle\Form\CaptchaType;

use Rapsys\UserBundle\Entity\Civility;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * {@inheritdoc}
 */
class RegisterType extends CaptchaType {
	/**
	 * {@inheritdoc}
	 */
	public function buildForm(FormBuilderInterface $builder, array $options): void {
		//Add extra mail field
		if (!empty($options['mail'])) {
			$builder->add('mail', EmailType::class, ['attr' => ['placeholder' => 'Your mail'], 'constraints' => [new NotBlank(['message' => 'Please provide your mail']), new Email(['message' => 'Your mail doesn\'t seems to be valid'])], 'required' => true]);
		}

		//Add extra password field
		if (!empty($options['password'])) {
			//Add password repeated field
			if (!empty($options['password_repeated'])) {
				$builder->add('password', RepeatedType::class, ['type' => PasswordType::class, 'invalid_message' => 'The password and confirmation must match', 'first_options' => ['attr' => ['placeholder' => 'Your password'], 'label' => 'Password'], 'second_options' => ['attr' => ['placeholder' => 'Your password confirmation'], 'label' => 'Confirm password'], 'options' => ['constraints' => [new NotBlank(['message' => 'Please provide your password'])]], 'required' => true]);
			//Add password field
			} else {
				$builder->add('password', PasswordType::class, ['attr' => ['placeholder' => 'Your password'], 'constraints' => [new NotBlank(['message' => 'Please provide your password'])], 'required' => true]);
			}
		}

		//Add extra civility field
		if (!empty($options['civility'])) {
			$builder->add('civility', EntityType::class, ['class' => $options['civility_class'], 'attr' => ['placeholder' => 'Your civility'], 'choice_translation_domain' => true, 'empty_data' => $options['civility_default'], 'required' => true]);
		}

		//Add extra forename field
		if (!empty($options['forename'])) {
			$builder->add('forename', TextType::class, ['attr' => ['placeholder' => 'Your forename']]);
		}

		//Add extra surname field
		if (!empty($options['surname'])) {
			$builder->add('surname', TextType::class, ['attr' => ['placeholder' => 'Your surname']]);
		}

		//Add extra active field
		if (!empty($options['active'])) {
			$builder->add('active', CheckboxType::class, ['attr' => ['placeholder' => 'Your active']]);
		}

		//Add extra enable field
		if (!empty($options['enable'])) {
			$builder->add('enable', CheckboxType::class, ['attr' => ['placeholder' => 'Your enable']]);
		}

		//Add submit
		$builder->add('submit', SubmitType::class, ['label' => 'Send', 'attr' => ['class' => 'submit']]);

		//Call parent
		parent::buildForm($builder, $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver): void {
		//Call parent configure options
		parent::configureOptions($resolver);

		//Set defaults
		$resolver->setDefaults(['error_bubbling' => true, 'civility' => true, 'civility_class' => 'Rapsys\UserBundle\Entity\Civility', 'civility_default' => null, 'mail' => true, 'password' => true, 'password_repeated' => true, 'forename' => true, 'surname' => true, 'active' => false, 'enable' => false]);

		//Add extra civility option
		$resolver->setAllowedTypes('civility', 'boolean');

		//Add civility class
		$resolver->setAllowedTypes('civility_class', 'string');

		//Add civility default
		//XXX: trigger strange error about table not existing is not specified in form create
		$resolver->setAllowedTypes('civility_default', [Civility::class, 'null']);

		//Add extra mail option
		$resolver->setAllowedTypes('mail', 'boolean');

		//Add extra password option
		$resolver->setAllowedTypes('password', 'boolean');

		//Add extra password repeated option
		$resolver->setAllowedTypes('password_repeated', 'boolean');

		//Add extra forename option
		$resolver->setAllowedTypes('forename', 'boolean');

		//Add extra surname option
		$resolver->setAllowedTypes('surname', 'boolean');

		//Add extra active option
		$resolver->setAllowedTypes('active', 'boolean');

		//Add extra enable option
		$resolver->setAllowedTypes('enable', 'boolean');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return 'rapsysuser_register';
	}
}
