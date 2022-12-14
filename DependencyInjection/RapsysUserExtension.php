<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys UserBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\UserBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

use Rapsys\UserBundle\RapsysUserBundle;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class RapsysUserExtension extends Extension {
	/**
	 * {@inheritdoc}
	 */
	public function load(array $configs, ContainerBuilder $container): void {
		//Load configuration
		$configuration = $this->getConfiguration($configs, $container);

		//Process the configuration to get merged config
		$config = $this->processConfiguration($configuration, $configs);

		//Detect when no user configuration is provided
		if ($configs === [[]]) {
			//Prepend default config
			$container->prependExtensionConfig(self::getAlias(), $config);
		}

		//Save configuration in parameters
		$container->setParameter(self::getAlias(), $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAlias(): string {
		return RapsysUserBundle::getAlias();
	}
}
