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
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as BaseAbstractController;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

use Rapsys\PackBundle\Util\SluggerUtil;

use Rapsys\UserBundle\RapsysUserBundle;

/**
 * Provides common features needed in controllers.
 *
 * {@inheritdoc}
 */
abstract class AbstractController extends BaseAbstractController implements ServiceSubscriberInterface {
	///Config array
	protected $config;

	///Context array
	protected $context;

	///ManagerRegistry
	protected $doctrine;

	///UserPasswordHasherInterface
	protected $hasher;

	///LoggerInterface
	protected $logger;

	///MailerInterface
	protected $mailer;

	///EntityManagerInterface
	protected $manager;

	///Router instance
	protected $router;

	///Slugger util
	protected $slugger;

	///Translator instance
	protected $translator;

	///Locale
	protected $locale;

	/**
	 * Abstract constructor
	 *
	 * @param ContainerInterface $container The container instance
	 * @param ManagerRegistry $doctrine The doctrine instance
	 * @param UserPasswordHasherInterface $hasher The password hasher instance
	 * @param LoggerInterface $logger The logger instance
	 * @param MailerInterface $mailer The mailer instance
	 * @param EntityManagerInterface $manager The manager instance
	 * @param RouterInterface $router The router instance
	 * @param SluggerUtil $slugger The slugger instance
	 * @param RequestStack $stack The stack instance
	 * @param TranslatorInterface $translator The translator instance
	 */
	public function __construct(ContainerInterface $container, ManagerRegistry $doctrine, UserPasswordHasherInterface $hasher, LoggerInterface $logger, MailerInterface $mailer, EntityManagerInterface $manager, RouterInterface $router, SluggerUtil $slugger, RequestStack $stack, TranslatorInterface $translator) {
		//Retrieve config
		$this->config = $container->getParameter(RapsysUserBundle::getAlias());

		//Set container
		$this->container = $container;

		//Set doctrine
		$this->doctrine = $doctrine;

		//Set hasher
		$this->hasher = $hasher;

		//Set logger
		$this->logger = $logger;

		//Set mailer
		$this->mailer = $mailer;

		//Set manager
		$this->manager = $manager;

		//Set router
		$this->router = $router;

		//Set slugger
		$this->slugger = $slugger;

		//Set translator
		$this->translator = $translator;

		//Get current request
		$request = $stack->getCurrentRequest();

		//Get current locale
		$this->locale = $request->getLocale();

		//Set locale
		$this->config['context']['locale'] = str_replace('_', '-', $this->locale);

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
			//Look for entry with title subkey
			if (!empty($current['title'])) {
				//Translate title value
				$this->config[$tag]['title'] = $this->translator->trans($current['title']);
			}

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
							foreach($this->config['locales'] as $locale) {
								//Set titles
								$titles = [];

								//Iterate on other locales
								foreach(array_diff($this->config['locales'], [$locale]) as $other) {
									$titles[$other] = $this->translator->trans($this->config['languages'][$locale], [], null, $other);
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
										'translated' => $this->translator->trans($this->config['languages'][$locale], [], null, $locale)
									];
								}

								//Add shorter locale
								if (empty($this->config[$tag][$view]['context']['alternates'][$slocale = substr($locale, 0, 2)])) {
									//Add shorter locale
									$this->config[$tag][$view]['context']['alternates'][$slocale] = [
										'absolute' => $this->router->generate($name, ['_locale' => $locale]+$route, UrlGeneratorInterface::ABSOLUTE_URL),
										'relative' => $this->router->generate($name, ['_locale' => $locale]+$route),
										'title' => implode('/', $titles),
										'translated' => $this->translator->trans($this->config['languages'][$locale], [], null, $locale)
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
	 * {@inheritdoc}
	 *
	 * @see vendor/symfony/framework-bundle/Controller/AbstractController.php
	 */
	public static function getSubscribedServices(): array {
		//Return subscribed services
		return [
			'service_container' => ContainerInterface::class,
			'doctrine' => ManagerRegistry::class,
			'doctrine.orm.default_entity_manager' => EntityManagerInterface::class,
			'logger' => LoggerInterface::class,
			'mailer.mailer' => MailerInterface::class,
			'rapsys_pack.slugger_util' => SluggerUtil::class,
			'request_stack' => RequestStack::class,
			'router' => RouterInterface::class,
			'security.user_password_hasher' => UserPasswordHasherInterface::class,
			'translator' => TranslatorInterface::class
		];
	}
}
