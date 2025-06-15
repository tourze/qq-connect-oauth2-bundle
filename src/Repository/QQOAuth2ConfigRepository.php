<?php

namespace Tourze\QQConnectOAuth2Bundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

/**
 * @extends ServiceEntityRepository<QQOAuth2Config>
 *
 * @method QQOAuth2Config|null find($id, $lockMode = null, $lockVersion = null)
 * @method QQOAuth2Config|null findOneBy(array $criteria, array $orderBy = null)
 * @method QQOAuth2Config[]    findAll()
 * @method QQOAuth2Config[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QQOAuth2ConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QQOAuth2Config::class);
    }


    public function findValidConfig(): ?QQOAuth2Config
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.valid = :valid')
            ->setParameter('valid', true)
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByAppId(string $appId): ?QQOAuth2Config
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.appId = :appId')
            ->setParameter('appId', $appId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}