<?php

namespace Rapsys\UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\NotBlank;

class RecoverMailType extends AbstractType {
	/**
	 * {@inheritdoc}
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		return $builder
			->add('password', RepeatedType::class, ['type' => PasswordType::class, 'invalid_message' => 'The password and confirmation must match', 'first_options' => ['attr' => ['placeholder' => 'Your password'], 'label' => 'Password'], 'second_options' => ['attr' => ['placeholder' => 'Your password confirmation'], 'label' => 'Confirm password'], 'options' => ['constraints' => [new NotBlank(['message' => 'Please provide your password'])]]])
			->add('submit', SubmitType::class, ['label' => 'Send', 'attr' => ['class' => 'submit']]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(['error_bubbling' => true]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return 'rapsys_user_recover_mail';
	}
}
