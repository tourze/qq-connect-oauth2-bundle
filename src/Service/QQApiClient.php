<?php

namespace Tourze\QQConnectOAuth2Bundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ApiException;

/**
 * 纯API通信层 - Linus KISS原则：专注单一职责
 */
class QQApiClient
{
    private const DEFAULT_TIMEOUT = 30;

    public function __construct(
        private HttpClientInterface $httpClient,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * 通用API请求处理器 - 消除重复代码
     * @param array<string, mixed> $requestOptions
     * @param array<string, mixed> $context
     * @return array{content: string, status_code: int}
     */
    public function makeRequest(string $operation, string $url, array $requestOptions, array $context = []): array
    {
        $fullContext = array_merge(['url' => $url], $context);
        $startTime = $this->logApiStart($operation, $fullContext);

        try {
            $response = $this->executeRequest($url, $requestOptions);
            $this->logApiSuccess($operation, $startTime, $fullContext + ['status_code' => $response->getStatusCode()]);

            return ['content' => $response->getContent(), 'status_code' => $response->getStatusCode()];
        } catch (\Exception $e) {
            $this->handleRequestError($operation, $startTime, $e, $fullContext, $url);
        }
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultHeaders(string $accept = 'application/json'): array
    {
        return [
            'User-Agent' => 'QQConnectOAuth2Bundle/1.0',
            'Accept' => $accept,
        ];
    }

    /**
     * @param array<string, mixed> $requestOptions
     * @return ResponseInterface
     */
    private function executeRequest(string $url, array $requestOptions)
    {
        return $this->httpClient->request('GET', $url, array_merge([
            'timeout' => self::DEFAULT_TIMEOUT,
        ], $requestOptions));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function handleRequestError(string $operation, float $startTime, \Exception $e, array $context, string $url): never
    {
        $this->logApiError($operation, $startTime, $e, $context);
        $message = $e instanceof HttpExceptionInterface
            ? "Failed to communicate with QQ API for {$operation}"
            : "Network error during {$operation}";
        throw new QQOAuth2ApiException($message, 0, $e, $url, null);
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
}
