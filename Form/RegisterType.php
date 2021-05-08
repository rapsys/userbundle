<?php

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
	public function buildForm(FormBuilderInterface $builder, array $options) {
		$form = $builder;

		//Add extra mail field
		if (!empty($options['mail'])) {
			$form->add('mail', EmailType::class, ['attr' => ['placeholder' => 'Your mail'], 'constraints' => [new NotBlank(['message' => 'Please provide your mail']), new Email(['message' => 'Your mail doesn\'t seems to be valid'])]]);
		}

		$form->add('civility', EntityType::class, ['class' => $options['class_civility'], 'attr' => ['placeholder' => 'Your civility'], 'constraints' => [new NotBlank(['message' => 'Please provide your civility'])], 'choice_translation_domain' => true, 'data' => $options['civility']])
			->add('pseudonym', TextType::class, ['attr' => ['placeholder' => 'Your pseudonym'], 'constraints' => [new NotBlank(['message' => 'Please provide your pseudonym'])]])
			->add('forename', TextType::class, ['attr' => ['placeholder' => 'Your forename'], 'constraints' => [new NotBlank(['message' => 'Please provide your forename'])]])
			->add('surname', TextType::class, ['attr' => ['placeholder' => 'Your surname'], 'constraints' => [new NotBlank(['message' => 'Please provide your surname'])]]);

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
	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(['error_bubbling' => true, 'class_civility' => 'RapsysUserBundle:Civility', 'civility' => null, 'password' => true, 'mail' => true]);
		$resolver->setAllowedTypes('class_civility', 'string');
		$resolver->setAllowedTypes('civility', [Civility::class, 'null']);
		$resolver->setAllowedTypes('password', 'boolean');
		$resolver->setAllowedTypes('mail', 'boolean');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return 'rapsys_user_register';
	}
}
