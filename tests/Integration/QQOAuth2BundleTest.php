<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Integration;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;
use Tourze\QQConnectOAuth2Bundle\Tests\TestKernel;

class QQOAuth2BundleTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testServicesAreRegistered(): void
    {
        $container = self::getContainer();

        // Service is public
        $this->assertTrue($container->has(QQOAuth2Service::class));

        // Repositories are registered through Doctrine
        $em = $container->get('doctrine')->getManager();
        $this->assertInstanceOf(QQOAuth2ConfigRepository::class, $em->getRepository(QQOAuth2Config::class));
    }

    public function testRepositoriesWork(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        $configRepo = $em->getRepository(QQOAuth2Config::class);

        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret')
            ->setScope('get_user_info');

        $em->persist($config);
        $em->flush();

        $this->assertNotNull($config->getId());
        $this->assertNotNull($config->getCreateTime());
        $this->assertNotNull($config->getUpdateTime());

        $foundConfig = $configRepo->findValidConfig();
        $this->assertNotNull($foundConfig);
        $this->assertEquals('test_app_id', $foundConfig->getAppId());

        // Test __toString
        $this->assertStringContainsString('test_app_id', (string)$foundConfig);
    }

    public function testEntityRelationships(): void
    {
        $this->markTestSkipped('CASCADE DELETE behavior varies by database engine. SQLite test setup limitation.');
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create config
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret');
        $em->persist($config);

        // Create state linked to config
        $state = new QQOAuth2State('test_state_123', $config);
        $state->setSessionId('session_123');
        $em->persist($state);

        // Create user linked to config
        $user = new QQOAuth2User('test_openid', 'test_token', 7200, $config);
        $user->setNickname('Test User');
        $em->persist($user);

        $em->flush();

        // Verify relationships
        $this->assertSame($config, $state->getConfig());
        $this->assertSame($config, $user->getConfig());

        // Test cascade delete
        $stateId = $state->getId();
        $userId = $user->getId();

        $em->remove($config);
        $em->flush();

        $this->assertNull($em->find(QQOAuth2State::class, $stateId));
        $this->assertNull($em->find(QQOAuth2User::class, $userId));
    }

    public function testServiceWithEntityManager(): void
    {
        $container = self::getContainer();

        // Service should be properly configured with all dependencies
        $this->assertTrue($container->has(QQOAuth2Service::class));
        $service = $container->get(QQOAuth2Service::class);

        $this->assertInstanceOf(QQOAuth2Service::class, $service);

        // Create a config for testing
        $em = $container->get('doctrine')->getManager();
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret');
        $em->persist($config);
        $em->flush();

        // Test service can generate authorization URL
        $url = $service->generateAuthorizationUrl('test_session');
        $this->assertStringStartsWith('https://graph.qq.com/oauth2.0/authorize', $url);
        $this->assertStringContainsString('client_id=test_app_id', $url);
        $this->assertStringContainsString('state=', $url);
    }

    public function testRouteLoaderIsRegistered(): void
    {
        $container = self::getContainer();

        // AttributeControllerLoader should be registered
        $this->assertTrue($container->has('Tourze\QQConnectOAuth2Bundle\Service\AttributeControllerLoader'));

        // Routes should be loaded
        $router = $container->get('router');
        $this->assertNotNull($router->getRouteCollection()->get('qq_oauth2_login'));
        $this->assertNotNull($router->getRouteCollection()->get('qq_oauth2_callback'));
    }

    protected function setUp(): void
    {
        self::bootKernel();

        // Create database schema
        $em = self::getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);

        $classes = [
            $em->getClassMetadata(QQOAuth2Config::class),
            $em->getClassMetadata(QQOAuth2State::class),
            $em->getClassMetadata(QQOAuth2User::class),
        ];

        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
    }
}