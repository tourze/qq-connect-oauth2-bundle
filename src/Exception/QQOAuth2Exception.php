<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Exception;

abstract class QQOAuth2Exception extends \RuntimeException
{
    /**
     * @param array<string, mixed>|null $context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?array $context = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }
}
