<?php

namespace Tourze\QQConnectOAuth2Bundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ApiException;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;

/**
 * Token生命周期管理 - 专注Token相关操作
 */
class QQTokenManager
{
    private const TOKEN_URL = 'https://graph.qq.com/oauth2.0/token';
    private const OPENID_URL = 'https://graph.qq.com/oauth2.0/me';

    public function __construct(
        private QQApiClient $apiClient,
        private QQOAuth2UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * 交换授权码获取令牌
     * @return array<int|string, array<mixed>|string>
     */
    public function exchangeCodeForToken(string $code, string $appId, string $appSecret, string $redirectUri): array
    {
        $requestOptions = [
            'query' => [
                'grant_type' => 'authorization_code',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ],
            'headers' => $this->apiClient->getDefaultHeaders('application/x-www-form-urlencoded'),
        ];

        $context = ['app_id' => $appId, 'redirect_uri' => $redirectUri];
        $response = $this->apiClient->makeRequest('token exchange', self::TOKEN_URL, $requestOptions, $context);

        return $this->processTokenResponse($response['content']);
    }

    /**
     * 获取OpenID
     * @return array<string, mixed>
     */
    public function getOpenid(string $accessToken): array
    {
        $requestOptions = [
            'query' => ['access_token' => $accessToken],
            'headers' => $this->apiClient->getDefaultHeaders('application/json'),
        ];

        $response = $this->apiClient->makeRequest('openid request', self::OPENID_URL, $requestOptions);

        return $this->parseOpenidResponse($response['content']);
    }

    /**
     * 刷新单个用户令牌
     */
    public function refreshToken(string $openid): bool
    {
        $user = $this->userRepository->findByOpenid($openid);
        if (null === $user || null === $user->getRefreshToken()) {
            return false;
        }

        return $this->performTokenRefresh($user, $openid);
    }

    /**
     * 批量刷新过期令牌
     */
    public function refreshExpiredTokens(): int
    {
        $expiredUsers = $this->userRepository->findExpiredTokenUsers();
        $refreshed = 0;

        foreach ($expiredUsers as $user) {
            if ($this->refreshToken($user->getOpenid())) {
                ++$refreshed;
            }
            $this->applyRateLimit();
        }

        return $refreshed;
    }

    /**
     * 批量更新令牌
     * @param array<int, array<string, mixed>> $userData
     */
    public function bulkUpdateTokens(array $userData): int
    {
        $updated = 0;

        foreach ($userData as $data) {
            if ($this->processSingleTokenUpdate($data)) {
                ++$updated;
                $this->handleBatchProcessing($updated);
            }
        }

        $this->completeBatchUpdate();

        return $updated;
    }

    /**
     * 执行令牌刷新
     */
    private function performTokenRefresh(QQOAuth2User $user, string $openid): bool
    {
        $requestOptions = $this->buildRefreshTokenRequest($user);
        $context = ['openid' => substr($openid, 0, 8) . '***'];

        try {
            return $this->executeTokenRefresh($requestOptions, $context, $user);
        } catch (\Exception $e) {
            $this->logger?->error('QQ OAuth2 refresh token error', $context + ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * 构建刷新令牌请求
     * @return array<string, mixed>
     */
    private function buildRefreshTokenRequest(QQOAuth2User $user): array
    {
        $config = $user->getConfig();
        if (null === $config) {
            throw new QQOAuth2ApiException('Configuration not found for user');
        }

        return [
            'query' => [
                'grant_type' => 'refresh_token',
                'client_id' => $config->getAppId(),
                'client_secret' => $config->getAppSecret(),
                'refresh_token' => $user->getRefreshToken(),
            ],
            'headers' => $this->apiClient->getDefaultHeaders('application/x-www-form-urlencoded'),
        ];
    }

    /**
     * 执行令牌刷新
     * @param array<string, mixed> $requestOptions
     * @param array<string, mixed> $context
     */
    private function executeTokenRefresh(array $requestOptions, array $context, QQOAuth2User $user): bool
    {
        $response = $this->apiClient->makeRequest('refresh token request', self::TOKEN_URL, $requestOptions, $context);
        $tokenData = $this->processTokenResponse($response['content']);

        if (isset($tokenData['error'])) {
            $this->logger?->warning('QQ OAuth2 refresh token failed', $context + ['error' => $tokenData['error']]);

            return false;
        }

        return $this->updateUserTokens($user, $tokenData, $context);
    }

    /**
     * 处理单个令牌更新
     * @param array<string, mixed> $data
     */
    private function processSingleTokenUpdate(array $data): bool
    {
        if (!isset($data['openid']) || !is_string($data['openid'])) {
            return false;
        }

        $user = $this->userRepository->findByOpenid($data['openid']);
        if (null === $user) {
            return false;
        }

        $this->applyTokenDataToBulkUser($user, $data);
        $this->entityManager->persist($user);

        return true;
    }

    /**
     * 应用令牌数据到批量用户
     * @param array<string, mixed> $data
     */
    private function applyTokenDataToBulkUser(QQOAuth2User $user, array $data): void
    {
        $accessToken = isset($data['access_token']) && is_string($data['access_token']) ? $data['access_token'] : '';
        $user->setAccessToken($accessToken);

        $expiresInRaw = $data['expires_in'] ?? 0;
        $expiresIn = is_int($expiresInRaw) ? $expiresInRaw : (is_numeric($expiresInRaw) ? (int) $expiresInRaw : 0);
        $user->setExpiresIn($expiresIn);

        if (isset($data['refresh_token']) && is_string($data['refresh_token'])) {
            $user->setRefreshToken($data['refresh_token']);
        }
    }

    /**
     * 处理批量处理逻辑
     */
    private function handleBatchProcessing(int $updated): void
    {
        if (0 === $updated % 100) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }
    }

    /**
     * 完成批量更新
     */
    private function completeBatchUpdate(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * 应用速率限制
     */
    private function applyRateLimit(): void
    {
        usleep(100000); // 0.1 seconds to avoid rate limiting
    }

    /**
     * 处理令牌响应并验证
     * @return array<int|string, array<mixed>|string>
     */
    private function processTokenResponse(string $content): array
    {
        parse_str($content, $data);
        /** @var array<int|string, array<mixed>|string> $data */
        $this->validateTokenResponse($data, $content);

        return $data;
    }

    /**
     * 验证令牌响应
     * @param array<int|string, array<mixed>|string> $data
     */
    private function validateTokenResponse(array $data, string $content): void
    {
        // 卫语句：检查 API 错误
        if (isset($data['error'])) {
            $this->handleTokenApiError($data);

            return;
        }

        // 卫语句：检查访问令牌
        if (!isset($data['access_token']) || '' === $data['access_token']) {
            $this->handleMissingAccessToken($content);
        }
    }

    /**
     * 处理令牌API错误
     * @param array<int|string, array<mixed>|string> $data
     */
    private function handleTokenApiError(array $data): void
    {
        $error = is_string($data['error']) ? $data['error'] : 'Unknown error';
        $errorDesc = isset($data['error_description']) && is_string($data['error_description']) ? $data['error_description'] : '';

        $this->logger?->warning('QQ OAuth2 token exchange API error', [
            'error' => $data['error'],
            'error_description' => $data['error_description'] ?? '',
        ]);

        throw new QQOAuth2ApiException(sprintf('Failed to exchange code for token: %s - %s', $error, $errorDesc), 0, null, self::TOKEN_URL, null);
    }

    /**
     * 处理缺失访问令牌的情况
     */
    private function handleMissingAccessToken(string $content): void
    {
        $this->logger?->error('QQ OAuth2 no access token received', [
            'response' => substr($content, 0, 200),
        ]);

        throw new QQOAuth2ApiException('No access token received from QQ API', 0, null, self::TOKEN_URL, null);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseOpenidResponse(string $content): array
    {
        // 卫语句：检查响应格式
        if (1 !== preg_match('/callback\(\s*({.*?})\s*\);/', $content, $matches)) {
            throw new QQOAuth2ApiException('Invalid openid response format', 0, null, self::OPENID_URL, null);
        }

        return $this->processOpenidJsonData($matches[1]);
    }

    /**
     * 处理 OpenID JSON 数据
     * @return array<string, mixed>
     */
    private function processOpenidJsonData(string $json): array
    {
        $data = json_decode($json, true);

        // 卫语句：检查 JSON 解析
        if (JSON_ERROR_NONE !== json_last_error() || !is_array($data)) {
            throw new QQOAuth2ApiException('Failed to parse openid response', 0, null, self::OPENID_URL, null);
        }

        /** @var array<string, mixed> $data */
        // 卫语句：检查 API 错误
        if (isset($data['error'])) {
            $error = is_string($data['error']) ? $data['error'] : 'Unknown error';
            $errorDesc = isset($data['error_description']) && is_string($data['error_description']) ? $data['error_description'] : '';
            throw new QQOAuth2ApiException(sprintf('Failed to get openid: %s - %s', $error, $errorDesc), 0, null, self::OPENID_URL, $data);
        }

        return $data;
    }

    /**
     * @param array<int|string, array<mixed>|string> $data
     * @param array<string, mixed> $context
     */
    private function updateUserTokens(QQOAuth2User $user, array $data, array $context): bool
    {
        // 卫语句：先验证必要的 access_token
        if (!$this->isValidAccessTokenData($data)) {
            $this->logMissingAccessToken($context, $data);

            return false;
        }

        $this->applyTokenData($user, $data);
        $this->saveUserChanges($user);

        return true;
    }

    /**
     * 验证访问令牌数据有效性
     * @param array<int|string, array<mixed>|string> $data
     */
    private function isValidAccessTokenData(array $data): bool
    {
        return isset($data['access_token']) && is_string($data['access_token']);
    }

    /**
     * 记录缺少访问令牌的警告
     * @param array<string, mixed> $context
     * @param array<int|string, array<mixed>|string> $data
     */
    private function logMissingAccessToken(array $context, array $data): void
    {
        $this->logger?->warning('QQ OAuth2 refresh token response missing access_token', $context + [
            'response_data' => $data,
        ]);
    }

    /**
     * 应用令牌数据到用户实体
     * @param array<int|string, array<mixed>|string> $data
     */
    private function applyTokenData(QQOAuth2User $user, array $data): void
    {
        $accessToken = is_string($data['access_token']) ? $data['access_token'] : '';
        $user->setAccessToken($accessToken);
        $user->setExpiresIn((int) $data['expires_in']);

        if (isset($data['refresh_token']) && is_string($data['refresh_token'])) {
            $user->setRefreshToken($data['refresh_token']);
        }
    }

    /**
     * 保存用户实体变更
     */
    private function saveUserChanges(QQOAuth2User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
