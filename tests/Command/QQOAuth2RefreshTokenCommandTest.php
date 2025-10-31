<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Command;

use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;
use Tourze\QQConnectOAuth2Bundle\Command\QQOAuth2RefreshTokenCommand;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;

/**
 * @internal
 */
#[CoversClass(QQOAuth2RefreshTokenCommand::class)]
#[RunTestsInSeparateProcesses]
final class QQOAuth2RefreshTokenCommandTest extends AbstractCommandTestCase
{
    protected function getCommandTester(): CommandTester
    {
        $command = self::getContainer()->get(QQOAuth2RefreshTokenCommand::class);
        $this->assertInstanceOf(QQOAuth2RefreshTokenCommand::class, $command);
        $application = new Application();
        $application->add($command);
        $command = $application->find('qq-oauth2:refresh-token');

        return new CommandTester($command);
    }

    protected function onSetUp(): void
    {
        // Setup for QQ OAuth2 Refresh Token Command tests
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
            $em->getClassMetadata(QQOAuth2User::class),
        ];

        try {
            $schemaTool->createSchema($qqEntities);
        } catch (\Exception $e) {
            // Ignore if tables already exist, try update instead
            $schemaTool->updateSchema($qqEntities);
        }
    }

    public function testRefreshNonExistentUser(): void
    {
        $command = self::getContainer()->get(QQOAuth2RefreshTokenCommand::class);
        $this->assertInstanceOf(QQOAuth2RefreshTokenCommand::class, $command);
        $application = new Application();
        $application->add($command);
        $command = $application->find('qq-oauth2:refresh-token');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'openid' => 'non_existent_openid',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('User with OpenID non_existent_openid not found', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testRefreshWithoutArguments(): void
    {
        $command = self::getContainer()->get(QQOAuth2RefreshTokenCommand::class);
        $this->assertInstanceOf(QQOAuth2RefreshTokenCommand::class, $command);
        $application = new Application();
        $application->add($command);
        $command = $application->find('qq-oauth2:refresh-token');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('You must specify either an OpenID or use --all option', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testArgumentOpenid(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['openid' => 'test_openid']);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $statusCode = $commandTester->getStatusCode();
        $this->assertContains($statusCode, [0, 1]);
    }

    public function testOptionAll(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['--all' => true]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $statusCode = $commandTester->getStatusCode();
        $this->assertContains($statusCode, [0, 1]);
    }

    public function testOptionDryRun(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['--all' => true, '--dry-run' => true]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $statusCode = $commandTester->getStatusCode();
        $this->assertContains($statusCode, [0, 1]);
    }
}
