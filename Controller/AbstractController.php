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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as BaseAbstractController;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use Twig\Environment;

use Rapsys\PackBundle\Util\SluggerUtil;

use Rapsys\UserBundle\RapsysUserBundle;

/**
 * Provides common features needed in controllers.
 *
 * {@inheritdoc}
 */
abstract class AbstractController extends BaseAbstractController implements ServiceSubscriberInterface {
	/**
	 * Config array
	 */
	protected array $config;

	/**
	 * Context array
	 */
	protected array $context;

	/**
	 * Locale string
	 */
	protected string $locale;

	/**
	 * Page integer
	 */
	protected int $page;

	/**
	 * Abstract constructor
	 *
	 * @param CacheInterface $cache The cache instance
	 * @param AuthorizationCheckerInterface $checker The checker instance
	 * @param ContainerInterface $container The container instance
	 * @param ManagerRegistry $doctrine The doctrine instance
	 * @param FormFactoryInterface $factory The factory instance
	 * @param UserPasswordHasherInterface $hasher The password hasher instance
	 * @param LoggerInterface $logger The logger instance
	 * @param MailerInterface $mailer The mailer instance
	 * @param EntityManagerInterface $manager The manager instance
	 * @param RouterInterface $router The router instance
	 * @param Security $security The security instance
	 * @param SluggerUtil $slugger The slugger instance
	 * @param RequestStack $stack The stack instance
	 * @param TranslatorInterface $translator The translator instance
	 * @param Environment $twig The twig environment instance
	 * @param integer $limit The page limit
	 */
	public function __construct(protected CacheInterface $cache, protected AuthorizationCheckerInterface $checker, protected ContainerInterface $container, protected ManagerRegistry $doctrine, protected FormFactoryInterface $factory, protected UserPasswordHasherInterface $hasher, protected LoggerInterface $logger, protected MailerInterface $mailer, protected EntityManagerInterface $manager, protected RouterInterface $router, protected Security $security, protected SluggerUtil $slugger, protected RequestStack $stack, protected TranslatorInterface $translator, protected Environment $twig, protected int $limit = 5) {
		//Retrieve config
		$this->config = $container->getParameter(RapsysUserBundle::getAlias());

		//Get current request
		$this->request = $stack->getCurrentRequest();

		//Get current page
		$this->page = (int) $this->request->query->get('page');

		//With negative page
		if ($this->page < 0) {
			$this->page = 0;
		}

		//Get current locale
		$this->locale = $this->request->getLocale();

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
						//Merge with global context
						$this->config[$tag][$view]['context'] = array_replace_recursive($this->config['context'], $this->config[$tag][$view]['context']);

						//Process every routes
						foreach($current['route'] as $route => $key) {
							//With confirm route
							if ($route == 'confirm') {
								//Skip route as it requires some parameters
								continue;
							}

							//Set value
							$value = $this->router->generate(
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
						if (!empty(array_intersect_key($translates, $this->config[$tag][$view]['context']))) {
							//Iterate on keys to translate
							foreach($this->config['translate'] as $translate) {
								//Set keys
								$keys = explode('.', $translate);

								//Set tmp
								$tmp = $this->config[$tag][$view]['context'];

								//Iterate on keys
								foreach($keys as $curkey) {
									//Without child key
									if (!isset($tmp[$curkey])) {
										//Skip to next key
										continue(2);
									}

									//Get child key
									$tmp = $tmp[$curkey];
								}

								//Translate tmp value
								$tmp = $this->translator->trans($tmp);

								//Iterate on keys
								foreach(array_reverse($keys) as $curkey) {
									//Set parent key
									$tmp = array_combine([$curkey], [$tmp]);
								}

								//Set value
								$this->config[$tag][$view]['context'] = array_replace_recursive($this->config[$tag][$view]['context'], $tmp);
							}
						}

						//With view context
						if ($view == 'view') {
							//Get context path
							$pathInfo = $this->router->getContext()->getPathInfo();

							//Iterate on locales excluding current one
							foreach(($locales = array_keys($this->config['default']['languages'])) as $locale) {
								//Set titles
								$titles = [];

								//Iterate on other locales
								foreach(array_diff($locales, [$locale]) as $other) {
									$titles[$other] = $this->translator->trans($this->config['default']['languages'][$locale], [], null, $other);
								}

								//Retrieve route matching path
								$route = $this->router->match($pathInfo);

								//Get route name
								$name = $route['_route'];

								//Unset route name
								unset($route['_route']);

								//With current locale
								if ($locale == $this->locale) {
									//Set locale locales context
									$this->config[$tag][$view]['context']['canonical'] = $this->router->generate($name, ['_locale' => $locale]+$route, UrlGeneratorInterface::ABSOLUTE_URL);
								} else {
									//Set locale locales context
									$this->config[$tag][$view]['context']['alternates'][$locale] = [
										'absolute' => $this->router->generate($name, ['_locale' => $locale]+$route, UrlGeneratorInterface::ABSOLUTE_URL),
										'relative' => $this->router->generate($name, ['_locale' => $locale]+$route),
										'title' => implode('/', $titles),
										'translated' => $this->translator->trans($this->config['default']['languages'][$locale], [], null, $locale)
									];
								}

								//Add shorter locale
								if (empty($this->config[$tag][$view]['context']['alternates'][$slocale = substr($locale, 0, 2)])) {
									//Add shorter locale
									$this->config[$tag][$view]['context']['alternates'][$slocale] = [
										'absolute' => $this->router->generate($name, ['_locale' => $locale]+$route, UrlGeneratorInterface::ABSOLUTE_URL),
										'relative' => $this->router->generate($name, ['_locale' => $locale]+$route),
										'title' => implode('/', $titles),
										'translated' => $this->translator->trans($this->config['default']['languages'][$locale], [], null, $locale)
									];
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Renders a view
	 *
	 * {@inheritdoc}
	 */
	protected function render(string $view, array $parameters = [], Response $response = null): Response {
		//Create response when null
		$response ??= new Response();

		//With empty head locale
		if (empty($parameters['locale'])) {
			//Set head locale
			$parameters['locale'] = $this->locale;
		}

		/*TODO: XXX: to drop, we have title => [ 'page' => XXX, section => XXX, site => XXX ]
		//With empty head title and section
		if (empty($parameters['head']['title']) && !empty($parameters['section'])) {
			//Set head title
			$parameters['title'] = implode(' - ', [$parameters['title'], $parameters['section'], $parameters['head']['site']]);
		//With empty head title
		} elseif (empty($parameters['head']['title'])) {
			//Set head title
			$parameters['head']['title'] = implode(' - ', [$parameters['title'], $parameters['head']['site']]);
		}*/

		//Call twig render method
		$content = $this->twig->render($view, $parameters);

		//Invalidate OK response on invalid form
		if (200 === $response->getStatusCode()) {
			foreach ($parameters as $v) {
				if ($v instanceof FormInterface && $v->isSubmitted() && !$v->isValid()) {
					$response->setStatusCode(422);
					break;
				}
			}
		}

		//Store content in response
		$response->setContent($content);

		//Return response
		return $response;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @see vendor/symfony/framework-bundle/Controller/AbstractController.php
	 */
	public static function getSubscribedServices(): array {
		//Return subscribed services
		return [
			'doctrine' => ManagerRegistry::class,
			'doctrine.orm.default_entity_manager' => EntityManagerInterface::class,
			'form.factory' => FormFactoryInterface::class,
			'logger' => LoggerInterface::class,
			'mailer.mailer' => MailerInterface::class,
			'rapsys_pack.slugger_util' => SluggerUtil::class,
			'request_stack' => RequestStack::class,
			'router' => RouterInterface::class,
			'security.authorization_checker' => AuthorizationCheckerInterface::class,
			'security.helper' => Security::class,
			'security.user_password_hasher' => UserPasswordHasherInterface::class,
			'service_container' => ContainerInterface::class,
			'translator' => TranslatorInterface::class,
			'twig' => Environment::class,
			'user.cache' => CacheInterface::class
		];
	}
}
