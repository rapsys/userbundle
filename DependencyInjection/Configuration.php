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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use Rapsys\UserBundle\RapsysUserBundle;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/configuration.html
 *
 * {@inheritdoc}
 */
class Configuration implements ConfigurationInterface {
	/**
	 * {@inheritdoc}
	 */
	public function getConfigTreeBuilder(): TreeBuilder {
		//Set tree builder
		$treeBuilder = new TreeBuilder(RapsysUserBundle::getAlias());

		//The bundle default values
		$defaults = [
			'class' => [
				'civility' => 'Rapsys\\UserBundle\\Entity\\Civility',
				'group' => 'Rapsys\\UserBundle\\Entity\\Group',
				'user' => 'Rapsys\\UserBundle\\Entity\\User'
			],
			'default' => [
				'admin' => 'ROLE_ADMIN',
				'civility' => 'Mister',
				'languages' => [
					'en_gb' => 'English'
				],
				'locales' => [ 'en_gb' ],
				'group' => [ 'User' ]
			],
			'route' => [
				'confirm' => [
					'name' => 'rapsys_user_confirm',
					'context' => []
				],
				'edit' => [
					'name' => 'rapsys_user_edit',
					'context' => []
				],
				'index' => [
					'name' => 'rapsys_user_index',
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
				'register' => [
					'name' => 'rapsys_user_register',
					'context' => []
				]
			],
			'translate' => [],
			'contact' => [
				'address' => 'contact@example.com',
				'name' => 'John Doe'
			],
			'context' => [],
			'edit' => [
				'admin' => ['mail' => true, 'pseudonym' => true],
				'field' => [],
				'route' => ['index' => 'index_url'],
				'view' => [
					'name' => '@RapsysUser/form/register.html.twig',
					'edit' => 'Rapsys\UserBundle\Form\EditType',
					'reset' => 'Rapsys\UserBundle\Form\ResetType',
					'context' => []
				]
			],
			'index' => [
				'route' => ['index' => 'index_url'],
				'view' => [
					'name' => '@RapsysUser/form/index.html.twig',
					'context' => []
				]
			],
			'login' => [
				'route' => ['index' => 'index_url'],
				'view' => [
					'name' => '@RapsysUser/form/login.html.twig',
					'form' => 'Rapsys\UserBundle\Form\LoginType',
					'context' => []
				]
			],
			'recover' => [
				'route' => ['index' => 'index_url', 'recover' => 'recover_url'],
				'view' => [
					'name' => '@RapsysUser/form/recover.html.twig',
					'form' => 'Rapsys\UserBundle\Form\RecoverType',
					'context' => []
				],
				'mail' => [
					'subject' => 'Welcome back!',
					'html' => '@RapsysUser/mail/recover.html.twig',
					'text' => '@RapsysUser/mail/recover.text.twig',
					'context' => []
				]
			],
			'register' => [
				'admin' => [],
				'field' => [],
				'route' => ['index' => 'index_url', 'confirm' => 'confirm_url'],
				'view' => [
					'name' => '@RapsysUser/form/register.html.twig',
					'form' => 'Rapsys\UserBundle\Form\RegisterType',
					'context' => []
				],
				'mail' => [
					'subject' => 'Welcome!',
					'html' => '@RapsysUser/mail/register.html.twig',
					'text' => '@RapsysUser/mail/register.text.twig',
					'context' => []
				]
			]
		];

		/**
		 * Defines parameters allowed to configure the bundle
		 *
		 * @link https://github.com/symfony/symfony/blob/master/src/Symfony/Bundle/FrameworkBundle/DependencyInjection/Configuration.php
		 * @link http://symfony.com/doc/current/components/config/definition.html
		 * @link https://github.com/symfony/assetic-bundle/blob/master/DependencyInjection/Configuration.php#L63
		 *
		 * @see php bin/console config:dump-reference rapsys_user to dump default config
		 * @see php bin/console debug:config rapsys_user to dump config
		 */
		$treeBuilder
			//Parameters
			->getRootNode()
				->addDefaultsIfNotSet()
				->children()
					->arrayNode('class')
						->addDefaultsIfNotSet()
						#XXX: ignoreExtraKeys(bool $remove = true)
						->ignoreExtraKeys(false)
						->children()
							->scalarNode('civility')->cannotBeEmpty()->defaultValue($defaults['class']['civility'])->end()
							->scalarNode('group')->cannotBeEmpty()->defaultValue($defaults['class']['group'])->end()
							->scalarNode('user')->cannotBeEmpty()->defaultValue($defaults['class']['user'])->end()
						->end()
					->end()
					->arrayNode('default')
						->addDefaultsIfNotSet()
						#XXX: ignoreExtraKeys(bool $remove = true)
						->ignoreExtraKeys(false)
						->children()
							->scalarNode('admin')->cannotBeEmpty()->defaultValue($defaults['default']['admin'])->end()
							->scalarNode('civility')->cannotBeEmpty()->defaultValue($defaults['default']['civility'])->end()
							#TODO: see if we can't prevent key normalisation with ->normalizeKeys(false)
							->arrayNode('languages')
								->treatNullLike([])
								->defaultValue($defaults['default']['languages'])
								->scalarPrototype()->end()
							->end()
							#TODO: see if we can't prevent key normalisation with ->normalizeKeys(false)
							->arrayNode('locales')
								->treatNullLike([])
								->defaultValue($defaults['default']['locales'])
								->scalarPrototype()->end()
							->end()
							->arrayNode('group')
								->treatNullLike([])
								->defaultValue($defaults['default']['group'])
								->scalarPrototype()->end()
							->end()
						->end()
					->end()
					->arrayNode('route')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('confirm')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['route']['confirm']['name'])->end()
									->arrayNode('context')
										->treatNullLike([])
										->defaultValue($defaults['route']['confirm']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
							->arrayNode('index')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['route']['index']['name'])->end()
									->arrayNode('context')
										->treatNullLike([])
										->defaultValue($defaults['route']['index']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
							->arrayNode('edit')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['route']['edit']['name'])->end()
									->arrayNode('context')
										->treatNullLike([])
										->defaultValue($defaults['route']['edit']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
							->arrayNode('login')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['route']['login']['name'])->end()
									->arrayNode('context')
										->treatNullLike([])
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
										->treatNullLike([])
										->defaultValue($defaults['route']['recover']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
							->arrayNode('register')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['route']['register']['name'])->end()
									->arrayNode('context')
										->treatNullLike([])
										->defaultValue($defaults['route']['register']['context'])
										->scalarPrototype()->end()
									->end()
								->end()
							->end()
						->end()
					->end()
					->arrayNode('translate')
						->treatNullLike([])
						->defaultValue($defaults['translate'])
						->scalarPrototype()->end()
					->end()
					->arrayNode('contact')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('address')->cannotBeEmpty()->defaultValue($defaults['contact']['address'])->end()
							->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['contact']['name'])->end()
						->end()
					->end()
					->arrayNode('context')
						->treatNullLike([])
						->defaultValue($defaults['context'])
						->variablePrototype()->end()
					->end()
					->arrayNode('edit')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('admin')
								->treatNullLike([])
								->defaultValue($defaults['edit']['admin'])
								->variablePrototype()->end()
							->end()
							->arrayNode('field')
								->treatNullLike([])
								->defaultValue($defaults['edit']['field'])
								->variablePrototype()->end()
							->end()
							->arrayNode('route')
								->treatNullLike([])
								->defaultValue($defaults['edit']['route'])
								->scalarPrototype()->end()
							->end()
							->arrayNode('view')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('edit')->cannotBeEmpty()->defaultValue($defaults['edit']['view']['edit'])->end()
									->scalarNode('reset')->cannotBeEmpty()->defaultValue($defaults['edit']['view']['reset'])->end()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['edit']['view']['name'])->end()
									->arrayNode('context')
										->treatNullLike([])
										->defaultValue($defaults['edit']['view']['context'])
										->variablePrototype()->end()
									->end()
								->end()
							->end()
						->end()
					->end()
					->arrayNode('index')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('route')
								->treatNullLike([])
								->defaultValue($defaults['index']['route'])
								->scalarPrototype()->end()
							->end()
							->arrayNode('view')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['index']['view']['name'])->end()
									->arrayNode('context')
										->treatNullLike([])
										->defaultValue($defaults['index']['view']['context'])
										->variablePrototype()->end()
									->end()
								->end()
							->end()
						->end()
					->end()
					->arrayNode('login')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('route')
								->treatNullLike([])
								->defaultValue($defaults['login']['route'])
								->scalarPrototype()->end()
							->end()
							->arrayNode('view')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['login']['view']['name'])->end()
									->scalarNode('form')->cannotBeEmpty()->defaultValue($defaults['login']['view']['form'])->end()
									->arrayNode('context')
										->treatNullLike([])
										->defaultValue($defaults['login']['view']['context'])
										->variablePrototype()->end()
									->end()
								->end()
							->end()
						->end()
					->end()
					->arrayNode('recover')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('route')
								->treatNullLike([])
								->defaultValue($defaults['recover']['route'])
								->scalarPrototype()->end()
							->end()
							->arrayNode('view')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['recover']['view']['name'])->end()
									->scalarNode('form')->cannotBeEmpty()->defaultValue($defaults['recover']['view']['form'])->end()
									->arrayNode('context')
										->treatNullLike([])
										->defaultValue($defaults['recover']['view']['context'])
										->variablePrototype()->end()
									->end()
								->end()
							->end()
							->arrayNode('mail')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('subject')->cannotBeEmpty()->defaultValue($defaults['recover']['mail']['subject'])->end()
									->scalarNode('html')->cannotBeEmpty()->defaultValue($defaults['recover']['mail']['html'])->end()
									->scalarNode('text')->cannotBeEmpty()->defaultValue($defaults['recover']['mail']['text'])->end()
									->arrayNode('context')
										->treatNullLike([])
										->defaultValue($defaults['recover']['mail']['context'])
										->variablePrototype()->end()
									->end()
								->end()
							->end()
						->end()
					->end()
					->arrayNode('register')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('admin')
								->treatNullLike([])
								->defaultValue($defaults['edit']['admin'])
								->variablePrototype()->end()
							->end()
							->arrayNode('field')
								->treatNullLike([])
								->defaultValue($defaults['register']['field'])
								->variablePrototype()->end()
							->end()
							->arrayNode('route')
								->treatNullLike([])
								->defaultValue($defaults['register']['route'])
								->scalarPrototype()->end()
							->end()
							->arrayNode('view')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('form')->cannotBeEmpty()->defaultValue($defaults['register']['view']['form'])->end()
									->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['register']['view']['name'])->end()
									->arrayNode('context')
										->treatNullLike([])
										->defaultValue($defaults['register']['view']['context'])
										->variablePrototype()->end()
									->end()
								->end()
							->end()
							->arrayNode('mail')
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('subject')->cannotBeEmpty()->defaultValue($defaults['register']['mail']['subject'])->end()
									->scalarNode('html')->cannotBeEmpty()->defaultValue($defaults['register']['mail']['html'])->end()
									->scalarNode('text')->cannotBeEmpty()->defaultValue($defaults['register']['mail']['text'])->end()
									->arrayNode('context')
										->treatNullLike([])
										->defaultValue($defaults['register']['mail']['context'])
										->variablePrototype()->end()
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
