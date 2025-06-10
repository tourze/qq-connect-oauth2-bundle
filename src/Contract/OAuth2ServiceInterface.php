<?php

namespace Tourze\QQConnectOAuth2Bundle\Contract;

use Tourze\QQConnectOAuth2Bundle\DTO\QQUserInfo;

/**
 * QQ互联OAuth2服务接口
 */
interface OAuth2ServiceInterface
{
    /**
     * 生成授权URL
     */
    public function getAuthorizationUrl(string $configName, string $state, ?string $scope = null): string;

    /**
     * 使用授权码获取访问令牌
     */
    public function getAccessToken(string $configName, string $code, string $state): string;

    /**
     * 获取用户OpenID
     */
    public function getOpenId(string $accessToken): string;

    /**
     * 获取用户信息
     */
    public function getUserInfo(string $configName, string $accessToken, string $openId): QQUserInfo;

    /**
     * 验证state参数
     */
    public function validateState(string $state): bool;

    /**
     * 生成state参数
     */
    public function generateState(): string;
}
