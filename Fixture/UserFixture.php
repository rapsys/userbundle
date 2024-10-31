<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys UserBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\UserBundle\Fixture;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

use Rapsys\UserBundle\RapsysUserBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * {@inheritdoc}
 */
class UserFixture extends Fixture {
	/**
	 * Config array
	 */
	protected array $config;

	/**
	 * Air fixtures constructor
	 */
	public function __construct(protected ContainerInterface $container, protected UserPasswordHasherInterface $hasher) {
		//Retrieve config
		$this->config = $container->getParameter(RapsysUserBundle::getAlias());
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		//Civility tree
		$civilityTree = [
			'Mister',
			'Madam',
			'Miss'
		];

		//Create titles
		$civilitys = [];
		foreach($civilityTree as $civilityData) {
			$civility = new $this->config['class']['civility']($civilityData);
			$manager->persist($civility);
			$civilitys[$civilityData] = $civility;
			unset($civility);
		}

		//Group tree
		//XXX: ROLE_XXX is required by
		$groupTree = [
			'Guest',
			'User',
			'Admin'
		];

		//Create groups
		$groups = [];
		foreach($groupTree as $groupData) {
			$group = new $this->config['class']['group']($groupData);
			$manager->persist($group);
			$groups[$groupData] = $group;
			unset($group);
		}

		//Flush to get the ids
		$manager->flush();

		//User tree
		$userTree = [
			[
				'civility' => 'Mister',
				'group' => 'Admin',
				'mail' => 'admin@example.com',
				'forename' => 'Forename',
				'surname' => 'Surname',
				'password' => 'test',
				'active' => true
			]
		];

		//Create users
		$users = [];
		foreach($userTree as $userData) {
			$user = new $this->config['class']['user']($userData['mail'], $userData['password'], $civilitys[$userData['civility']], $userData['forename'], $userData['surname'], $userData['active']);
			#TODO: check that password is hashed correctly !!!
			$user->setPassword($this->hasher->hashPassword($user, $userData['password']));
			$user->addGroup($groups[$userData['group']]);
			$manager->persist($user);
			$users[] = $user;
			unset($user);
		}

		//Flush to get the ids
		$manager->flush();
	}
}
