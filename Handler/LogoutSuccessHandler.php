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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Logout\DefaultLogoutSuccessHandler;

use Rapsys\UserBundle\RapsysUserBundle;

/**
 * {@inheritdoc}
 */
class LogoutSuccessHandler extends DefaultLogoutSuccessHandler {
	/**
	 * Config array
	 */
	protected $config;

	/**
	 * {@inheritdoc}
	 */
	protected $router;

	/**
	 * {@inheritdoc}
	 */
	protected $targetUrl;

	/**
	 * @xxx Second argument will be replaced by security.firewalls.main.logout.target
	 * @see vendor/symfony/security-bundle/DependencyInjection/SecurityExtension.php +360
	 *
	 * {@inheritdoc}
	 */
	public function __construct(ContainerInterface $container, string $targetUrl = '/', RouterInterface $router) {
		//Set config
		$this->config = $container->getParameter(self::getAlias());

		//Set target url
		$this->targetUrl = $targetUrl;

		//Set router
		$this->router = $router;
	}

	/**
	 * {@inheritdoc}
	 */
	public function onLogoutSuccess(Request $request): Response {
		//Retrieve logout route
		$logout = $request->get('_route');

		//Extract and process referer
		if (($referer = $request->headers->get('referer'))) {
			//Create referer request instance
			$req = Request::create($referer);

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

				//Without logout route name
				if (($name = $route['_route']) != $logout) {
					//Remove route and controller from route defaults
					unset($route['_route'], $route['_controller'], $route['_canonical_route']);

					//Generate url
					$url = $this->router->generate($name, $route);

					//Return generated route
					return new RedirectResponse($url, 302);
				//With logout route name
				} else {
					//Unset referer and route
					unset($referer, $route);
				}
			//No route matched
			} catch (ResourceNotFoundException $e) {
				//Unset referer and route
				unset($referer, $route);
			}
		}

		//With index route from config
		if (!empty($name = $this->config['route']['index']['name']) && is_array($context = $this->config['route']['index']['context'])) {
			//Without logout route name
			if (($name = $route['_route']) != $logout) {
				//Try index route
				try {
					//Generate url
					$url = $this->router->generate($name, $context);

					//Return generated route
					return new RedirectResponse($url, 302);
				//No route matched
				} catch (ResourceNotFoundException $e) {
					//Unset name and context
					unset($name, $context);
				}
			//With logout route name
			} else {
				//Unset name and context
				unset($name, $context);
			}
		}

		//Try target url
		try {
			//Save old context
			$oldContext = $this->router->getContext();

			//Force clean context
			//XXX: prevent MethodNotAllowedException on GET only routes because our context method is POST
			//XXX: see vendor/symfony/routing/Matcher/Dumper/CompiledUrlMatcherTrait.php +42
			$this->router->setContext(new RequestContext());

			//Retrieve route matching target url
			$route = $this->router->match($this->targetUrl);

			//Reset context
			$this->router->setContext($oldContext);

			//Clear old context
			unset($oldContext);

			//Without logout route name
			if (($name = $route['_route']) != $logout) {
				//Remove route and controller from route defaults
				unset($route['_route'], $route['_controller'], $route['_canonical_route']);

				//Generate url
				$url = $this->router->generate($name, $route);

				//Return generated route
				return new RedirectResponse($url, 302);
			//With logout route name
			} else {
				//Unset name and route
				unset($name, $route);
			}
		//Get first route from route collection if / path was not matched
		} catch (ResourceNotFoundException $e) {
			//Unset name and route
			unset($name, $route);
		}

		//Throw exception
		throw new \RuntimeException('You must provide a valid logout target url or route name');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAlias(): string {
		return RapsysUserBundle::getAlias();
	}
}
