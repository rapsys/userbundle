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

use Rapsys\UserBundle\RapsysUserBundle;

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
class UserController extends AbstractController {
	/**
	 * User index
	 *
	 * @param Request $request The request
	 * @return Response The response
	 */
	public function index(Request $request): Response {
		//Without admin
		if (!$this->checker->isGranted($this->config['default']['admin'])) {
			//Throw 403
			throw $this->createAccessDeniedException($this->translator->trans('Unable to list users'));
		}

		//Get count
		$this->context['count'] = $this->doctrine->getRepository($this->config['class']['user'])->findCountAsInt();

		//With not enough users
		if ($this->context['count'] - $this->page * $this->limit < 0) {
			//Throw 404
			throw $this->createNotFoundException($this->translator->trans('Unable to find users'));
		}

		//Get users
		$this->context['users'] = $this->doctrine->getRepository($this->config['class']['user'])->findAllAsArray($this->page, $this->limit);

		//Render view
		return $this->render(
			//Template
			$this->config['index']['view']['name'],
			//Context
			$this->context+$this->config['index']['view']['context']
		);
	}

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
			$this->addFlash('error', $this->translator->trans('Account do not exists'));

			//Redirect to register view
			return $this->redirectToRoute($this->config['route']['register']['name'], $this->config['route']['register']['context']);
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
			throw $this->createNotFoundException($this->translator->trans('Unable to find account'));
		}

		//Prevent access when not admin, user is not guest and not currently logged user
		if (!$this->checker->isGranted($this->config['default']['admin']) && $user != $this->security->getUser() || !$this->checker->isGranted('IS_AUTHENTICATED_FULLY')) {
			//Throw access denied
			//XXX: prevent slugger reverse engineering by not displaying decoded mail
			throw $this->createAccessDeniedException($this->translator->trans('Unable to access user'));
		}

		//Create the EditType form and give the proper parameters
		$edit = $this->factory->create($this->config['edit']['view']['edit'], $user, [
			//Set action to edit route name and context
			'action' => $this->generateUrl($this->config['route']['edit']['name'], ['mail' => $smail, 'hash' => $this->slugger->hash($smail)]+$this->config['route']['edit']['context']),
			//Set civility class
			'civility_class' => $this->config['class']['civility'],
			//Set civility default
			'civility_default' => $this->doctrine->getRepository($this->config['class']['civility'])->findOneByTitle($this->config['default']['civility']),
			//Set method
			'method' => 'POST'
		]+($this->checker->isGranted($this->config['default']['admin'])?$this->config['edit']['admin']:$this->config['edit']['field']));

		//With admin role
		if ($this->checker->isGranted($this->config['default']['admin'])) {
			//Create the EditType form and give the proper parameters
			$reset = $this->factory->create($this->config['edit']['view']['reset'], $user, [
				//Set action to edit route name and context
				'action' => $this->generateUrl($this->config['route']['edit']['name'], ['mail' => $smail, 'hash' => $this->slugger->hash($smail)]+$this->config['route']['edit']['context']),
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
					$this->addFlash('notice', $this->translator->trans('Account password updated'));

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
					$this->addFlash('notice', $this->translator->trans('Account updated'));

					//Redirect to cleanup the form
					return $this->redirectToRoute($this->config['route']['edit']['name'], ['mail' => $smail = $this->slugger->short($mail), 'hash' => $this->slugger->hash($smail)]+$this->config['route']['edit']['context']);
				//Catch double slug or mail
				} catch (UniqueConstraintViolationException $e) {
					//Add error message mail already exists
					$this->addFlash('error', $this->translator->trans('Account already exists'));
				}
			}
		//Without admin role
		//XXX: prefer a reset on login to force user unspam action
		} elseif (!$this->checker->isGranted($this->config['default']['admin'])) {
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
		$login = $this->factory->create($this->config['login']['view']['form'], null, [
			//Set action to login route name and context
			'action' => $this->generateUrl($this->config['route']['login']['name'], $this->config['route']['login']['context']),
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

			//Create the RecoverType form and give the proper parameters
			$recover = $this->factory->create($this->config['recover']['view']['form'], null, [
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
			['login' => $login->createView(), 'disabled' => $request->query->get('disabled', 0), 'sent' => $request->query->get('sent', 0)]+$context+$this->config['login']['view']['context']
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
		//Set user
		$user = null;

		//Set context
		$context = [];

		//With mail, pass and hash
		if (!empty($mail) && !empty($pass) && !empty($hash)) {
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
				throw $this->createNotFoundException($this->translator->trans('Unable to find account'));
			}

			//With unmatched pass
			if ($pass != $this->slugger->hash($user->getPassword())) {
				//Throw not found
				//XXX: prevent use of outdated recover link
				throw $this->createNotFoundException($this->translator->trans('Outdated recover link'));
			}

			//Set context
			$context = ['mail' => $smail, 'pass' => $pass, 'hash' => $hash];
		}

		//Create the LoginType form and give the proper parameters
		$form = $this->factory->create($this->config['recover']['view']['form'], $user, [
			//Set action to recover route name and context
			'action' => $this->generateUrl($this->config['route']['recover']['name'], $context+$this->config['route']['recover']['context']),
			//With user disable mail
			'mail' => ($user === null),
			//With user enable password
			'password' => ($user !== null),
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

				//With user
				if ($user !== null) {
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
					$this->addFlash('notice', $this->translator->trans('Account password updated'));

					//Redirect to user login
					return $this->redirectToRoute($this->config['route']['login']['name'], ['mail' => $smail, 'hash' => $this->slugger->hash($smail)]+$this->config['route']['login']['context']);
				//Find user by data mail
				} elseif ($user = $this->doctrine->getRepository($this->config['class']['user'])->findOneByMail($data['mail'])) {
					//Set context
					$context = [
						'recipient_mail' => $user->getMail(),
						'recipient_name' => $user->getRecipientName()
					] + array_replace_recursive(
						$this->config['context'],
						$this->config['recover']['view']['context'],
						$this->config['recover']['mail']['context']
					);

					//Generate each route route
					foreach($this->config['recover']['route'] as $route => $tag) {
						//Only process defined routes
						if (!empty($this->config['route'][$route])) {
							//Process for recover mail url
							if ($route == 'recover') {
								//Set the url in context
								$context[$tag] = $this->router->generate(
									$this->config['route'][$route]['name'],
									//Prepend recover context with tag
									[
										'mail' => $smail = $this->slugger->short($context['recipient_mail']),
										'pass' => $spass = $this->slugger->hash($pass = $user->getPassword()),
										'hash' => $this->slugger->hash($smail.$spass)
									]+$this->config['route'][$route]['context'],
									UrlGeneratorInterface::ABSOLUTE_URL
								);
							}
						}
					}

					//Iterate on keys to translate
					foreach($this->config['translate'] as $translate) {
						//Extract keys
						$keys = explode('.', $translate);

						//Set current
						$current =& $context;

						//Iterate on each subkey
						do {
							//Skip unset translation keys
							if (!isset($current[current($keys)])) {
								continue(2);
							}

							//Set current to subkey
							$current =& $current[current($keys)];
						} while(next($keys));

						//Set translation
						$current = $this->translator->trans($current);

						//Remove reference
						unset($current);
					}

					//Translate subject
					$context['subject'] = $subject = ucfirst(
						$this->translator->trans(
							$this->config['recover']['mail']['subject'],
							$this->slugger->flatten($context, null, '.', '%', '%')
						)
					);

					//Create message
					$message = (new TemplatedEmail())
						//Set sender
						->from(new Address($this->config['contact']['address'], $this->translator->trans($this->config['contact']['name'])))
						//Set recipient
						//XXX: remove the debug set in vendor/symfony/mime/Address.php +46
						->to(new Address($context['recipient_mail'], $context['recipient_name']))
						//Set subject
						->subject($context['subject'])

						//Set path to twig templates
						->htmlTemplate($this->config['recover']['mail']['html'])
						->textTemplate($this->config['recover']['mail']['text'])

						//Set context
						->context($context);

					//Try sending message
					//XXX: mail delivery may silently fail
					try {
						//Send message
						$this->mailer->send($message);

						//Add notice
						$this->addFlash('notice', $this->translator->trans('Your recovery mail has been sent, to retrieve your account follow the recuperate link inside'));

						//Add junk warning
						$this->addFlash('warning', $this->translator->trans('If you did not receive a recovery mail, check your Spam or Junk mail folder'));

						//Redirect on the same route with sent=1 to cleanup form
						return $this->redirectToRoute($request->get('_route'), ['sent' => 1]+$request->get('_route_params'), 302);
					//Catch obvious transport exception
					} catch(TransportExceptionInterface $e) {
						//Add error message mail unreachable
						$form->get('mail')->addError(new FormError($this->translator->trans('Unable to reach account')));
					}
				}
			}
		}

		//Render view
		return $this->render(
			//Template
			$this->config['recover']['view']['name'],
			//Context
			['recover' => $form->createView(), 'sent' => $request->query->get('sent', 0)]+$this->config['recover']['view']['context']
		);
	}

	/**
	 * Register an account
	 *
	 * @param Request $request The request
	 * @return Response The response
	 */
	public function register(Request $request): Response {
		//With mail
		if (!empty($_POST['register']['mail'])) {
			//Log new user infos
			$this->logger->emergency(
				$this->translator->trans(
					'register: mail=%mail% locale=%locale% confirm=%confirm% ip=%ip%',
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
						),
						'%ip%' => $request->getClientIp()
					]
				)
			);
		}

		//Init reflection
		$reflection = new \ReflectionClass($this->config['class']['user']);

		//Create new user
		$user = $reflection->newInstance('', '');

		//Create the RegisterType form and give the proper parameters
		$form = $this->factory->create($this->config['register']['view']['form'], $user, [
			//Set action to register route name and context
			'action' => $this->generateUrl($this->config['route']['register']['name'], $this->config['route']['register']['context']),
			//Set civility class
			'civility_class' => $this->config['class']['civility'],
			//Set civility default
			'civility_default' => $this->doctrine->getRepository($this->config['class']['civility'])->findOneByTitle($this->config['default']['civility']),
			//Set method
			'method' => 'POST'
		]+($this->checker->isGranted($this->config['default']['admin'])?$this->config['register']['admin']:$this->config['register']['field']));

		//With post method
		if ($request->isMethod('POST')) {
			//Refill the fields in case the form is not valid.
			$form->handleRequest($request);

			//With form submitted and valid
			if ($form->isSubmitted() && $form->isValid()) {
				//Set data
				$data = $form->getData();

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
						throw new \Exception(sprintf('Group %s listed in %s.default.group[%d] not found by title', $groupTitle, RapsysUserBundle::getAlias(), $i));
					}
				}

				//Set context
				$context = [
					'recipient_mail' => $user->getMail(),
					'recipient_name' => $user->getRecipientName()
				] + array_replace_recursive(
					$this->config['context'],
					$this->config['register']['view']['context'],
					$this->config['register']['mail']['context']
				);

				//Generate each route route
				foreach($this->config['register']['route'] as $route => $tag) {
					//Only process defined routes
					if (!empty($this->config['route'][$route])) {
						//Process for confirm mail url
						if ($route == 'confirm') {
							//Set the url in context
							$context[$tag] = $this->router->generate(
								$this->config['route'][$route]['name'],
								//Prepend register context with tag
								[
									'mail' => $smail = $this->slugger->short($context['recipient_mail']),
									'hash' => $this->slugger->hash($smail)
								]+$this->config['route'][$route]['context'],
								UrlGeneratorInterface::ABSOLUTE_URL
							);
						}
					}
				}

				//Iterate on keys to translate
				foreach($this->config['translate'] as $translate) {
					//Extract keys
					$keys = explode('.', $translate);

					//Set current
					$current =& $context;

					//Iterate on each subkey
					do {
						//Skip unset translation keys
						if (!isset($current[current($keys)])) {
							continue(2);
						}

						//Set current to subkey
						$current =& $current[current($keys)];
					} while(next($keys));

					//Set translation
					$current = $this->translator->trans($current);

					//Remove reference
					unset($current);
				}

				//Translate subject
				$context['subject'] = $subject = ucfirst(
					$this->translator->trans(
						$this->config['register']['mail']['subject'],
						$this->slugger->flatten($context, null, '.', '%', '%')
					)
				);

				//Create message
				$message = (new TemplatedEmail())
					//Set sender
					->from(new Address($this->config['contact']['address'], $this->translator->trans($this->config['contact']['name'])))
					//Set recipient
					//XXX: remove the debug set in vendor/symfony/mime/Address.php +46
					->to(new Address($context['recipient_mail'], $context['recipient_name']))
					//Set subject
					->subject($context['subject'])

					//Set path to twig templates
					->htmlTemplate($this->config['register']['mail']['html'])
					->textTemplate($this->config['register']['mail']['text'])

					//Set context
					->context($context);

				//Try saving in database
				try {
					//Send to database
					$this->manager->flush();

					//Add error message mail already exists
					$this->addFlash('notice', $this->translator->trans('Account created'));

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
						$form->get('mail')->addError(new FormError($this->translator->trans('Unable to reach account')));
					}
				//Catch double subscription
				} catch (UniqueConstraintViolationException $e) {
					//Add error message mail already exists
					$this->addFlash('error', $this->translator->trans('Account already exists'));
				}
			}
		}

		//Render view
		return $this->render(
			//Template
			$this->config['register']['view']['name'],
			//Context
			['register' => $form->createView(), 'sent' => $request->query->get('sent', 0)]+$this->config['register']['view']['context']
		);
	}
}
