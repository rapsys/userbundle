<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys UserBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\UserBundle\Handler;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

use Rapsys\PackBundle\Util\SluggerUtil;
use Rapsys\UserBundle\Exception\UnactivatedException;
use Rapsys\UserBundle\RapsysUserBundle;

/**
 * {@inheritdoc}
 */
class AuthenticationFailureHandler extends DefaultAuthenticationFailureHandler {
	/**
	 * Config array
	 */
	protected array $config;
	protected array $options;
	protected array $defaultOptions = [
		'failure_path' => null,
		'failure_forward' => false,
		'login_path' => '/login',
		'failure_path_parameter' => '_failure_path',
	];

	/**
	 * Doctrine instance
	 */
	protected ManagerRegistry $doctrine;

	/**
	 * MailerInterface
	 */
	protected MailerInterface $mailer;

	/**
	 * Router instance
	 */
	protected RouterInterface $router;

	/**
	 * Slugger instance
	 */
	protected SluggerUtil $slugger;

	/**
	 * RequestStack instance
	 */
	protected RequestStack $stack;

	/**
	 * Translator instance
	 */
	protected TranslatorInterface $translator;

	/**
	 * @xxx Second argument will be replaced by security.firewalls.main.logout.target
	 * @see vendor/symfony/security-bundle/DependencyInjection/SecurityExtension.php +360
	 *
	 * @param HttpKernelInterface $httpKernel The http kernel
	 * @param HttpUtils $httpUtils The http utils
	 * @param array $options The options
	 * @param LoggerInterface $logger The logger instance
	 * @param ContainerInterface $container The container instance
	 * @param ManagerRegistry $doctrine The doctrine instance
	 * @param MailerInterface $mailer The mailer instance
	 * @param RouterInterface $router The router instance
	 * @param SluggerUtil $slugger The slugger instance
	 * @param RequestStack $stack The stack instance
	 * @param TranslatorInterface $translator The translator instance
	 *
	 * {@inheritdoc}
	 */
	public function __construct(HttpKernelInterface $httpKernel, HttpUtils $httpUtils, array $options, LoggerInterface $logger, ContainerInterface $container, ManagerRegistry $doctrine, MailerInterface $mailer, RouterInterface $router, SluggerUtil $slugger, RequestStack $stack, TranslatorInterface $translator) {
		//Set config
		$this->config = $container->getParameter(self::getAlias());

		//Set doctrine
		$this->doctrine = $doctrine;

		//Set mailer
		$this->mailer = $mailer;

		//Set router
		$this->router = $router;

		//Set slugger
		$this->slugger = $slugger;

		//Set stack
		$this->stack = $stack;

		//Set translator
		$this->translator = $translator;

		//Call parent constructor
		parent::__construct($httpKernel, $httpUtils, $options, $logger);
	}

	/**
	 * Adds a flash message to the current session for type.
	 *
	 * @throws \LogicException
	 */
	protected function addFlash(string $type, mixed $message): void {
		try {
			$session = $this->stack->getSession();
		} catch (SessionNotFoundException $e) {
			throw new \LogicException('You cannot use the addFlash method if sessions are disabled. Enable them in "config/packages/framework.yaml".', 0, $e);
		}

		if (!$session instanceof FlashBagAwareSessionInterface) {
			throw new \LogicException(sprintf('You cannot use the addFlash method because class "%s" doesn\'t implement "%s".', get_debug_type($session), FlashBagAwareSessionInterface::class));
		}

		$session->getFlashBag()->add($type, $message);
	}

	/**
	 * This is called when an interactive authentication attempt fails
	 *
	 * {@inheritdoc}
	 */
	public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response {
		//With bad credential exception
		if ($exception instanceof BadCredentialsException) {
			//With parent exception
			if (($parent = $exception->getPrevious()) instanceof UserNotFoundException) {
				/** Disabled to prevent user mail + hash retrieval for each unactivated/locked accounts

				//Get user identifier
				$mail = $parent->getUserIdentifier();

				//Set extra parameters
				$extra = ['mail' => $smail = $this->slugger->short($mail), 'hash' => $this->slugger->hash($smail)];*/

				//With failure target path option
				if (!empty($failurePath = $this->options['failure_path'])) {
					//With path
					if ($failurePath[0] == '/') {
						//Create login path request instance
						$req = Request::create($failurePath);

						//Get login path pathinfo
						$path = $req->getPathInfo();

						//Remove script name
						$path = str_replace($request->getScriptName(), '', $path);

						//Try with login path path
						try {
							//Save old context
							$oldContext = $this->router->getContext();

							//Force clean context
							//XXX: prevent MethodNotAllowedException on GET only routes because our context method is POST
							//XXX: see vendor/symfony/routing/Matcher/Dumper/CompiledUrlMatcherTrait.php +42
							$this->router->setContext(new RequestContext());

							//Retrieve route matching path
							$route = $this->router->match($path);

							//Reset context
							$this->router->setContext($oldContext);

							//Clear old context
							unset($oldContext);

							//With route name
							if ($name = $route['_route']) {
								//Remove route and controller from route defaults
								unset($route['_route'], $route['_controller'], $route['_canonical_route']);

								//Generate url
								$url = $this->router->generate($name, /*$extra+*/$route);

								//Return redirect to url response
								return new RedirectResponse($url, 302);
							}
						//No route matched
						} catch (ResourceNotFoundException $e) {
							//Unset default path, name and route
							unset($failurePath, $name, $route);
						}
					//With route name
					} else {
						//Try with login path route
						try {
							//Retrieve route matching path
							$url = $this->router->generate($failurePath/*, $extra*/);

							//Return redirect to url response
							return new RedirectResponse($url, 302);
						//Route not found, missing parameter or invalid parameter
						} catch (RouteNotFoundException|MissingMandatoryParametersException|InvalidParameterException $e) {
							//Unset default path and url
							unset($failurePath, $url);
						}
					}
				}
			//With not enabled user
			} elseif ($parent instanceof DisabledException) {
				//Add error message account is not enabled
				$this->addFlash('error', $this->translator->trans('Your account is not enabled'));

				//Redirect on the same route with sent=1 to cleanup form
				return new RedirectResponse($this->router->generate($request->get('_route'), $request->get('_route_params')), 302);
			//With not activated user
			} elseif ($parent instanceof UnactivatedException) {
				//Set user
				$user = $parent->getUser();

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
						//Process for confirm url
						if ($route == 'confirm') {
							//Set the url in context
							$context[$tag] = $this->router->generate(
								$this->config['route'][$route]['name'],
								//Prepend confirm context with tag
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
					->from(new Address($this->config['contact']['address'], $this->config['contact']['name']))
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

				//Try sending message
				//XXX: mail delivery may silently fail
				try {
					//Send message
					$this->mailer->send($message);
				//Catch obvious transport exception
				} catch(TransportExceptionInterface $e) {
					//Add error message mail unreachable
					$this->addFlash('error', $this->translator->trans('Unable to reach account'));
				}

				//Add notice
				$this->addFlash('notice', $this->translator->trans('Your verification mail has been sent, to activate your account you must follow the confirmation link inside'));

				//Add junk warning
				$this->addFlash('warning', $this->translator->trans('If you did not receive a verification mail, check your Spam or Junk mail folders'));

				//Redirect on the same route with sent=1 to cleanup form
				return new RedirectResponse($this->router->generate($request->get('_route'), $request->get('_route_params')), 302);
			}
		}

		//Call parent function
		return parent::onAuthenticationFailure($request, $exception);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAlias(): string {
		return RapsysUserBundle::getAlias();
	}
}
