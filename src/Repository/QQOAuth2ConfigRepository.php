<?php

namespace Tourze\QQConnectOAuth2Bundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
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
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_KEY_VALID_CONFIG = 'qq_oauth2.valid_config';
    private const CACHE_KEY_APP_CONFIG = 'qq_oauth2.app_config.%s';

    public function __construct(
        ManagerRegistry $registry,
        private ?CacheInterface $cache = null
    ) {
        parent::__construct($registry, QQOAuth2Config::class);
    }


    public function findValidConfig(): ?QQOAuth2Config
    {
        if (!$this->cache) {
            return $this->findValidConfigFromDatabase();
        }

        return $this->cache->get(self::CACHE_KEY_VALID_CONFIG, function (ItemInterface $item): ?QQOAuth2Config {
            $item->expiresAfter(self::CACHE_TTL);
            return $this->findValidConfigFromDatabase();
        });
    }

    private function findValidConfigFromDatabase(): ?QQOAuth2Config
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
        if (!$this->cache) {
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
        return $this->createQueryBuilder('c')
            ->andWhere('c.appId = :appId')
            ->setParameter('appId', $appId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function clearCache(): void
    {
        if (!$this->cache) {
            return;
        }

        $this->cache->delete(self::CACHE_KEY_VALID_CONFIG);
        // Note: For app-specific cache, would need a cache tag system or manual deletion
    }
}