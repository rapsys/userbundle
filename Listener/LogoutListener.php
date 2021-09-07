<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys UserBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\UserBundle\Listener;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

use Rapsys\UserBundle\RapsysUserBundle;

/**
 * {@inheritdoc}
 */
class LogoutListener implements EventSubscriberInterface {
	/**
	 * Config array
	 */
	protected $config;

	/**
	 * Target url
	 */
	private $targetUrl;

	/**
	 * {@inheritdoc}
	 *
	 * @xxx Second argument will be replaced by security.firewalls.main.logout.target
	 * @see vendor/symfony/security-bundle/DependencyInjection/SecurityExtension.php +445
	 */
	public function __construct(ContainerInterface $container, string $targetUrl, RouterInterface $router) {
		//Set config
		$this->config = $container->getParameter(RapsysUserBundle::getAlias());

		//Set target url
		$this->targetUrl = $targetUrl;

		//Set router
		$this->router = $router;
	}

	/**
	 * {@inheritdoc}
	 */
	public function onLogout(LogoutEvent $event): void {
		//Get request
		$request = $event->getRequest();

		//Retrieve logout route
		$logout = $request->attributes->get('_route');

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

					//Set event response
					$event->setResponse(new RedirectResponse($url, 302));

					//Return
					return;
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
			if ($name != $logout) {
				//Try index route
				try {
					//Generate url
					$url = $this->router->generate($name, $context);

					//Set event response
					$event->setResponse(new RedirectResponse($url, 302));

					//Return
					return;
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

			//With logout target path
			if ($this->targetUrl[0] == '/') {
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

					//Set event response
					$event->setResponse(new RedirectResponse($url, 302));

					//Return
					return;
				//With logout route name
				} else {
					//Unset name and route
					unset($name, $route);
				}
			//With route name
			} else {
				//Retrieve route matching path
				$url = $this->router->generate($this->targetUrl);

				//Set event response
				$event->setResponse(new RedirectResponse($url, 302));

				//Return
				return;
			}
		//Get first route from route collection if / path was not matched
		} catch (ResourceNotFoundException|RouteNotFoundException|MissingMandatoryParametersException|InvalidParameterException $e) {
			//Unset name and route
			unset($name, $route);
		}

		//Set event response
		$event->setResponse(new RedirectResponse('/', 302));
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents(): array {
		return [
			LogoutEvent::class => ['onLogout', 64],
		];
	}
}
