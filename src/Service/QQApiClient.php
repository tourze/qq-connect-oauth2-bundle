<?php

namespace Tourze\QQConnectOAuth2Bundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\QQConnectOAuth2Bundle\Contract\QQApiClientInterface;
use Tourze\QQConnectOAuth2Bundle\Exception\AccessTokenException;
use Tourze\QQConnectOAuth2Bundle\Exception\ApiException;

/**
 * QQ互联API客户端实现
 */
class QQApiClient implements QQApiClientInterface
{
    private const TOKEN_URL = 'https://graph.qq.com/oauth2.0/token';
    private const OPENID_URL = 'https://graph.qq.com/oauth2.0/me';
    private const USER_INFO_URL = 'https://graph.qq.com/user/get_user_info';
    private const REFRESH_TOKEN_URL = 'https://graph.qq.com/oauth2.0/token';

    private const DEFAULT_TIMEOUT = 10;
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger
    ) {}

    public function getAccessToken(string $appId, string $appKey, string $code, string $redirectUri): array
    {
        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $appId,
            'client_secret' => $appKey,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        $this->logger->info('请求获取访问令牌', [
            'appId' => $appId,
            'redirectUri' => $redirectUri
        ]);

        try {
            $response = $this->makeRequest('GET', self::TOKEN_URL, ['query' => $params]);
            $content = $response->getContent();

            // QQ API返回的是URL编码的字符串格式
            parse_str($content, $data);

            if (isset($data['error'])) {
                $this->logger->error('获取访问令牌失败', [
                    'error' => $data['error'],
                    'error_description' => $data['error_description'] ?? ''
                ]);
                throw AccessTokenException::requestFailed($data['error'], $data['error_description'] ?? '');
            }

            if (!isset($data['access_token'])) {
                $this->logger->error('访问令牌响应格式错误', ['response' => $content]);
                throw AccessTokenException::invalidResponse($content);
            }

            $this->logger->info('获取访问令牌成功', [
                'expiresIn' => $data['expires_in'] ?? 'unknown'
            ]);

            return [
                'access_token' => $data['access_token'],
                'expires_in' => isset($data['expires_in']) ? (int)$data['expires_in'] : 7200,
                'refresh_token' => $data['refresh_token'] ?? '',
            ];
        } catch (\Exception $e) {
            if ($e instanceof AccessTokenException) {
                throw $e;
            }

            $this->logger->error('访问令牌请求异常', [
                'error' => $e->getMessage(),
                'appId' => $appId
            ]);
            throw AccessTokenException::requestFailed($e->getMessage());
        }
    }

    public function getOpenId(string $accessToken): array
    {
        $params = [
            'access_token' => $accessToken,
        ];

        $this->logger->info('请求获取OpenID');

        try {
            $response = $this->makeRequest('GET', self::OPENID_URL, ['query' => $params]);
            $content = $response->getContent();

            // QQ API返回的是JSONP格式: callback( {"client_id":"xxx","openid":"xxx"} );
            $jsonpPattern = '/callback\s*\(\s*(.+)\s*\)\s*;?/';
            if (preg_match($jsonpPattern, $content, $matches)) {
                $data = json_decode($matches[1], true);
            } else {
                $data = json_decode($content, true);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('OpenID响应解析失败', [
                    'response' => $content,
                    'jsonError' => json_last_error_msg()
                ]);
                throw ApiException::invalidJson($content);
            }

            if (isset($data['error'])) {
                $this->logger->error('获取OpenID失败', [
                    'error' => $data['error'],
                    'error_description' => $data['error_description'] ?? ''
                ]);
                throw ApiException::qqError(0, $data['error'], $data);
            }

            if (!isset($data['openid'])) {
                $this->logger->error('OpenID响应缺少openid字段', ['response' => $content]);
                throw ApiException::unexpectedResponse(self::OPENID_URL, $content);
            }

            $this->logger->info('获取OpenID成功', [
                'openId' => $data['openid']
            ]);

            return [
                'openid' => $data['openid'],
                'client_id' => $data['client_id'] ?? '',
            ];
        } catch (\Exception $e) {
            if ($e instanceof ApiException) {
                throw $e;
            }

            $this->logger->error('OpenID请求异常', ['error' => $e->getMessage()]);
            throw ApiException::networkError(self::OPENID_URL, $e->getMessage());
        }
    }

    public function getUserInfo(string $accessToken, string $appId, string $openId): array
    {
        $params = [
            'access_token' => $accessToken,
            'oauth_consumer_key' => $appId,
            'openid' => $openId,
        ];

        $this->logger->info('请求获取用户信息', ['openId' => $openId]);

        try {
            $response = $this->makeRequest('GET', self::USER_INFO_URL, ['query' => $params]);
            $content = $response->getContent();
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('用户信息响应解析失败', [
                    'response' => $content,
                    'jsonError' => json_last_error_msg()
                ]);
                throw ApiException::invalidJson($content);
            }

            if (isset($data['ret']) && $data['ret'] !== 0) {
                $this->logger->error('获取用户信息失败', [
                    'ret' => $data['ret'],
                    'msg' => $data['msg'] ?? ''
                ]);
                throw ApiException::qqError($data['ret'], $data['msg'] ?? '', $data);
            }

            $this->logger->info('获取用户信息成功', [
                'openId' => $openId,
                'nickname' => $data['nickname'] ?? 'unknown'
            ]);

            return $data;
        } catch (\Exception $e) {
            if ($e instanceof ApiException) {
                throw $e;
            }

            $this->logger->error('用户信息请求异常', [
                'error' => $e->getMessage(),
                'openId' => $openId
            ]);
            throw ApiException::networkError(self::USER_INFO_URL, $e->getMessage());
        }
    }

    public function refreshToken(string $appId, string $appKey, string $refreshToken): array
    {
        $params = [
            'grant_type' => 'refresh_token',
            'client_id' => $appId,
            'client_secret' => $appKey,
            'refresh_token' => $refreshToken,
        ];

        $this->logger->info('请求刷新访问令牌', ['appId' => $appId]);

        try {
            $response = $this->makeRequest('GET', self::REFRESH_TOKEN_URL, ['query' => $params]);
            $content = $response->getContent();

            // QQ API返回的是URL编码的字符串格式
            parse_str($content, $data);

            if (isset($data['error'])) {
                $this->logger->error('刷新访问令牌失败', [
                    'error' => $data['error'],
                    'error_description' => $data['error_description'] ?? ''
                ]);
                throw AccessTokenException::requestFailed($data['error'], $data['error_description'] ?? '');
            }

            if (!isset($data['access_token'])) {
                $this->logger->error('刷新令牌响应格式错误', ['response' => $content]);
                throw AccessTokenException::invalidResponse('缺少access_token字段');
            }

            $this->logger->info('刷新访问令牌成功', [
                'expiresIn' => $data['expires_in'] ?? 'unknown'
            ]);

            return [
                'access_token' => $data['access_token'],
                'expires_in' => isset($data['expires_in']) ? (int)$data['expires_in'] : 7200,
                'refresh_token' => $data['refresh_token'] ?? $refreshToken,
            ];
        } catch (\Exception $e) {
            if ($e instanceof AccessTokenException) {
                throw $e;
            }

            $this->logger->error('刷新令牌请求异常', [
                'error' => $e->getMessage(),
                'appId' => $appId
            ]);
            throw AccessTokenException::requestFailed($e->getMessage(), $e);
        }
    }

    /**
     * 执行HTTP请求
     */
    private function makeRequest(string $method, string $url, array $options = []): ResponseInterface
    {
        $defaultOptions = [
            'timeout' => self::DEFAULT_TIMEOUT,
            'max_redirects' => 3,
        ];

        $options = array_merge($defaultOptions, $options);

        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $attempt++;

                $this->logger->debug('发起HTTP请求', [
                    'method' => $method,
                    'url' => $url,
                    'attempt' => $attempt,
                    'maxRetries' => self::MAX_RETRIES
                ]);

                $response = $this->httpClient->request($method, $url, $options);

                // 检查响应状态码
                $statusCode = $response->getStatusCode();
                if ($statusCode >= 400) {
                    throw new \RuntimeException(sprintf('HTTP请求失败，状态码: %d', $statusCode));
                }

                return $response;
            } catch (\Exception $e) {
                $lastException = $e;

                $this->logger->warning('HTTP请求失败，准备重试', [
                    'method' => $method,
                    'url' => $url,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);

                if ($attempt >= self::MAX_RETRIES) {
                    break;
                }

                // 指数退避重试
                usleep(pow(2, $attempt - 1) * 100000); // 100ms, 200ms, 400ms
            }
        }

        $this->logger->error('HTTP请求最终失败', [
            'method' => $method,
            'url' => $url,
            'attempts' => $attempt,
            'error' => $lastException?->getMessage()
        ]);

        throw $lastException ?? new \RuntimeException('HTTP请求失败');
    }
}
