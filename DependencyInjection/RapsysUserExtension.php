<?php

namespace Rapsys\UserBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

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
		$loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
		$loader->load('services.yml');

		//Load configuration
		$configuration = $this->getConfiguration($configs, $container);
		$config = $this->processConfiguration($configuration, $configs);

		//Set default config in parameter
		if (!$container->hasParameter($alias = $this->getAlias())) {
			$container->setParameter($alias, $config[$alias]);
		} else {
			$config[$alias] = $container->getParameter($alias);
		}

		//Transform the two level tree in flat parameters
		foreach($config[$alias] as $k => $v) {
			foreach($v as $s => $d) {
				//Set is as parameters
				$container->setParameter($alias.'.'.$k.'.'.$s, $d);
			}
		}
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
