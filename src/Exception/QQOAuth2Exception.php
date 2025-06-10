<?php

namespace Tourze\QQConnectOAuth2Bundle\Exception;

/**
 * QQ互联OAuth2基础异常
 */
class QQOAuth2Exception extends \Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly array $context = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取异常上下文信息
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 创建带上下文的异常
     *
     * @param array<string, mixed> $context
     */
    public static function withContext(string $message, array $context = [], int $code = 0, ?\Throwable $previous = null): static
    {
        return new static($message, $code, $previous, $context);
    }
}
