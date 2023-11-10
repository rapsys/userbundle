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

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\ParameterBagUtils;

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
	 * Router instance
	 */
	protected RouterInterface $router;

	/**
	 * Slugger instance
	 */
	protected SluggerUtil $slugger;

	/**
	 * @xxx Second argument will be replaced by security.firewalls.main.logout.target
	 * @see vendor/symfony/security-bundle/DependencyInjection/SecurityExtension.php +360
	 *
	 * {@inheritdoc}
	 */
	public function __construct(HttpKernelInterface $httpKernel, HttpUtils $httpUtils, array $options, LoggerInterface $logger, ContainerInterface $container, RouterInterface $router, SluggerUtil $slugger) {
		//Set config
		$this->config = $container->getParameter(self::getAlias());

		//Set router
		$this->router = $router;

		//Set slugger
		$this->slugger = $slugger;

		//Call parent constructor
		parent::__construct($httpKernel, $httpUtils, $options, $logger);
	}

	/**
	 * This is called when an interactive authentication attempt fails
	 *
	 * User may retrieve mail + field + hash for each unactivated/locked accounts
	 *
	 * {@inheritdoc}
	 */
	public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response {
		//With bad credential exception
		if ($exception instanceof BadCredentialsException) {
			//With parent exception
			if ($parent = $exception->getPrevious()) {
				//Retrieve login
				//TODO: check form _token validity ???
				if (
					$request->request->has('login') &&
					!empty($login = $request->request->get('login')) &&
					!empty($mail = $login['mail'])
				) {
					//Redirect on register
					if ($parent instanceof UnactivatedException || $parent instanceof DisabledException) {
						//Set extra parameters
						$extra = ['mail' => $smail = $this->slugger->short($mail), 'field' => $sfield = $this->slugger->serialize([]), 'hash' => $this->slugger->hash($smail.$sfield)];

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
										$url = $this->router->generate($name, $extra+$route);

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
									$url = $this->router->generate($failurePath, $extra);

									//Return redirect to url response
									return new RedirectResponse($url, 302);
								//Route not found, missing parameter or invalid parameter
								} catch (RouteNotFoundException|MissingMandatoryParametersException|InvalidParameterException $e) {
									//Unset default path and url
									unset($failurePath, $url);
								}
							}
						}

						//With index route from config
						if (!empty($name = $this->config['route']['register']['name']) && is_array($context = $this->config['route']['register']['context'])) {
							//Try index route
							try {
								//Generate url
								$url = $this->router->generate($name, $extra+$context);

								//Return generated route
								return new RedirectResponse($url, 302);
							//No route matched
							} catch (ResourceNotFoundException $e) {
								//Unset name and context
								unset($name, $context);
							}
						} 

						//With login target path option
						if (!empty($loginPath = $this->options['login_path'])) {
							//With path
							if ($loginPath[0] == '/') {
								//Create login path request instance
								$req = Request::create($loginPath);

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
										$url = $this->router->generate($name, $extra+$route);

										//Return redirect to url response
										return new RedirectResponse($url, 302);
									}
								//No route matched
								} catch (ResourceNotFoundException $e) {
									//Unset default path, name and route
									unset($loginPath, $name, $route);
								}
							//With route name
							} else {
								//Try with login path route
								try {
									//Retrieve route matching path
									$url = $this->router->generate($loginPath, $extra);

									//Return redirect to url response
									return new RedirectResponse($url, 302);
								//Route not found, missing parameter or invalid parameter
								} catch (RouteNotFoundException|MissingMandatoryParametersException|InvalidParameterException $e) {
									//Unset default path and url
									unset($loginPath, $url);
								}
							}
						}
					}
				}
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
