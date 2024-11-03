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

use Psr\Container\ContainerInterface;

use Rapsys\PackBundle\Util\SluggerUtil;
use Rapsys\UserBundle\RapsysUserBundle;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * {@inheritdoc}
 *
 * Repository
 */
class Repository extends EntityRepository {
	/**
	 * Alias string
	 */
	protected string $alias;

	/**
	 * Config array
	 */
	protected array $config;

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
	 * @param ContainerInterface $container The container instance
	 * @param RouterInterface $router The router instance
	 * @param SluggerUtil $slugger The SluggerUtil instance
	 * @param TranslatorInterface $translator The TranslatorInterface instance
	 * @param string $locale The current locale
	 */
	public function __construct(protected EntityManagerInterface $manager, protected ClassMetadata $class, protected ContainerInterface $container, protected RouterInterface $router, protected SluggerUtil $slugger, protected TranslatorInterface $translator, protected string $locale) {
		//Call parent constructor
		parent::__construct($manager, $class);

		//Get config
		//XXX: extracting doctrine.orm.resolve_target_entities seems too complicated and doctrine listener is unusable to get reliable target entities resolution here
		$this->config = $container->getParameter($this->alias = RapsysUserBundle::getAlias());

		//Get quote strategy
		$qs = $manager->getConfiguration()->getQuoteStrategy();
		$dp = $manager->getConnection()->getDatabasePlatform();

		//Set quoted table names
		//XXX: this allow to make this code table name independent
		//XXX: remember to place longer prefix before shorter to avoid strange replacings
		//XXX: entity short syntax removed in doctrine/persistence 3.x: https://github.com/doctrine/orm/issues/8818
		$tables = [
			//Set entities
			'Rapsys\UserBundle\Entity\UserGroup' => $qs->getJoinTableName($manager->getClassMetadata($this->config['class']['user'])->getAssociationMapping('groups'), $manager->getClassMetadata($this->config['class']['user']), $dp),
			'Rapsys\UserBundle\Entity\Civility' => $qs->getTableName($manager->getClassMetadata($this->config['class']['civility']), $dp),
			'Rapsys\UserBundle\Entity\Group' => $qs->getTableName($manager->getClassMetadata($this->config['class']['group']), $dp),
			'Rapsys\UserBundle\Entity\User' => $qs->getTableName($manager->getClassMetadata($this->config['class']['user']), $dp),
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
