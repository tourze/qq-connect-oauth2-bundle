<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Exception;

class QQOAuth2RefreshTokenException extends QQOAuth2Exception
{
    /**
     * @param array<string, mixed>|null $refreshTokenData
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?string $openid = null,
        private ?array $refreshTokenData = null,
    ) {
        parent::__construct($message, $code, $previous, [
            'openid' => $openid,
            'refresh_token_data' => $refreshTokenData,
        ]);
    }

    public function getOpenid(): ?string
    {
        return $this->openid;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRefreshTokenData(): ?array
    {
        return $this->refreshTokenData;
    }
}
