<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Command;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Tests\TestKernel;

class QQOAuth2ConfigCommandTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testCreateConfig(): void
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
        $this->assertEquals(0, $commandTester->getStatusCode());

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

    public function testCreateConfigWithoutRequiredOptions(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:config');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'action' => 'create',
            '--app-id' => 'test_app',
            // Missing app-secret
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Required options: --app-id, --app-secret', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testListConfigs(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create test configs
        $config1 = new QQOAuth2Config();
        $config1->setAppId('app1')
            ->setAppSecret('secret1')
            ->setScope('get_user_info');

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
        $this->assertStringContainsString('get_user_info', $output);
        $this->assertStringContainsString('Yes', $output); // Valid status
        $this->assertStringContainsString('No', $output);  // Invalid status
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testListConfigsWhenEmpty(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:config');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['action' => 'list']);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No QQ OAuth2 configurations found', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testUpdateConfig(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create a config to update
        $config = new QQOAuth2Config();
        $config->setAppId('original_app')
            ->setAppSecret('original_secret');

        $em->persist($config);
        $em->flush();

        $configId = $config->getId();

        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:config');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'action' => 'update',
            '--id' => $configId,
            '--app-secret' => 'new_secret',
            '--scope' => 'new_scope',
            '--enabled' => 'false',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString("QQ OAuth2 config $configId updated", $output);
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Verify changes
        $em->refresh($config);
        $this->assertEquals('original_app', $config->getAppId()); // Unchanged
        $this->assertEquals('new_secret', $config->getAppSecret());
        $this->assertEquals('new_scope', $config->getScope());
        $this->assertFalse($config->isValid());
    }

    public function testUpdateNonExistentConfig(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:config');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'action' => 'update',
            '--id' => 999999,
            '--app-secret' => 'new_secret',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Config with ID 999999 not found', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testDeleteConfig(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create a config to delete
        $config = new QQOAuth2Config();
        $config->setAppId('to_delete')
            ->setAppSecret('secret');

        $em->persist($config);
        $em->flush();

        $configId = $config->getId();

        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:config');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'action' => 'delete',
            '--id' => $configId,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString("QQ OAuth2 config $configId deleted", $output);
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Verify deletion
        $this->assertNull($em->find(QQOAuth2Config::class, $configId));
    }

    public function testInvalidAction(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:config');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'action' => 'invalid',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Unknown action: invalid', $output);
        $this->assertStringContainsString('Valid actions are: create, update, delete,', $output);
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
        ];

        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
    }
}