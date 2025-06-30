<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Repository;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2StateRepository;
use Tourze\QQConnectOAuth2Bundle\Tests\TestKernel;

class QQOAuth2StateRepositoryTest extends KernelTestCase
{
    private QQOAuth2StateRepository $repository;
    private QQOAuth2Config $config;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testFindValidState(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // 创建有效状态
        $validState = new QQOAuth2State('valid_state_123', $this->config, 600);

        // 创建过期状态
        $expiredState = new QQOAuth2State('expired_state_456', $this->config, -1);

        $em->persist($validState);
        $em->persist($expiredState);
        $em->flush();

        $result = $this->repository->findValidState('valid_state_123');

        $this->assertNotNull($result);
        $this->assertEquals('valid_state_123', $result->getState());
        $this->assertTrue($result->isValid());
    }

    public function testFindValidStateExpired(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        $expiredState = new QQOAuth2State('expired_state', $this->config, -1);
        $em->persist($expiredState);
        $em->flush();

        $result = $this->repository->findValidState('expired_state');

        $this->assertNull($result);
    }

    public function testFindValidStateUsed(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        $usedState = new QQOAuth2State('used_state', $this->config, 600);
        $usedState->markAsUsed();
        $em->persist($usedState);
        $em->flush();

        $result = $this->repository->findValidState('used_state');

        $this->assertNull($result);
    }

    public function testFindValidStateNotFound(): void
    {
        $result = $this->repository->findValidState('non_existent_state');
        $this->assertNull($result);
    }

    public function testFindExpiredStatesUsingValidStateMethod(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // 创建有效状态
        $validState = new QQOAuth2State('valid_state', $this->config, 600);

        // 创建过期状态
        $expiredState1 = new QQOAuth2State('expired_state_1', $this->config, -1);
        $expiredState2 = new QQOAuth2State('expired_state_2', $this->config, -100);

        $em->persist($validState);
        $em->persist($expiredState1);
        $em->persist($expiredState2);
        $em->flush();

        // 测试有效状态可以找到
        $foundValidState = $this->repository->findValidState('valid_state');
        $this->assertNotNull($foundValidState);

        // 测试过期状态找不到
        $foundExpiredState1 = $this->repository->findValidState('expired_state_1');
        $this->assertNull($foundExpiredState1);

        $foundExpiredState2 = $this->repository->findValidState('expired_state_2');
        $this->assertNull($foundExpiredState2);
    }

    public function testCleanupExpiredStates(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // 创建有效状态
        $validState = new QQOAuth2State('valid_state', $this->config, 600);

        // 创建过期状态
        $expiredState1 = new QQOAuth2State('expired_state_1', $this->config, -1);
        $expiredState2 = new QQOAuth2State('expired_state_2', $this->config, -100);

        $em->persist($validState);
        $em->persist($expiredState1);
        $em->persist($expiredState2);
        $em->flush();

        $deletedCount = $this->repository->cleanupExpiredStates();

        $this->assertEquals(2, $deletedCount);

        // 验证只剩下有效状态
        $remainingStates = $this->repository->findAll();
        $this->assertCount(1, $remainingStates);
        $this->assertEquals('valid_state', $remainingStates[0]->getState());
    }

    public function testFindBySessionId(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        $state1 = new QQOAuth2State('state_1', $this->config, 600);
        $state1->setSessionId('session_123');

        $state2 = new QQOAuth2State('state_2', $this->config, 600);
        $state2->setSessionId('session_456');

        $state3 = new QQOAuth2State('state_3', $this->config, 600);
        // state3 没有设置 sessionId

        $em->persist($state1);
        $em->persist($state2);
        $em->persist($state3);
        $em->flush();

        $result = $this->repository->findBySessionId('session_123');

        $this->assertCount(1, $result);
        $this->assertEquals('state_1', $result[0]->getState());
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->repository = $container->get(QQOAuth2StateRepository::class);
        $em = $container->get('doctrine')->getManager();

        // 创建数据库schema
        $schemaTool = new SchemaTool($em);
        $classes = [
            $em->getClassMetadata(QQOAuth2Config::class),
            $em->getClassMetadata(QQOAuth2State::class),
        ];
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);

        // 创建测试用配置
        $this->config = new QQOAuth2Config();
        $this->config->setAppId('test_app')->setAppSecret('test_secret');
        $em->persist($this->config);
        $em->flush();
    }
} 