<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;

/**
 * @internal
 */
#[CoversClass(QQOAuth2State::class)]
final class QQOAuth2StateTest extends AbstractEntityTestCase
{
    public function testDefaultValues(): void
    {
        $config = $this->createMockConfig();
        $state = new QQOAuth2State();
        $state->setState('test_state_123');
        $state->setConfig($config);

        $this->assertNull($state->getId());
        $this->assertEquals('test_state_123', $state->getState());
        $this->assertNull($state->getSessionId());
        $this->assertNull($state->getMetadata());
        $this->assertFalse($state->isUsed());
        $this->assertSame($config, $state->getConfig());
        $this->assertNull($state->getCreateTime());
        $this->assertNotNull($state->getExpireTime());
    }

    private function createMockConfig(): QQOAuth2Config
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');
        $config->setAppSecret('test_secret');

        return $config;
    }

    public function testExpirationLogic(): void
    {
        $config = $this->createMockConfig();
        $state = new QQOAuth2State();
        $state->setState('test_state');
        $state->setConfig($config);
        $state->setExpireTimeFromTtl(600);

        $this->assertFalse($state->isExpired());
        $this->assertTrue($state->isValid());

        $expiredState = new QQOAuth2State();
        $expiredState->setState('expired_state');
        $expiredState->setConfig($config);
        $expiredState->setExpireTimeFromTtl(600);
        $expiredState->setExpireTime(new \DateTimeImmutable('-1 hour'));
        $this->assertTrue($expiredState->isExpired());
        $this->assertFalse($expiredState->isValid());
    }

    public function testMarkAsUsed(): void
    {
        $config = $this->createMockConfig();
        $state = new QQOAuth2State();
        $state->setState('test_state');
        $state->setConfig($config);
        $state->setExpireTimeFromTtl(600);

        $this->assertFalse($state->isUsed());
        $this->assertTrue($state->isValid());

        $state->markAsUsed();

        $this->assertTrue($state->isUsed());
        $this->assertFalse($state->isValid());
    }

    public function testSettersAndGetters(): void
    {
        $config = $this->createMockConfig();
        $state = new QQOAuth2State();
        $state->setState('test_state');
        $state->setConfig($config);
        $state->setExpireTimeFromTtl(600);

        $state->setSessionId('session_123');
        $state->setMetadata(['key' => 'value']);

        $this->assertEquals('session_123', $state->getSessionId());
        $this->assertEquals(['key' => 'value'], $state->getMetadata());
    }

    public function testCustomTtl(): void
    {
        $config = $this->createMockConfig();
        $ttl = 300; // 5 minutes
        $state = new QQOAuth2State();
        $state->setState('test_state');
        $state->setConfig($config);
        $state->setExpireTimeFromTtl($ttl);

        // Expire time should be ttl seconds from now
        $now = new \DateTime();
        $expireTime = $state->getExpireTime();
        $diff = abs($expireTime->getTimestamp() - $now->getTimestamp() - $ttl);
        $this->assertLessThan(2, $diff);
    }

    protected function createEntity(): object
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');
        $config->setAppSecret('test_secret');

        $state = new QQOAuth2State();
        $state->setState('test_state');
        $state->setConfig($config);

        return $state;
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'sessionId' => ['sessionId', 'test_session_id'];
        yield 'metadata' => ['metadata', ['key' => 'value']];
    }
}
