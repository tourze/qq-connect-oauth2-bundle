<?php

namespace Tourze\QQConnectOAuth2Bundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Tourze\QQConnectOAuth2Bundle\Contract\OAuth2ServiceInterface;
use Tourze\QQConnectOAuth2Bundle\Contract\QQApiClientInterface;
use Tourze\QQConnectOAuth2Bundle\DTO\OAuth2Token;
use Tourze\QQConnectOAuth2Bundle\DTO\QQUserInfo;
use Tourze\QQConnectOAuth2Bundle\Exception\ConfigurationNotFoundException;
use Tourze\QQConnectOAuth2Bundle\Exception\InvalidStateException;
use Tourze\QQConnectOAuth2Bundle\Repository\QQConfigRepository;

/**
 * QQ互联OAuth2服务实现
 */
class OAuth2Service implements OAuth2ServiceInterface
{
    private const QQ_AUTHORIZATION_BASE_URL = 'https://graph.qq.com/oauth2.0/authorize';
    private const STATE_SESSION_KEY = 'qq_oauth2_state';

    public function __construct(
        private readonly QQConfigRepository $configRepository,
        private readonly QQApiClientInterface $apiClient,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger
    ) {}

    public function getAuthorizationUrl(string $configName, string $state, ?string $scope = null): string
    {
        $config = $this->configRepository->findByName($configName);
        if (!$config || !$config->isUsable()) {
            $this->logger->error('QQ配置未找到或不可用', ['configName' => $configName]);
            throw ConfigurationNotFoundException::forName($configName);
        }

        // 将state存储到会话中
        $session = $this->getSession();
        $session->set(self::STATE_SESSION_KEY, $state);

        $params = [
            'response_type' => 'code',
            'client_id' => $config->getAppId(),
            'redirect_uri' => $config->getRedirectUri(),
            'state' => $state,
            'scope' => $scope ?: $config->getScope() ?: 'get_user_info'
        ];

        $url = self::QQ_AUTHORIZATION_BASE_URL . '?' . http_build_query($params);

        $this->logger->info('生成QQ授权URL', [
            'configName' => $configName,
            'appId' => $config->getAppId(),
            'redirectUri' => $config->getRedirectUri(),
            'scope' => $params['scope']
        ]);

        return $url;
    }

    public function getAccessToken(string $configName, string $code, string $state): string
    {
        // 验证state参数
        if (!$this->validateState($state)) {
            $this->logger->error('State参数验证失败', ['state' => $state]);
            throw InvalidStateException::invalid($state, 'State参数验证失败');
        }

        $config = $this->configRepository->findByName($configName);
        if (!$config || !$config->isUsable()) {
            $this->logger->error('QQ配置未找到或不可用', ['configName' => $configName]);
            throw ConfigurationNotFoundException::forName($configName);
        }

        try {
            $tokenData = $this->apiClient->getAccessToken(
                $config->getAppId(),
                $config->getAppKey(),
                $code,
                $config->getRedirectUri()
            );

            $token = OAuth2Token::fromApiResponse($tokenData);

            $this->logger->info('获取访问令牌成功', [
                'configName' => $configName,
                'appId' => $config->getAppId(),
                'expiresIn' => $token->getExpiresIn()
            ]);

            // 清除会话中的state
            $this->getSession()->remove(self::STATE_SESSION_KEY);

            return $token->getAccessToken();
        } catch (\Exception $e) {
            $this->logger->error('获取访问令牌失败', [
                'configName' => $configName,
                'code' => $code,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getOpenId(string $accessToken): string
    {
        try {
            $openIdData = $this->apiClient->getOpenId($accessToken);

            $this->logger->info('获取OpenID成功', [
                'openId' => $openIdData['openid'] ?? 'unknown'
            ]);

            return $openIdData['openid'] ?? '';
        } catch (\Exception $e) {
            $this->logger->error('获取OpenID失败', [
                'accessToken' => substr($accessToken, 0, 10) . '...',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getUserInfo(string $configName, string $accessToken, string $openId): QQUserInfo
    {
        $config = $this->configRepository->findByName($configName);
        if (!$config || !$config->isUsable()) {
            $this->logger->error('QQ配置未找到或不可用', ['configName' => $configName]);
            throw ConfigurationNotFoundException::forName($configName);
        }

        try {
            $userInfoData = $this->apiClient->getUserInfo(
                $accessToken,
                $config->getAppId(),
                $openId
            );

            $userInfo = QQUserInfo::fromApiResponse($userInfoData, $openId);

            $this->logger->info('获取用户信息成功', [
                'configName' => $configName,
                'openId' => $openId,
                'nickname' => $userInfo->getNickname()
            ]);

            return $userInfo;
        } catch (\Exception $e) {
            $this->logger->error('获取用户信息失败', [
                'configName' => $configName,
                'openId' => $openId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function validateState(string $state): bool
    {
        $session = $this->getSession();
        $storedState = $session->get(self::STATE_SESSION_KEY);

        if (empty($storedState) || $storedState !== $state) {
            return false;
        }

        return true;
    }

    public function generateState(): string
    {
        $state = bin2hex(random_bytes(16));

        $this->logger->debug('生成state参数', ['state' => $state]);

        return $state;
    }

    /**
     * 获取当前会话
     */
    private function getSession(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !$request->hasSession()) {
            throw new \RuntimeException('当前请求没有可用的会话');
        }

        return $request->getSession();
    }
}
