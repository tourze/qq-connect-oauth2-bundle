<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ConfigurationException;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2Exception;

/**
 * @internal
 */
#[CoversClass(QQOAuth2Exception::class)]
final class QQOAuth2ExceptionTest extends AbstractExceptionTestCase
{
    public function testIsAbstractClass(): void
    {
        $reflectionClass = new \ReflectionClass(QQOAuth2Exception::class);
        $this->assertTrue($reflectionClass->isAbstract());
    }

    public function testExtendsRuntimeException(): void
    {
        $reflectionClass = new \ReflectionClass(QQOAuth2Exception::class);
        $this->assertTrue($reflectionClass->isSubclassOf(\RuntimeException::class));
    }

    public function testAbstractClassBehaviorThroughConcreteImplementation(): void
    {
        $message = 'Test exception message';
        $code = 123;
        $previous = new \Exception('Previous exception');
        $context = ['key' => 'value', 'foo' => 'bar'];

        // 使用具体实现类来测试抽象类的功能
        $exception = new QQOAuth2ConfigurationException($message, $code, $previous, $context);

        $this->assertInstanceOf(QQOAuth2Exception::class, $exception);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals($context, $exception->getContext());
    }

    public function testContextHandlingThroughConcreteImplementation(): void
    {
        $context = [
            'user_id' => 12345,
            'action' => 'login',
            'timestamp' => '2025-06-29',
        ];

        $exception = new QQOAuth2ConfigurationException('Test', 0, null, $context);
        $this->assertEquals($context, $exception->getContext());
    }

    public function testMinimalParametersThroughConcreteImplementation(): void
    {
        $exception = new QQOAuth2ConfigurationException();

        $this->assertInstanceOf(QQOAuth2Exception::class, $exception);
        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getContext());
    }
}
