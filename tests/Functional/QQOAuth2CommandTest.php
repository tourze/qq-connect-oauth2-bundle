<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Tests\TestKernel;

/**
 * @group functional
 */
class QQOAuth2CommandTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testConfigCommandCreate(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:config');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'action' => 'create',
            '--app-id' => 'test_app_123',
            '--app-secret' => 'test_secret_456',
            '--scope' => 'get_user_info,get_simple_userinfo',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('QQ OAuth2 config created with ID:', $output);

        // Verify in database
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();
        $repo = $em->getRepository(QQOAuth2Config::class);
        $config = $repo->findByAppId('test_app_123');

        $this->assertNotNull($config);
        $this->assertEquals('test_app_123', $config->getAppId());
        $this->assertEquals('test_secret_456', $config->getAppSecret());
        $this->assertEquals('get_user_info,get_simple_userinfo', $config->getScope());
        $this->assertTrue($config->isValid());
    }

    public function testConfigCommandList(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create test configs
        $config1 = new QQOAuth2Config();
        $config1->setAppId('app1')
            ->setAppSecret('secret1');

        $config2 = new QQOAuth2Config();
        $config2->setAppId('app2')
            ->setAppSecret('secret2')
            ->setValid(false);

        $em->persist($config1);
        $em->persist($config2);
        $em->flush();

        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:config');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['action' => 'list']);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('app1', $output);
        $this->assertStringContainsString('app2', $output);
        $this->assertStringContainsString('Yes', $output); // Valid status
        $this->assertStringContainsString('No', $output);  // Invalid status
    }

    public function testCleanupCommand(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create expired and valid states
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')
            ->setAppSecret('test_secret');
        $em->persist($config);

        $validState = new QQOAuth2State('valid_state', $config, 600);
        $expiredState = new QQOAuth2State('expired_state', $config, -1); // Already expired

        $em->persist($validState);
        $em->persist($expiredState);
        $em->flush();

        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:cleanup');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Successfully cleaned up 1 expired states', $output);

        // Verify in database
        $repo = $em->getRepository(QQOAuth2State::class);
        $states = $repo->findAll();
        $this->assertCount(1, $states);
        $this->assertEquals('valid_state', $states[0]->getState());
    }

    public function testRefreshTokenCommand(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create config
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')
            ->setAppSecret('test_secret');
        $em->persist($config);

        // Create test user
        $user = new QQOAuth2User('test_openid', 'old_token', 3600, $config);
        $user->setRefreshToken('refresh_token_123');
        $user->setNickname('Test User');

        $em->persist($user);
        $em->flush();

        // Mock HTTP client
        $httpClient = $this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class);
        $response = $this->createMock(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getContent')
            ->willReturn('access_token=new_token&expires_in=7200&refresh_token=new_refresh_token');

        $httpClient->method('request')->willReturn($response);
        $container->set('http_client', $httpClient);

        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:refresh-token');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'openid' => 'test_openid',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Successfully refreshed token for user test_openid', $output);
    }

    public function testRefreshTokenDryRun(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create config
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')
            ->setAppSecret('test_secret');
        $em->persist($config);

        // Create test users
        $user1 = new QQOAuth2User('openid1', 'token1', -1, $config); // Expired
        $user1->setRefreshToken('refresh1');

        $user2 = new QQOAuth2User('openid2', 'token2', 7200, $config); // Not expired
        $user2->setRefreshToken('refresh2');

        $em->persist($user1);
        $em->persist($user2);
        $em->flush();

        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:refresh-token');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--all' => true,
            '--dry-run' => true,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Would refresh token for user openid1', $output);
        $this->assertStringContainsString('Would refresh 1 expired tokens', $output);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->setupDatabaseSchema();
    }

    private function setupDatabaseSchema(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);

        $classes = [
            $em->getClassMetadata(QQOAuth2Config::class),
            $em->getClassMetadata(QQOAuth2State::class),
            $em->getClassMetadata(QQOAuth2User::class),
        ];

        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
    }
}