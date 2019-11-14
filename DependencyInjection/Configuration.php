<?php

namespace Rapsys\UserBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface {
	/**
	 * {@inheritdoc}
	 */
	public function getConfigTreeBuilder() {
		//Set tree builder
		$treeBuilder = new TreeBuilder('rapsys_user');

		//The bundle default values
		$defaults = [
	    		'class' => [
				'group' => 'Rapsys\\UserBundle\\Entity\\Group',
				'title' => 'Rapsys\\UserBundle\\Entity\\Title',
				'user' => 'Rapsys\\UserBundle\\Entity\\User'
			],
			'route' => [
				'homepage' => [
					'name' => 'rapsys_user_homepage',
					'context' => []
				],
				'login' => [
					'name' => 'rapsys_user_login',
					'context' => []
				],
				'recover' => [
					'name' => 'rapsys_user_recover',
					'context' => []
				],
				'recover_mail' => [
					'name' => 'rapsys_user_recover_mail',
					'context' => []
				],
				'register' => [
					'name' => 'rapsys_user_register',
					'context' => []
				]
			],
			'contact' => [
				'name' => 'John Doe',
				'mail' => 'contact@example.com'
			],
			'login' => [
				'view' => [
					'name' => '@RapsysUser/form/login.html.twig',
					'form' => 'Rapsys\UserBundle\Form\LoginType',
					'context' => []
				]
			],
			'recover' => [
				'view' => [
					'name' => '@RapsysUser/form/recover.html.twig',
					'form' => 'Rapsys\UserBundle\Form\RecoverType',
					'context' => []
				],
				'mail' => [
					'subject' => 'Welcome back!',
					'html' => '@RapsysUser/mail/recover.html.twig',
					'text' => '@RapsysUser/mail/recover.text.twig',
					'route' => ['homepage' => 'homepage_url', 'recover_mail' => 'recover_url'],
					'context' => []
				]
			],
			'recover_mail' => [
				'view' => [
					'name' => '@RapsysUser/form/recover_mail.html.twig',
					'form' => 'Rapsys\UserBundle\Form\RecoverMailType',
					'context' => []
				],
				'mail' => [
					'subject' => 'Welcome back!',
					'html' => '@RapsysUser/mail/recover_mail.html.twig',
					'text' => '@RapsysUser/mail/recover_mail.text.twig',
					'route' => ['homepage' => 'homepage_url'],
					'context' => []
				]
			],
			'register' => [
				'view' => [
					'form' => 'Rapsys\UserBundle\Form\RegisterType',
					'name' => '@RapsysUser/form/register.html.twig',
					'context' => []
				],
				'mail' => [
					'subject' => 'Welcome!',
					'html' => '@RapsysUser/mail/register.html.twig',
					'text' => '@RapsysUser/mail/register.text.twig',
					'route' => ['homepage' => 'homepage_url'],
					'context' => []
				]
			]
		];

		//Here we define the parameters that are allowed to configure the bundle.
		//TODO: see https://github.com/symfony/symfony/blob/master/src/Symfony/Bundle/FrameworkBundle/DependencyInjection/Configuration.php for default value and description
		//TODO: see http://symfony.com/doc/current/components/config/definition.html
		//TODO: see fosuser DependencyInjection/Configuration.php
		//XXX: use bin/console config:dump-reference to dump class infos

		//Here we define the parameters that are allowed to configure the bundle.
		$treeBuilder
			//Parameters
			->getRootNode()
				->addDefaultsIfNotSet()
				->children()
					->arrayNode('class')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('group')->cannotBeEmpty()->defaultValue($defaults['class']['group'])->end()
							->scalarNode('title')->cannotBeEmpty()->defaultValue($defaults['class']['title'])->end()
							->scalarNode('user')->cannotBeEmpty()->defaultValue($defaults['class']['user'])->end()
						->end()
					->end()
					->arrayNode('route')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('homepage')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['route']['homepage']['name'])->end()
									->arrayNode('context')
										->treatNullLike(array())
										->defaultValue($defaults['route']['homepage']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
							->arrayNode('login')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['route']['login']['name'])->end()
									->arrayNode('context')
										->treatNullLike(array())
										->defaultValue($defaults['route']['login']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
							->arrayNode('recover')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['route']['recover']['name'])->end()
									->arrayNode('context')
										->treatNullLike(array())
										->defaultValue($defaults['route']['recover']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
							->arrayNode('recover_mail')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['route']['recover_mail']['name'])->end()
									->arrayNode('context')
										->treatNullLike(array())
										->defaultValue($defaults['route']['recover_mail']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
							->arrayNode('register')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['route']['register']['name'])->end()
									->arrayNode('context')
										->treatNullLike(array())
										->defaultValue($defaults['route']['register']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
						->end()
					->end()
					->arrayNode('contact')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['contact']['name'])->end()
							->scalarNode('mail')->cannotBeEmpty()->defaultValue($defaults['contact']['mail'])->end()
						->end()
					->end()
					->arrayNode('login')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('view')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['login']['view']['name'])->end()
									->scalarNode('form')->cannotBeEmpty()->defaultValue($defaults['login']['view']['form'])->end()
									->arrayNode('context')
										->treatNullLike(array())
										->defaultValue($defaults['login']['view']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
						->end()
					->end()
					->arrayNode('recover')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('view')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['recover']['view']['name'])->end()
									->scalarNode('form')->cannotBeEmpty()->defaultValue($defaults['recover']['view']['form'])->end()
									->arrayNode('context')
										->treatNullLike(array())
										->defaultValue($defaults['recover']['view']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
							->arrayNode('mail')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('subject')->cannotBeEmpty()->defaultValue($defaults['recover']['mail']['subject'])->end()
									->scalarNode('html')->cannotBeEmpty()->defaultValue($defaults['recover']['mail']['html'])->end()
									->scalarNode('text')->cannotBeEmpty()->defaultValue($defaults['recover']['mail']['text'])->end()
									->arrayNode('route')
										->treatNullLike(array())
										->defaultValue($defaults['recover']['mail']['route'])
										->scalarPrototype()->end()
									->end()
									->arrayNode('context')
										->treatNullLike(array())
										->defaultValue($defaults['recover']['mail']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
						->end()
					->end()
					->arrayNode('recover_mail')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('view')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['recover_mail']['view']['name'])->end()
									->scalarNode('form')->cannotBeEmpty()->defaultValue($defaults['recover_mail']['view']['form'])->end()
									->arrayNode('context')
										->treatNullLike(array())
										->defaultValue($defaults['recover_mail']['view']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
							->arrayNode('mail')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('subject')->cannotBeEmpty()->defaultValue($defaults['recover_mail']['mail']['subject'])->end()
									->scalarNode('html')->cannotBeEmpty()->defaultValue($defaults['recover_mail']['mail']['html'])->end()
									->scalarNode('text')->cannotBeEmpty()->defaultValue($defaults['recover_mail']['mail']['text'])->end()
									->arrayNode('route')
										->treatNullLike(array())
										->defaultValue($defaults['recover_mail']['mail']['route'])
										->scalarPrototype()->end()
									->end()
									->arrayNode('context')
										->treatNullLike(array())
										->defaultValue($defaults['recover_mail']['mail']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
						->end()
					->end()
					->arrayNode('register')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('view')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('form')->cannotBeEmpty()->defaultValue($defaults['register']['view']['form'])->end()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['register']['view']['name'])->end()
									->arrayNode('context')
										->treatNullLike(array())
										->defaultValue($defaults['register']['view']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
							->arrayNode('mail')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('subject')->cannotBeEmpty()->defaultValue($defaults['register']['mail']['subject'])->end()
									->scalarNode('html')->cannotBeEmpty()->defaultValue($defaults['register']['mail']['html'])->end()
									->scalarNode('text')->cannotBeEmpty()->defaultValue($defaults['register']['mail']['text'])->end()
									->arrayNode('route')
										->treatNullLike(array())
										->defaultValue($defaults['register']['mail']['route'])
										->scalarPrototype()->end()
									->end()
									->arrayNode('context')
										->treatNullLike(array())
										->defaultValue($defaults['register']['mail']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
						->end()
					->end()
				->end()
			->end();

		return $treeBuilder;
	}
}
