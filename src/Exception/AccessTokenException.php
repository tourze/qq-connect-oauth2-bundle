<?php

namespace Tourze\QQConnectOAuth2Bundle\Exception;

/**
 * 访问令牌异常
 */
class AccessTokenException extends QQOAuth2Exception
{
    public static function requestFailed(string $error, string $errorDescription = ''): self
    {
        return self::withContext(
            "Failed to get access token: {$error}" . ($errorDescription ? " - {$errorDescription}" : ''),
            [
                'error' => $error,
                'error_description' => $errorDescription,
                'oauth2_error' => true
            ],
            401
        );
    }

    public static function invalidResponse(string $response): self
    {
        return self::withContext(
            'Invalid access token response from QQ OAuth2 server',
            [
                'response' => $response,
                'error_type' => 'INVALID_RESPONSE'
            ],
            502
        );
    }

    public static function expired(string $accessToken): self
    {
        return self::withContext(
            'Access token has expired',
            [
                'access_token' => substr($accessToken, 0, 10) . '...',
                'error_type' => 'TOKEN_EXPIRED'
            ],
            401
        );
    }

    public static function invalid(string $accessToken): self
    {
        return self::withContext(
            'Access token is invalid',
            [
                'access_token' => substr($accessToken, 0, 10) . '...',
                'error_type' => 'TOKEN_INVALID'
            ],
            401
        );
    }

    public static function refreshFailed(string $refreshToken, string $reason = ''): self
    {
        return self::withContext(
            'Failed to refresh access token' . ($reason ? ": {$reason}" : ''),
            [
                'refresh_token' => substr($refreshToken, 0, 10) . '...',
                'reason' => $reason,
                'error_type' => 'REFRESH_FAILED'
            ],
            401
        );
    }
}
