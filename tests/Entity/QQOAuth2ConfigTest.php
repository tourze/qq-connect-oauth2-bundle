<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

class QQOAuth2ConfigTest extends TestCase
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
        
        $config->setAppId('test_app_id')
            ->setAppSecret('test_app_secret')
            ->setScope('get_user_info')
            ->setValid(false);
        
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
        $now = new \DateTime();
        $config->setCreateTime($now);
        $config->setUpdateTime($now);
        
        $this->assertSame($now, $config->getCreateTime());
        $this->assertSame($now, $config->getUpdateTime());
        
        // Test retrieve timestamp array
        $timestamps = $config->retrieveTimestampArray();
        $this->assertEquals($now->format('Y-m-d H:i:s'), $timestamps['createTime']);
        $this->assertEquals($now->format('Y-m-d H:i:s'), $timestamps['updateTime']);
    }
}