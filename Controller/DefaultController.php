<?php

namespace Rapsys\UserBundle\Controller;

use Rapsys\UserBundle\Utils\Slugger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\NamedAddress;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Translation\TranslatorInterface;

class DefaultController extends AbstractController {
	//Config array
	protected $config;

	//Translator instance
	protected $translator;

	public function __construct(ContainerInterface $container, TranslatorInterface $translator) {
		//Retrieve config
		$this->config = $container->getParameter($this->getAlias());

		//Set the translator
		$this->translator = $translator;
	}

	public function login(Request $request, AuthenticationUtils $authenticationUtils) {
		//Create the LoginType form and give the proper parameters
		$form = $this->createForm($this->config['login']['view']['form'], null, [
			//Set action to login route name and context
			'action' => $this->generateUrl($this->config['route']['login']['name'], $this->config['route']['login']['context']),
			'method' => 'POST'
		]);

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
		return $this->render(
			//Template
			$this->config['login']['view']['name'],
			//Context
			['form' => $form->createView(), 'error' => $error]+$this->config['login']['view']['context']
		);
	}

	public function recover(Request $request, Slugger $slugger, MailerInterface $mailer) {
		//Create the RecoverType form and give the proper parameters
		$form = $this->createForm($this->config['recover']['view']['form'], null, array(
			//Set action to recover route name and context
			'action' => $this->generateUrl($this->config['route']['recover']['name'], $this->config['route']['recover']['context']),
			'method' => 'POST'
		));

		if ($request->isMethod('POST')) {
			//Refill the fields in case the form is not valid.
			$form->handleRequest($request);

			if ($form->isValid()) {
				//Get doctrine
				$doctrine = $this->getDoctrine();

				//Set data
				$data = $form->getData();

				//Try to find user
				if ($user = $doctrine->getRepository($this->config['class']['user'])->findOneByMail($data['mail'])) {
					//Set mail shortcut
					$mail =& $this->config['recover']['mail'];

					//Generate each route route
					foreach($mail['route'] as $route => $tag) {
						//Only process defined routes
						if (empty($mail['context'][$tag]) && !empty($this->config['route'][$route])) {
							//Process for recover mail url
							if ($route == 'recover_mail') {
								//Prepend recover context with tag
								$this->config['route'][$route]['context'] = [
									'recipient' => $slugger->short($user->getMail()),
									'hash' => $slugger->hash($user->getPassword())
								]+$this->config['route'][$route]['context'];
							}
							//Set the url in context
							$mail['context'][$tag] = $this->get('router')->generate(
								$this->config['route'][$route]['name'],
								$this->config['route'][$route]['context'],
								UrlGeneratorInterface::ABSOLUTE_URL
							);

						}
					}

					//Set recipient_name
					$mail['context']['recipient_mail'] = $data['mail'];

					//Set recipient_name
					$mail['context']['recipient_name'] = trim($user->getForename().' '.$user->getSurname().($user->getPseudonym()?' ('.$user->getPseudonym().')':''));

					//Init subject context
					$subjectContext = [];

					//Process each context pair
					foreach($mail['context'] as $k => $v) {
						//Reinsert each context pair with the key surrounded by %
						$subjectContext['%'.$k.'%'] = $v;
					}

					//Translate subject
					$mail['subject'] = ucfirst($this->translator->trans($mail['subject'], $subjectContext));

					//Create message
					$message = (new TemplatedEmail())
						//Set sender
						->from(new NamedAddress($this->config['contact']['mail'], $this->config['contact']['name']))
						//Set recipient
						//XXX: remove the debug set in vendor/symfony/mime/Address.php +46
						->to(new NamedAddress($mail['context']['recipient_mail'], $mail['context']['recipient_name']))
						//Set subject
						->subject($mail['subject'])

						//Set path to twig templates
						->htmlTemplate($mail['html'])
						->textTemplate($mail['text'])

						//Set context
						->context(['subject' => $mail['subject']]+$mail['context']);

					//Try sending message
					//XXX: mail delivery may silently fail
					try {
						//Send message
						$mailer->send($message);

						//Redirect on the same route with sent=1 to cleanup form
						#return $this->redirectToRoute('rapsys_user_register', array('sent' => 1));
						return $this->redirectToRoute($request->get('_route'), ['sent' => 1]+$request->get('_route_params'));
					//Catch obvious transport exception
					} catch(TransportExceptionInterface $e) {
						//Add error message mail unreachable
						$form->get('mail')->addError(new FormError($this->translator->trans('Account found but unable to contact: %mail%', array('%mail%' => $data['mail']))));
					}
				//Accout not found
				} else {
					//Add error message to mail field
					$form->get('mail')->addError(new FormError($this->translator->trans('Unable to find account: %mail%', ['%mail%' => $data['mail']])));
				}
			}
		}

		//Render view
		return $this->render(
			//Template
			$this->config['recover']['view']['name'],
			//Context
			['form' => $form->createView(), 'sent' => $request->query->get('sent', 0)]+$this->config['recover']['view']['context']
		);
	}

	public function recoverMail(Request $request, UserPasswordEncoderInterface $encoder, Slugger $slugger, MailerInterface $mailer, $recipient, $hash) {
		//Create the RecoverType form and give the proper parameters
		$form = $this->createForm($this->config['recover_mail']['view']['form'], null, array(
			//Set action to recover route name and context
			'action' => $this->generateUrl($this->config['route']['recover_mail']['name'], ['recipient' => $recipient, 'hash' => $hash]+$this->config['route']['recover_mail']['context']),
			'method' => 'POST'
		));

		//Get doctrine
		$doctrine = $this->getDoctrine();

		//Init not found
		$notfound = 1;

		//Retrieve user
		if (($user = $doctrine->getRepository($this->config['class']['user'])->findOneByMail($slugger->unshort($recipient))) && $hash == $slugger->hash($user->getPassword())) {
			//User was found
			$notfound = 0;

			if ($request->isMethod('POST')) {
				//Refill the fields in case the form is not valid.
				$form->handleRequest($request);

				if ($form->isValid()) {
					//Set data
					$data = $form->getData();

					//set encoded password
					$encoded = $encoder->encodePassword($user, $data['password']);

					//Set user password
					$user->setPassword($encoded);

					//Get manager
					$manager = $doctrine->getManager();

					//Persist user
					$manager->persist($user);

					//Send to database
					$manager->flush();

					//Set mail shortcut
					$mail =& $this->config['recover_mail']['mail'];

					//Regen hash
					$hash = $slugger->hash($encoded);

					//Generate each route route
					foreach($mail['route'] as $route => $tag) {
						//Only process defined routes
						if (empty($mail['context'][$tag]) && !empty($this->config['route'][$route])) {
							//Process for recover mail url
							if ($route == 'recover_mail') {
								//Prepend recover context with tag
								$this->config['route'][$route]['context'] = [
									'recipient' => $recipient,
									'hash' => $hash
								]+$this->config['route'][$route]['context'];
							}
							//Set the url in context
							$mail['context'][$tag] = $this->get('router')->generate(
								$this->config['route'][$route]['name'],
								$this->config['route'][$route]['context'],
								UrlGeneratorInterface::ABSOLUTE_URL
							);
						}
					}

					//Set recipient_name
					$mail['context']['recipient_mail'] = $user->getMail();

					//Set recipient_name
					$mail['context']['recipient_name'] = trim($user->getForename().' '.$user->getSurname().($user->getPseudonym()?' ('.$user->getPseudonym().')':''));

					//Init subject context
					$subjectContext = [];

					//Process each context pair
					foreach($mail['context'] as $k => $v) {
						//Reinsert each context pair with the key surrounded by %
						$subjectContext['%'.$k.'%'] = $v;
					}

					//Translate subject
					$mail['subject'] = ucfirst($this->translator->trans($mail['subject'], $subjectContext));

					//Create message
					$message = (new TemplatedEmail())
						//Set sender
						->from(new NamedAddress($this->config['contact']['mail'], $this->config['contact']['name']))
						//Set recipient
						//XXX: remove the debug set in vendor/symfony/mime/Address.php +46
						->to(new NamedAddress($mail['context']['recipient_mail'], $mail['context']['recipient_name']))
						//Set subject
						->subject($mail['subject'])

						//Set path to twig templates
						->htmlTemplate($mail['html'])
						->textTemplate($mail['text'])

						//Set context
						->context(['subject' => $mail['subject']]+$mail['context']);

					//Try sending message
					//XXX: mail delivery may silently fail
					try {
						//Send message
						$mailer->send($message);

						//Redirect on the same route with sent=1 to cleanup form
						return $this->redirectToRoute($request->get('_route'), ['recipient' => $recipient, 'hash' => $hash, 'sent' => 1]+$request->get('_route_params'));
					//Catch obvious transport exception
					} catch(TransportExceptionInterface $e) {
						//Add error message mail unreachable
						$form->get('mail')->addError(new FormError($this->translator->trans('Account password updated but unable to contact: %mail%', array('%mail%' => $mail['context']['recipient_mail']))));
					}
				}
			}
		//Accout not found
		} else {
			//Add error message to mail field
			$form->get('mail')->addError(new FormError($this->translator->trans('Unable to find account: %mail%', ['%mail%' => $slugger->unshort($recipient)])));
		}

		//Render view
		return $this->render(
			//Template
			$this->config['recover_mail']['view']['name'],
			//Context
			['form' => $form->createView(), 'sent' => $request->query->get('sent', 0), 'notfound' => $notfound]+$this->config['recover_mail']['view']['context']
		);
	}

	public function register(Request $request, UserPasswordEncoderInterface $encoder, MailerInterface $mailer) {
		//Create the RegisterType form and give the proper parameters
		$form = $this->createForm($this->config['register']['view']['form'], null, array(
			'class_title' => $this->config['class']['title'],
			//Set action to register route name and context
			'action' => $this->generateUrl($this->config['route']['register']['name'], $this->config['route']['register']['context']),
			'method' => 'POST'
		));

		if ($request->isMethod('POST')) {
			// Refill the fields in case the form is not valid.
			$form->handleRequest($request);

			if ($form->isValid()) {
				//Set data
				$data = $form->getData();

				//Set mail shortcut
				$mail =& $this->config['register']['mail'];

				//Generate each route route
				foreach($mail['route'] as $route => $tag) {
					if (empty($mail['context'][$tag]) && !empty($this->config['route'][$route])) {
						$mail['context'][$tag] = $this->get('router')->generate(
							$this->config['route'][$route]['name'],
							$this->config['route'][$route]['context'],
							UrlGeneratorInterface::ABSOLUTE_URL
						);
					}
				}

				//Set recipient_name
				$mail['context']['recipient_mail'] = $data['mail'];

				//Set recipient_name
				$mail['context']['recipient_name'] = trim($data['forename'].' '.$data['surname'].($data['pseudonym']?' ('.$data['pseudonym'].')':''));

				//Init subject context
				$subjectContext = [];

				//Process each context pair
				foreach($mail['context'] as $k => $v) {
					//Reinsert each context pair with the key surrounded by %
					$subjectContext['%'.$k.'%'] = $v;
				}

				//Translate subject
				$mail['subject'] = ucfirst($this->translator->trans($mail['subject'], $subjectContext));

				//Create message
				$message = (new TemplatedEmail())
					//Set sender
					->from(new NamedAddress($this->config['contact']['mail'], $this->config['contact']['name']))
					//Set recipient
					//XXX: remove the debug set in vendor/symfony/mime/Address.php +46
					->to(new NamedAddress($mail['context']['recipient_mail'], $mail['context']['recipient_name']))
					//Set subject
					->subject($mail['subject'])

					//Set path to twig templates
					->htmlTemplate($mail['html'])
					->textTemplate($mail['text'])

					//Set context
					->context(['subject' => $mail['subject']]+$mail['context']);

				//Get doctrine
				$doctrine = $this->getDoctrine();

				//Get manager
				$manager = $doctrine->getManager();

				//Init reflection
				$reflection = new \ReflectionClass($this->config['class']['user']);

				//Create new user
				$user = $reflection->newInstance();

				$user->setMail($data['mail']);
				$user->setPseudonym($data['pseudonym']);
				$user->setForename($data['forename']);
				$user->setSurname($data['surname']);
				$user->setPhone($data['phone']);
				$user->setPassword($encoder->encodePassword($user, $data['password']));
				$user->setActive(true);
				$user->setTitle($data['title']);

				//XXX: For now there is no point in setting a role at subscription
				//TODO: see if we can't modify group constructor to set role directly from args
				//XXX: see vendor/symfony/symfony/src/Symfony/Component/Security/Core/Role/Role.php
				#$user->addGroup($doctrine->getRepository($this->config['class']['group'])->findOneByRole('ROLE_USER'));

				$user->setCreated(new \DateTime('now'));
				$user->setUpdated(new \DateTime('now'));

				//Persist user
				$manager->persist($user);

				//Try saving in database
				try {
					//Send to database
					$manager->flush();

					//Try sending message
					//XXX: mail delivery may silently fail
					try {
						//Send message
						$mailer->send($message);

						//Redirect on the same route with sent=1 to cleanup form
						#return $this->redirectToRoute('rapsys_user_register', array('sent' => 1));
						return $this->redirectToRoute($request->get('_route'), ['sent' => 1]+$request->get('_route_params'));
					//Catch obvious transport exception
					} catch(TransportExceptionInterface $e) {
						//Add error message mail unreachable
						$form->get('mail')->addError(new FormError($this->translator->trans('Account created but unable to contact: %mail%', array('%mail%' => $data['mail']))));
					}
				//Catch double subscription
				} catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
					//Add error message mail already exists
					$form->get('mail')->addError(new FormError($this->translator->trans('Account already exists: %mail%', ['%mail%' => $data['mail']])));
				}
			}
		}

		//Render view
		return $this->render(
			//Template
			$this->config['register']['view']['name'],
			//Context
			['form' => $form->createView(), 'sent' => $request->query->get('sent', 0)]+$this->config['register']['view']['context']
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAlias() {
		return 'rapsys_user';
	}
}
