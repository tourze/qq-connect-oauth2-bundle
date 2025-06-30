<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2Exception;

class QQOAuth2ExceptionTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $message = 'Test exception message';
        $code = 123;
        $previous = new \Exception('Previous exception');
        $context = ['key' => 'value', 'foo' => 'bar'];
        
        $exception = new QQOAuth2Exception($message, $code, $previous, $context);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals($context, $exception->getContext());
    }
    
    public function testConstructorWithMinimalParameters(): void
    {
        $exception = new QQOAuth2Exception();
        
        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getContext());
    }
    
    public function testConstructorWithMessageOnly(): void
    {
        $message = 'Error occurred';
        $exception = new QQOAuth2Exception($message);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getContext());
    }
    
    public function testGetContext(): void
    {
        $context = [
            'user_id' => 12345,
            'action' => 'login',
            'timestamp' => '2025-06-29'
        ];
        
        $exception = new QQOAuth2Exception('Test', 0, null, $context);
        $this->assertEquals($context, $exception->getContext());
    }
    
    public function testExtendsRuntimeException(): void
    {
        $exception = new QQOAuth2Exception();
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}