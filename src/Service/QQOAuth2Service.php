<?php

namespace Tourze\QQConnectOAuth2Bundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ApiException;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ConfigurationException;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2Exception;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2StateRepository;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;

class QQOAuth2Service
{
    private const AUTHORIZE_URL = 'https://graph.qq.com/oauth2.0/authorize';
    private const TOKEN_URL = 'https://graph.qq.com/oauth2.0/token';
    private const OPENID_URL = 'https://graph.qq.com/oauth2.0/me';
    private const USER_INFO_URL = 'https://graph.qq.com/user/get_user_info';
    private const DEFAULT_TIMEOUT = 30;
    private const MAX_RETRY_ATTEMPTS = 3;

    public function __construct(
        private HttpClientInterface $httpClient,
        private QQOAuth2ConfigRepository $configRepository,
        private QQOAuth2StateRepository $stateRepository,
        private QQOAuth2UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function generateAuthorizationUrl(?string $sessionId = null): string
    {
        $config = $this->configRepository->findValidConfig();
        if (!$config) {
            throw new QQOAuth2ConfigurationException('No valid QQ OAuth2 configuration found');
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
            throw new QQOAuth2Exception('Invalid or expired state', 0, null, ['state' => $state]);
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
        try {
            $response = $this->httpClient->request('GET', self::TOKEN_URL, [
                'query' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $appId,
                    'client_secret' => $appSecret,
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ],
                'timeout' => self::DEFAULT_TIMEOUT,
                'headers' => [
                    'User-Agent' => 'QQConnectOAuth2Bundle/1.0',
                    'Accept' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $content = $response->getContent();
            parse_str($content, $data);
        } catch (HttpExceptionInterface $e) {
            $this->logger?->error('QQ OAuth2 token exchange HTTP error', [
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse()->getStatusCode(),
            ]);
            throw new QQOAuth2ApiException(
                'Failed to communicate with QQ API for token exchange',
                0,
                $e,
                self::TOKEN_URL,
                null
            );
        } catch (\Exception $e) {
            $this->logger?->error('QQ OAuth2 token exchange error', ['error' => $e->getMessage()]);
            throw new QQOAuth2ApiException(
                'Network error during token exchange',
                0,
                $e,
                self::TOKEN_URL,
                null
            );
        }

        if (isset($data['error'])) {
            $this->logger?->warning('QQ OAuth2 token exchange API error', [
                'error' => $data['error'],
                'error_description' => $data['error_description'] ?? '',
            ]);
            throw new QQOAuth2ApiException(
                sprintf('Failed to exchange code for token: %s - %s', $data['error'], $data['error_description'] ?? ''),
                0,
                null,
                self::TOKEN_URL,
                $data
            );
        }

        if (!isset($data['access_token']) || empty($data['access_token'])) {
            $this->logger?->error('QQ OAuth2 no access token received', [
                'response' => substr($content, 0, 200),
            ]);
            throw new QQOAuth2ApiException(
                'No access token received from QQ API',
                0,
                null,
                self::TOKEN_URL,
                $data
            );
        }

        return $data;
    }

    private function getOpenid(string $accessToken): array
    {
        $response = $this->httpClient->request('GET', self::OPENID_URL, [
            'query' => ['access_token' => $accessToken],
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => [
                'User-Agent' => 'QQConnectOAuth2Bundle/1.0',
                'Accept' => 'application/json',
            ],
        ]);

        $content = $response->getContent();
        
        // QQ returns JSONP format: callback( {...} );
        if (preg_match('/callback\(\s*({.*?})\s*\);/', $content, $matches)) {
            $json = $matches[1];
            $data = json_decode($json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new QQOAuth2ApiException('Failed to parse openid response', 0, null, self::OPENID_URL, null);
            }
            
            if (isset($data['error'])) {
                throw new QQOAuth2ApiException(sprintf('Failed to get openid: %s - %s', $data['error'], $data['error_description'] ?? ''), 0, null, self::OPENID_URL, $data);
            }
            
            return $data;
        }
        
        throw new QQOAuth2ApiException('Invalid openid response format', 0, null, self::OPENID_URL, null);
    }

    private function fetchUserInfo(string $accessToken, string $appId, string $openid): array
    {
        $response = $this->httpClient->request('GET', self::USER_INFO_URL, [
            'query' => [
                'access_token' => $accessToken,
                'oauth_consumer_key' => $appId,
                'openid' => $openid,
            ],
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => [
                'User-Agent' => 'QQConnectOAuth2Bundle/1.0',
                'Accept' => 'application/json',
            ],
        ]);

        $data = json_decode($response->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new QQOAuth2ApiException('Failed to parse user info response', 0, null, self::USER_INFO_URL, null);
        }
        
        if ($data['ret'] !== 0) {
            throw new QQOAuth2ApiException(sprintf('Failed to get user info: %s - %s', $data['ret'], $data['msg'] ?? ''), 0, null, self::USER_INFO_URL, $data);
        }
        
        return $data;
    }

    public function getUserInfo(string $openid, bool $forceRefresh = false): array
    {
        $user = $this->userRepository->findByOpenid($openid);
        if (!$user) {
            throw new QQOAuth2Exception('User not found', 0, null, ['openid' => $openid]);
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

    public function refreshExpiredTokens(): int
    {
        $expiredUsers = $this->userRepository->findExpiredTokenUsers();
        $refreshed = 0;

        foreach ($expiredUsers as $user) {
            if ($this->refreshToken($user->getOpenid())) {
                $refreshed++;
            }
            
            // Add small delay to avoid rate limiting
            usleep(100000); // 0.1 seconds
        }

        return $refreshed;
    }

    private function executeWithRetry(callable $operation, int $maxRetries = self::MAX_RETRY_ATTEMPTS): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (HttpExceptionInterface $e) {
                $lastException = $e;
                
                // Don't retry on client errors (4xx)
                if ($e->getResponse()->getStatusCode() >= 400 && $e->getResponse()->getStatusCode() < 500) {
                    throw $e;
                }
                
                // Only retry on server errors (5xx) or network issues
                if ($attempt < $maxRetries) {
                    $this->logger?->warning('QQ OAuth2 request failed, retrying', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'error' => $e->getMessage(),
                    ]);
                    
                    // Exponential backoff: 1s, 2s, 4s
                    sleep(2 ** ($attempt - 1));
                }
            } catch (\Exception $e) {
                $lastException = $e;
                if ($attempt < $maxRetries) {
                    $this->logger?->warning('QQ OAuth2 request failed, retrying', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'error' => $e->getMessage(),
                    ]);
                    sleep(1);
                }
            }
        }

        throw $lastException;
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
                'timeout' => self::DEFAULT_TIMEOUT,
                'headers' => [
                    'User-Agent' => 'QQConnectOAuth2Bundle/1.0',
                    'Accept' => 'application/x-www-form-urlencoded',
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