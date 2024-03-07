Contribute
==========

You may buy me a Beer, a Tea or help with Server fees with a paypal donation to
the address <paypal@rapsys.eu>.

Don't forget to show your love for this project, feel free to report bugs to
the author, issues which are security relevant should be disclosed privately
first.

Patches are welcomed and grant credit when requested.

Installation
============

Applications that use Symfony Flex
----------------------------------

Add bundle custom repository to your project's `composer.json` file:

```json
{
	...,
	"repositories": [
		{
			"type": "package",
			"package": {
				"name": "rapsys/userbundle",
				"version": "dev-master",
				"source": {
					"type": "git",
					"url": "https://git.rapsys.eu/userbundle",
					"reference": "master"
				},
				"autoload": {
					"psr-4": {
						"Rapsys\\UserBundle\\": ""
					}
				},
				"require": {
					"doctrine/doctrine-bundle": "^1.0|^2.0",
					"rapsys/packbundle": "dev-master",
					"symfony/flex": "^1.0",
					"symfony/form": "^4.0|^5.0",
					"symfony/framework-bundle": "^4.0|^5.0",
					"symfony/security-bundle": "^4.0|^5.0",
					"symfony/validator": "^4.0|^5.0"
				}
			}
		}
	],
	...
}
```

Then open a command console, enter your project directory and execute:

```console
$ composer require rapsys/userbundle dev-master
```

Applications that don't use Symfony Flex
----------------------------------------

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require rapsys/userbundle dev-master
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
	public function registerBundles()
	{
		$bundles = array(
			// ...
			new Rapsys\UserBundle\RapsysUserBundle(),
		);

		// ...
	}

	// ...
}
```

### Step 3: Configure the Bundle

Setup configuration file `config/packages/rapsysuser.yaml` with the following
content available in `Rapsys/UserBundle/Resources/config/packages/rapsysuser.yaml`:

```yaml
#Doctrine configuration
doctrine:
    #Orm configuration
    orm:
        #Force resolution of UserBundle entities to CustomBundle one
        #XXX: without these lines, relations are lookup in parent namespace ignoring CustomBundle extension
        resolve_target_entities:
            Rapsys\UserBundle\Entity\Group: 'CustomBundle\Entity\Group'
            Rapsys\UserBundle\Entity\Civility: 'CustomBundle\Entity\Civility'
            Rapsys\UserBundle\Entity\User: 'CustomBundle\Entity\User'

#RapsysUser configuration
rapsysuser:
    #Class replacement
    class:
        group: 'CustomBundle\Entity\Group'
        civility: 'CustomBundle\Entity\Civility'
        user: 'CustomBundle\Entity\User'
    #Default replacement
    default:
        group: [ 'User' ]
        civility: 'Mister'
    #Route replacement
    route:
        index:
            name: 'custom_index'

#Service configuration
services:
    #Register security context service
    rapsysuser.access_decision_manager:
        class: 'Symfony\Component\Security\Core\Authorization\AccessDecisionManager'
        public: true
        arguments: [ [ '@security.access.role_hierarchy_voter' ] ]
    #Register default controller
    Rapsys\UserBundle\Controller\DefaultController:
        arguments: [ '@service_container', '@router', '@translator' ]
        autowire: true
        tags: [ 'controller.service_arguments' ]
    #Register Authentication success handler
    security.authentication.success_handler:
        class: 'Rapsys\UserBundle\Handler\AuthenticationSuccessHandler'
        arguments: [ '@router', {} ]
    #Register Authentication failure handler
    security.authentication.failure_handler:
        class: 'Rapsys\UserBundle\Handler\AuthenticationFailureHandler'
        arguments: [ '@http_kernel', '@security.http_utils', {}, '@logger', '@service_container', '@router', '@rapsys_pack.slugger_util']
    #Register logout success handler
    security.logout.success_handler:
        class: 'Rapsys\UserBundle\Handler\LogoutSuccessHandler'
        arguments: [ '@service_container', '/', '@router' ]
    #Register security user checker
    security.user_checker:
        class: 'Rapsys\UserBundle\Checker\UserChecker'
```

Open a command console, enter your project directory and execute the following
command to see default bundle configuration:

```console
$ php bin/console config:dump-reference RapsysUserBundle
```

Open a command console, enter your project directory and execute the following
command to see current bundle configuration:

```console
$ php bin/console debug:config RapsysUserBundle
```

### Step 4: Setup custom bundle entities

Setup configuration file `CustomBundle/Resources/config/doctrine/User.orm.yml` with the
following content:

```yaml
CustomBundle\Entity\User:
    type: entity
    #repositoryClass: CustomBundle\Repository\UserRepository
    table: users
    associationOverride:
        groups:
            joinTable:
                name: users_groups
                joinColumns:
                    id:
                        name: user_id
                inverseJoinColumns:
                    id:
                        name: group_id
```

Setup configuration file `Resources/config/doctrine/Group.orm.yml` with the
following content:

```yaml
CustomBundle\Entity\Group:
    type: entity
    #repositoryClass: CustomBundle\Repository\GroupRepository
    table: groups
    manyToMany:
        users:
            targetEntity: Rapsys\AirBundle\Entity\User
            mappedBy: groups
```

Setup configuration file `Resources/config/doctrine/Civility.orm.yml` with the
following content:

```yaml
CustomBundle\Entity\Civility:
    type: entity
    #repositoryClass: CustomBundle\Repository\CivilityRepository
    table: civilities
    oneToMany:
        users:
            targetEntity: User
            mappedBy: civility
```

Setup entity file `CustomBundle/Entity/User.php` with the following content:

```php
<?php

namespace CustomBundle\Entity;

use Rapsys\UserBundle\Entity\User as BaseUser;

class User extends BaseUser {}
```

Setup entity file `CustomBundle/Entity/Group.php` with the following content:

```php
<?php

namespace CustomBundle\Entity;

use Rapsys\GroupBundle\Entity\Group as BaseGroup;

class Group extends BaseGroup {}
```

Setup entity file `CustomBundle/Entity/Civility.php` with the following content:

```php
<?php

namespace CustomBundle\Entity;

use Rapsys\CivilityBundle\Entity\Civility as BaseCivility;

class Civility extends BaseCivility {}
```
