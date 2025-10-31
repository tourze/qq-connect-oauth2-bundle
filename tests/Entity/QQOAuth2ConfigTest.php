<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

/**
 * @internal
 */
#[CoversClass(QQOAuth2Config::class)]
final class QQOAuth2ConfigTest extends AbstractEntityTestCase
{
    public function testDefaultValues(): void
    {
        $config = new QQOAuth2Config();

        $this->assertNull($config->getId());
        $this->assertEquals('', $config->getAppId());
        $this->assertEquals('', $config->getAppSecret());
        $this->assertNull($config->getScope());
        $this->assertTrue($config->isValid());
        $this->assertNull($config->getCreateTime());
        $this->assertNull($config->getUpdateTime());
    }

    public function testSettersAndGetters(): void
    {
        $config = new QQOAuth2Config();

        $config->setAppId('test_app_id');
        $config->setAppSecret('test_app_secret');
        $config->setScope('get_user_info');
        $config->setValid(false);

        $this->assertEquals('test_app_id', $config->getAppId());
        $this->assertEquals('test_app_secret', $config->getAppSecret());
        $this->assertEquals('get_user_info', $config->getScope());
        $this->assertFalse($config->isValid());
    }

    public function testTimestampableAware(): void
    {
        $config = new QQOAuth2Config();

        // Test that timestamps start as null
        $this->assertNull($config->getCreateTime());
        $this->assertNull($config->getUpdateTime());

        // Test setting timestamps
        $now = new \DateTimeImmutable();
        $config->setCreateTime($now);
        $config->setUpdateTime($now);

        $this->assertEquals($now, $config->getCreateTime());
        $this->assertEquals($now, $config->getUpdateTime());

        // Test retrieve timestamp array
        $timestamps = $config->retrieveTimestampArray();
        $this->assertEquals($now->format('Y-m-d H:i:s'), $timestamps['createTime']);
        $this->assertEquals($now->format('Y-m-d H:i:s'), $timestamps['updateTime']);
    }

    protected function createEntity(): object
    {
        return new QQOAuth2Config();
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'appId' => ['appId', 'test_app_id'];
        yield 'appSecret' => ['appSecret', 'test_secret'];
        yield 'scope' => ['scope', 'get_user_info'];
        yield 'valid' => ['valid', true];
    }
}
