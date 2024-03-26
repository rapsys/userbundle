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
FROM RapsysUserBundle:User AS u
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
	 * Find all users as array
	 *
	 * @param integer $page The page
	 * @param integer $count The count
	 * @return array The users sorted by id
	 */
	public function findAllAsArray(int $page, int $count): array {
		//Set the request
		$req = <<<SQL
SELECT
	u.id,
	u.mail,
	u.forename,
	u.surname,
	CONCAT_WS(" ", u.forename, u.surname) AS pseudonym,
	c.id AS c_id,
	c.title AS c_title,
	GROUP_CONCAT(g.id ORDER BY g.id SEPARATOR "\\n") AS g_ids,
	GROUP_CONCAT(g.title ORDER BY g.id SEPARATOR "\\n") AS g_titles
FROM RapsysUserBundle:User AS u
JOIN RapsysUserBundle:UserGroup AS gu ON (gu.user_id = u.id)
JOIN RapsysUserBundle:Group AS g ON (g.id = gu.group_id)
JOIN RapsysUserBundle:Civility AS c ON (c.id = u.civility_id)
GROUP BY u.id
ORDER BY u.id ASC
LIMIT :offset, :count
SQL;

		//Replace bundle entity name by table name
		$req = $this->replace($req);

		//Get result set mapping instance
		//XXX: DEBUG: see ../blog.orig/src/Rapsys/UserBundle/Repository/ArticleRepository.php
		$rsm = new ResultSetMapping();

		//Declare all fields
		//XXX: see vendor/doctrine/dbal/lib/Doctrine/DBAL/Types/Types.php
		//addScalarResult($sqlColName, $resColName, $type = 'string');
		$rsm->addScalarResult('id', 'id', 'integer')
			->addScalarResult('mail', 'mail', 'string')
			->addScalarResult('forename', 'forename', 'string')
			->addScalarResult('surname', 'surname', 'string')
			->addScalarResult('pseudonym', 'pseudonym', 'string')
			->addScalarResult('c_id', 'c_id', 'integer')
			->addScalarResult('c_title', 'c_title', 'string')
			//XXX: is a string because of \n separator
			->addScalarResult('g_ids', 'g_ids', 'string')
			//XXX: is a string because of \n separator
			->addScalarResult('g_titles', 'g_titles', 'string');

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
			//Set data
			$ret[$data['id']] = [
				'mail' => $data['mail'],
				'forename' => $data['forename'],
				'surname' => $data['surname'],
				'pseudonym' => $data['pseudonym'],
				'groups' => [],
				'slug' => $this->slugger->slug($data['pseudonym']),
				'link' => $this->router->generate('rapsysuser_edit', ['mail' => $short = $this->slugger->short($data['mail']), 'hash' => $this->slugger->hash($short)])
			];

			//With groups
			if (!empty($data['g_ids'])) {
				//Set titles
				$titles = explode("\n", $data['g_titles']);

				//Iterate on each group
				foreach(explode("\n", $data['g_ids']) as $k => $id) {
					//Add group
					$ret[$data['id']]['groups'][$id] = [
						'title' => $group = $this->translator->trans($titles[$k]),
						#'slug' => $this->slugger->slug($group)
						#'link' => $this->router->generate('rapsysuser_group_view', ['id' => $id, 'slug' => $this->slugger->short($group)])
					];
				}
			}
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
