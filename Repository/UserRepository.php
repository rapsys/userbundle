<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys UserBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\UserBundle\Repository;

use Doctrine\ORM\Query\ResultSetMapping;

use Rapsys\UserBundle\Repository;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * UserRepository
 */
class UserRepository extends Repository implements PasswordUpgraderInterface {
	/**
	 * Find user count as int
	 *
	 * @return integer The keywords count
	 */
	public function findCountAsInt(): int {
		//Set the request
		$req = <<<SQL
SELECT COUNT(u.id) AS count
FROM Rapsys\UserBundle\Entity\User AS u
SQL;

		//Get result set mapping instance
		$req = $this->replace($req);

		//Get result set mapping instance
		//XXX: DEBUG: see ../blog.orig/src/Rapsys/BlogBundle/Repository/ArticleRepository.php
		$rsm = new ResultSetMapping();

		//Declare all fields
		//XXX: see vendor/doctrine/dbal/lib/Doctrine/DBAL/Types/Types.php
		//addScalarResult($sqlColName, $resColName, $type = 'string');
		$rsm->addScalarResult('count', 'count', 'integer');

		//Get result
		return $this->_em
			->createNativeQuery($req, $rsm)
			->getSingleScalarResult();
	}

	/**
	 * Find all users grouped by translated group
	 *
	 * @param integer $page The page
	 * @param integer $count The count
	 * @return array The user keyed by group and id
	 */
	public function findIndexByGroupId(int $page, int $count): array {
		//Set the request
		$req = <<<SQL
SELECT
	t.id,
	t.mail,
	t.forename,
	t.surname,
	t.g_id,
	t.g_title
FROM (
	SELECT
		u.id,
		u.mail,
		u.forename,
		u.surname,
		g.id AS g_id,
		g.title AS g_title
	FROM Rapsys\UserBundle\Entity\User AS u
	LEFT JOIN Rapsys\UserBundle\Entity\UserGroup AS gu ON (gu.user_id = u.id)
	LEFT JOIN Rapsys\UserBundle\Entity\Group AS g ON (g.id = gu.group_id)
	ORDER BY NULL
	LIMIT 0, :limit
) AS t
GROUP BY t.g_id, t.id
ORDER BY t.g_id DESC, t.id ASC
LIMIT :offset, :count
SQL;

		//Replace bundle entity name by table name
		$req = $this->replace($req);

		//Get result set mapping instance
		//XXX: DEBUG: see ../blog.orig/src/Rapsys/BlogBundle/Repository/ArticleRepository.php
		$rsm = new ResultSetMapping();

		//Declare all fields
		//XXX: see vendor/doctrine/dbal/lib/Doctrine/DBAL/Types/Types.php
		//addScalarResult($sqlColName, $resColName, $type = 'string');
		$rsm->addScalarResult('id', 'id', 'integer')
			->addScalarResult('mail', 'mail', 'string')
			->addScalarResult('forename', 'forename', 'string')
			->addScalarResult('surname', 'surname', 'string')
			->addScalarResult('g_title', 'g_title', 'string');

		//Fetch result
		$res = $this->_em
			->createNativeQuery($req, $rsm)
			->setParameter('offset', $page * $count)
			->setParameter('count', $count)
			->getResult();

		//Init return
		$ret = [];

		//Process result
		foreach($res as $data) {
			//Get translated group
			$group = $this->translator->trans($data['g_title']?:'Null', [], $this->alias);

			//Init group subarray
			if (!isset($ret[$group])) {
				$ret[$group] = [];
			}

			//Set data
			$ret[$group][$data['id']] = [
				'mail' => $data['mail'],
				'forename' => $data['forename'],
				'surname' => $data['surname'],
				//Milonga Raphaël exception
				'edit' => $this->router->generate('rapsysuser_edit', ['mail' => $short = $this->slugger->short($data['mail']), 'hash' => $this->slugger->hash($short)])
			];
		}

		//Send result
		return $ret;
	}

	/**
	 * {@inheritdoc}
	 */
	public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $hash): void {
		//Set new hashed password
		$user->setPassword($hash);

		//Flush data to database
		$this->getEntityManager()->flush();
	}
}
