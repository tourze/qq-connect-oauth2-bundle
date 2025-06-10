<?php

namespace Tourze\QQConnectOAuth2Bundle\Exception;

/**
 * API调用异常
 */
class ApiException extends QQOAuth2Exception
{
    public static function requestFailed(string $url, int $statusCode, string $response = ''): self
    {
        return self::withContext(
            "QQ API request failed: {$url} (HTTP {$statusCode})",
            [
                'url' => $url,
                'status_code' => $statusCode,
                'response' => $response,
                'error_type' => 'HTTP_ERROR'
            ],
            $statusCode
        );
    }

    public static function networkError(string $url, string $error): self
    {
        return self::withContext(
            "Network error while calling QQ API: {$url}",
            [
                'url' => $url,
                'error' => $error,
                'error_type' => 'NETWORK_ERROR'
            ],
            503
        );
    }

    public static function timeout(string $url, int $timeoutSeconds): self
    {
        return self::withContext(
            "QQ API request timeout: {$url} (timeout: {$timeoutSeconds}s)",
            [
                'url' => $url,
                'timeout_seconds' => $timeoutSeconds,
                'error_type' => 'TIMEOUT'
            ],
            408
        );
    }

    public static function invalidJson(string $response): self
    {
        return self::withContext(
            'Invalid JSON response from QQ API',
            [
                'response' => $response,
                'error_type' => 'INVALID_JSON'
            ],
            502
        );
    }

    public static function qqError(int $ret, string $msg, array $data = []): self
    {
        return self::withContext(
            "QQ API error: {$msg} (code: {$ret})",
            [
                'qq_ret' => $ret,
                'qq_msg' => $msg,
                'qq_data' => $data,
                'error_type' => 'QQ_API_ERROR'
            ],
            400
        );
    }

    public static function rateLimited(string $url, int $retryAfter = 0): self
    {
        return self::withContext(
            "QQ API rate limit exceeded: {$url}",
            [
                'url' => $url,
                'retry_after' => $retryAfter,
                'error_type' => 'RATE_LIMITED'
            ],
            429
        );
    }

    public static function unexpectedResponse(string $url, string $response): self
    {
        return self::withContext(
            "Unexpected response from QQ API: {$url}",
            [
                'url' => $url,
                'response' => $response,
                'error_type' => 'UNEXPECTED_RESPONSE'
            ],
            502
        );
    }
}
