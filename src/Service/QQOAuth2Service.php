<?php

namespace Tourze\QQConnectOAuth2Bundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2StateRepository;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;

class QQOAuth2Service
{
    private const AUTHORIZE_URL = 'https://graph.qq.com/oauth2.0/authorize';
    private const TOKEN_URL = 'https://graph.qq.com/oauth2.0/token';
    private const OPENID_URL = 'https://graph.qq.com/oauth2.0/me';
    private const USER_INFO_URL = 'https://graph.qq.com/user/get_user_info';

    public function __construct(
        private HttpClientInterface $httpClient,
        private QQOAuth2ConfigRepository $configRepository,
        private QQOAuth2StateRepository $stateRepository,
        private QQOAuth2UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function generateAuthorizationUrl(?string $sessionId = null): string
    {
        $config = $this->configRepository->findValidConfig();
        if (!$config) {
            throw new \RuntimeException('No valid QQ OAuth2 configuration found');
        }

        $state = bin2hex(random_bytes(16));
        $stateEntity = new QQOAuth2State($state, $config);
        
        if ($sessionId) {
            $stateEntity->setSessionId($sessionId);
        }
        
        $this->entityManager->persist($stateEntity);
        $this->entityManager->flush();

        $redirectUri = $this->urlGenerator->generate('qq_oauth2_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        
        $params = [
            'response_type' => 'code',
            'client_id' => $config->getAppId(),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => $config->getScope() ?: 'get_user_info',
        ];

        return self::AUTHORIZE_URL . '?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $state): QQOAuth2User
    {
        $stateEntity = $this->stateRepository->findValidState($state);
        if (!$stateEntity || !$stateEntity->isValid()) {
            throw new \RuntimeException('Invalid or expired state');
        }

        $stateEntity->markAsUsed();
        $this->entityManager->persist($stateEntity);
        $this->entityManager->flush();

        // Get config from state
        $config = $stateEntity->getConfig();
        
        // Generate redirect URI
        $redirectUri = $this->urlGenerator->generate('qq_oauth2_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        
        // Exchange code for access token
        $tokenData = $this->exchangeCodeForToken($code, $config->getAppId(), $config->getAppSecret(), $redirectUri);
        
        // Get user openid
        $openidData = $this->getOpenid($tokenData['access_token']);
        
        // Get user info
        $userInfo = $this->fetchUserInfo($tokenData['access_token'], $config->getAppId(), $openidData['openid']);
        
        // Merge all data
        $userData = array_merge($tokenData, $openidData, $userInfo);
        
        return $this->userRepository->updateOrCreate($userData, $config);
    }

    private function exchangeCodeForToken(string $code, string $appId, string $appSecret, string $redirectUri): array
    {
        $response = $this->httpClient->request('GET', self::TOKEN_URL, [
            'query' => [
                'grant_type' => 'authorization_code',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ],
        ]);

        $content = $response->getContent();
        parse_str($content, $data);

        if (isset($data['error'])) {
            throw new \RuntimeException(sprintf('Failed to exchange code for token: %s - %s', $data['error'], $data['error_description'] ?? ''));
        }

        if (!isset($data['access_token']) || empty($data['access_token'])) {
            throw new \RuntimeException(sprintf('No access token received from QQ API. Response: %s', substr($content, 0, 200)));
        }

        return $data;
    }

    private function getOpenid(string $accessToken): array
    {
        $response = $this->httpClient->request('GET', self::OPENID_URL, [
            'query' => ['access_token' => $accessToken],
        ]);

        $content = $response->getContent();
        
        // QQ returns JSONP format: callback( {...} );
        if (preg_match('/callback\(\s*({.*?})\s*\);/', $content, $matches)) {
            $json = $matches[1];
            $data = json_decode($json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Failed to parse openid response');
            }
            
            if (isset($data['error'])) {
                throw new \RuntimeException(sprintf('Failed to get openid: %s - %s', $data['error'], $data['error_description'] ?? ''));
            }
            
            return $data;
        }
        
        throw new \RuntimeException('Invalid openid response format');
    }

    private function fetchUserInfo(string $accessToken, string $appId, string $openid): array
    {
        $response = $this->httpClient->request('GET', self::USER_INFO_URL, [
            'query' => [
                'access_token' => $accessToken,
                'oauth_consumer_key' => $appId,
                'openid' => $openid,
            ],
        ]);

        $data = json_decode($response->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse user info response');
        }
        
        if ($data['ret'] !== 0) {
            throw new \RuntimeException(sprintf('Failed to get user info: %s - %s', $data['ret'], $data['msg'] ?? ''));
        }
        
        return $data;
    }

    public function getUserInfo(string $openid, bool $forceRefresh = false): array
    {
        $user = $this->userRepository->findByOpenid($openid);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        if (!$forceRefresh && !$user->isTokenExpired() && $user->getRawData()) {
            return $user->getRawData();
        }

        if ($user->isTokenExpired() && $user->getRefreshToken()) {
            $this->refreshToken($openid);
            $user = $this->userRepository->findByOpenid($openid);
        }

        $config = $user->getConfig();
        $userInfo = $this->fetchUserInfo($user->getAccessToken(), $config->getAppId(), $openid);
        
        $user->setNickname($userInfo['nickname'] ?? null)
            ->setAvatar($userInfo['figureurl_qq_2'] ?? $userInfo['figureurl_qq_1'] ?? null)
            ->setGender($userInfo['gender'] ?? null)
            ->setProvince($userInfo['province'] ?? null)
            ->setCity($userInfo['city'] ?? null)
            ->setRawData($userInfo);
            
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return $userInfo;
    }

    public function refreshToken(string $openid): bool
    {
        $user = $this->userRepository->findByOpenid($openid);
        if (!$user || !$user->getRefreshToken()) {
            return false;
        }

        $config = $user->getConfig();

        try {
            $response = $this->httpClient->request('GET', self::TOKEN_URL, [
                'query' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $config->getAppId(),
                    'client_secret' => $config->getAppSecret(),
                    'refresh_token' => $user->getRefreshToken(),
                ],
            ]);

            $content = $response->getContent();
            parse_str($content, $data);

            if (isset($data['error'])) {
                return false;
            }

            $user->setAccessToken($data['access_token'])
                ->setExpiresIn((int)$data['expires_in']);
                
            if (isset($data['refresh_token'])) {
                $user->setRefreshToken($data['refresh_token']);
            }
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function cleanupExpiredStates(): int
    {
        return $this->stateRepository->cleanupExpiredStates();
    }
}