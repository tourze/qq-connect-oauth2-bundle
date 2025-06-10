<?php

namespace Tourze\QQConnectOAuth2Bundle\Contract;

use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

/**
 * QQ互联配置Repository接口
 */
interface QQConfigRepositoryInterface
{
    /**
     * 根据名称获取配置
     */
    public function findByName(string $name): ?QQOAuth2Config;

    /**
     * 根据环境获取默认配置
     */
    public function findDefaultByEnvironment(string $environment): ?QQOAuth2Config;

    /**
     * 获取所有激活的配置
     *
     * @return QQOAuth2Config[]
     */
    public function findAllActive(): array;

    /**
     * 获取指定环境的所有激活配置
     *
     * @return QQOAuth2Config[]
     */
    public function findActiveByEnvironment(string $environment): array;

    /**
     * 保存配置
     */
    public function save(QQOAuth2Config $config): void;

    /**
     * 删除配置
     */
    public function delete(QQOAuth2Config $config): void;
}
