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

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\ParameterBagUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * {@inheritdoc}
 */
class AuthenticationSuccessHandler extends DefaultAuthenticationSuccessHandler {
	/**
	 * Allows to use getTargetPath and removeTargetPath private functions
	 */
	use TargetPathTrait;

	/**
	 * Default options
	 */
	protected array $defaultOptions = [
		'always_use_default_target_path' => false,
		'default_target_path' => '/',
		'login_path' => '/login',
		'target_path_parameter' => '_target_path',
		'use_referer' => false,
	];

	/**
	 * {@inheritdoc}
	 */
	public function __construct(protected RouterInterface $router, protected array $options = []) {
		//Set options
		$this->setOptions($options);
	}

	/**
	 * {@inheritdoc}
	 *
	 * This is called when an interactive authentication attempt succeeds
	 *
	 * In use_referer case it will handle correctly when login_path is a route name or path
	 */
	public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response {
		//Set login route
		$login = $request->get('_route');

		//With login path option
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

					//Set login route
					if (!empty($route['_route'])) {
						//Set login route
						$login = $route['_route'];
					}
				//No route matched
				} catch (ResourceNotFoundException $e) {
					throw new \UnexpectedValueException(sprintf('The "login_path" path "%s" must match a route', $this->options['login_path']), $e->getCode(), $e);
				}
			//With route
			} else {
				//Try with login path route
				try {
					//Retrieve route matching path
					$path = $this->router->generate($loginPath);

					//Set login route
					$login = $loginPath;
				//No route found
				} catch (RouteNotFoundException $e) {
					throw new \UnexpectedValueException(sprintf('The "login_path" route "%s" must match a route name', $this->options['login_path']), $e->getCode(), $e);
				//Ignore missing or invalid parameter
				//XXX: useless or would not work ?
				} catch (MissingMandatoryParametersException|InvalidParameterException $e) {
					//Set login route
					$login = $loginPath;
				}
			}
		}

		//Without always_use_default_target_path
		if (empty($this->options['always_use_default_target_path'])) {
			//With _target_path
			if ($targetUrl = ParameterBagUtils::getRequestParameterValue($request, $this->options['target_path_parameter'])) {
				//Set target url
				$url = $targetUrl;

				//Return redirect to url response
				return new RedirectResponse($url, 302);
			//With session and target path in session
			} elseif (
				!empty($this->providerKey) &&
				($session = $request->getSession()) &&
				($targetUrl = $this->getTargetPath($session, $this->providerKey))
			) {
				//Remove session target path
				$this->removeTargetPath($session, $this->providerKey);

				//Set target url
				$url = $targetUrl;

				//Return redirect to url response
				return new RedirectResponse($url, 302);
			//Extract and process referer
			} elseif ($this->options['use_referer'] && ($targetUrl = $request->headers->get('referer'))) {
				//Create referer request instance
				$req = Request::create($targetUrl);

				//Get referer path
				$path = $req->getPathInfo();

				//Get referer query string
				$query = $req->getQueryString();

				//Remove script name
				$path = str_replace($request->getScriptName(), '', $path);

				//Try with referer path
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

					//With differing route from login one
					if (($name = $route['_route']) != $login) {
						//Remove route and controller from route defaults
						unset($route['_route'], $route['_controller'], $route['_canonical_route']);

						//Set url to generated one from referer route
						$url = $this->router->generate($name, $route);

						//Return redirect to url response
						return new RedirectResponse($url, 302);
					}
				//No route matched
				} catch (ResourceNotFoundException $e) {
					//Unset target url, route and name
					unset($targetUrl, $route, $name);
				}
			}
		}

		//With default target path option
		if (!empty($defaultPath = $this->options['default_target_path'])) {
			//With path
			if ($defaultPath[0] == '/') {
				//Create login path request instance
				$req = Request::create($defaultPath);

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

					//Without login route name
					if (($name = $route['_route']) != $login) {
						//Remove route and controller from route defaults
						unset($route['_route'], $route['_controller'], $route['_canonical_route']);

						//Generate url
						$url = $this->router->generate($name, $route);

						//Return redirect to url response
						return new RedirectResponse($url, 302);
					//With logout route name
					} else {
						//Unset default path, name and route
						unset($defaultPath, $name, $route);
					}
				//No route matched
				} catch (ResourceNotFoundException $e) {
					throw \Exception('', $e->getCode(), $e);
					//Unset default path, name and route
					unset($defaultPath, $name, $route);
				}
			//Without login route name
			} elseif ($defaultPath != $login) {
				//Try with login path route
				try {
					//Retrieve route matching path
					$url = $this->router->generate($defaultPath);

					//Return redirect to url response
					return new RedirectResponse($url, 302);
				//Route not found, missing parameter or invalid parameter
				} catch (RouteNotFoundException|MissingMandatoryParametersException|InvalidParameterException $e) {
					//Unset default path and url
					unset($defaultPath, $url);
				}
			}
		}

		//Throw exception
		throw new \UnexpectedValueException('You must provide a valid login target url or route name');
	}
}
