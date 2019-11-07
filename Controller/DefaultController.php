<?php

namespace Rapsys\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Form\FormError;
use Rapsys\UserBundle\Utils\Slugger;

class DefaultController extends AbstractController {
	//Config array
	protected $config;

	//Translator instance
	protected $translator;

	public function __construct(ContainerInterface $container, Translator $translator) {
		//Retrieve config
		$this->config = $container->getParameter($this->getAlias());

		//Set the translator
		$this->translator = $translator;
	}

	//FIXME: we need to change the $this->container->getParameter($alias.'.xyz') to $this->container->getParameter($alias)['xyz']
	public function loginAction(Request $request, AuthenticationUtils $authenticationUtils) {
		//Get template
		$template = $this->config['login']['template'];
		//Get context
		$context = $this->config['login']['context'];

		//Create the form according to the FormType created previously.
		//And give the proper parameters
		$form = $this->createForm('Rapsys\UserBundle\Form\LoginType', null, array(
			// To set the action use $this->generateUrl('route_identifier')
			'action' => $this->generateUrl('rapsys_user_login'),
			'method' => 'POST'
		));

		//Get the login error if there is one
		if ($error = $authenticationUtils->getLastAuthenticationError()) {
			//Get translated error
			$error = $this->translator->trans($error->getMessageKey());

			//Add error message to mail field
			$form->get('mail')->addError(new FormError($error));
		}

		//Last username entered by the user
		if ($lastUsername = $authenticationUtils->getLastUsername()) {
			$form->get('mail')->setData($lastUsername);
		}

		//Render view
		return $this->render($template, $context+array('form' => $form->createView(), 'error' => $error));
	}

	public function registerAction(Request $request, UserPasswordEncoderInterface $encoder) {
		//Get mail template
		$mailTemplate = $this->config['register']['mail_template'];
		//Get mail context
		$mailContext = $this->config['register']['mail_context'];
		//Get template
		$template = $this->config['register']['template'];
		//Get context
		$context = $this->config['register']['context'];
		//Get home name
		$homeName = $this->config['contact']['home_name'];
		//Get home args
		$homeArgs = $this->config['contact']['home_args'];
		//Get contact name
		$contactName = $this->config['contact']['name'];
		//Get contact mail
		$contactMail = $this->config['contact']['mail'];
		//TODO: check if doctrine orm replacement is enough with default classes here
		//Get class user
		$classUser = $this->config['class']['user'];
		//Get class group
		$classGroup = $this->config['class']['group'];
		//Get class title
		$classTitle = $this->config['class']['title'];

		//Create the form according to the FormType created previously.
		//And give the proper parameters
		$form = $this->createForm('Rapsys\UserBundle\Form\RegisterType', null, array(
			// To set the action use $this->generateUrl('route_identifier')
			'class_title' => $classTitle,
			'action' => $this->generateUrl('rapsys_user_register'),
			'method' => 'POST'
		));

		if ($request->isMethod('POST')) {
			// Refill the fields in case the form is not valid.
			$form->handleRequest($request);

			if ($form->isValid()) {
				//Set data
				$data = $form->getData();

				//Translate title
				$mailContext['title'] = $this->translator->trans($mailContext['title']);

				//Translate title
				$mailContext['subtitle'] = $this->translator->trans($mailContext['subtitle'], array('%name%' => $data['forename'].' '.$data['surname'].' ('.$data['pseudonym'].')'));

				//Translate subject
				$mailContext['subject'] = $this->translator->trans($mailContext['subject'], array('%title%' => $mailContext['title']));

				//Translate message
				$mailContext['message'] = $this->translator->trans($mailContext['message'], array('%title%' => $mailContext['title']));

				//Create message
				$message = \Swift_Message::newInstance()
					->setSubject($mailContext['subject'])
					->setFrom(array($contactMail => $contactName))
					->setTo(array($data['mail'] => $data['forename'].' '.$data['surname']))
					->setBody($mailContext['message'])
					->addPart(
						$this->renderView(
							$mailTemplate,
							$mailContext+array(
								'home' => $this->get('router')->generate($homeName, $homeArgs, UrlGeneratorInterface::ABSOLUTE_URL)
							)
						),
						'text/html'
					);

				//Get doctrine
				$doctrine = $this->getDoctrine();

				//Get manager
				$manager = $doctrine->getManager();

				//Init reflection
				$reflection = new \ReflectionClass($classUser);

				//Create new user
				$user = $reflection->newInstance();

				$user->setMail($data['mail']);
				$user->setPseudonym($data['pseudonym']);
				$user->setForename($data['forename']);
				$user->setSurname($data['surname']);
				$user->setPassword($encoder->encodePassword($user, $data['password']));
				$user->setActive(true);
				$user->setTitle($data['title']);
				//TODO: see if we can't modify group constructor to set role directly from args
				//XXX: see vendor/symfony/symfony/src/Symfony/Component/Security/Core/Role/Role.php
				$user->addGroup($doctrine->getRepository($classGroup)->findOneByRole('ROLE_USER'));
				$user->setCreated(new \DateTime('now'));
				$user->setUpdated(new \DateTime('now'));

				//Persist user
				$manager->persist($user);

				try {
					//Send to database
					$manager->flush();

					//Send message
					if ($this->get('mailer')->send($message)) {
						//Redirect to cleanup the form
						return $this->redirectToRoute('rapsys_user_register', array('sent' => 1));
					}
				} catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
					//Add error message mail already exists
					$form->get('mail')->addError(new FormError($this->translator->trans('Account already exists: %mail%', array('%mail%' => $data['mail']))));
				}
			}
		}

		//Render view
		return $this->render($template, $context+array('form' => $form->createView(), 'sent' => $request->query->get('sent', 0)));
	}

	public function recoverAction(Request $request, Slugger $slugger) {
		//Get mail template
		$mailTemplate = $this->config['recover']['mail_template'];
		//Get mail context
		$mailContext = $this->config['recover']['mail_context'];
		//Get template
		$template = $this->config['recover']['template'];
		//Get context
		$context = $this->config['recover']['context'];
		//Get url name
		$urlName = $this->config['recover']['url_name'];
		//Get url args
		$urlArgs = $this->config['recover']['url_args'];
		//Get home name
		$homeName = $this->config['contact']['home_name'];
		//Get home args
		$homeArgs = $this->config['contact']['home_args'];
		//Get contact name
		$contactName = $this->config['contact']['name'];
		//Get contact mail
		$contactMail = $this->config['contact']['mail'];
		//Get class user
		$classUser = $this->config['class']['user'];

		//Create the form according to the FormType created previously.
		//And give the proper parameters
		$form = $this->createForm('Rapsys\UserBundle\Form\RecoverType', null, array(
			// To set the action use $this->generateUrl('route_identifier')
			'action' => $this->generateUrl('rapsys_user_recover'),
			'method' => 'POST'
		));

		if ($request->isMethod('POST')) {
			// Refill the fields in case the form is not valid.
			$form->handleRequest($request);

			if ($form->isValid()) {
				//Get doctrine
				$doctrine = $this->getDoctrine();

				//Set data
				$data = $form->getData();

				//Translate title
				$mailContext['title'] = $this->translator->trans($mailContext['title']);

				//Try to find user
				if ($user = $doctrine->getRepository($classUser)->findOneByMail($data['mail'])) {
					//Translate title
					$mailContext['subtitle'] = $this->translator->trans($mailContext['subtitle'], array('%name%' => $user->getForename().' '.$user->getSurname().' ('.$user->getPseudonym().')'));

					//Translate subject
					$mailContext['subject'] = $this->translator->trans($mailContext['subject'], array('%title%' => $mailContext['title']));

					//Translate message
					$mailContext['raw'] = $this->translator->trans($mailContext['raw'], array('%title%' => $mailContext['title'], '%url%' => $this->get('router')->generate($urlName, $urlArgs+array('mail' => $slugger->short($user->getMail()), 'hash' => $slugger->hash($user->getPassword())), UrlGeneratorInterface::ABSOLUTE_URL)));

					//Create message
					$message = \Swift_Message::newInstance()
						->setSubject($mailContext['subject'])
						->setFrom(array($contactMail => $contactName))
						->setTo(array($user->getMail() => $user->getForename().' '.$user->getSurname()))
						->setBody(strip_tags($mailContext['raw']))
						->addPart(
							$this->renderView(
								$mailTemplate,
								$mailContext+array(
									'home' => $this->get('router')->generate($homeName, $homeArgs, UrlGeneratorInterface::ABSOLUTE_URL)
								)
							),
							'text/html'
						);

					//Send message
					if ($this->get('mailer')->send($message)) {
						//Redirect to cleanup the form
						return $this->redirectToRoute('rapsys_user_recover', array('sent' => 1));
					}
				//Accout not found
				} else {
					//Add error message to mail field
					$form->get('mail')->addError(new FormError($this->translator->trans('Unable to find account: %mail%', array('%mail%' => $data['mail']))));
				}
			}
		}

		//Render view
		return $this->render($template, $context+array('form' => $form->createView(), 'sent' => $request->query->get('sent', 0)));
	}

	public function recoverMailAction(Request $request, UserPasswordEncoderInterface $encoder, Slugger $slugger, $mail, $hash) {
		//Get mail template
		$mailTemplate = $this->config['recover_mail']['mail_template'];
		//Get mail context
		$mailContext = $this->config['recover_mail']['mail_context'];
		//Get template
		$template = $this->config['recover_mail']['template'];
		//Get context
		$context = $this->config['recover_mail']['context'];
		//Get url name
		$urlName = $this->config['recover_mail']['url_name'];
		//Get url args
		$urlArgs = $this->config['recover_mail']['url_args'];
		//Get home name
		$homeName = $this->config['contact']['home_name'];
		//Get home args
		$homeArgs = $this->config['contact']['home_args'];
		//Get contact name
		$contactName = $this->config['contact']['name'];
		//Get contact mail
		$contactMail = $this->config['contact']['mail'];
		//Get class user
		$classUser = $this->config['class']['user'];

		//Create the form according to the FormType created previously.
		//And give the proper parameters
		$form = $this->createForm('Rapsys\UserBundle\Form\RecoverMailType', null, array(
			// To set the action use $this->generateUrl('route_identifier')
			'action' => $this->generateUrl('rapsys_user_recover_mail', array('mail' => $mail, 'hash' => $hash)),
			'method' => 'POST'
		));

		//Get doctrine
		$doctrine = $this->getDoctrine();

		//Init not found
		$notfound = 1;

		//Retrieve user
		if (($user = $doctrine->getRepository($classUser)->findOneByMail($slugger->unshort($mail))) && $hash == $slugger->hash($user->getPassword())) {
			//User was found
			$notfound = 0;

			if ($request->isMethod('POST')) {
				// Refill the fields in case the form is not valid.
				$form->handleRequest($request);

				if ($form->isValid()) {
					//Set data
					$data = $form->getData();

					//Translate title
					$mailContext['title'] = $this->translator->trans($mailContext['title']);

					//Translate title
					$mailContext['subtitle'] = $this->translator->trans($mailContext['subtitle'], array('%name%' => $user->getForename().' '.$user->getSurname().' ('.$user->getPseudonym().')'));

					//Translate subject
					$mailContext['subject'] = $this->translator->trans($mailContext['subject'], array('%title%' => $mailContext['title']));

					//Set user password
					$user->setPassword($encoder->encodePassword($user, $data['password']));

					//Translate message
					$mailContext['raw'] = $this->translator->trans($mailContext['raw'], array('%title%' => $mailContext['title'], '%url%' => $this->get('router')->generate($urlName, $urlArgs+array('mail' => $slugger->short($user->getMail()), 'hash' => $slugger->hash($user->getPassword())), UrlGeneratorInterface::ABSOLUTE_URL)));

					//Get manager
					$manager = $doctrine->getManager();

					//Persist user
					$manager->persist($user);

					//Send to database
					$manager->flush();

					//Create message
					$message = \Swift_Message::newInstance()
						->setSubject($mailContext['subject'])
						->setFrom(array($contactMail => $contactName))
						->setTo(array($user->getMail() => $user->getForename().' '.$user->getSurname()))
						->setBody(strip_tags($mailContext['raw']))
						->addPart(
							$this->renderView(
								$mailTemplate,
								$mailContext+array(
									'home' => $this->get('router')->generate($homeName, $homeArgs, UrlGeneratorInterface::ABSOLUTE_URL)
								)
							),
							'text/html'
						);

					//Send message
					if ($this->get('mailer')->send($message)) {
						//Redirect to cleanup the form
						return $this->redirectToRoute('rapsys_user_recover_mail', array('mail' => $mail, 'hash' => $hash, 'sent' => 1));
					}
				}
			}
		}

		//Render view
		return $this->render($template, $context+array('form' => $form->createView(), 'sent' => $request->query->get('sent', 0), 'notfound' => $notfound));
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAlias() {
		return 'rapsys_user';
	}
}
