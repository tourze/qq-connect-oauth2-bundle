<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Exception;

class QQOAuth2TokenExpiredException extends QQOAuth2Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?string $openid = null,
        private ?\DateTimeInterface $expiredAt = null,
    ) {
        parent::__construct($message, $code, $previous, [
            'openid' => $openid,
            'expired_at' => $expiredAt?->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function getOpenid(): ?string
    {
        return $this->openid;
    }

    public function getExpiredAt(): ?\DateTimeInterface
    {
        return $this->expiredAt;
    }
}
