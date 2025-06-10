<?php

namespace Tourze\QQConnectOAuth2Bundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

/**
 * QQ配置更新事件
 */
class QQConfigUpdatedEvent extends Event
{
    public const NAME = 'qq_connect_oauth2.config.updated';

    public function __construct(
        private readonly QQOAuth2Config $config,
        private readonly string $operation,
        private readonly array $context = [],
    ) {}

    public function getConfig(): QQOAuth2Config
    {
        return $this->config;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * 获取事件上下文信息
     * 
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 获取配置名称
     */
    public function getConfigName(): string
    {
        return $this->config->getName();
    }

    /**
     * 获取环境标识
     */
    public function getEnvironment(): string
    {
        return $this->config->getEnvironment();
    }

    /**
     * 是否为创建操作
     */
    public function isCreate(): bool
    {
        return $this->operation === 'create';
    }

    /**
     * 是否为更新操作
     */
    public function isUpdate(): bool
    {
        return $this->operation === 'update';
    }

    /**
     * 是否为删除操作
     */
    public function isDelete(): bool
    {
        return $this->operation === 'delete';
    }

    /**
     * 是否为状态变更操作
     */
    public function isStatusChange(): bool
    {
        return $this->operation === 'status_change';
    }
}
