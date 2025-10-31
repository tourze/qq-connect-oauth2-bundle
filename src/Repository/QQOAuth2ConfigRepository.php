<?php

namespace Tourze\QQConnectOAuth2Bundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

/**
 * @extends ServiceEntityRepository<QQOAuth2Config>
 */
#[AsRepository(entityClass: QQOAuth2Config::class)]
class QQOAuth2ConfigRepository extends ServiceEntityRepository
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_KEY_VALID_CONFIG = 'qq_oauth2.valid_config';
    private const CACHE_KEY_APP_CONFIG = 'qq_oauth2.app_config.%s';

    public function __construct(
        ManagerRegistry $registry,
        private ?CacheInterface $cache = null,
    ) {
        parent::__construct($registry, QQOAuth2Config::class);
    }

    public function findValidConfig(): ?QQOAuth2Config
    {
        if (null === $this->cache) {
            return $this->findValidConfigFromDatabase();
        }

        return $this->cache->get(self::CACHE_KEY_VALID_CONFIG, function (ItemInterface $item): ?QQOAuth2Config {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->findValidConfigFromDatabase();
        });
    }

    private function findValidConfigFromDatabase(): ?QQOAuth2Config
    {
        $result = $this->createQueryBuilder('c')
            ->andWhere('c.valid = :valid')
            ->setParameter('valid', true)
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof QQOAuth2Config ? $result : null;
    }

    public function findByAppId(string $appId): ?QQOAuth2Config
    {
        if (null === $this->cache) {
            return $this->findByAppIdFromDatabase($appId);
        }

        $cacheKey = sprintf(self::CACHE_KEY_APP_CONFIG, $appId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($appId): ?QQOAuth2Config {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->findByAppIdFromDatabase($appId);
        });
    }

    private function findByAppIdFromDatabase(string $appId): ?QQOAuth2Config
    {
        $result = $this->createQueryBuilder('c')
            ->andWhere('c.appId = :appId')
            ->setParameter('appId', $appId)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof QQOAuth2Config ? $result : null;
    }

    public function clearCache(): void
    {
        if (null === $this->cache) {
            return;
        }

        $this->cache->delete(self::CACHE_KEY_VALID_CONFIG);
        // Note: For app-specific cache, would need a cache tag system or manual deletion
    }

    public function save(QQOAuth2Config $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
            $this->clearCache();
        }
    }

    public function remove(QQOAuth2Config $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
            $this->clearCache();
        }
    }
}
