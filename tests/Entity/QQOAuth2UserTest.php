<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;

/**
 * @internal
 */
#[CoversClass(QQOAuth2User::class)]
final class QQOAuth2UserTest extends AbstractEntityTestCase
{
    public function testDefaultValues(): void
    {
        $config = $this->createMockConfig();
        $user = $this->createMockUser('test_openid', 'test_token', 7200, $config);

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
        $this->assertNotNull($user->getTokenUpdateTime());
        $this->assertNull($user->getCreateTime());
        $this->assertNull($user->getUpdateTime());
    }

    private function createMockConfig(): QQOAuth2Config
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');
        $config->setAppSecret('test_secret');

        return $config;
    }

    private function createMockUser(string $openid, string $accessToken, int $expiresIn, QQOAuth2Config $config): QQOAuth2User
    {
        $user = new QQOAuth2User();
        $user->setOpenid($openid);
        $user->setAccessToken($accessToken);
        $user->setExpiresIn($expiresIn);
        $user->setConfig($config);

        return $user;
    }

    public function testSettersAndGetters(): void
    {
        $config = $this->createMockConfig();
        $user = $this->createMockUser('test_openid', 'test_token', 7200, $config);

        $user->setUnionid('test_unionid');
        $user->setNickname('Test User');
        $user->setAvatar('https://example.com/avatar.jpg');
        $user->setGender('male');
        $user->setProvince('Beijing');
        $user->setCity('Beijing');
        $user->setRefreshToken('refresh_token');
        $user->setUserReference('user_123');
        $user->setRawData(['key' => 'value']);

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
        $user = $this->createMockUser('test_openid', 'test_token', 7200, $config);

        $this->assertFalse($user->isTokenExpired());

        // Create a user with an expired token
        $expiredUser = $this->createMockUser('expired_openid', 'expired_token', 3600, $config);
        $expiredUser->setTokenUpdateTime(new \DateTimeImmutable('-2 hours'));
        $this->assertTrue($expiredUser->isTokenExpired());
    }

    public function testUpdateToken(): void
    {
        $config = $this->createMockConfig();
        $user = $this->createMockUser('test_openid', 'old_token', 3600, $config);
        $originalTokenUpdateTime = $user->getTokenUpdateTime();

        sleep(1);
        $user->setAccessToken('new_token');
        $user->setExpiresIn(7200);

        $this->assertEquals('new_token', $user->getAccessToken());
        $this->assertEquals(7200, $user->getExpiresIn());
        $this->assertNotEquals($originalTokenUpdateTime, $user->getTokenUpdateTime());
    }

    public function testTimestampableAware(): void
    {
        $config = $this->createMockConfig();
        $user = $this->createMockUser('test_openid', 'test_token', 7200, $config);

        // Test that timestamps start as null
        $this->assertNull($user->getCreateTime());
        $this->assertNull($user->getUpdateTime());

        // Test setting timestamps
        $now = new \DateTimeImmutable();
        $user->setCreateTime($now);
        $user->setUpdateTime($now);

        $this->assertEquals($now, $user->getCreateTime());
        $this->assertEquals($now, $user->getUpdateTime());

        // Test retrieve timestamp array
        $timestamps = $user->retrieveTimestampArray();
        $this->assertEquals($now->format('Y-m-d H:i:s'), $timestamps['createTime']);
        $this->assertEquals($now->format('Y-m-d H:i:s'), $timestamps['updateTime']);
    }

    protected function createEntity(): object
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');
        $config->setAppSecret('test_secret');

        return $this->createMockUser('test_openid', 'test_token', 7200, $config);
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'unionid' => ['unionid', 'test_unionid'];
        yield 'nickname' => ['nickname', 'Test User'];
        yield 'avatar' => ['avatar', 'https://example.com/avatar.jpg'];
        yield 'gender' => ['gender', 'male'];
        yield 'province' => ['province', 'Beijing'];
        yield 'city' => ['city', 'Beijing'];
        yield 'refreshToken' => ['refreshToken', 'refresh_token'];
        yield 'userReference' => ['userReference', 'user_123'];
        yield 'rawData' => ['rawData', ['key' => 'value']];
        yield 'accessToken' => ['accessToken', 'new_token'];
        yield 'expiresIn' => ['expiresIn', 3600];
    }
}
