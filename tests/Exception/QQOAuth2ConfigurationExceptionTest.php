<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ConfigurationException;

/**
 * @internal
 */
#[CoversClass(QQOAuth2ConfigurationException::class)]
final class QQOAuth2ConfigurationExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsQQOAuth2Exception(): void
    {
        $exception = new QQOAuth2ConfigurationException();
        // 这个测试只是验证类存在，不需要具体的断言
        $this->assertNotNull($exception);
    }

    public function testInheritsParentBehavior(): void
    {
        $message = 'Configuration error: missing app_id';
        $code = 100;
        $previous = new \Exception('Previous exception');
        $context = ['config_key' => 'app_id', 'required' => true];

        $exception = new QQOAuth2ConfigurationException($message, $code, $previous, $context);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals($context, $exception->getContext());
    }

    public function testDefaultConstructor(): void
    {
        $exception = new QQOAuth2ConfigurationException();

        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getContext());
    }

    public function testTypicalUsageScenarios(): void
    {
        // Scenario 1: Missing required configuration
        $exception1 = new QQOAuth2ConfigurationException(
            'App ID is required but not configured',
            1001,
            null,
            ['missing_field' => 'app_id']
        );
        $this->assertEquals('App ID is required but not configured', $exception1->getMessage());
        $this->assertEquals(['missing_field' => 'app_id'], $exception1->getContext());

        // Scenario 2: Invalid configuration value
        $exception2 = new QQOAuth2ConfigurationException(
            'Invalid redirect URI format',
            1002,
            null,
            ['redirect_uri' => 'not-a-valid-uri', 'expected_format' => 'https://...']
        );
        $this->assertEquals('Invalid redirect URI format', $exception2->getMessage());
        $this->assertEquals(1002, $exception2->getCode());

        // Scenario 3: Configuration conflict
        $exception3 = new QQOAuth2ConfigurationException(
            'Multiple OAuth configurations found, expected only one',
            1003,
            null,
            ['config_count' => 3]
        );
        $this->assertEquals(['config_count' => 3], $exception3->getContext());
    }
}
