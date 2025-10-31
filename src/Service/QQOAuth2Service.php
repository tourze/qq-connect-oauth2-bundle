<?php

namespace Tourze\QQConnectOAuth2Bundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ConfigurationException;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;

/**
 * QQ OAuth2 核心编排服务 - Linus "好品味"重构版
 *
 * 职责：
 * - 协调各个专业服务完成OAuth流程
 * - 对外暴露高层API，保持向后兼容
 * - 不处理具体业务逻辑，只做编排
 */
#[Autoconfigure(public: true)]
class QQOAuth2Service
{
    public function __construct(
        private QQOAuth2ConfigRepository $configRepository,
        private QQStateManager $stateManager,
        private QQTokenManager $tokenManager,
        private QQUserManager $userManager,
    ) {
    }

    public function generateAuthorizationUrl(?string $sessionId = null): string
    {
        $config = $this->configRepository->findValidConfig();
        if (null === $config) {
            throw new QQOAuth2ConfigurationException('No valid QQ OAuth2 configuration found');
        }

        return $this->stateManager->generateAuthorizationUrl($config, $sessionId);
    }

    public function handleCallback(string $code, string $state): QQOAuth2User
    {
        $stateEntity = $this->stateManager->validateAndMarkStateAsUsed($state);
        $config = $stateEntity->getConfig();
        $redirectUri = $this->stateManager->generateRedirectUri();

        if (null === $config) {
            throw new QQOAuth2ConfigurationException('Configuration not found in state');
        }

        $userData = $this->collectUserData($code, $config, $redirectUri);

        return $this->userManager->updateOrCreateUser($userData, $config);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getUserInfo(string $openid, bool $forceRefresh = false): array
    {
        return $this->userManager->getUserInfo($openid, $forceRefresh);
    }

    public function refreshExpiredTokens(): int
    {
        return $this->tokenManager->refreshExpiredTokens();
    }

    public function refreshToken(string $openid): bool
    {
        return $this->tokenManager->refreshToken($openid);
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public function updateOrCreateUser(array $data, QQOAuth2Config $config): QQOAuth2User
    {
        return $this->userManager->updateOrCreateUser($data, $config);
    }

    /**
     * @param array<int, array<string, mixed>> $userData
     */
    public function bulkUpdateTokens(array $userData): int
    {
        return $this->tokenManager->bulkUpdateTokens($userData);
    }

    public function cleanupExpiredStates(): int
    {
        return $this->stateManager->cleanupExpiredStates();
    }

    /**
     * 收集用户数据 - 简化版本，委托给各个管理器
     * @return array<int|string, mixed>
     */
    private function collectUserData(string $code, QQOAuth2Config $config, string $redirectUri): array
    {
        $tokenData = $this->tokenManager->exchangeCodeForToken(
            $code,
            $config->getAppId(),
            $config->getAppSecret(),
            $redirectUri
        );

        $accessToken = is_string($tokenData['access_token']) ? $tokenData['access_token'] : '';
        $openidData = $this->tokenManager->getOpenid($accessToken);
        $openid = is_string($openidData['openid']) ? $openidData['openid'] : '';
        $userInfo = $this->userManager->fetchUserInfo($accessToken, $config->getAppId(), $openid);

        return $this->userManager->mergeUserData($tokenData, $openidData, $userInfo);
    }
}
