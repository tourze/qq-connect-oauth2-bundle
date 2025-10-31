<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2TokenExpiredException;

/**
 * @internal
 */
#[CoversClass(QQOAuth2TokenExpiredException::class)]
class QQOAuth2TokenExpiredExceptionTest extends AbstractExceptionTestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $message = 'Token expired';
        $code = 401;
        $previous = new \Exception('Previous exception');
        $openid = 'test_openid';
        $expiredAt = new \DateTimeImmutable('2024-01-01 12:00:00');

        $exception = new QQOAuth2TokenExpiredException(
            $message,
            $code,
            $previous,
            $openid,
            $expiredAt
        );

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($openid, $exception->getOpenid());
        $this->assertSame($expiredAt, $exception->getExpiredAt());
    }

    public function testConstructorWithMinimalParameters(): void
    {
        $exception = new QQOAuth2TokenExpiredException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getOpenid());
        $this->assertNull($exception->getExpiredAt());
    }

    public function testGetContext(): void
    {
        $openid = 'test_openid';
        $expiredAt = new \DateTimeImmutable('2024-01-01 12:00:00');

        $exception = new QQOAuth2TokenExpiredException(
            'Test message',
            0,
            null,
            $openid,
            $expiredAt
        );

        $context = $exception->getContext();
        $this->assertIsArray($context);
        $this->assertArrayHasKey('openid', $context);
        $this->assertArrayHasKey('expired_at', $context);
        $this->assertSame($openid, $context['openid']);
        $this->assertSame($expiredAt->format(\DateTimeInterface::ATOM), $context['expired_at']);
    }

    public function testGetContextWithNullExpiredAt(): void
    {
        $exception = new QQOAuth2TokenExpiredException('Test message', 0, null, 'test_openid', null);

        $context = $exception->getContext();
        $this->assertIsArray($context);
        $this->assertArrayHasKey('expired_at', $context);
        $this->assertNull($context['expired_at']);
    }
}
