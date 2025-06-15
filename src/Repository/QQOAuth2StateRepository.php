<?php

namespace Tourze\QQConnectOAuth2Bundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;

/**
 * @extends ServiceEntityRepository<QQOAuth2State>
 *
 * @method QQOAuth2State|null find($id, $lockMode = null, $lockVersion = null)
 * @method QQOAuth2State|null findOneBy(array $criteria, array $orderBy = null)
 * @method QQOAuth2State[]    findAll()
 * @method QQOAuth2State[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QQOAuth2StateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QQOAuth2State::class);
    }


    public function findValidState(string $state): ?QQOAuth2State
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.state = :state')
            ->andWhere('s.used = :used')
            ->andWhere('s.expireTime > :now')
            ->setParameter('state', $state)
            ->setParameter('used', false)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function cleanupExpiredStates(): int
    {
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.expireTime < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    public function findBySessionId(string $sessionId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('s.createTime', 'DESC')
            ->getQuery()
            ->getResult();
    }
}