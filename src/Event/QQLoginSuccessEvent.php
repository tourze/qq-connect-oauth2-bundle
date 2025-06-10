<?php

namespace Tourze\QQConnectOAuth2Bundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\QQConnectOAuth2Bundle\DTO\QQUserInfo;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

/**
 * QQ登录成功事件
 */
class QQLoginSuccessEvent extends Event
{
    public const NAME = 'qq_connect_oauth2.login.success';

    public function __construct(
        private readonly QQUserInfo $userInfo,
        private readonly QQOAuth2Config $config,
        private readonly string $accessToken,
        private readonly array $context = [],
    ) {}

    public function getUserInfo(): QQUserInfo
    {
        return $this->userInfo;
    }

    public function getConfig(): QQOAuth2Config
    {
        return $this->config;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
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
     * 获取用户OpenID
     */
    public function getOpenId(): string
    {
        return $this->userInfo->openId;
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
}
