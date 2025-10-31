<?php

namespace Tourze\QQConnectOAuth2Bundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ApiException;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ConfigurationException;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;

/**
 * 用户数据管理 - 专注用户信息的CRUD操作
 */
class QQUserManager
{
    private const USER_INFO_URL = 'https://graph.qq.com/user/get_user_info';

    public function __construct(
        private QQApiClient $apiClient,
        private QQTokenManager $tokenManager,
        private QQOAuth2UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * 获取用户信息（支持缓存和强制刷新）
     * @return array<int|string, mixed>
     */
    public function getUserInfo(string $openid, bool $forceRefresh = false): array
    {
        $user = $this->findUserOrThrow($openid);

        if ($this->canUseCachedUserInfo($user, $forceRefresh)) {
            return $user->getRawData() ?? [];
        }

        return $this->fetchAndUpdateUserInfo($user, $openid);
    }

    /**
     * 获取用户信息从API
     * @return array<string, mixed>
     */
    public function fetchUserInfo(string $accessToken, string $appId, string $openid): array
    {
        $requestOptions = [
            'query' => [
                'access_token' => $accessToken,
                'oauth_consumer_key' => $appId,
                'openid' => $openid,
            ],
            'headers' => $this->apiClient->getDefaultHeaders('application/json'),
        ];

        $context = [
            'app_id' => $appId,
            'openid' => substr($openid, 0, 8) . '***',
        ];

        $response = $this->apiClient->makeRequest('user info request', self::USER_INFO_URL, $requestOptions, $context);
        $userInfo = $this->processJsonResponse($response['content']);
        $this->validateUserInfoResponse($userInfo);

        return $userInfo;
    }

    /**
     * 创建或更新用户
     * @param array<int|string, mixed> $data
     */
    public function updateOrCreateUser(array $data, QQOAuth2Config $config): QQOAuth2User
    {
        $user = $this->getOrCreateUser($data, $config);
        $this->applyUserData($user, $data);
        $this->persistUserChanges($user);

        return $user;
    }

    /**
     * 合并用户数据
     * @param array<int|string, array<mixed>|string> $tokenData
     * @param array<string, mixed> $openidData
     * @param array<string, mixed> $userInfo
     * @return array<int|string, mixed>
     */
    public function mergeUserData(array $tokenData, array $openidData, array $userInfo): array
    {
        return array_merge(
            array_map(fn ($v) => is_array($v) ? $v : $v, $tokenData),
            array_map(fn ($v) => is_array($v) ? $v : $v, $openidData),
            array_map(fn ($v) => is_array($v) ? $v : $v, $userInfo)
        );
    }

    /**
     * 检查是否可以使用缓存的用户信息
     */
    private function canUseCachedUserInfo(QQOAuth2User $user, bool $forceRefresh): bool
    {
        return !$forceRefresh && !$user->isTokenExpired() && null !== $user->getRawData();
    }

    /**
     * 获取并更新用户信息
     * @return array<int|string, mixed>
     */
    private function fetchAndUpdateUserInfo(QQOAuth2User $user, string $openid): array
    {
        $user = $this->refreshUserTokenIfNeeded($user, $openid);
        $config = $user->getConfig();
        if (null === $config) {
            throw new QQOAuth2ConfigurationException('User config is null');
        }
        $userInfo = $this->fetchUserInfo($user->getAccessToken(), $config->getAppId(), $openid);

        $this->updateUserProfile($user, $userInfo);
        $user->setRawData($userInfo);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $userInfo;
    }

    /**
     * 获取或创建用户
     * @param array<int|string, mixed> $data
     */
    private function getOrCreateUser(array $data, QQOAuth2Config $config): QQOAuth2User
    {
        if (!isset($data['openid']) || !is_string($data['openid'])) {
            throw new QQOAuth2ApiException('Missing or invalid openid in user data', 0, null, null, null);
        }

        $user = $this->userRepository->findByOpenid($data['openid']);

        if (null === $user) {
            $user = new QQOAuth2User();
            $user->setOpenid($data['openid']);
            $user->setConfig($config);

            $accessToken = isset($data['access_token']) && is_string($data['access_token']) ? $data['access_token'] : '';
            $expiresInRaw = $data['expires_in'] ?? 0;
            $expiresIn = is_int($expiresInRaw) ? $expiresInRaw : (is_numeric($expiresInRaw) ? (int) $expiresInRaw : 0);
            $user->updateToken($accessToken, $expiresIn);

            return $user;
        }

        $this->updateExistingUser($user, $data);

        return $user;
    }

    /**
     * 应用用户数据
     * @param array<int|string, mixed> $data
     */
    private function applyUserData(QQOAuth2User $user, array $data): void
    {
        $this->updateUserProfile($user, $data);
        $user->setRawData($data);
    }

    /**
     * 持久化用户变更
     */
    private function persistUserChanges(QQOAuth2User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function updateExistingUser(QQOAuth2User $user, array $data): void
    {
        $accessToken = isset($data['access_token']) && is_string($data['access_token']) ? $data['access_token'] : '';
        $user->setAccessToken($accessToken);

        $expiresInRaw = $data['expires_in'] ?? 0;
        $expiresIn = is_int($expiresInRaw) ? $expiresInRaw : (is_numeric($expiresInRaw) ? (int) $expiresInRaw : 0);
        $user->setExpiresIn($expiresIn);

        if (isset($data['refresh_token']) && is_string($data['refresh_token'])) {
            $user->setRefreshToken($data['refresh_token']);
        }

        if (isset($data['unionid']) && is_string($data['unionid'])) {
            $user->setUnionid($data['unionid']);
        }
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function updateUserProfile(QQOAuth2User $user, array $data): void
    {
        $this->updateUserBasicInfo($user, $data);
        $this->updateUserAvatar($user, $data);
    }

    /**
     * 更新用户基础信息
     * @param array<int|string, mixed> $data
     */
    private function updateUserBasicInfo(QQOAuth2User $user, array $data): void
    {
        if (isset($data['nickname']) && is_string($data['nickname'])) {
            $user->setNickname($data['nickname']);
        }

        if (isset($data['gender']) && is_string($data['gender'])) {
            $user->setGender($data['gender']);
        }

        if (isset($data['province']) && is_string($data['province'])) {
            $user->setProvince($data['province']);
        }

        if (isset($data['city']) && is_string($data['city'])) {
            $user->setCity($data['city']);
        }
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function updateUserAvatar(QQOAuth2User $user, array $data): void
    {
        $avatar = $data['figureurl_qq_2'] ?? $data['figureurl_qq_1'] ?? null;
        if (is_string($avatar) && '' !== $avatar) {
            $user->setAvatar($avatar);
        }
    }

    private function findUserOrThrow(string $openid): QQOAuth2User
    {
        $user = $this->userRepository->findByOpenid($openid);
        if (null === $user) {
            throw new QQOAuth2ApiException('User not found', 0, null, null, ['openid' => $openid]);
        }

        return $user;
    }

    private function refreshUserTokenIfNeeded(QQOAuth2User $user, string $openid): QQOAuth2User
    {
        // 卫语句：不需要刷新的情况直接返回
        if (!$user->isTokenExpired() || null === $user->getRefreshToken()) {
            return $user;
        }

        return $this->performUserTokenRefresh($openid);
    }

    /**
     * 执行用户令牌刷新，消除嵌套逻辑
     */
    private function performUserTokenRefresh(string $openid): QQOAuth2User
    {
        $this->tokenManager->refreshToken($openid);

        $refreshedUser = $this->userRepository->findByOpenid($openid);
        if (null === $refreshedUser) {
            throw new QQOAuth2ApiException('User not found after token refresh', 0, null, null, ['openid' => $openid]);
        }

        return $refreshedUser;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateUserInfoResponse(array $data): void
    {
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new QQOAuth2ApiException('Failed to parse user info response', 0, null, self::USER_INFO_URL, null);
        }

        $this->validateReturnCode($data);
    }

    /**
     * 验证API返回码
     * @param array<string, mixed> $data
     */
    private function validateReturnCode(array $data): void
    {
        $ret = $data['ret'] ?? 'unknown';
        if (!is_int($ret) && !is_string($ret)) {
            throw new QQOAuth2ApiException('Invalid ret value in response', 0, null, self::USER_INFO_URL, $data);
        }

        $retInt = is_int($ret) ? $ret : (is_numeric($ret) ? (int) $ret : -1);
        if (0 !== $retInt) {
            $msg = isset($data['msg']) && is_string($data['msg']) ? $data['msg'] : '';
            $retStr = is_string($ret) ? $ret : (string) $ret;
            throw new QQOAuth2ApiException(sprintf('Failed to get user info: %s - %s', $retStr, $msg), 0, null, self::USER_INFO_URL, $data);
        }
    }

    /**
     * 处理JSON响应并验证
     * @return array<string, mixed>
     */
    private function processJsonResponse(string $content): array
    {
        $data = json_decode($content, true);
        if (JSON_ERROR_NONE !== json_last_error() || !is_array($data)) {
            throw new QQOAuth2ApiException('Failed to parse JSON response', 0, null, null, null);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
