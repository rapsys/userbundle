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
use Rapsys\UserBundle\Entity\Title;

class RegisterType extends AbstractType {
	/**
	 * {@inheritdoc}
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		return $builder
			->add('mail', EmailType::class, ['attr' => ['placeholder' => 'Your mail'], 'constraints' => [new NotBlank(['message' => 'Please provide your mail']), new Email(['message' => 'Your mail doesn\'t seems to be valid'])]])
			->add('title', EntityType::class, ['class' => $options['class_title'], 'attr' => ['placeholder' => 'Your title'], 'constraints' => [new NotBlank(['message' => 'Please provide your title'])], 'choice_translation_domain' => true, 'data' => $options['title']])
			->add('pseudonym', TextType::class, ['attr' => ['placeholder' => 'Your pseudonym'], 'constraints' => [new NotBlank(['message' => 'Please provide your pseudonym'])]])
			->add('forename', TextType::class, ['attr' => ['placeholder' => 'Your forename'], 'constraints' => [new NotBlank(['message' => 'Please provide your forename'])]])
			->add('surname', TextType::class, ['attr' => ['placeholder' => 'Your surname'], 'constraints' => [new NotBlank(['message' => 'Please provide your surname'])]])
			->add('password', RepeatedType::class, ['type' => PasswordType::class, 'invalid_message' => 'The password and confirmation must match', 'first_options' => ['attr' => ['placeholder' => 'Your password'], 'label' => 'Password'], 'second_options' => ['attr' => ['placeholder' => 'Your password confirmation'], 'label' => 'Confirm password'], 'options' => ['constraints' => [new NotBlank(['message' => 'Please provide your password'])]]])
			->add('submit', SubmitType::class, ['label' => 'Send', 'attr' => ['class' => 'submit']]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(['error_bubbling' => true, 'class_title' => 'RapsysUserBundle:Title', 'title' => null]);
		$resolver->setAllowedTypes('class_title', 'string');
		$resolver->setAllowedTypes('title', [Title::class, 'null']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return 'rapsys_user_register';
	}
}
