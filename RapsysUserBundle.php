<?php

namespace Rapsys\UserBundle;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class RapsysUserBundle extends Bundle {
	/**
	 * Return bundle alias
	 *
	 * @return string The bundle alias
	 */
    public function getAlias(): string {
		//With namespace
		if ($npos = strrpos(static::class, '\\')) {
			//Set name pos
			$npos++;
		//Without namespace
		} else {
			$npos = 0;
		}

		//With trailing bundle
		if (substr(static::class, -strlen('Bundle'), strlen('Bundle')) === 'Bundle') {
			//Set bundle pos
			$bpos = strlen(static::class) - $npos - strlen('Bundle');
		//Without bundle
		} else {
			//Set bundle pos
			$bpos = strlen(static::class) - $npos;
		}

		//Return underscored lowercase bundle alias
		return Container::underscore(substr(static::class, $npos, $bpos));
    }
}
