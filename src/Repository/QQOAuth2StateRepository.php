<?php

namespace Tourze\QQConnectOAuth2Bundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;

/**
 * @extends ServiceEntityRepository<QQOAuth2State>
 */
#[AsRepository(entityClass: QQOAuth2State::class)]
class QQOAuth2StateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QQOAuth2State::class);
    }

    public function findValidState(string $state): ?QQOAuth2State
    {
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.state = :state')
            ->andWhere('s.used = :used')
            ->andWhere('s.expireTime > :now')
            ->setParameter('state', $state)
            ->setParameter('used', false)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof QQOAuth2State ? $result : null;
    }

    public function cleanupExpiredStates(): int
    {
        $result = $this->createQueryBuilder('s')
            ->delete()
            ->where('s.expireTime < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute()
        ;

        return is_int($result) ? $result : 0;
    }

    /**
     * @return QQOAuth2State[]
     */
    public function findBySessionId(string $sessionId): array
    {
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('s.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        /** @var array<QQOAuth2State> $result */
        return is_array($result) ? $result : [];
    }

    public function save(QQOAuth2State $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(QQOAuth2State $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
