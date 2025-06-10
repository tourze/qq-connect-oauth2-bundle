<?php

namespace Tourze\QQConnectOAuth2Bundle\Contract;

/**
 * QQ互联API客户端接口
 */
interface QQApiClientInterface
{
    /**
     * 获取访问令牌
     *
     * @return array{access_token: string, expires_in: int, refresh_token: string}
     */
    public function getAccessToken(string $appId, string $appKey, string $code, string $redirectUri): array;

    /**
     * 获取用户OpenID
     *
     * @return array{openid: string, client_id: string}
     */
    public function getOpenId(string $accessToken): array;

    /**
     * 获取用户信息
     *
     * @return array<string, mixed>
     */
    public function getUserInfo(string $accessToken, string $appId, string $openId): array;

    /**
     * 刷新访问令牌
     *
     * @return array{access_token: string, expires_in: int, refresh_token: string}
     */
    public function refreshToken(string $appId, string $appKey, string $refreshToken): array;
}
