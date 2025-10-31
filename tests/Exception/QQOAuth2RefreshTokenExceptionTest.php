<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2RefreshTokenException;

/**
 * @internal
 */
#[CoversClass(QQOAuth2RefreshTokenException::class)]
class QQOAuth2RefreshTokenExceptionTest extends AbstractExceptionTestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $message = 'Refresh token error';
        $code = 400;
        $previous = new \Exception('Previous exception');
        $openid = 'test_openid';
        $refreshTokenData = ['token' => 'test_token', 'expires_in' => 3600];

        $exception = new QQOAuth2RefreshTokenException(
            $message,
            $code,
            $previous,
            $openid,
            $refreshTokenData
        );

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($openid, $exception->getOpenid());
        $this->assertSame($refreshTokenData, $exception->getRefreshTokenData());
    }

    public function testConstructorWithMinimalParameters(): void
    {
        $exception = new QQOAuth2RefreshTokenException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getOpenid());
        $this->assertNull($exception->getRefreshTokenData());
    }

    public function testGetContext(): void
    {
        $openid = 'test_openid';
        $refreshTokenData = ['token' => 'test_token'];

        $exception = new QQOAuth2RefreshTokenException(
            'Test message',
            0,
            null,
            $openid,
            $refreshTokenData
        );

        $context = $exception->getContext();
        $this->assertIsArray($context);
        $this->assertArrayHasKey('openid', $context);
        $this->assertArrayHasKey('refresh_token_data', $context);
        $this->assertSame($openid, $context['openid']);
        $this->assertSame($refreshTokenData, $context['refresh_token_data']);
    }
}
