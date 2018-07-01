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
		return $builder->add('password', RepeatedType::class, array('type' => PasswordType::class, 'invalid_message' => 'The password and confirmation must match', 'first_options' => array('attr' => array('placeholder' => 'Your password'), 'label' => 'Password'), 'second_options' => array('attr' => array('placeholder' => 'Your password confirmation'), 'label' => 'Confirm password'), 'options' => array('constraints' => array(new NotBlank(array('message' => 'Please provide your password'))))))
			->add('submit', SubmitType::class, array('label' => 'Send', 'attr' => array('class' => 'submit')));
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
