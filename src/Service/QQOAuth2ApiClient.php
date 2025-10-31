<?php

namespace Tourze\QQConnectOAuth2Bundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ApiException;

class QQOAuth2ApiClient
{
    private const TOKEN_URL = 'https://graph.qq.com/oauth2.0/token';
    private const OPENID_URL = 'https://graph.qq.com/oauth2.0/me';
    private const USER_INFO_URL = 'https://graph.qq.com/user/get_user_info';
    private const DEFAULT_TIMEOUT = 30;

    public function __construct(
        private HttpClientInterface $httpClient,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array<int|string, array<mixed>|string>
     */
    public function exchangeCodeForToken(string $code, string $appId, string $appSecret, string $redirectUri): array
    {
        $context = ['url' => self::TOKEN_URL, 'app_id' => $appId, 'redirect_uri' => $redirectUri];
        $startTime = $this->logApiStart('token exchange', $context);

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
                'headers' => $this->getDefaultHeaders('application/x-www-form-urlencoded'),
            ]);

            $content = $response->getContent();
            parse_str($content, $data);
            /** @var array<int|string, array<mixed>|string> $data */
            $this->logApiSuccess('token exchange', $startTime, $context + [
                'status_code' => $response->getStatusCode(),
                'has_access_token' => isset($data['access_token']),
            ]);
        } catch (\Exception $e) {
            $this->logApiError('token exchange', $startTime, $e, $context);
            $message = $e instanceof HttpExceptionInterface ? 'Failed to communicate with QQ API for token exchange' : 'Network error during token exchange';
            throw new QQOAuth2ApiException($message, 0, $e, self::TOKEN_URL, null);
        }

        $this->validateTokenResponse($data, $content);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOpenid(string $accessToken): array
    {
        $context = ['url' => self::OPENID_URL];
        $startTime = $this->logApiStart('openid request', $context);

        try {
            $response = $this->httpClient->request('GET', self::OPENID_URL, [
                'query' => ['access_token' => $accessToken],
                'timeout' => self::DEFAULT_TIMEOUT,
                'headers' => $this->getDefaultHeaders('application/json'),
            ]);

            $content = $response->getContent();
            $this->logApiSuccess('openid request', $startTime, $context + ['status_code' => $response->getStatusCode()]);
        } catch (\Exception $e) {
            $this->logApiError('openid request', $startTime, $e, $context);
            throw new QQOAuth2ApiException('Failed to get openid from QQ API', 0, $e, self::OPENID_URL, null);
        }

        return $this->parseOpenidResponse($content);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchUserInfo(string $accessToken, string $appId, string $openid): array
    {
        $context = [
            'url' => self::USER_INFO_URL,
            'app_id' => $appId,
            'openid' => substr($openid, 0, 8) . '***',
        ];
        $startTime = $this->logApiStart('user info request', $context);

        try {
            $response = $this->httpClient->request('GET', self::USER_INFO_URL, [
                'query' => [
                    'access_token' => $accessToken,
                    'oauth_consumer_key' => $appId,
                    'openid' => $openid,
                ],
                'timeout' => self::DEFAULT_TIMEOUT,
                'headers' => $this->getDefaultHeaders('application/json'),
            ]);

            $content = $response->getContent();
            $data = json_decode($content, true);

            if (!is_array($data)) {
                throw new QQOAuth2ApiException('Invalid JSON response from QQ API', 0, null, self::USER_INFO_URL, null);
            }

            /** @var array<string, mixed> $data */
            $this->logApiSuccess('user info request', $startTime, $context + [
                'status_code' => $response->getStatusCode(),
                'response_ret' => $data['ret'] ?? null,
            ]);
        } catch (\Exception $e) {
            $this->logApiError('user info request', $startTime, $e, $context);
            throw new QQOAuth2ApiException('Failed to get user info from QQ API', 0, $e, self::USER_INFO_URL, null);
        }

        $this->validateUserInfoResponse($data);

        return $data;
    }

    /**
     * @return array<int|string, array<mixed>|string>
     */
    public function refreshToken(string $refreshToken, string $appId, string $appSecret): array
    {
        $context = ['url' => self::TOKEN_URL];
        $startTime = $this->logApiStart('refresh token request', $context);

        try {
            $response = $this->httpClient->request('GET', self::TOKEN_URL, [
                'query' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $appId,
                    'client_secret' => $appSecret,
                    'refresh_token' => $refreshToken,
                ],
                'timeout' => self::DEFAULT_TIMEOUT,
                'headers' => $this->getDefaultHeaders('application/x-www-form-urlencoded'),
            ]);

            $content = $response->getContent();
            parse_str($content, $data);
            /** @var array<int|string, array<mixed>|string> $data */
            $this->logApiSuccess('refresh token request', $startTime, $context + ['status_code' => $response->getStatusCode()]);

            return $data;
        } catch (\Exception $e) {
            $this->logApiError('refresh token request', $startTime, $e, $context);
            throw new QQOAuth2ApiException('Failed to refresh token', 0, $e, self::TOKEN_URL, null);
        }
    }

    /**
     * @return array<string, string>
     */
    private function getDefaultHeaders(string $accept = 'application/json'): array
    {
        return [
            'User-Agent' => 'QQConnectOAuth2Bundle/1.0',
            'Accept' => $accept,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logApiStart(string $operation, array $context): float
    {
        $this->logger?->info("QQ OAuth2 {$operation} started", $context);

        return microtime(true);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logApiSuccess(string $operation, float $startTime, array $context): void
    {
        $duration = microtime(true) - $startTime;
        $this->logger?->info("QQ OAuth2 {$operation} completed successfully", $context + [
            'duration_ms' => round($duration * 1000, 2),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logApiError(string $operation, float $startTime, \Exception $e, array $context): void
    {
        $duration = microtime(true) - $startTime;
        $logContext = $context + [
            'error' => $e->getMessage(),
            'duration_ms' => round($duration * 1000, 2),
        ];

        if ($e instanceof HttpExceptionInterface) {
            $logContext['status_code'] = $e->getResponse()->getStatusCode();
        }

        $this->logger?->error("QQ OAuth2 {$operation} error", $logContext);
    }

    /**
     * @param array<int|string, array<mixed>|string> $data
     */
    private function validateTokenResponse(array $data, string $content): void
    {
        if (isset($data['error'])) {
            $this->logger?->warning('QQ OAuth2 token exchange API error', [
                'error' => $data['error'],
                'error_description' => $data['error_description'] ?? '',
            ]);
            $error = is_string($data['error']) ? $data['error'] : 'Unknown error';
            $errorDesc = isset($data['error_description']) && is_string($data['error_description']) ? $data['error_description'] : '';
            throw new QQOAuth2ApiException(sprintf('Failed to exchange code for token: %s - %s', $error, $errorDesc), 0, null, self::TOKEN_URL, null);
        }

        if (!isset($data['access_token']) || '' === $data['access_token']) {
            $this->logger?->error('QQ OAuth2 no access token received', [
                'response' => substr($content, 0, 200),
            ]);
            throw new QQOAuth2ApiException('No access token received from QQ API', 0, null, self::TOKEN_URL, null);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseOpenidResponse(string $content): array
    {
        if (1 === preg_match('/callback\(\s*({.*?})\s*\);/', $content, $matches)) {
            $json = $matches[1];
            $data = json_decode($json, true);

            if (JSON_ERROR_NONE !== json_last_error() || !is_array($data)) {
                throw new QQOAuth2ApiException('Failed to parse openid response', 0, null, self::OPENID_URL, null);
            }

            /** @var array<string, mixed> $data */
            if (isset($data['error'])) {
                $error = is_string($data['error']) ? $data['error'] : 'Unknown error';
                $errorDesc = isset($data['error_description']) && is_string($data['error_description']) ? $data['error_description'] : '';
                throw new QQOAuth2ApiException(sprintf('Failed to get openid: %s - %s', $error, $errorDesc), 0, null, self::OPENID_URL, $data);
            }

            return $data;
        }

        throw new QQOAuth2ApiException('Invalid openid response format', 0, null, self::OPENID_URL, null);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateUserInfoResponse(array $data): void
    {
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new QQOAuth2ApiException('Failed to parse user info response', 0, null, self::USER_INFO_URL, null);
        }

        $ret = $data['ret'] ?? 'unknown';
        if (!is_int($ret) && !is_string($ret)) {
            throw new QQOAuth2ApiException('Invalid ret value in response', 0, null, self::USER_INFO_URL, $data);
        }

        $retInt = is_int($ret) ? $ret : (int) $ret;
        if (0 !== $retInt) {
            $msg = isset($data['msg']) && is_string($data['msg']) ? $data['msg'] : '';
            $retStr = is_string($ret) ? $ret : (string) $ret;
            throw new QQOAuth2ApiException(sprintf('Failed to get user info: %s - %s', $retStr, $msg), 0, null, self::USER_INFO_URL, $data);
        }
    }
}
