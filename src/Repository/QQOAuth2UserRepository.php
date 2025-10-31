<?php

namespace Tourze\QQConnectOAuth2Bundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;

/**
 * @extends ServiceEntityRepository<QQOAuth2User>
 */
#[AsRepository(entityClass: QQOAuth2User::class)]
class QQOAuth2UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QQOAuth2User::class);
    }

    /**
     * @return QQOAuth2User[]
     */
    public function findByUnionid(string $unionid): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.unionid = :unionid')
            ->setParameter('unionid', $unionid)
            ->getQuery()
            ->getResult()
        ;

        /** @var array<QQOAuth2User> $result */
    }

    /**
     * @return QQOAuth2User[]
     */
    public function findByUserReference(string $userReference): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.userReference = :userReference')
            ->setParameter('userReference', $userReference)
            ->getQuery()
            ->getResult()
        ;

        /** @var array<QQOAuth2User> $result */
    }

    public function findByOpenid(string $openid): ?QQOAuth2User
    {
        $result = $this->createQueryBuilder('u')
            ->andWhere('u.openid = :openid')
            ->setParameter('openid', $openid)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof QQOAuth2User ? $result : null;
    }

    /**
     * @return QQOAuth2User[]
     */
    public function findExpiredTokenUsers(): array
    {
        $qb = $this->createQueryBuilder('u');
        $qb->andWhere($qb->expr()->lt(
            'DATE_ADD(u.tokenUpdateTime, u.expiresIn, \'SECOND\')',
            ':now'
        ))
            ->setParameter('now', new \DateTime())
            ->andWhere($qb->expr()->isNotNull('u.refreshToken'))
        ;

        return $qb->getQuery()->getResult();
        /** @var array<QQOAuth2User> $result */
    }

    public function deleteExpiredStatesUsers(int $maxAge = 86400): int
    {
        $expiredDate = new \DateTime();
        $expiredDate->modify(sprintf('-%d seconds', $maxAge));

        $qb = $this->createQueryBuilder('u')->delete()
            ->andWhere('u.tokenUpdateTime < :expiredDate')
            ->andWhere('u.refreshToken IS NULL')
            ->setParameter('expiredDate', $expiredDate)
        ;

        $result = $qb->getQuery()->execute();

        return is_int($result) ? $result : 0;
    }

    /**
     * @param string[] $openids
     * @return QQOAuth2User[]
     */
    public function findUsersByOpenids(array $openids): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.openid IN (:openids)')
            ->setParameter('openids', $openids)
            ->getQuery()
            ->getResult()
        ;

        /** @var array<QQOAuth2User> $result */
    }

    public function save(QQOAuth2User $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(QQOAuth2User $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
