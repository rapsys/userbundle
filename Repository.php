<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys UserBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\UserBundle;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use Rapsys\PackBundle\Util\SluggerUtil;

/**
 * {@inheritdoc}
 *
 * Repository
 */
class Repository extends EntityRepository {
	/**
	 * The table keys array
	 *
	 * @var array
	 */
	protected array $tableKeys;

	/**
	 * The table values array
	 *
	 * @var array
	 */
	protected array $tableValues;

	/**
	 * Initializes a new LocationRepository instance
	 *
	 * @param EntityManagerInterface $manager The EntityManagerInterface instance
	 * @param ClassMetadata $class The ClassMetadata instance
	 * @param RouterInterface $router The router instance
	 * @param SluggerUtil $slugger The SluggerUtil instance
	 * @param TranslatorInterface $translator The TranslatorInterface instance
	 * @param string $locale The current locale
	 */
	public function __construct(protected EntityManagerInterface $manager, protected ClassMetadata $class, protected RouterInterface $router, protected SluggerUtil $slugger, protected TranslatorInterface $translator, protected string $locale) {
		//Call parent constructor
		parent::__construct($manager, $class);

		//Get quote strategy
		$qs = $manager->getConfiguration()->getQuoteStrategy();
		$dp = $manager->getConnection()->getDatabasePlatform();

		//Set quoted table names
		//XXX: this allow to make this code table name independent
		//XXX: remember to place longer prefix before shorter to avoid strange replacings
		$tables = [
			//Set entities
			'RapsysUserBundle:UserGroup' => $qs->getJoinTableName($manager->getClassMetadata('Rapsys\UserBundle\Entity\User')->getAssociationMapping('groups'), $manager->getClassMetadata('Rapsys\UserBundle\Entity\User'), $dp),
			'RapsysUserBundle:Civility' => $qs->getTableName($manager->getClassMetadata('Rapsys\UserBundle\Entity\Civility'), $dp),
			'RapsysUserBundle:Group' => $qs->getTableName($manager->getClassMetadata('Rapsys\UserBundle\Entity\Group'), $dp),
			'RapsysUserBundle:User' => $qs->getTableName($manager->getClassMetadata('Rapsys\UserBundle\Entity\User'), $dp),
			//Set locale
			//XXX: or $manager->getConnection()->quote($this->locale) ???
			':locale' => $dp->quoteStringLiteral($this->locale),
			//Set limit
			//XXX: Set limit used to workaround mariadb subselect optimization
			':limit' => PHP_INT_MAX,
			//Set cleanup
			"\t" => '',
			"\r" => ' ',
			"\n" => ' '
		];

		//Set quoted table name keys
		$this->tableKeys = array_keys($tables);

		//Set quoted table name values
		$this->tableValues = array_values($tables);
	}

	/**
	 * Get replaced query
	 *
	 * @param string $req The request to replace
	 * @return string The replaced request
	 */
	protected function replace(string $req): string {
		//Replace bundle entity name by table name
		return str_replace($this->tableKeys, $this->tableValues, $req);
	}
}
