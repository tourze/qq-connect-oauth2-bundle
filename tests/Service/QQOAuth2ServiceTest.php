<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ConfigurationException;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

/**
 * @internal
 */
#[CoversClass(QQOAuth2Service::class)]
#[RunTestsInSeparateProcesses]
final class QQOAuth2ServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 清理数据库，确保测试隔离
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 清理所有QQ OAuth2配置
        $em->createQuery('DELETE FROM ' . QQOAuth2Config::class)->execute();
        $em->createQuery('DELETE FROM ' . QQOAuth2User::class)->execute();
        $em->createQuery('DELETE FROM ' . QQOAuth2State::class)->execute();

        // 清理Repository缓存的逻辑已移除，因为不应该从EntityManager直接获取Repository
    }

    public function testGenerateAuthorizationUrlWithoutConfig(): void
    {
        $container = self::getContainer();
        $service = $container->get(QQOAuth2Service::class);
        $this->assertInstanceOf(QQOAuth2Service::class, $service);

        $this->expectException(QQOAuth2ConfigurationException::class);
        $this->expectExceptionMessage('No valid QQ OAuth2 configuration found');

        $service->generateAuthorizationUrl('test_session');
    }

    public function testBulkUpdateTokens(): void
    {
        $container = self::getContainer();
        $service = $container->get(QQOAuth2Service::class);
        $this->assertInstanceOf(QQOAuth2Service::class, $service);

        $userData = [
            [
                'openid' => 'test_openid_1',
                'access_token' => 'new_token_1',
                'expires_in' => 7200,
                'refresh_token' => 'refresh_token_1',
            ],
            [
                'openid' => 'test_openid_2',
                'access_token' => 'new_token_2',
                'expires_in' => 3600,
            ],
        ];

        $count = $service->bulkUpdateTokens($userData);
        $this->assertEquals(0, $count);
    }

    public function testCleanupExpiredStates(): void
    {
        $container = self::getContainer();
        $service = $container->get(QQOAuth2Service::class);
        $this->assertInstanceOf(QQOAuth2Service::class, $service);

        $count = $service->cleanupExpiredStates();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testHandleCallback(): void
    {
        $container = self::getContainer();
        $service = $container->get(QQOAuth2Service::class);
        $this->assertInstanceOf(QQOAuth2Service::class, $service);

        $this->expectException(\Exception::class);
        $service->handleCallback('invalid_code', 'invalid_state');
    }

    public function testRefreshExpiredTokens(): void
    {
        $container = self::getContainer();
        $service = $container->get(QQOAuth2Service::class);
        $this->assertInstanceOf(QQOAuth2Service::class, $service);

        $count = $service->refreshExpiredTokens();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testRefreshToken(): void
    {
        $container = self::getContainer();
        $service = $container->get(QQOAuth2Service::class);
        $this->assertInstanceOf(QQOAuth2Service::class, $service);

        $result = $service->refreshToken('non_existent_openid');
        $this->assertFalse($result);
    }

    public function testUpdateOrCreateUser(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);
        $service = $container->get(QQOAuth2Service::class);
        $this->assertInstanceOf(QQOAuth2Service::class, $service);

        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');
        $config->setAppSecret('test_secret');

        // 先持久化配置
        $em->persist($config);
        $em->flush();

        $userData = [
            'openid' => 'test_openid',
            'access_token' => 'test_token',
            'expires_in' => 7200,
            'nickname' => 'Test User',
        ];

        $user = $service->updateOrCreateUser($userData, $config);
        $this->assertInstanceOf(QQOAuth2User::class, $user);
        $this->assertEquals('test_openid', $user->getOpenid());
        $this->assertEquals('test_token', $user->getAccessToken());
    }
}
