<?php

namespace Rapsys\UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class RecoverType extends AbstractType {
	/**
	 * {@inheritdoc}
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		return $builder
			->add('mail', EmailType::class, ['attr' => ['placeholder' => 'Your mail'], 'constraints' => [new NotBlank(['message' => 'Please provide your mail']), new Email(['message' => 'Your mail doesn\'t seems to be valid'])]])
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
		return 'rapsys_user_recover';
	}
}
