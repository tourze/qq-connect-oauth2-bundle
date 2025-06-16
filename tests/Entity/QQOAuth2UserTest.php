<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;

class QQOAuth2UserTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = $this->createMockConfig();
        $user = new QQOAuth2User('test_openid', 'test_token', 7200, $config);

        $this->assertNull($user->getId());
        $this->assertEquals('test_openid', $user->getOpenid());
        $this->assertEquals('test_token', $user->getAccessToken());
        $this->assertEquals(7200, $user->getExpiresIn());
        $this->assertNull($user->getUnionid());
        $this->assertNull($user->getNickname());
        $this->assertNull($user->getAvatar());
        $this->assertNull($user->getGender());
        $this->assertNull($user->getProvince());
        $this->assertNull($user->getCity());
        $this->assertNull($user->getRefreshToken());
        $this->assertNull($user->getUserReference());
        $this->assertNull($user->getRawData());
        $this->assertSame($config, $user->getConfig());
        $this->assertInstanceOf(\DateTime::class, $user->getTokenUpdateTime());
        $this->assertNull($user->getCreateTime());
        $this->assertNull($user->getUpdateTime());
    }

    private function createMockConfig(): QQOAuth2Config
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret');
        return $config;
    }

    public function testSettersAndGetters(): void
    {
        $config = $this->createMockConfig();
        $user = new QQOAuth2User('test_openid', 'test_token', 7200, $config);
        
        $user->setUnionid('test_unionid')
            ->setNickname('Test User')
            ->setAvatar('https://example.com/avatar.jpg')
            ->setGender('male')
            ->setProvince('Beijing')
            ->setCity('Beijing')
            ->setRefreshToken('refresh_token')
            ->setUserReference('user_123')
            ->setRawData(['key' => 'value']);
        
        $this->assertEquals('test_unionid', $user->getUnionid());
        $this->assertEquals('Test User', $user->getNickname());
        $this->assertEquals('https://example.com/avatar.jpg', $user->getAvatar());
        $this->assertEquals('male', $user->getGender());
        $this->assertEquals('Beijing', $user->getProvince());
        $this->assertEquals('Beijing', $user->getCity());
        $this->assertEquals('refresh_token', $user->getRefreshToken());
        $this->assertEquals('user_123', $user->getUserReference());
        $this->assertEquals(['key' => 'value'], $user->getRawData());
    }

    public function testTokenExpiration(): void
    {
        $config = $this->createMockConfig();
        $user = new QQOAuth2User('test_openid', 'test_token', 7200, $config);
        
        $this->assertFalse($user->isTokenExpired());
        
        // Create a user with an expired token
        $expiredUser = new QQOAuth2User('expired_openid', 'expired_token', -1, $config);
        $this->assertTrue($expiredUser->isTokenExpired());
    }

    public function testUpdateToken(): void
    {
        $config = $this->createMockConfig();
        $user = new QQOAuth2User('test_openid', 'old_token', 3600, $config);
        $originalTokenUpdateTime = $user->getTokenUpdateTime();
        
        sleep(1);
        $user->setAccessToken('new_token')->setExpiresIn(7200);
        
        $this->assertEquals('new_token', $user->getAccessToken());
        $this->assertEquals(7200, $user->getExpiresIn());
        $this->assertNotEquals($originalTokenUpdateTime, $user->getTokenUpdateTime());
    }

    public function testTimestampableAware(): void
    {
        $config = $this->createMockConfig();
        $user = new QQOAuth2User('test_openid', 'test_token', 7200, $config);
        
        // Test that timestamps start as null
        $this->assertNull($user->getCreateTime());
        $this->assertNull($user->getUpdateTime());
        
        // Test setting timestamps
        $now = new \DateTimeImmutable();
        $user->setCreateTime($now);
        $user->setUpdateTime($now);
        
        $this->assertSame($now, $user->getCreateTime());
        $this->assertSame($now, $user->getUpdateTime());
        
        // Test retrieve timestamp array
        $timestamps = $user->retrieveTimestampArray();
        $this->assertEquals($now->format('Y-m-d H:i:s'), $timestamps['createTime']);
        $this->assertEquals($now->format('Y-m-d H:i:s'), $timestamps['updateTime']);
    }
}
