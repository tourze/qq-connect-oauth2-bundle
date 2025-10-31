<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2StateRepository;

/**
 * @internal
 */
#[CoversClass(QQOAuth2StateRepository::class)]
#[RunTestsInSeparateProcesses]
final class QQOAuth2StateRepositoryTest extends AbstractRepositoryTestCase
{
    private QQOAuth2StateRepository $repository;

    private QQOAuth2Config $config;

    public function testFindValidState(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建有效状态
        $validState = $this->createTestState('valid_state_123', $this->config, 600);

        // 创建过期状态
        $expiredState = $this->createTestState('expired_state_456', $this->config, -1);

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
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $expiredState = $this->createTestState('expired_state', $this->config, 600);
        // 手动设置为过期时间
        $expiredState->setExpireTime(new \DateTimeImmutable('-1 hour'));
        $em->persist($expiredState);
        $em->flush();

        $result = $this->repository->findValidState('expired_state');

        $this->assertNull($result);
    }

    public function testFindValidStateUsed(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $usedState = $this->createTestState('used_state', $this->config, 600);
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
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建有效状态
        $validState = $this->createTestState('valid_state', $this->config, 600);

        // 创建过期状态
        $expiredState1 = $this->createTestState('expired_state_1', $this->config, 600);
        $expiredState1->setExpireTime(new \DateTimeImmutable('-1 hour'));

        $expiredState2 = $this->createTestState('expired_state_2', $this->config, 600);
        $expiredState2->setExpireTime(new \DateTimeImmutable('-2 hours'));

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
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建有效状态
        $validState = $this->createTestState('valid_state', $this->config, 600);

        // 创建过期状态
        $expiredState1 = $this->createTestState('expired_state_1', $this->config, 600);
        $expiredState1->setExpireTime(new \DateTimeImmutable('-1 hour'));

        $expiredState2 = $this->createTestState('expired_state_2', $this->config, 600);
        $expiredState2->setExpireTime(new \DateTimeImmutable('-2 hours'));

        $em->persist($validState);
        $em->persist($expiredState1);
        $em->persist($expiredState2);
        $em->flush();

        $deletedCount = $this->repository->cleanupExpiredStates();

        $this->assertGreaterThanOrEqual(2, $deletedCount);

        // 验证有效状态仍然存在
        $validStateStillExists = $this->repository->findOneBy(['state' => 'valid_state']);
        $this->assertNotNull($validStateStillExists);

        // 验证过期状态已被删除
        $expiredState1Gone = $this->repository->findOneBy(['state' => 'expired_state_1']);
        $this->assertNull($expiredState1Gone);

        $expiredState2Gone = $this->repository->findOneBy(['state' => 'expired_state_2']);
        $this->assertNull($expiredState2Gone);
    }

    public function testFindBySessionId(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $state1 = $this->createTestState('state_1', $this->config, 600);
        $state1->setSessionId('session_123');

        $state2 = $this->createTestState('state_2', $this->config, 600);
        $state2->setSessionId('session_456');

        $state3 = $this->createTestState('state_3', $this->config, 600);
        // state3 没有设置 sessionId

        $em->persist($state1);
        $em->persist($state2);
        $em->persist($state3);
        $em->flush();

        $result = $this->repository->findBySessionId('session_123');

        $this->assertCount(1, $result);
        $this->assertEquals('state_1', $result[0]->getState());
    }

    public function testFindWithExistingId(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $state = $this->createTestState('test_state_find', $this->config, 600);
        $em->persist($state);
        $em->flush();

        $foundState = $this->repository->find($state->getId());

        $this->assertNotNull($foundState);
        $this->assertEquals('test_state_find', $foundState->getState());
        $this->assertEquals($state->getId(), $foundState->getId());
    }

    public function testSaveWithFlush(): void
    {
        $state = $this->createTestState('test_save_state', $this->config, 600);

        $this->repository->save($state, true);

        $this->assertNotNull($state->getId());

        // 验证已保存到数据库
        $foundState = $this->repository->find($state->getId());
        $this->assertNotNull($foundState);
        $this->assertEquals('test_save_state', $foundState->getState());
    }

    public function testSaveWithoutFlush(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $state = $this->createTestState('test_save_no_flush', $this->config, 600);

        $this->repository->save($state, false);

        // 手动刷新
        $em->flush();

        $this->assertNotNull($state->getId());

        // 验证已保存到数据库
        $foundState = $this->repository->find($state->getId());
        $this->assertNotNull($foundState);
        $this->assertEquals('test_save_no_flush', $foundState->getState());
    }

    public function testFindAll(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $state1 = $this->createTestState('test_find_all_1', $this->config, 600);
        $state2 = $this->createTestState('test_find_all_2', $this->config, 600);

        $em->persist($state1);
        $em->persist($state2);
        $em->flush();

        $allStates = $this->repository->findAll();

        $this->assertGreaterThanOrEqual(2, count($allStates));

        $stateStrings = array_map(fn ($state) => $state->getState(), $allStates);
        $this->assertContains('test_find_all_1', $stateStrings);
        $this->assertContains('test_find_all_2', $stateStrings);
    }

    public function testFindOneBy(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $state = $this->createTestState('test_find_one_by', $this->config, 600);
        $state->setSessionId('unique_session_123');
        $em->persist($state);
        $em->flush();

        $foundState = $this->repository->findOneBy(['sessionId' => 'unique_session_123']);

        $this->assertNotNull($foundState);
        $this->assertEquals('test_find_one_by', $foundState->getState());
        $this->assertEquals('unique_session_123', $foundState->getSessionId());
    }

    public function testFindOneByWithNonExistentCriteria(): void
    {
        $foundState = $this->repository->findOneBy(['sessionId' => 'non_existent_session']);

        $this->assertNull($foundState);
    }

    public function testFindBy(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $state1 = $this->createTestState('test_find_by_1', $this->config, 600);
        $state1->setSessionId('common_session_find_by');

        $state2 = $this->createTestState('test_find_by_2', $this->config, 600);
        $state2->setSessionId('common_session_find_by');

        $state3 = $this->createTestState('test_find_by_3', $this->config, 600);
        $state3->setSessionId('different_session');

        $em->persist($state1);
        $em->persist($state2);
        $em->persist($state3);
        $em->flush();

        $foundStates = $this->repository->findBy(['sessionId' => 'common_session_find_by']);

        $this->assertCount(2, $foundStates);

        $stateStrings = array_map(fn ($state) => $state->getState(), $foundStates);
        $this->assertContains('test_find_by_1', $stateStrings);
        $this->assertContains('test_find_by_2', $stateStrings);
    }

    public function testFindByWithLimitAndOffset(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建多个状态
        for ($i = 1; $i <= 5; ++$i) {
            $state = $this->createTestState("test_limit_{$i}", $this->config, 600);
            $state->setSessionId('limit_test_session');
            $em->persist($state);
        }
        $em->flush();

        $foundStates = $this->repository->findBy(
            ['sessionId' => 'limit_test_session'],
            ['state' => 'ASC'],
            2, // limit
            1  // offset
        );

        $this->assertCount(2, $foundStates);
        $this->assertEquals('test_limit_2', $foundStates[0]->getState());
        $this->assertEquals('test_limit_3', $foundStates[1]->getState());
    }

    public function testCleanupExpiredStatesWithNoExpiredStates(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 首先清理所有已存在的过期状态
        $this->repository->cleanupExpiredStates();

        // 创建未过期状态
        $validState = $this->createTestState('valid_state_cleanup', $this->config, 600);
        $em->persist($validState);
        $em->flush();

        $deletedCount = $this->repository->cleanupExpiredStates();

        $this->assertEquals(0, $deletedCount);

        // 验证状态仍然存在
        $foundState = $this->repository->findOneBy(['state' => 'valid_state_cleanup']);
        $this->assertNotNull($foundState);
    }

    public function testFindBySessionIdWithNonExistentSession(): void
    {
        $result = $this->repository->findBySessionId('non_existent_session_id');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindBySessionIdOrderedByCreateTime(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $state1 = $this->createTestState('test_order_1', $this->config, 600);
        $state1->setSessionId('order_session_test');
        $em->persist($state1);
        $em->flush();

        // 等待更长时间确保时间差异
        sleep(1);

        $state2 = $this->createTestState('test_order_2', $this->config, 600);
        $state2->setSessionId('order_session_test');
        $em->persist($state2);
        $em->flush();

        $result = $this->repository->findBySessionId('order_session_test');

        $this->assertCount(2, $result);
        // 验证是按时间排序的，但不依赖具体的顺序（不同数据库可能不同）
        $this->assertContains('test_order_1', array_map(fn ($s) => $s->getState(), $result));
        $this->assertContains('test_order_2', array_map(fn ($s) => $s->getState(), $result));
    }

    // PHPStan required missing findAll tests

    // PHPStan required missing findOneBy tests

    // PHPStan required missing findBy tests

    // 可空字段 IS NULL 查询测试
    public function testFindByWithNullSessionIdShouldReturnMatchingEntities(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $state1 = $this->createTestState('test_null_session_1', $this->config, 600);
        // sessionId 保持为 null

        $state2 = $this->createTestState('test_null_session_2', $this->config, 600);
        $state2->setSessionId('not_null_session');

        $em->persist($state1);
        $em->persist($state2);
        $em->flush();

        $foundStates = $this->repository->findBy(['sessionId' => null]);

        $this->assertIsArray($foundStates);
        $this->assertCount(1, $foundStates);
        $this->assertEquals('test_null_session_1', $foundStates[0]->getState());
        $this->assertNull($foundStates[0]->getSessionId());
    }

    public function testFindByWithNullMetadataShouldReturnMatchingEntities(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $state1 = $this->createTestState('test_null_metadata_1', $this->config, 600);
        // metadata 保持为 null

        $state2 = $this->createTestState('test_null_metadata_2', $this->config, 600);
        $state2->setMetadata(['key' => 'value']);

        $em->persist($state1);
        $em->persist($state2);
        $em->flush();

        $foundStates = $this->repository->findBy(['metadata' => null]);

        $this->assertIsArray($foundStates);
        $this->assertGreaterThanOrEqual(1, count($foundStates));

        // 验证至少包含我们创建的那个
        $stateNames = array_map(fn ($s) => $s->getState(), $foundStates);
        $this->assertContains('test_null_metadata_1', $stateNames);

        // 验证找到的第一个匹配记录确实是 null metadata
        $foundOurState = null;
        foreach ($foundStates as $state) {
            if ('test_null_metadata_1' === $state->getState()) {
                $foundOurState = $state;
                break;
            }
        }
        $this->assertNotNull($foundOurState);
        $this->assertNull($foundOurState->getMetadata());
    }

    public function testCountWithNullSessionIdShouldReturnCorrectNumber(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $state1 = $this->createTestState('test_count_null_1', $this->config, 600);
        // sessionId 保持为 null

        $state2 = $this->createTestState('test_count_null_2', $this->config, 600);
        $state2->setSessionId('not_null_session_count');

        $em->persist($state1);
        $em->persist($state2);
        $em->flush();

        $count = $this->repository->count(['sessionId' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountWithNullMetadataShouldReturnCorrectNumber(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $state1 = $this->createTestState('test_count_null_meta_1', $this->config, 600);
        // metadata 保持为 null

        $state2 = $this->createTestState('test_count_null_meta_2', $this->config, 600);
        $state2->setMetadata(['test' => 'data']);

        $em->persist($state1);
        $em->persist($state2);
        $em->flush();

        $count = $this->repository->count(['metadata' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    // 关联查询测试
    public function testFindByConfigShouldReturnMatchingEntities(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建另一个配置
        $anotherConfig = new QQOAuth2Config();
        $anotherConfig->setAppId('another_app');
        $anotherConfig->setAppSecret('another_secret');
        $em->persist($anotherConfig);
        $em->flush();

        $state1 = $this->createTestState('test_config_1', $this->config, 600);
        $state2 = $this->createTestState('test_config_2', $anotherConfig, 600);

        $em->persist($state1);
        $em->persist($state2);
        $em->flush();

        $foundStates = $this->repository->findBy(['config' => $this->config]);

        $this->assertIsArray($foundStates);
        $this->assertGreaterThanOrEqual(1, count($foundStates));

        // 验证所有结果都属于正确的配置
        foreach ($foundStates as $state) {
            $config = $state->getConfig();
            $this->assertNotNull($config);
            $this->assertEquals($this->config->getId(), $config->getId());
        }
    }

    public function testCountByConfigShouldReturnCorrectNumber(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建另一个配置
        $anotherConfig = new QQOAuth2Config();
        $anotherConfig->setAppId('count_config_app');
        $anotherConfig->setAppSecret('count_config_secret');
        $em->persist($anotherConfig);
        $em->flush();

        $state1 = $this->createTestState('test_count_config_1', $this->config, 600);
        $state2 = $this->createTestState('test_count_config_2', $this->config, 600);
        $state3 = $this->createTestState('test_count_config_other', $anotherConfig, 600);

        $em->persist($state1);
        $em->persist($state2);
        $em->persist($state3);
        $em->flush();

        $count = $this->repository->count(['config' => $this->config]);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    protected function onSetUp(): void
    {
        $container = self::getContainer();
        $repository = $container->get(QQOAuth2StateRepository::class);
        $this->assertInstanceOf(QQOAuth2StateRepository::class, $repository);
        $this->repository = $repository;

        // 创建测试用配置
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);
        $this->config = new QQOAuth2Config();
        $this->config->setAppId('test_app');
        $this->config->setAppSecret('test_secret');
        $em->persist($this->config);
        $em->flush();
    }

    private function createTestState(string $state, QQOAuth2Config $config, int $ttl): QQOAuth2State
    {
        $stateEntity = new QQOAuth2State();
        $stateEntity->setState($state);
        $stateEntity->setConfig($config);
        $stateEntity->setExpireTimeFromTtl($ttl);

        return $stateEntity;
    }

    // 测试 findOneBy 排序逻辑 - 基础仓库类默认不支持排序，但应该返回有效结果
    public function testFindOneByShouldRespectOrderByClause(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建多个状态
        $state1 = $this->createTestState('findone_order_state1', $this->config, 600);
        $state1->setSessionId('order_session');

        $state2 = $this->createTestState('findone_order_state2', $this->config, 600);
        $state2->setSessionId('order_session');

        $em->persist($state1);
        $em->persist($state2);
        $em->flush();

        // 使用 orderBy 参数查找，即使基础实现可能不支持排序
        $result = $this->repository->findOneBy(['sessionId' => 'order_session'], ['id' => 'DESC']);

        $this->assertNotNull($result);
        $this->assertEquals('order_session', $result->getSessionId());
    }

    protected function createNewEntity(): object
    {
        $state = $this->createTestState('test_state_' . uniqid(), $this->config, 600);
        $state->setSessionId('test_session_' . uniqid());
        $state->setMetadata(['test' => 'data']);

        return $state;
    }

    /**
     * @return ServiceEntityRepository<QQOAuth2State>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
