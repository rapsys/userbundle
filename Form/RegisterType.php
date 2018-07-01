<?php

namespace Rapsys\UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegisterType extends AbstractType {
	/**
	 * {@inheritdoc}
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		return $builder->add('mail', EmailType::class, array('attr' => array('placeholder' => 'Your mail address'), 'constraints' => array(new NotBlank(array('message' => 'Please provide your mail')), new Email(array('message' => 'Your mail doesn\'t seems to be valid')))))
			#'RapsysUserBundle:Title'
			->add('title', EntityType::class, array('class' => $options['class_title'], 'choice_label' => 'title', 'attr' => array('placeholder' => 'Your title'), 'constraints' => array(new NotBlank(array('message' => 'Please provide your title')))))
			->add('pseudonym', TextType::class, array('attr' => array('placeholder' => 'Your pseudonym'), 'constraints' => array(new NotBlank(array('message' => 'Please provide your pseudonym')))))
			->add('forename', TextType::class, array('attr' => array('placeholder' => 'Your forename'), 'constraints' => array(new NotBlank(array('message' => 'Please provide your forename')))))
			->add('surname', TextType::class, array('attr' => array('placeholder' => 'Your surname'), 'constraints' => array(new NotBlank(array('message' => 'Please provide your surname')))))
			->add('password', RepeatedType::class, array('type' => PasswordType::class, 'invalid_message' => 'The password and confirmation must match', 'first_options' => array('attr' => array('placeholder' => 'Your password'), 'label' => 'Password'), 'second_options' => array('attr' => array('placeholder' => 'Your password confirmation'), 'label' => 'Confirm password'), 'options' => array('constraints' => array(new NotBlank(array('message' => 'Please provide your password'))))))
			->add('submit', SubmitType::class, array('label' => 'Send', 'attr' => array('class' => 'submit')));
	}

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(['error_bubbling' => true]);
		$resolver->setRequired('class_title');
		$resolver->setAllowedTypes('class_title', 'string');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return 'rapsys_user_register';
	}
}
