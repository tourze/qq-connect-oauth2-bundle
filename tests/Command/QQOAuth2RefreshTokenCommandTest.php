<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Command;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Tests\TestKernel;

class QQOAuth2RefreshTokenCommandTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testRefreshSingleToken(): void
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
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Verify token was updated
        $em->refresh($user);
        $this->assertEquals('new_token', $user->getAccessToken());
        $this->assertEquals(7200, $user->getExpiresIn());
        $this->assertEquals('new_refresh_token', $user->getRefreshToken());
    }

    public function testRefreshNonExistentUser(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:refresh-token');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'openid' => 'non_existent_openid',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('User with OpenID non_existent_openid not found', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testRefreshUserWithoutRefreshToken(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create config
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')
            ->setAppSecret('test_secret');
        $em->persist($config);

        // Create user without refresh token
        $user = new QQOAuth2User('test_openid', 'token', 3600, $config);
        // No refresh token set

        $em->persist($user);
        $em->flush();

        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:refresh-token');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'openid' => 'test_openid',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('User test_openid does not have a refresh token', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testRefreshAllExpiredTokens(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create config
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')
            ->setAppSecret('test_secret');
        $em->persist($config);

        // Create users with different token states
        $expiredUser1 = new QQOAuth2User('openid1', 'token1', -1, $config); // Expired
        $expiredUser1->setRefreshToken('refresh1');

        $expiredUser2 = new QQOAuth2User('openid2', 'token2', -1, $config); // Expired
        $expiredUser2->setRefreshToken('refresh2');

        $validUser = new QQOAuth2User('openid3', 'token3', 7200, $config); // Not expired
        $validUser->setRefreshToken('refresh3');

        $expiredNoRefresh = new QQOAuth2User('openid4', 'token4', -1, $config); // Expired but no refresh token

        $em->persist($expiredUser1);
        $em->persist($expiredUser2);
        $em->persist($validUser);
        $em->persist($expiredNoRefresh);
        $em->flush();

        // Mock HTTP client to return success
        $httpClient = $this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class);
        $response = $this->createMock(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getContent')
            ->willReturn('access_token=new_token&expires_in=7200');

        $httpClient->method('request')->willReturn($response);
        $container->set('http_client', $httpClient);

        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:refresh-token');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--all' => true,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Refreshed token for user openid1', $output);
        $this->assertStringContainsString('Refreshed token for user openid2', $output);
        $this->assertStringContainsString('Refreshed 2 tokens successfully, 0 failed out of 2 expired tokens', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testRefreshAllDryRun(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create config
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')
            ->setAppSecret('test_secret');
        $em->persist($config);

        // Create expired users
        $user1 = new QQOAuth2User('openid1', 'token1', -1, $config);
        $user1->setRefreshToken('refresh1');

        $user2 = new QQOAuth2User('openid2', 'token2', -1, $config);
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
        $this->assertStringContainsString('Would refresh token for user openid2', $output);
        $this->assertStringContainsString('Would refresh 2 expired tokens', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Verify tokens were not actually changed
        $em->refresh($user1);
        $this->assertEquals('token1', $user1->getAccessToken());
    }

    public function testRefreshWithoutArguments(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:refresh-token');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('You must specify either an OpenID or use --all option', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testRefreshFailedToken(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create config
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')
            ->setAppSecret('test_secret');
        $em->persist($config);

        // Create user
        $user = new QQOAuth2User('test_openid', 'old_token', 3600, $config);
        $user->setRefreshToken('invalid_refresh_token');

        $em->persist($user);
        $em->flush();

        // Mock HTTP client to return error
        $httpClient = $this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class);
        $response = $this->createMock(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getContent')
            ->willReturn('error=invalid_grant&error_description=Invalid refresh token');

        $httpClient->method('request')->willReturn($response);
        $container->set('http_client', $httpClient);

        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:refresh-token');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'openid' => 'test_openid',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Failed to refresh token for user test_openid', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    protected function setUp(): void
    {
        self::bootKernel();

        // Create database schema
        $em = self::getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);

        $classes = [
            $em->getClassMetadata(QQOAuth2Config::class),
            $em->getClassMetadata(QQOAuth2User::class),
        ];

        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
    }
}