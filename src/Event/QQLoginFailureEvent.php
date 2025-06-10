<?php

namespace Tourze\QQConnectOAuth2Bundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

/**
 * QQ登录失败事件
 */
class QQLoginFailureEvent extends Event
{
    public const NAME = 'qq_connect_oauth2.login.failure';

    public function __construct(
        private readonly \Throwable $exception,
        private readonly ?QQOAuth2Config $config = null,
        private readonly array $context = [],
    ) {}

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function getConfig(): ?QQOAuth2Config
    {
        return $this->config;
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
     * 获取错误消息
     */
    public function getErrorMessage(): string
    {
        return $this->exception->getMessage();
    }

    /**
     * 获取配置名称
     */
    public function getConfigName(): ?string
    {
        return $this->config?->getName();
    }

    /**
     * 获取环境标识
     */
    public function getEnvironment(): ?string
    {
        return $this->config?->getEnvironment();
    }

    /**
     * 获取异常类型
     */
    public function getExceptionType(): string
    {
        return get_class($this->exception);
    }
}
