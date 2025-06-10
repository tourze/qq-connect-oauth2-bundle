<?php

namespace Tourze\QQConnectOAuth2Bundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

/**
 * QQ互联配置Repository
 *
 * @method QQOAuth2Config|null find($id, $lockMode = null, $lockVersion = null)
 * @method QQOAuth2Config|null findOneBy(array $criteria, array $orderBy = null)
 * @method QQOAuth2Config[] findAll()
 * @method QQOAuth2Config[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QQConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QQOAuth2Config::class);
    }

    /**
     * 根据配置名称查找配置
     */
    public function findByName(string $name): ?QQOAuth2Config
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * 根据环境查找启用的配置列表
     */
    public function findActiveByEnvironment(string $environment): array
    {
        return $this->findBy(
            ['environment' => $environment, 'valid' => true],
            ['sortOrder' => 'ASC', 'id' => 'ASC']
        );
    }

    /**
     * 根据APP ID和环境查找配置
     */
    public function findByAppIdAndEnvironment(string $appId, string $environment): ?QQOAuth2Config
    {
        return $this->findOneBy([
            'appId' => $appId,
            'environment' => $environment
        ]);
    }

    /**
     * 获取指定环境下的默认配置（优先级最高的配置）
     */
    public function findDefaultByEnvironment(string $environment): ?QQOAuth2Config
    {
        return $this->findOneBy(
            ['environment' => $environment, 'valid' => true],
            ['sortOrder' => 'ASC', 'id' => 'ASC']
        );
    }

    /**
     * 检查配置名称是否已存在
     */
    public function existsByName(string $name): bool
    {
        return $this->count(['name' => $name]) > 0;
    }

    /**
     * 检查APP ID在指定环境下是否已存在
     */
    public function existsByAppIdAndEnvironment(string $appId, string $environment): bool
    {
        return $this->count(['appId' => $appId, 'environment' => $environment]) > 0;
    }

    /**
     * 获取所有环境列表
     */
    public function findDistinctEnvironments(): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('DISTINCT c.environment')
            ->orderBy('c.environment', 'ASC');

        return array_column($qb->getQuery()->getResult(), 'environment');
    }

    /**
     * 统计指定环境下的配置数量
     */
    public function countByEnvironment(string $environment): int
    {
        return $this->count(['environment' => $environment]);
    }

    /**
     * 查找所有启用的配置
     */
    public function findAllActive(): array
    {
        return $this->findBy(
            ['valid' => true],
            ['environment' => 'ASC', 'sortOrder' => 'ASC', 'id' => 'ASC']
        );
    }
}
