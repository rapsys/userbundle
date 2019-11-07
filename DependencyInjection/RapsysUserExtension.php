<?php

namespace Rapsys\UserBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class RapsysUserExtension extends Extension {
	/**
	 * {@inheritdoc}
	 */
	public function load(array $configs, ContainerBuilder $container) {
		//Load configuration
		$configuration = $this->getConfiguration($configs, $container);

		//Process the configuration to get merged config
		$config = $this->processConfiguration($configuration, $configs);

		//Detect when no user configuration is provided
		if ($configs === [[]]) {
			//Prepend default config
			$container->prependExtensionConfig($this->getAlias(), $config);
		}

		//Save configuration in parameters
		$container->setParameter($this->getAlias(), $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAlias() {
		return 'rapsys_user';
	}

	/**
	 * The function that parses the array to flatten it into a one level depth array
	 *
	 * @param $array	The config values array
	 * @param $path		The current key path
	 * @param $depth	The maxmium depth
	 * @param $sep		The separator string
	 */
	/*protected function flatten($array, $path, $depth = 10, $sep = '.') {
		//Init res
		$res = array();

		//Pass through non hashed or empty array
		if ($depth && is_array($array) && ($array === [] || array_keys($array) === range(0, count($array) - 1))) {
			$res[$path] = $array;
		//Flatten hashed array
		} elseif ($depth && is_array($array)) {
			foreach($array as $k => $v) {
				$sub = $path ? $path.$sep.$k:$k;
				$res += $this->flatten($v, $sub, $depth - 1, $sep);
			}
		//Pass scalar value directly
		} else {
			$res[$path] = $array;
		}

		//Return result
		return $res;
	}*/
}
