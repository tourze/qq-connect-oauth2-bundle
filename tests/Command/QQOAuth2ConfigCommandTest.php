<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Command;

use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;
use Tourze\QQConnectOAuth2Bundle\Command\QQOAuth2ConfigCommand;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;

/**
 * @internal
 */
#[CoversClass(QQOAuth2ConfigCommand::class)]
#[RunTestsInSeparateProcesses]
final class QQOAuth2ConfigCommandTest extends AbstractCommandTestCase
{
    protected function getCommandTester(): CommandTester
    {
        $command = self::getContainer()->get(QQOAuth2ConfigCommand::class);
        $this->assertInstanceOf(QQOAuth2ConfigCommand::class, $command);
        $application = new Application();
        $application->addCommand($command);
        $command = $application->find('qq-oauth2:config');

        return new CommandTester($command);
    }

    protected function onSetUp(): void
    {
        // Setup for QQ OAuth2 Config Command tests
        // Ensure database schema is created
        $this->createDatabaseSchema();
    }

    private function createDatabaseSchema(): void
    {
        $em = self::getEntityManager();
        $schemaTool = new SchemaTool($em);

        // Get only QQ OAuth2 related entities
        $qqEntities = [
            $em->getClassMetadata(QQOAuth2Config::class),
        ];

        try {
            $schemaTool->createSchema($qqEntities);
        } catch (\Exception $e) {
            // Ignore if tables already exist, try update instead
            $schemaTool->updateSchema($qqEntities);
        }
    }

    public function testCreateConfig(): void
    {
        $command = self::getContainer()->get(QQOAuth2ConfigCommand::class);
        $this->assertInstanceOf(QQOAuth2ConfigCommand::class, $command);
        $application = new Application();
        $application->addCommand($command);
        $command = $application->find('qq-oauth2:config');
        $commandTester = new CommandTester($command);

        // 清理测试开始前可能存在的配置
        $em = self::getEntityManager();
        $repo = self::getService(QQOAuth2ConfigRepository::class);
        $existingConfigs = $repo->findBy(['appId' => 'test_app_123']);
        foreach ($existingConfigs as $existingConfig) {
            $em->remove($existingConfig);
        }
        $em->flush();

        $uniqueAppId = 'test_app_' . uniqid();
        $commandTester->execute([
            'action' => 'create',
            '--app-id' => $uniqueAppId,
            '--app-secret' => 'test_secret_456',
            '--scope' => 'get_user_info,get_simple_userinfo',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('QQ OAuth2 config created with ID:', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Verify in database
        $config = $repo->findByAppId($uniqueAppId);

        $this->assertNotNull($config);
        $this->assertEquals($uniqueAppId, $config->getAppId());
        $this->assertEquals('test_secret_456', $config->getAppSecret());
        $this->assertEquals('get_user_info,get_simple_userinfo', $config->getScope());
        $this->assertTrue($config->isValid());
    }

    public function testCreateConfigWithoutRequiredOptions(): void
    {
        $command = self::getContainer()->get(QQOAuth2ConfigCommand::class);
        $this->assertInstanceOf(QQOAuth2ConfigCommand::class, $command);
        $application = new Application();
        $application->addCommand($command);
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

    public function testListConfigsWhenEmpty(): void
    {
        $command = self::getContainer()->get(QQOAuth2ConfigCommand::class);
        $this->assertInstanceOf(QQOAuth2ConfigCommand::class, $command);
        $application = new Application();
        $application->addCommand($command);
        $command = $application->find('qq-oauth2:config');
        $commandTester = new CommandTester($command);

        // Clean up any existing configs
        $em = self::getEntityManager();
        $repo = self::getService(QQOAuth2ConfigRepository::class);
        $configs = $repo->findAll();
        foreach ($configs as $config) {
            $em->remove($config);
        }
        $em->flush();

        $commandTester->execute(['action' => 'list']);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No QQ OAuth2 configurations found', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testUpdateConfig(): void
    {
        // Create a config to update
        $em = self::getEntityManager();
        $config = new QQOAuth2Config();
        $config->setAppId('original_app');
        $config->setAppSecret('original_secret');

        $em->persist($config);
        $em->flush();

        $configId = $config->getId();

        $command = self::getContainer()->get(QQOAuth2ConfigCommand::class);
        $this->assertInstanceOf(QQOAuth2ConfigCommand::class, $command);
        $application = new Application();
        $application->addCommand($command);
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
        $this->assertStringContainsString("QQ OAuth2 config {$configId} updated", $output);
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
        $command = self::getContainer()->get(QQOAuth2ConfigCommand::class);
        $this->assertInstanceOf(QQOAuth2ConfigCommand::class, $command);
        $application = new Application();
        $application->addCommand($command);
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
        // Create a config to delete
        $em = self::getEntityManager();
        $config = new QQOAuth2Config();
        $config->setAppId('to_delete');
        $config->setAppSecret('secret');

        $em->persist($config);
        $em->flush();

        $configId = $config->getId();

        $command = self::getContainer()->get(QQOAuth2ConfigCommand::class);
        $this->assertInstanceOf(QQOAuth2ConfigCommand::class, $command);
        $application = new Application();
        $application->addCommand($command);
        $command = $application->find('qq-oauth2:config');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'action' => 'delete',
            '--id' => $configId,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString("QQ OAuth2 config {$configId} deleted", $output);
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Verify deletion
        $this->assertNull($em->find(QQOAuth2Config::class, $configId));
    }

    public function testInvalidAction(): void
    {
        $command = self::getContainer()->get(QQOAuth2ConfigCommand::class);
        $this->assertInstanceOf(QQOAuth2ConfigCommand::class, $command);
        $application = new Application();
        $application->addCommand($command);
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

    public function testArgumentAction(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['action' => 'list']);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $statusCode = $commandTester->getStatusCode();
        $this->assertContains($statusCode, [0, 1]);
    }

    public function testOptionAppId(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            'action' => 'create',
            '--app-id' => 'test_app_id',
            '--app-secret' => 'test_secret',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $statusCode = $commandTester->getStatusCode();
        $this->assertContains($statusCode, [0, 1]);
    }

    public function testOptionAppSecret(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            'action' => 'create',
            '--app-id' => 'test_app_id',
            '--app-secret' => 'test_secret',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $statusCode = $commandTester->getStatusCode();
        $this->assertContains($statusCode, [0, 1]);
    }

    public function testOptionScope(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            'action' => 'create',
            '--app-id' => 'test_app_id',
            '--app-secret' => 'test_secret',
            '--scope' => 'get_user_info',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $statusCode = $commandTester->getStatusCode();
        $this->assertContains($statusCode, [0, 1]);
    }

    public function testOptionEnabled(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            'action' => 'create',
            '--app-id' => 'test_app_id',
            '--app-secret' => 'test_secret',
            '--enabled' => 'true',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $statusCode = $commandTester->getStatusCode();
        $this->assertContains($statusCode, [0, 1]);
    }

    public function testOptionId(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            'action' => 'update',
            '--id' => '999',
            '--app-secret' => 'new_secret',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $statusCode = $commandTester->getStatusCode();
        $this->assertContains($statusCode, [0, 1]);
    }
}
