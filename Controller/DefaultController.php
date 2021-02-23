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
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Translation\TranslatorInterface;

class DefaultController extends AbstractController {
	//Config array
	protected $config;

	//Translator instance
	protected $translator;

	/**
	 * Constructor
	 *
	 * @param ContainerInterface $container The containter instance
	 * @param RouterInterface $router The router instance
	 * @param TranslatorInterface $translator The translator instance
	 */
	public function __construct(ContainerInterface $container, RouterInterface $router, TranslatorInterface $translator) {
		//Retrieve config
		$this->config = $container->getParameter($this->getAlias());

		//Set the translator
		$this->translator = $translator;

		//Get current action
		//XXX: we don't use this as it would be too slow, maybe ???
		#$action = str_replace(self::getAlias().'_', '', $container->get('request_stack')->getCurrentRequest()->get('_route'));

		//Set translate array
		$translates = [];

		//Look for keys to translate
		if (!empty($this->config['translate'])) {
			//Iterate on keys to translate
			foreach($this->config['translate'] as $translate) {
				//Set tmp
				$tmp = null;
				//Iterate on keys
				foreach(array_reverse(explode('.', $translate)) as $curkey) {
					$tmp = array_combine([$curkey], [$tmp]);
				}
				//Append tree
				$translates = array_replace_recursive($translates, $tmp);
			}
		}

		//Inject every requested route in view and mail context
		foreach($this->config as $tag => $current) {
			//Look for entry with route subkey
			if (!empty($current['route'])) {
				//Generate url for both view and mail
				foreach(['view', 'mail'] as $view) {
					//Check that context key is usable
					if (isset($current[$view]['context']) && is_array($current[$view]['context'])) {
						//Process every routes
						foreach($current['route'] as $route => $key) {
							//Skip recover_mail route as it requires some parameters
							if ($route == 'recover_mail') {
								continue;
							}

							//Set value
							$value = $router->generate(
								$this->config['route'][$route]['name'],
								$this->config['route'][$route]['context'],
								//Generate absolute url for mails
								$view=='mail'?UrlGeneratorInterface::ABSOLUTE_URL:UrlGeneratorInterface::ABSOLUTE_PATH
							);

							//Multi level key
							if (strpos($key, '.') !== false) {
								//Set tmp
								$tmp = $value;

								//Iterate on key
								foreach(array_reverse(explode('.', $key)) as $curkey) {
									$tmp = array_combine([$curkey], [$tmp]);
								}

								//Set value
								$this->config[$tag][$view]['context'] = array_replace_recursive($this->config[$tag][$view]['context'], $tmp);
							//Single level key
							} else {
								//Set value
								$this->config[$tag][$view]['context'][$key] = $value;
							}
						}

						//Look for successful intersections
						if (!empty(array_intersect_key($translates, $current[$view]['context']))) {
							//Iterate on keys to translate
							foreach($this->config['translate'] as $translate) {
								//Set keys
								$keys = explode('.', $translate);

								//Set tmp
								$tmp = $current[$view]['context'];

								//Iterate on keys
								foreach($keys as $curkey) {
									//Get child key
									$tmp = $tmp[$curkey];
								}

								//Translate tmp value
								$tmp = $translator->trans($tmp);

								//Iterate on keys
								foreach(array_reverse($keys) as $curkey) {
									//Set parent key
									$tmp = array_combine([$curkey], [$tmp]);
								}

								//Set value
								$this->config[$tag][$view]['context'] = array_replace_recursive($this->config[$tag][$view]['context'], $tmp);
							}
						}

						//Get current locale
						$currentLocale = $router->getContext()->getParameters()['_locale'];

						//Iterate on locales excluding current one
						foreach($this->config['locales'] as $locale) {
							//Set titles
							$titles = [];

							//Iterate on other locales
							foreach(array_diff($this->config['locales'], [$locale]) as $other) {
								$titles[$other] = $translator->trans($this->config['languages'][$locale], [], null, $other);
							}

							//Get context path
							$path = $router->getContext()->getPathInfo();

							//Retrieve route matching path
							$route = $router->match($path);

							//Get route name
							$name = $route['_route'];

							//Unset route name
							unset($route['_route']);

							//With current locale
							if ($locale == $currentLocale) {
								//Set locale locales context
								$this->config[$tag][$view]['context']['canonical'] = $router->generate($name, ['_locale' => $locale]+$route, UrlGeneratorInterface::ABSOLUTE_URL);
							} else {
								//Set locale locales context
								$this->config[$tag][$view]['context']['alternates'][] = [
									'lang' => $locale,
									'absolute' => $router->generate($name, ['_locale' => $locale]+$route, UrlGeneratorInterface::ABSOLUTE_URL),
									'relative' => $router->generate($name, ['_locale' => $locale]+$route),
									'title' => implode('/', $titles),
									'translated' => $translator->trans($this->config['languages'][$locale], [], null, $locale)
								];
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Login
	 *
	 * @param Request $request The request
	 * @param AuthenticationUtils $authenticationUtils The authentication utils
	 * @return Response The response
	 */
	public function login(Request $request, AuthenticationUtils $authenticationUtils) {
		//Create the LoginType form and give the proper parameters
		$login = $this->createForm($this->config['login']['view']['form'], null, [
			//Set action to login route name and context
			'action' => $this->generateUrl($this->config['route']['login']['name'], $this->config['route']['login']['context']),
			'method' => 'POST'
		]);

		//Init context
		$context = [];

		//Last username entered by the user
		if ($lastUsername = $authenticationUtils->getLastUsername()) {
			$login->get('mail')->setData($lastUsername);
		}

		//Get the login error if there is one
		if ($error = $authenticationUtils->getLastAuthenticationError()) {
			//Get translated error
			$error = $this->translator->trans($error->getMessageKey());

			//Add error message to mail field
			$login->get('mail')->addError(new FormError($error));

			//Create the RecoverType form and give the proper parameters
			$recover = $this->createForm($this->config['recover']['view']['form'], null, [
				//Set action to recover route name and context
				'action' => $this->generateUrl($this->config['route']['recover']['name'], $this->config['route']['recover']['context']),
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
	 * @param Slugger $slugger The slugger
	 * @param MailerInterface $mailer The mailer
	 * @return Response The response
	 */
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
					foreach($this->config['recover']['route'] as $route => $tag) {
						//Only process defined routes
						if (empty($mail['context'][$tag]) && !empty($this->config['route'][$route])) {
							//Process for recover mail url
							if ($route == 'recover_mail') {
								//Set the url in context
								$mail['context'][$tag] = $this->get('router')->generate(
									$this->config['route'][$route]['name'],
									//Prepend recover context with tag
									[
										'recipient' => $slugger->short($user->getMail()),
										'hash' => $slugger->hash($user->getPassword())
									]+$this->config['route'][$route]['context'],
									UrlGeneratorInterface::ABSOLUTE_URL
								);
							}
						}
					}

					//Set recipient_name
					$mail['context']['recipient_mail'] = $data['mail'];

					//Set recipient_name
					$mail['context']['recipient_name'] = trim($user->getForename().' '.$user->getSurname().($user->getPseudonym()?' ('.$user->getPseudonym().')':''));

					//Init subject context
					$subjectContext = $this->flatten(array_replace_recursive($this->config['recover']['view']['context'], $mail['context']), null, '.', '%', '%');

					//Translate subject
					$mail['subject'] = ucfirst($this->translator->trans($mail['subject'], $subjectContext));

					//Create message
					$message = (new TemplatedEmail())
						//Set sender
						->from(new Address($this->config['contact']['mail'], $this->config['contact']['name']))
						//Set recipient
						//XXX: remove the debug set in vendor/symfony/mime/Address.php +46
						->to(new Address($mail['context']['recipient_mail'], $mail['context']['recipient_name']))
						//Set subject
						->subject($mail['subject'])

						//Set path to twig templates
						->htmlTemplate($mail['html'])
						->textTemplate($mail['text'])

						//Set context
						//XXX: require recursive merge to avoid loosing subkeys
						//['subject' => $mail['subject']]+$mail['context']+$this->config['recover']['view']['context']
						->context(array_replace_recursive($this->config['recover']['view']['context'], $mail['context'], ['subject' => $mail['subject']]));

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

	/**
	 * Recover account with mail link
	 *
	 * @param Request $request The request
	 * @param UserPasswordEncoderInterface $encoder The password encoder
	 * @param Slugger $slugger The slugger
	 * @param MailerInterface $mailer The mailer
	 * @param string $recipient The shorted recipient mail address
	 * @param string $hash The hashed password
	 * @return Response The response
	 */
	public function recoverMail(Request $request, UserPasswordEncoderInterface $encoder, Slugger $slugger, MailerInterface $mailer, $recipient, $hash) {
		//Create the RecoverType form and give the proper parameters
		$form = $this->createForm($this->config['recover_mail']['view']['form'], null, array(
			//Set action to recover route name and context
			'action' => $this->generateUrl($this->config['route']['recover_mail']['name'], ['recipient' => $recipient, 'hash' => $hash]+$this->config['route']['recover_mail']['context']),
			'method' => 'POST'
		));

		//Get doctrine
		$doctrine = $this->getDoctrine();

		//Init found
		$found = false;

		//Retrieve user
		if (($user = $doctrine->getRepository($this->config['class']['user'])->findOneByMail($slugger->unshort($recipient))) && $found = ($hash == $slugger->hash($user->getPassword()))) {
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
					foreach($this->config['recover_mail']['route'] as $route => $tag) {
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
					$subjectContext = $this->flatten(array_replace_recursive($this->config['recover_mail']['view']['context'], $mail['context']), null, '.', '%', '%');

					//Translate subject
					$mail['subject'] = ucfirst($this->translator->trans($mail['subject'], $subjectContext));

					//Create message
					$message = (new TemplatedEmail())
						//Set sender
						->from(new Address($this->config['contact']['mail'], $this->config['contact']['name']))
						//Set recipient
						//XXX: remove the debug set in vendor/symfony/mime/Address.php +46
						->to(new Address($mail['context']['recipient_mail'], $mail['context']['recipient_name']))
						//Set subject
						->subject($mail['subject'])

						//Set path to twig templates
						->htmlTemplate($mail['html'])
						->textTemplate($mail['text'])

						//Set context
						//XXX: require recursive merge to avoid loosing subkeys
						//['subject' => $mail['subject']]+$mail['context']+$this->config['recover_mail']['view']['context']
						->context(array_replace_recursive($this->config['recover_mail']['view']['context'], $mail['context'], ['subject' => $mail['subject']]));

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
						$form->get('password')->get('first')->addError(new FormError($this->translator->trans('Account %mail% updated but unable to contact', array('%mail%' => $mail['context']['recipient_mail']))));
					}
				}
			}
		//Accout not found
		} else {
			//Add error in flash message
			//XXX: prevent slugger reverse engineering by not displaying decoded recipient
			#$this->addFlash('error', $this->translator->trans('Unable to find account %mail%', ['%mail%' => $slugger->unshort($recipient)]));
		}

		//Render view
		return $this->render(
			//Template
			$this->config['recover_mail']['view']['name'],
			//Context
			['form' => $form->createView(), 'sent' => $request->query->get('sent', 0), 'found' => $found]+$this->config['recover_mail']['view']['context']
		);
	}

	/**
	 * Register an account
	 *
	 * @todo: activation link
	 *
	 * @param Request $request The request
	 * @param UserPasswordEncoderInterface $encoder The password encoder
	 * @param MailerInterface $mailer The mailer
	 * @return Response The response
	 */
	public function register(Request $request, UserPasswordEncoderInterface $encoder, MailerInterface $mailer) {
		//Get doctrine
		$doctrine = $this->getDoctrine();

		//Create the RegisterType form and give the proper parameters
		$form = $this->createForm($this->config['register']['view']['form'], null, array(
			'class_civility' => $this->config['class']['civility'],
			'civility' => $doctrine->getRepository($this->config['class']['civility'])->findOneByTitle($this->config['default']['civility']),
			//Set action to register route name and context
			'action' => $this->generateUrl($this->config['route']['register']['name'], $this->config['route']['register']['context']),
			'method' => 'POST'
		));

		if ($request->isMethod('POST')) {
			//Refill the fields in case the form is not valid.
			$form->handleRequest($request);

			if ($form->isValid()) {
				//Set data
				$data = $form->getData();

				//Set mail shortcut
				$mail =& $this->config['register']['mail'];

				//Generate each route route
				foreach($this->config['register']['route'] as $route => $tag) {
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
				$subjectContext = $this->flatten(array_replace_recursive($this->config['register']['view']['context'], $mail['context']), null, '.', '%', '%');

				//Translate subject
				$mail['subject'] = ucfirst($this->translator->trans($mail['subject'], $subjectContext));

				//Create message
				$message = (new TemplatedEmail())
					//Set sender
					->from(new Address($this->config['contact']['mail'], $this->config['contact']['name']))
					//Set recipient
					//XXX: remove the debug set in vendor/symfony/mime/Address.php +46
					->to(new Address($mail['context']['recipient_mail'], $mail['context']['recipient_name']))
					//Set subject
					->subject($mail['subject'])

					//Set path to twig templates
					->htmlTemplate($mail['html'])
					->textTemplate($mail['text'])

					//Set context
					//XXX: require recursive merge to avoid loosing subkeys
					//['subject' => $mail['subject']]+$mail['context']+$this->config['register']['view']['context']
					->context(array_replace_recursive($this->config['register']['view']['context'], $mail['context'], ['subject' => $mail['subject']]));

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
				$user->setCivility($data['civility']);

				//Iterate on default group
				foreach($this->config['default']['group'] as $i => $groupTitle) {
					//Fetch group
					if (($group = $doctrine->getRepository($this->config['class']['group'])->findOneByTitle($groupTitle))) {
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
						$form->get('mail')->addError(new FormError($this->translator->trans('Account %mail% created but unable to contact', array('%mail%' => $data['mail']))));
					}
				//Catch double subscription
				} catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
					//Add error message mail already exists
					$form->get('mail')->addError(new FormError($this->translator->trans('Account %mail% already exists', ['%mail%' => $data['mail']])));
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
	 * Recursively flatten an array
	 *
	 * @param array $data The data tree
	 * @param string|null $current The current prefix
	 * @param string $sep The key separator
	 * @param string $prefix The key prefix
	 * @param string $suffix The key suffix
	 * @return array The flattened data
	 */
	protected function flatten($data, $current = null, $sep = '.', $prefix = '', $suffix = '') {
		//Init result
		$ret = [];

		//Look for data array
		if (is_array($data)) {
			//Iteare on each pair
			foreach($data as $k => $v) {
				//Merge flattened value in return array
				$ret += $this->flatten($v, empty($current) ? $k : $current.$sep.$k, $sep, $prefix, $suffix);
			}
		//Look flat data
		} else {
			//Store data in flattened key
			$ret[$prefix.$current.$suffix] = $data;
		}

		//Return result
		return $ret;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAlias() {
		return 'rapsys_user';
	}
}
