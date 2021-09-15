<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys UserBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\UserBundle\Controller;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * {@inheritdoc}
 */
class DefaultController extends AbstractController {
	/**
	 * Confirm account from mail link
	 *
	 * @param Request $request The request
	 * @param string $hash The hashed password
	 * @param string $mail The shorted mail address
	 * @return Response The response
	 */
	public function confirm(Request $request, string $hash, string $mail): Response {
		//With invalid hash
		if ($hash != $this->slugger->hash($mail)) {
			//Throw bad request
			throw new BadRequestHttpException($this->translator->trans('Invalid %field% field: %value%', ['%field%' => 'hash', '%value%' => $hash]));
		}

		//Get mail
		$mail = $this->slugger->unshort($smail = $mail);

		//Without valid mail
		if (filter_var($mail, FILTER_VALIDATE_EMAIL) === false) {
			//Throw bad request
			//XXX: prevent slugger reverse engineering by not displaying decoded mail
			throw new BadRequestHttpException($this->translator->trans('Invalid %field% field: %value%', ['%field%' => 'mail', '%value%' => $smail]));
		}

		//Without existing registrant
		if (!($user = $this->doctrine->getRepository($this->config['class']['user'])->findOneByMail($mail))) {
			//Add error message mail already exists
			//XXX: prevent slugger reverse engineering by not displaying decoded mail
			$this->addFlash('error', $this->translator->trans('Account %mail% do not exists', ['%mail%' => $smail]));

			//Redirect to register view
			return $this->redirectToRoute($this->config['route']['register']['name'], ['mail' => $smail, 'field' => $sfield = $this->slugger->serialize([]), 'hash' => $this->slugger->hash($smail.$sfield)]+$this->config['route']['register']['context']);
		}

		//Set active
		$user->setActive(true);

		//Persist user
		$this->manager->persist($user);

		//Send to database
		$this->manager->flush();

		//Add error message mail already exists
		$this->addFlash('notice', $this->translator->trans('Your account has been activated'));

		//Redirect to user view
		return $this->redirectToRoute($this->config['route']['edit']['name'], ['mail' => $smail, 'hash' => $this->slugger->hash($smail)]+$this->config['route']['edit']['context']);
	}

	/**
	 * Edit account by shorted mail
	 *
	 * @param Request $request The request
	 * @param string $hash The hashed password
	 * @param string $mail The shorted mail address
	 * @return Response The response
	 */
	public function edit(Request $request, string $hash, string $mail): Response {
		//With invalid hash
		if ($hash != $this->slugger->hash($mail)) {
			//Throw bad request
			throw new BadRequestHttpException($this->translator->trans('Invalid %field% field: %value%', ['%field%' => 'hash', '%value%' => $hash]));
		}

		//Get mail
		$mail = $this->slugger->unshort($smail = $mail);

		//With existing subscriber
		if (empty($user = $this->doctrine->getRepository($this->config['class']['user'])->findOneByMail($mail))) {
			//Throw not found
			//XXX: prevent slugger reverse engineering by not displaying decoded mail
			throw $this->createNotFoundException($this->translator->trans('Unable to find account %mail%', ['%mail%' => $smail]));
		}

		//Prevent access when not admin, user is not guest and not currently logged user
		if (!$this->isGranted('ROLE_ADMIN') && $user != $this->getUser() || !$this->isGranted('IS_AUTHENTICATED_FULLY')) {
			//Throw access denied
			//XXX: prevent slugger reverse engineering by not displaying decoded mail
			throw $this->createAccessDeniedException($this->translator->trans('Unable to access user: %mail%', ['%mail%' => $smail]));
		}

		//Create the RegisterType form and give the proper parameters
		$edit = $this->createForm($this->config['edit']['view']['edit'], $user, [
			//Set action to register route name and context
			'action' => $this->generateUrl($this->config['route']['edit']['name'], ['mail' => $smail, 'hash' => $this->slugger->hash($smail)]+$this->config['route']['edit']['context']),
			//Set civility class
			'civility_class' => $this->config['class']['civility'],
			//Set civility default
			'civility_default' => $this->doctrine->getRepository($this->config['class']['civility'])->findOneByTitle($this->config['default']['civility']),
			//Disable mail
			'mail' => $this->isGranted('ROLE_ADMIN'),
			//Disable password
			'password' => false,
			//Set method
			'method' => 'POST'
		]+$this->config['edit']['field']);

		//With admin role
		if ($this->isGranted('ROLE_ADMIN')) {
			//Create the LoginType form and give the proper parameters
			$reset = $this->createForm($this->config['edit']['view']['reset'], $user, [
				//Set action to register route name and context
				'action' => $this->generateUrl($this->config['route']['edit']['name'], ['mail' => $smail, 'hash' => $this->slugger->hash($smail)]+$this->config['route']['edit']['context']),
				//Disable mail
				'mail' => false,
				//Set method
				'method' => 'POST'
			]);

			//With post method
			if ($request->isMethod('POST')) {
				//Refill the fields in case the form is not valid.
				$reset->handleRequest($request);

				//With reset submitted and valid
				if ($reset->isSubmitted() && $reset->isValid()) {
					//Set data
					$data = $reset->getData();

					//Set password
					$data->setPassword($this->hasher->hashPassword($data, $data->getPassword()));

					//Queue snippet save
					$this->manager->persist($data);

					//Flush to get the ids
					$this->manager->flush();

					//Add notice
					$this->addFlash('notice', $this->translator->trans('Account %mail% password updated', ['%mail%' => $mail = $data->getMail()]));

					//Redirect to cleanup the form
					return $this->redirectToRoute($this->config['route']['edit']['name'], ['mail' => $smail = $this->slugger->short($mail), 'hash' => $this->slugger->hash($smail)]+$this->config['route']['edit']['context']);
				}
			}

			//Add reset view
			$this->config['edit']['view']['context']['reset'] = $reset->createView();
		}

		//With post method
		if ($request->isMethod('POST')) {
			//Refill the fields in case the form is not valid.
			$edit->handleRequest($request);

			//With edit submitted and valid
			if ($edit->isSubmitted() && $edit->isValid()) {
				//Set data
				$data = $edit->getData();

				//Queue snippet save
				$this->manager->persist($data);

				//Try saving in database
				try {
					//Flush to get the ids
					$this->manager->flush();

					//Add notice
					$this->addFlash('notice', $this->translator->trans('Account %mail% updated', ['%mail%' => $mail = $data->getMail()]));

					//Redirect to cleanup the form
					return $this->redirectToRoute($this->config['route']['edit']['name'], ['mail' => $smail = $this->slugger->short($mail), 'hash' => $this->slugger->hash($smail)]+$this->config['route']['edit']['context']);
				//Catch double slug or mail
				} catch (UniqueConstraintViolationException $e) {
					//Add error message mail already exists
					$this->addFlash('error', $this->translator->trans('Account %mail% already exists', ['%mail%' => $data->getMail()]));
				}
			}
		//Without admin role
		//XXX: prefer a reset on login to force user unspam action
		} elseif (!$this->isGranted('ROLE_ADMIN')) {
			//Add notice
			$this->addFlash('notice', $this->translator->trans('To change your password login with your mail and any password then follow the procedure'));
		}

		//Render view
		return $this->render(
			//Template
			$this->config['edit']['view']['name'],
			//Context
			['edit' => $edit->createView(), 'sent' => $request->query->get('sent', 0)]+$this->config['edit']['view']['context']
		);
	}

	/**
	 * Login
	 *
	 * @param Request $request The request
	 * @param AuthenticationUtils $authenticationUtils The authentication utils
	 * @param ?string $hash The hashed password
	 * @param ?string $mail The shorted mail address
	 * @return Response The response
	 */
	public function login(Request $request, AuthenticationUtils $authenticationUtils, ?string $hash, ?string $mail): Response {
		//Create the LoginType form and give the proper parameters
		$login = $this->createForm($this->config['login']['view']['form'], null, [
			//Set action to login route name and context
			'action' => $this->generateUrl($this->config['route']['login']['name'], $this->config['route']['login']['context']),
			//Disable repeated password
			'password_repeated' => false,
			//Set method
			'method' => 'POST'
		]);

		//Init context
		$context = [];

		//With mail
		if (!empty($mail) && !empty($hash)) {
			//With invalid hash
			if ($hash != $this->slugger->hash($mail)) {
				//Throw bad request
				throw new BadRequestHttpException($this->translator->trans('Invalid %field% field: %value%', ['%field%' => 'hash', '%value%' => $hash]));
			}

			//Get mail
			$mail = $this->slugger->unshort($smail = $mail);

			//Without valid mail
			if (filter_var($mail, FILTER_VALIDATE_EMAIL) === false) {
				//Throw bad request
				throw new BadRequestHttpException($this->translator->trans('Invalid %field% field: %value%', ['%field%' => 'mail', '%value%' => $smail]));
			}

			//Prefilled mail
			$login->get('mail')->setData($mail);
		//Last username entered by the user
		} elseif ($lastUsername = $authenticationUtils->getLastUsername()) {
			$login->get('mail')->setData($lastUsername);
		}

		//Get the login error if there is one
		if ($error = $authenticationUtils->getLastAuthenticationError()) {
			//Get translated error
			$error = $this->translator->trans($error->getMessageKey());

			//Add error message to mail field
			$login->get('mail')->addError(new FormError($error));

			//Create the LoginType form and give the proper parameters
			$recover = $this->createForm($this->config['recover']['view']['form'], null, [
				//Set action to recover route name and context
				'action' => $this->generateUrl($this->config['route']['recover']['name'], $this->config['route']['recover']['context']),
				//Without password
				'password' => false,
				//Set method
				'method' => 'POST'
			]);

			//Get recover mail entity
			$recover->get('mail')
				//Set mail from login form
				->setData($login->get('mail')->getData())
				//Add recover error
				->addError(new FormError($this->translator->trans('Use this form to recover your account')));

			//Add recover form to context
			$context['recover'] = $recover->createView();
		} else {
			//Add notice
			$this->addFlash('notice', $this->translator->trans('To change your password login with your mail and any password then follow the procedure'));
		}

		//Render view
		return $this->render(
			//Template
			$this->config['login']['view']['name'],
			//Context
			['login' => $login->createView()]+$context+$this->config['login']['view']['context']
		);
	}

	/**
	 * Recover account
	 *
	 * @param Request $request The request
	 * @param ?string $hash The hashed password
	 * @param ?string $pass The shorted password
	 * @param ?string $mail The shorted mail address
	 * @return Response The response
	 */
	public function recover(Request $request, ?string $hash, ?string $pass, ?string $mail): Response {
		//Without mail, pass and hash
		if (empty($mail) && empty($pass) && empty($hash)) {
			//Create the LoginType form and give the proper parameters
			$form = $this->createForm($this->config['recover']['view']['form'], null, [
				//Set action to recover route name and context
				'action' => $this->generateUrl($this->config['route']['recover']['name'], $this->config['route']['recover']['context']),
				//Without password
				'password' => false,
				//Set method
				'method' => 'POST'
			]);

			//With post method
			if ($request->isMethod('POST')) {
				//Refill the fields in case the form is not valid.
				$form->handleRequest($request);

				//With form submitted and valid
				if ($form->isSubmitted() && $form->isValid()) {
					//Set data
					$data = $form->getData();

					//Find user by data mail
					if ($user = $this->doctrine->getRepository($this->config['class']['user'])->findOneByMail($data['mail'])) {
						//Set mail shortcut
						$recoverMail =& $this->config['recover']['mail'];

						//Set mail
						$mail = $this->slugger->short($user->getMail());

						//Set pass
						$pass = $this->slugger->hash($user->getPassword());

						//Generate each route route
						foreach($this->config['recover']['route'] as $route => $tag) {
							//Only process defined routes
							if (!empty($this->config['route'][$route])) {
								//Process for recover mail url
								if ($route == 'recover') {
									//Set the url in context
									$recoverMail['context'][$tag] = $this->router->generate(
										$this->config['route'][$route]['name'],
										//Prepend recover context with tag
										[
											'mail' => $mail,
											'pass' => $pass,
											'hash' => $this->slugger->hash($mail.$pass)
										]+$this->config['route'][$route]['context'],
										UrlGeneratorInterface::ABSOLUTE_URL
									);
								}
							}
						}

						//Set recipient_name
						$recoverMail['context']['recipient_mail'] = $user->getMail();

						//Set recipient_name
						$recoverMail['context']['recipient_name'] = $user->getRecipientName();

						//Init subject context
						$subjectContext = $this->slugger->flatten(array_replace_recursive($this->config['recover']['view']['context'], $recoverMail['context']), null, '.', '%', '%');

						//Translate subject
						$recoverMail['subject'] = ucfirst($this->translator->trans($recoverMail['subject'], $subjectContext));

						//Create message
						$message = (new TemplatedEmail())
							//Set sender
							->from(new Address($this->config['contact']['mail'], $this->config['contact']['title']))
							//Set recipient
							//XXX: remove the debug set in vendor/symfony/mime/Address.php +46
							->to(new Address($recoverMail['context']['recipient_mail'], $recoverMail['context']['recipient_name']))
							//Set subject
							->subject($recoverMail['subject'])

							//Set path to twig templates
							->htmlTemplate($recoverMail['html'])
							->textTemplate($recoverMail['text'])

							//Set context
							//XXX: require recursive merge to avoid loosing subkeys
							//['subject' => $recoverMail['subject']]+$recoverMail['context']+$this->config['recover']['view']['context']
							->context(array_replace_recursive($this->config['recover']['view']['context'], $recoverMail['context'], ['subject' => $recoverMail['subject']]));

						//Try sending message
						//XXX: mail delivery may silently fail
						try {
							//Send message
							$this->mailer->send($message);

							//Redirect on the same route with sent=1 to cleanup form
							return $this->redirectToRoute($request->get('_route'), ['sent' => 1]+$request->get('_route_params'));
						//Catch obvious transport exception
						} catch(TransportExceptionInterface $e) {
							//Add error message mail unreachable
							$form->get('mail')->addError(new FormError($this->translator->trans('Account found but unable to contact: %mail%', array('%mail%' => $data['mail']))));
						}
					//Accout not found
					} else {
						//Add error message to mail field
						$form->get('mail')->addError(new FormError($this->translator->trans('Unable to find account %mail%', ['%mail%' => $data['mail']])));
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

		//With invalid hash
		if ($hash != $this->slugger->hash($mail.$pass)) {
			//Throw bad request
			throw new BadRequestHttpException($this->translator->trans('Invalid %field% field: %value%', ['%field%' => 'hash', '%value%' => $hash]));
		}

		//Get mail
		$mail = $this->slugger->unshort($smail = $mail);

		//Without valid mail
		if (filter_var($mail, FILTER_VALIDATE_EMAIL) === false) {
			//Throw bad request
			//XXX: prevent slugger reverse engineering by not displaying decoded mail
			throw new BadRequestHttpException($this->translator->trans('Invalid %field% field: %value%', ['%field%' => 'mail', '%value%' => $smail]));
		}

		//With existing subscriber
		if (empty($user = $this->doctrine->getRepository($this->config['class']['user'])->findOneByMail($mail))) {
			//Throw not found
			//XXX: prevent slugger reverse engineering by not displaying decoded mail
			throw $this->createNotFoundException($this->translator->trans('Unable to find account %mail%', ['%mail%' => $smail]));
		}

		//With unmatched pass
		if ($pass != $this->slugger->hash($user->getPassword())) {
			//Throw not found
			//XXX: prevent use of outdated recover link
			throw $this->createNotFoundException($this->translator->trans('Outdated recover link'));
		}

		//Create the LoginType form and give the proper parameters
		$form = $this->createForm($this->config['recover']['view']['form'], $user, [
			//Set action to recover route name and context
			'action' => $this->generateUrl($this->config['route']['recover']['name'], ['mail' => $smail, 'pass' => $pass, 'hash' => $hash]+$this->config['route']['recover']['context']),
			//Without mail
			'mail' => false,
			//Set method
			'method' => 'POST'
		]);

		//With post method
		if ($request->isMethod('POST')) {
			//Refill the fields in case the form is not valid.
			$form->handleRequest($request);

			//With form submitted and valid
			if ($form->isSubmitted() && $form->isValid()) {
				//Set data
				$data = $form->getData();

				//Set hashed password
				$hashed = $this->hasher->hashPassword($user, $user->getPassword());

				//Update pass
				$pass = $this->slugger->hash($hashed);

				//Set user password
				$user->setPassword($hashed);

				//Persist user
				$this->manager->persist($user);

				//Send to database
				$this->manager->flush();

				//Add notice
				$this->addFlash('notice', $this->translator->trans('Account %mail% password updated', ['%mail%' => $mail]));

				//Redirect to user login
				return $this->redirectToRoute($this->config['route']['login']['name'], ['mail' => $smail, 'hash' => $this->slugger->hash($smail)]+$this->config['route']['login']['context']);
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

	/**
	 * Register an account
	 *
	 * @param Request $request The request
	 * @param ?string $hash The hashed serialized field array
	 * @param ?string $field The serialized then shorted form field array
	 * @param ?string $mail The shorted mail address
	 * @return Response The response
	 */
	public function register(Request $request, ?string $hash, ?string $field, ?string $mail): Response {
		//With mail
		if (!empty($_POST['register']['mail'])) {
			//Log new user infos
			$this->logger->emergency(
				$this->translator->trans(
					'register: mail=%mail% locale=%locale% confirm=%confirm%',
					[
						'%mail%' => $postMail = $_POST['register']['mail'],
						'%locale%' => $request->getLocale(),
						'%confirm%' => $this->router->generate(
							$this->config['route']['confirm']['name'],
							//Prepend subscribe context with tag
							[
								'mail' => $postSmail = $this->slugger->short($postMail),
								'hash' => $this->slugger->hash($postSmail)
							]+$this->config['route']['confirm']['context'],
							UrlGeneratorInterface::ABSOLUTE_URL
						)
					]
				)
			);
		}

		//With mail and field
		if (!empty($field) && !empty($hash)) {
			//With invalid hash
			if ($hash != $this->slugger->hash($mail.$field)) {
				//Throw bad request
				throw new BadRequestHttpException($this->translator->trans('Invalid %field% field: %value%', ['%field%' => 'hash', '%value%' => $hash]));
			}

			//With mail
			if (!empty($mail)) {
				//Get mail
				$mail = $this->slugger->unshort($smail = $mail);

				//Without valid mail
				if (filter_var($mail, FILTER_VALIDATE_EMAIL) === false) {
					//Throw bad request
					//XXX: prevent slugger reverse engineering by not displaying decoded mail
					throw new BadRequestHttpException($this->translator->trans('Invalid %field% field: %value%', ['%field%' => 'mail', '%value%' => $smail]));
				}

				//With existing registrant
				if ($existing = $this->doctrine->getRepository($this->config['class']['user'])->findOneByMail($mail)) {
					//With disabled existing
					if ($existing->isDisabled()) {
						//Render view
						$response = $this->render(
							//Template
							$this->config['register']['view']['name'],
							//Context
							['title' => $this->translator->trans('Access denied'), 'disabled' => 1]+$this->config['register']['view']['context']
						);

						//Set 403
						$response->setStatusCode(403);

						//Return response
						return $response;
					//With unactivated existing
					} elseif (!$existing->isActivated()) {
						//Set mail shortcut
						$activateMail =& $this->config['register']['mail'];

						//Generate each route route
						foreach($this->config['register']['route'] as $route => $tag) {
							//Only process defined routes
							if (!empty($this->config['route'][$route])) {
								//Process for confirm url
								if ($route == 'confirm') {
									//Set the url in context
									$activateMail['context'][$tag] = $this->router->generate(
										$this->config['route'][$route]['name'],
										//Prepend subscribe context with tag
										[
											'mail' => $smail = $this->slugger->short($existing->getMail()),
											'hash' => $this->slugger->hash($smail)
										]+$this->config['route'][$route]['context'],
										UrlGeneratorInterface::ABSOLUTE_URL
									);
								}
							}
						}

						//Set recipient_name
						$activateMail['context']['recipient_mail'] = $existing->getMail();

						//Set recipient name
						$activateMail['context']['recipient_name'] = $existing->getRecipientName();

						//Init subject context
						$subjectContext = $this->slugger->flatten(array_replace_recursive($this->config['register']['view']['context'], $activateMail['context']), null, '.', '%', '%');

						//Translate subject
						$activateMail['subject'] = ucfirst($this->translator->trans($activateMail['subject'], $subjectContext));

						//Create message
						$message = (new TemplatedEmail())
							//Set sender
							->from(new Address($this->config['contact']['mail'], $this->config['contact']['title']))
							//Set recipient
							//XXX: remove the debug set in vendor/symfony/mime/Address.php +46
							->to(new Address($activateMail['context']['recipient_mail'], $activateMail['context']['recipient_name']))
							//Set subject
							->subject($activateMail['subject'])

							//Set path to twig templates
							->htmlTemplate($activateMail['html'])
							->textTemplate($activateMail['text'])

							//Set context
							->context(['subject' => $activateMail['subject']]+$activateMail['context']);

						//Try sending message
						//XXX: mail delivery may silently fail
						try {
							//Send message
							$this->mailer->send($message);
						//Catch obvious transport exception
						} catch(TransportExceptionInterface $e) {
							//Add error message mail unreachable
							$this->addFlash('error', $this->translator->trans('Account %mail% tried activate but unable to contact', ['%mail%' => $existing->getMail()]));
						}

						//Get route params
						$routeParams = $request->get('_route_params');

						//Remove mail, field and hash from route params
						unset($routeParams['mail'], $routeParams['field'], $routeParams['hash']);

						//Redirect on the same route with sent=1 to cleanup form
						return $this->redirectToRoute($request->get('_route'), ['sent' => 1]+$routeParams);
					}

					//Add error message mail already exists
					$this->addFlash('warning', $this->translator->trans('Account %mail% already exists', ['%mail%' => $existing->getMail()]));

					//Redirect to user view
					return $this->redirectToRoute(
						$this->config['route']['edit']['name'],
						[
							'mail' => $smail = $this->slugger->short($existing->getMail()),
							'hash' => $this->slugger->hash($smail)
						]+$this->config['route']['edit']['context']
					);
				}
			//Without mail
			} else {
				//Set smail
				$smail = $mail;
			}

			//Try
			try {
				//Unshort then unserialize field
				$field = $this->slugger->unserialize($sfield = $field);
			//Catch type error
			} catch (\Error|\Exception $e) {
				//Throw bad request
				throw new BadRequestHttpException($this->translator->trans('Invalid %field% field: %value%', ['%field%' => 'field', '%value%' => $field]), $e);
			}

			//With non array field
			if (!is_array($field)) {
				//Throw bad request
				throw new BadRequestHttpException($this->translator->trans('Invalid %field% field: %value%', ['%field%' => 'field', '%value%' => $field]));
			}
		//Without field and hash
		} else {
			//Set smail
			$smail = $mail;

			//Set smail
			$sfield = $field;

			//Reset field
			$field = [];
		}

		//Init reflection
		$reflection = new \ReflectionClass($this->config['class']['user']);

		//Create new user
		$user = $reflection->newInstance(strval($mail));

		//Create the RegisterType form and give the proper parameters
		$form = $this->createForm($this->config['register']['view']['form'], $user, $field+[
			//Set action to register route name and context
			'action' => $this->generateUrl($this->config['route']['register']['name'], ['mail' => $smail, 'field' => $sfield, 'hash' => $hash]+$this->config['route']['register']['context']),
			//Set civility class
			'civility_class' => $this->config['class']['civility'],
			//Set civility default
			'civility_default' => $this->doctrine->getRepository($this->config['class']['civility'])->findOneByTitle($this->config['default']['civility']),
			//With mail
			'mail' => true,
			//Set method
			'method' => 'POST'
		]+$this->config['register']['field']);

		//With post method
		if ($request->isMethod('POST')) {
			//Refill the fields in case the form is not valid.
			$form->handleRequest($request);

			//With form submitted and valid
			if ($form->isSubmitted() && $form->isValid()) {
				//Set data
				$data = $form->getData();

				//With existing registrant
				if ($this->doctrine->getRepository($this->config['class']['user'])->findOneByMail($mail = $data->getMail())) {
					//Add error message mail already exists
					$this->addFlash('warning', $this->translator->trans('Account %mail% already exists', ['%mail%' => $mail]));

					//Redirect to user view
					return $this->redirectToRoute(
						$this->config['route']['edit']['name'],
						[
							'mail' => $smail = $this->slugger->short($mail),
							'hash' => $this->slugger->hash($smail)
						]+$this->config['route']['edit']['context']
					);
				}

				//Set mail shortcut
				$registerMail =& $this->config['register']['mail'];

				//Set password
				$user->setPassword($this->hasher->hashPassword($user, $user->getPassword()));

				//Persist user
				$this->manager->persist($user);

				//Iterate on default group
				foreach($this->config['default']['group'] as $i => $groupTitle) {
					//Fetch group
					if (($group = $this->doctrine->getRepository($this->config['class']['group'])->findOneByTitle($groupTitle))) {
						//Set default group
						//XXX: see vendor/symfony/security-core/Role/Role.php
						$user->addGroup($group);
					//Group not found
					} else {
						//Throw exception
						//XXX: consider missing group as fatal
						throw new \Exception(sprintf('Group from rapsys_user.default.group[%d] not found by title: %s', $i, $groupTitle));
					}
				}

				//Generate each route route
				foreach($this->config['register']['route'] as $route => $tag) {
					//Only process defined routes
					if (!empty($this->config['route'][$route])) {
						//Process for confirm url
						if ($route == 'confirm') {
							//Set the url in context
							$registerMail['context'][$tag] = $this->router->generate(
								$this->config['route'][$route]['name'],
								//Prepend subscribe context with tag
								[
									'mail' => $smail = $this->slugger->short($data->getMail()),
									'hash' => $this->slugger->hash($smail)
								]+$this->config['route'][$route]['context'],
								UrlGeneratorInterface::ABSOLUTE_URL
							);
						}
					}
				}

				//Set recipient_name
				$registerMail['context']['recipient_mail'] = $data->getMail();

				//Set recipient name
				$registerMail['context']['recipient_name'] = $data->getRecipientName();

				//Init subject context
				$subjectContext = $this->slugger->flatten(array_replace_recursive($this->config['register']['view']['context'], $registerMail['context']), null, '.', '%', '%');

				//Translate subject
				$registerMail['subject'] = ucfirst($this->translator->trans($registerMail['subject'], $subjectContext));

				//Create message
				$message = (new TemplatedEmail())
					//Set sender
					->from(new Address($this->config['contact']['mail'], $this->config['contact']['title']))
					//Set recipient
					//XXX: remove the debug set in vendor/symfony/mime/Address.php +46
					->to(new Address($registerMail['context']['recipient_mail'], $registerMail['context']['recipient_name']))
					//Set subject
					->subject($registerMail['subject'])

					//Set path to twig templates
					->htmlTemplate($registerMail['html'])
					->textTemplate($registerMail['text'])

					//Set context
					->context(['subject' => $registerMail['subject']]+$registerMail['context']);

				//Try saving in database
				try {
					//Send to database
					$this->manager->flush();

					//Add error message mail already exists
					$this->addFlash('notice', $this->translator->trans('Your account has been created'));

					//Try sending message
					//XXX: mail delivery may silently fail
					try {
						//Send message
						$this->mailer->send($message);

						//Redirect on the same route with sent=1 to cleanup form
						return $this->redirectToRoute($request->get('_route'), ['sent' => 1]+$request->get('_route_params'));
					//Catch obvious transport exception
					} catch(TransportExceptionInterface $e) {
						//Add error message mail unreachable
						$form->get('mail')->addError(new FormError($this->translator->trans('Account %mail% tried subscribe but unable to contact', ['%mail%' => $data->getMail()])));
					}
				//Catch double subscription
				} catch (UniqueConstraintViolationException $e) {
					//Add error message mail already exists
					$this->addFlash('error', $this->translator->trans('Account %mail% already exists', ['%mail%' => $mail]));
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
}
