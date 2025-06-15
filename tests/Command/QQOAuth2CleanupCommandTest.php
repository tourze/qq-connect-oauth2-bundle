<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Command;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Tests\TestKernel;

class QQOAuth2CleanupCommandTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testCleanupExpiredStates(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create config
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')
            ->setAppSecret('test_secret');
        $em->persist($config);

        // Create expired and valid states
        $validState = new QQOAuth2State('valid_state', $config, 600);
        $expiredState1 = new QQOAuth2State('expired_state1', $config, -1); // Already expired
        $expiredState2 = new QQOAuth2State('expired_state2', $config, -100); // Already expired

        $em->persist($validState);
        $em->persist($expiredState1);
        $em->persist($expiredState2);
        $em->flush();

        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:cleanup');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Successfully cleaned up 2 expired states', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Verify in database
        $repo = $em->getRepository(QQOAuth2State::class);
        $states = $repo->findAll();
        $this->assertCount(1, $states);
        $this->assertEquals('valid_state', $states[0]->getState());
    }

    public function testCleanupWhenNoExpiredStates(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create config
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')
            ->setAppSecret('test_secret');
        $em->persist($config);

        // Create only valid states
        $validState1 = new QQOAuth2State('valid_state1', $config, 600);
        $validState2 = new QQOAuth2State('valid_state2', $config, 3600);

        $em->persist($validState1);
        $em->persist($validState2);
        $em->flush();

        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:cleanup');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Successfully cleaned up 0 expired states', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testCleanupUsedStates(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create config
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')
            ->setAppSecret('test_secret');
        $em->persist($config);

        // Create used but not expired state
        $usedState = new QQOAuth2State('used_state', $config, 600);
        $usedState->markAsUsed();

        // Create expired state
        $expiredState = new QQOAuth2State('expired_state', $config, -1);

        $em->persist($usedState);
        $em->persist($expiredState);
        $em->flush();

        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:cleanup');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Successfully cleaned up 1 expired states', $output);

        // Verify used but not expired state remains
        $repo = $em->getRepository(QQOAuth2State::class);
        $states = $repo->findAll();
        $this->assertCount(1, $states);
        $this->assertEquals('used_state', $states[0]->getState());
        $this->assertTrue($states[0]->isUsed());
    }

    public function testCleanupWithSessionId(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create config
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')
            ->setAppSecret('test_secret');
        $em->persist($config);

        // Create expired states with different session IDs
        $expiredState1 = new QQOAuth2State('expired_state1', $config, -1);
        $expiredState1->setSessionId('session_123');

        $expiredState2 = new QQOAuth2State('expired_state2', $config, -1);
        $expiredState2->setSessionId('session_456');

        $validState = new QQOAuth2State('valid_state', $config, 600);
        $validState->setSessionId('session_789');

        $em->persist($expiredState1);
        $em->persist($expiredState2);
        $em->persist($validState);
        $em->flush();

        $application = new Application(self::$kernel);
        $command = $application->find('qq-oauth2:cleanup');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Successfully cleaned up 2 expired states', $output);

        // Verify only valid state remains
        $repo = $em->getRepository(QQOAuth2State::class);
        $states = $repo->findAll();
        $this->assertCount(1, $states);
        $this->assertEquals('session_789', $states[0]->getSessionId());
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
        ];

        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
    }
}