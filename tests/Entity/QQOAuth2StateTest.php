<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;

class QQOAuth2StateTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = $this->createMockConfig();
        $state = new QQOAuth2State('test_state_123', $config);

        $this->assertNull($state->getId());
        $this->assertEquals('test_state_123', $state->getState());
        $this->assertNull($state->getSessionId());
        $this->assertNull($state->getMetadata());
        $this->assertFalse($state->isUsed());
        $this->assertSame($config, $state->getConfig());
        $this->assertNull($state->getCreateTime());
        $this->assertInstanceOf(\DateTimeImmutable::class, $state->getExpireTime());
    }

    private function createMockConfig(): QQOAuth2Config
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret');
        return $config;
    }

    public function testExpirationLogic(): void
    {
        $config = $this->createMockConfig();
        $state = new QQOAuth2State('test_state', $config, 600);
        
        $this->assertFalse($state->isExpired());
        $this->assertTrue($state->isValid());
        
        $expiredState = new QQOAuth2State('expired_state', $config, -1);
        $this->assertTrue($expiredState->isExpired());
        $this->assertFalse($expiredState->isValid());
    }

    public function testMarkAsUsed(): void
    {
        $config = $this->createMockConfig();
        $state = new QQOAuth2State('test_state', $config);
        
        $this->assertFalse($state->isUsed());
        $this->assertTrue($state->isValid());
        
        $state->markAsUsed();
        
        $this->assertTrue($state->isUsed());
        $this->assertFalse($state->isValid());
    }

    public function testSettersAndGetters(): void
    {
        $config = $this->createMockConfig();
        $state = new QQOAuth2State('test_state', $config);
        
        $state->setSessionId('session_123')
            ->setMetadata(['key' => 'value']);
        
        $this->assertEquals('session_123', $state->getSessionId());
        $this->assertEquals(['key' => 'value'], $state->getMetadata());
    }

    public function testCustomTtl(): void
    {
        $config = $this->createMockConfig();
        $ttl = 300; // 5 minutes
        $state = new QQOAuth2State('test_state', $config, $ttl);
        
        // Expire time should be ttl seconds from now
        $now = new \DateTime();
        $expireTime = $state->getExpireTime();
        $diff = abs($expireTime->getTimestamp() - $now->getTimestamp() - $ttl);
        $this->assertLessThan(2, $diff);
    }
}