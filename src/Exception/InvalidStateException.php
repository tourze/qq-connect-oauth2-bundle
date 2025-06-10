<?php

namespace Tourze\QQConnectOAuth2Bundle\Exception;

/**
 * State验证失败异常
 */
class InvalidStateException extends QQOAuth2Exception
{
    public static function mismatch(string $expectedState, string $actualState): self
    {
        return self::withContext(
            'OAuth2 state parameter mismatch, possible CSRF attack',
            [
                'expected_state' => $expectedState,
                'actual_state' => $actualState,
                'security_issue' => 'CSRF_ATTACK_SUSPECTED'
            ],
            400
        );
    }

    public static function missing(): self
    {
        return self::withContext(
            'OAuth2 state parameter is missing',
            ['security_issue' => 'MISSING_STATE_PARAMETER'],
            400
        );
    }

    public static function expired(string $state): self
    {
        return self::withContext(
            'OAuth2 state parameter has expired',
            [
                'state' => $state,
                'security_issue' => 'EXPIRED_STATE_PARAMETER'
            ],
            400
        );
    }

    public static function invalid(string $state, string $reason = ''): self
    {
        return self::withContext(
            'OAuth2 state parameter is invalid' . ($reason ? ": {$reason}" : ''),
            [
                'state' => $state,
                'reason' => $reason,
                'security_issue' => 'INVALID_STATE_PARAMETER'
            ],
            400
        );
    }
}
