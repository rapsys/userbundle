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
			'contact' => [
				'name' => 'John Doe',
				'mail' => 'contact@example.com',
				'home_name' => 'rapsys_user_homepage',
				'home_args' => []
			],
			'login' => [
				'template' => '@@RapsysUser/security/login.html.twig',
				'context' => []
			],
			'register' => [
				'mail_template' => '@@RapsysUser/mail/register.html.twig',
				'mail_context' => [
					'title' => 'Title',
					'subtitle' => 'Hi, %%name%%',
					'subject' => 'Welcome to %%title%%',
					'message' => 'Thanks so much for joining us, from now on, you are part of %%title%%.'
				],
				'template' => '@@RapsysUser/security/register.html.twig',
				'context' => []
			],
			'recover' => [
				'mail_template' => '@@RapsysUser/mail/recover.html.twig',
				'mail_context' => [
					'title' => 'Title',
					'subtitle' => 'Hi, %%name%%',
					'subject' => 'Recover account on %%title%%',
					'raw' => 'Thanks so much for joining us, to recover your account you can follow this link: <a href="%%url%%">%%url%%</a>'
				],
				'url_name' => 'rapsys_user_recover_mail',
				'url_args' => [],
				'template' => '@@RapsysUser/security/recover.html.twig',
				'context' => []
			],
			'recover_mail' => [
				'mail_template' => '@@RapsysUser/mail/recover.html.twig',
				'mail_context' => [
					'title' => 'Title',
					'subtitle' => 'Hi, %%name%%',
					'subject' => 'Account recovered on %%title%%',
					'raw' => 'Your account password has been changed, to recover your account you can follow this link: <a href="%%url%%">%%url%%</a>'
				],
				'url_name' => 'rapsys_user_recover_mail',
				'url_args' => [],
				'template' => '@@RapsysUser/security/recover_mail.html.twig',
				'context' => []
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
					->arrayNode('contact')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['contact']['name'])->end()
							->scalarNode('mail')->cannotBeEmpty()->defaultValue($defaults['contact']['mail'])->end()
							->scalarNode('home_name')->cannotBeEmpty()->defaultValue($defaults['contact']['home_name'])->end()
							->arrayNode('home_args')
								->cannotBeEmpty()
								->treatNullLike($defaults['contact']['home_args'])
								->defaultValue($defaults['contact']['home_args'])
								->scalarPrototype()->end()
							->end()
						->end()
					->end()
					->arrayNode('login')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('template')->cannotBeEmpty()->defaultValue($defaults['login']['template'])->end()
							->arrayNode('context')
								->cannotBeEmpty()
								->treatNullLike(array())
								->defaultValue($defaults['login']['context'])
								->scalarPrototype()->end()
							->end()
						->end()
					->end()
					->arrayNode('register')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('mail_template')->cannotBeEmpty()->defaultValue($defaults['register']['mail_template'])->end()
							->arrayNode('mail_context')
								->cannotBeEmpty()
								->treatNullLike($defaults['register']['mail_context'])
								->defaultValue($defaults['register']['mail_context'])
								->scalarPrototype()->end()
							->end()
							->scalarNode('template')->cannotBeEmpty()->defaultValue($defaults['register']['template'])->end()
							->arrayNode('context')
								->cannotBeEmpty()
								->treatNullLike($defaults['register']['context'])
								->defaultValue($defaults['register']['context'])
								->scalarPrototype()->end()
							->end()
						->end()
					->end()
					->arrayNode('recover')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('mail_template')->cannotBeEmpty()->defaultValue($defaults['recover']['mail_template'])->end()
							->arrayNode('mail_context')
								->cannotBeEmpty()
								->treatNullLike($defaults['recover']['mail_context'])
								->defaultValue($defaults['recover']['mail_context'])
								->scalarPrototype()->end()
							->end()
							->scalarNode('url_name')->cannotBeEmpty()->defaultValue($defaults['recover']['url_name'])->end()
							->arrayNode('url_args')
								->cannotBeEmpty()
								->treatNullLike($defaults['recover']['url_args'])
								->defaultValue($defaults['recover']['url_args'])
								->scalarPrototype()->end()
							->end()
							->scalarNode('template')->cannotBeEmpty()->defaultValue($defaults['recover']['template'])->end()
							->arrayNode('context')
								->cannotBeEmpty()
								->treatNullLike(array())
								->defaultValue($defaults['recover']['context'])
								->scalarPrototype()->end()
							->end()
						->end()
					->end()
					->arrayNode('recover_mail')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('mail_template')->cannotBeEmpty()->defaultValue($defaults['recover']['mail_template'])->end()
							->arrayNode('mail_context')
								->cannotBeEmpty()
								->treatNullLike($defaults['recover']['mail_context'])
								->defaultValue($defaults['recover']['mail_context'])
								->scalarPrototype()->end()
							->end()
							->scalarNode('url_name')->cannotBeEmpty()->defaultValue($defaults['recover']['url_name'])->end()
							->arrayNode('url_args')
								->cannotBeEmpty()
								->treatNullLike($defaults['recover']['url_args'])
								->defaultValue($defaults['recover']['url_args'])
								->scalarPrototype()->end()
							->end()
							->scalarNode('template')->cannotBeEmpty()->defaultValue($defaults['recover']['template'])->end()
							->arrayNode('context')
								->cannotBeEmpty()
								->treatNullLike(array())
								->defaultValue($defaults['recover']['context'])
								->scalarPrototype()->end()
							->end()
						->end()
					->end()
				->end()
			->end();

		return $treeBuilder;
	}
}
