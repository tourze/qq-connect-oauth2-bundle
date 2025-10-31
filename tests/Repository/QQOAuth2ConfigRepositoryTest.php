<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;

/**
 * @internal
 */
#[CoversClass(QQOAuth2ConfigRepository::class)]
#[RunTestsInSeparateProcesses]
final class QQOAuth2ConfigRepositoryTest extends AbstractRepositoryTestCase
{
    private QQOAuth2ConfigRepository $repository;

    public function testFindValidConfig(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建有效配置
        $validConfig = new QQOAuth2Config();
        $validConfig->setAppId('valid_app_id');
        $validConfig->setAppSecret('valid_secret');
        $validConfig->setValid(true);

        // 创建无效配置
        $invalidConfig = new QQOAuth2Config();
        $invalidConfig->setAppId('invalid_app_id');
        $invalidConfig->setAppSecret('invalid_secret');
        $invalidConfig->setValid(false);

        $em->persist($validConfig);
        $em->persist($invalidConfig);
        $em->flush();

        $result = $this->repository->findValidConfig();

        $this->assertNotNull($result);
        $this->assertEquals('valid_app_id', $result->getAppId());
        $this->assertTrue($result->isValid());
    }

    public function testFindValidConfigWhenNoneExists(): void
    {
        $result = $this->repository->findValidConfig();
        $this->assertNull($result);
    }

    public function testFindByAppId(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $config = new QQOAuth2Config();
        $config->setAppId('test_find_by_app_id_unique');
        $config->setAppSecret('test_secret_unique');

        $em->persist($config);
        $em->flush();

        $result = $this->repository->findByAppId('test_find_by_app_id_unique');

        $this->assertNotNull($result);
        $this->assertEquals('test_find_by_app_id_unique', $result->getAppId());
        $this->assertEquals('test_secret_unique', $result->getAppSecret());
    }

    public function testFindByAppIdNotFound(): void
    {
        $result = $this->repository->findByAppId('non_existent');
        $this->assertNull($result);
    }

    public function testFindValidConfigWithMultipleConfigs(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建多个配置
        $config1 = new QQOAuth2Config();
        $config1->setAppId('app1');
        $config1->setAppSecret('secret1');
        $config1->setValid(true);

        $config2 = new QQOAuth2Config();
        $config2->setAppId('app2');
        $config2->setAppSecret('secret2');
        $config2->setValid(false);

        $config3 = new QQOAuth2Config();
        $config3->setAppId('app3');
        $config3->setAppSecret('secret3');
        $config3->setValid(true);

        $em->persist($config1);
        $em->persist($config2);
        $em->persist($config3);
        $em->flush();

        // findValidConfig 应该返回最新的有效配置（按ID降序）
        $validConfig = $this->repository->findValidConfig();

        $this->assertNotNull($validConfig);
        $this->assertEquals('app3', $validConfig->getAppId()); // 最后插入的有效配置
        $this->assertTrue($validConfig->isValid());
    }

    public function testClearCache(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建配置项用于测试缓存
        $config = new QQOAuth2Config();
        $config->setAppId('cached_app_id');
        $config->setAppSecret('cached_secret');
        $config->setValid(true);

        $em->persist($config);
        $em->flush();

        // 第一次查询建立缓存
        $firstResult = $this->repository->findValidConfig();
        $this->assertNotNull($firstResult);

        // 清除缓存
        $this->repository->clearCache();

        // 再次查询验证缓存清除后依然能正常工作
        $secondResult = $this->repository->findValidConfig();
        $this->assertNotNull($secondResult);
        $this->assertEquals($firstResult->getAppId(), $secondResult->getAppId());
    }

    public function testSaveMethodWithFlush(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('save_test_app');
        $config->setAppSecret('save_test_secret');
        $config->setValid(true);

        $this->repository->save($config, true);

        $this->assertNotNull($config->getId());

        // 验证保存到数据库
        $savedConfig = $this->repository->find($config->getId());
        $this->assertNotNull($savedConfig);
        $this->assertEquals('save_test_app', $savedConfig->getAppId());
        $this->assertEquals('save_test_secret', $savedConfig->getAppSecret());
        $this->assertTrue($savedConfig->isValid());
    }

    public function testSaveMethodWithoutFlush(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $config = new QQOAuth2Config();
        $config->setAppId('save_no_flush_app');
        $config->setAppSecret('save_no_flush_secret');
        $config->setValid(false);

        $this->repository->save($config, false);

        // 手动刷新
        $em->flush();

        $this->assertNotNull($config->getId());

        // 验证保存到数据库
        $savedConfig = $this->repository->find($config->getId());
        $this->assertNotNull($savedConfig);
        $this->assertEquals('save_no_flush_app', $savedConfig->getAppId());
        $this->assertEquals('save_no_flush_secret', $savedConfig->getAppSecret());
        $this->assertFalse($savedConfig->isValid());
    }

    public function testRemoveMethodWithFlush(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 先创建一个实体
        $config = new QQOAuth2Config();
        $config->setAppId('remove_test_app');
        $config->setAppSecret('remove_test_secret');
        $config->setValid(true);

        $em->persist($config);
        $em->flush();

        $configId = $config->getId();
        $this->assertNotNull($configId);

        // 验证实体存在
        $existingConfig = $this->repository->find($configId);
        $this->assertNotNull($existingConfig);

        // 移除实体
        $this->repository->remove($config, true);

        // 验证实体已被移除
        $removedConfig = $this->repository->find($configId);
        $this->assertNull($removedConfig);
    }

    public function testRemoveMethodWithoutFlush(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 先创建一个实体
        $config = new QQOAuth2Config();
        $config->setAppId('remove_no_flush_app');
        $config->setAppSecret('remove_no_flush_secret');
        $config->setValid(true);

        $em->persist($config);
        $em->flush();

        $configId = $config->getId();
        $this->assertNotNull($configId);

        // 移除实体但不刷新
        $this->repository->remove($config, false);

        // 手动刷新
        $em->flush();

        // 验证实体已被移除
        $removedConfig = $this->repository->find($configId);
        $this->assertNull($removedConfig);
    }

    public function testFindByAppIdWithEmptyString(): void
    {
        $result = $this->repository->findByAppId('');
        $this->assertNull($result);
    }

    public function testFindByAppIdCaching(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $config = new QQOAuth2Config();
        $config->setAppId('cache_test_app');
        $config->setAppSecret('cache_test_secret');
        $config->setValid(true);

        $em->persist($config);
        $em->flush();

        // 第一次查询，建立缓存
        $firstResult = $this->repository->findByAppId('cache_test_app');
        $this->assertNotNull($firstResult);
        $this->assertEquals('cache_test_app', $firstResult->getAppId());

        // 第二次查询，应该从缓存获取
        $secondResult = $this->repository->findByAppId('cache_test_app');
        $this->assertNotNull($secondResult);
        $this->assertEquals('cache_test_app', $secondResult->getAppId());
        $this->assertEquals($firstResult->getId(), $secondResult->getId());
    }

    public function testFindAllMethod(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建多个配置
        $config1 = new QQOAuth2Config();
        $config1->setAppId('all_test_app1');
        $config1->setAppSecret('secret1');
        $config1->setValid(true);

        $config2 = new QQOAuth2Config();
        $config2->setAppId('all_test_app2');
        $config2->setAppSecret('secret2');
        $config2->setValid(false);

        $em->persist($config1);
        $em->persist($config2);
        $em->flush();

        $allConfigs = $this->repository->findAll();

        $this->assertIsArray($allConfigs);
        $this->assertGreaterThanOrEqual(2, count($allConfigs));

        // 验证我们创建的配置在结果中
        $appIds = array_map(fn ($config) => $config->getAppId(), $allConfigs);
        $this->assertContains('all_test_app1', $appIds);
        $this->assertContains('all_test_app2', $appIds);
    }

    public function testFindByMethod(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建测试数据
        $config1 = new QQOAuth2Config();
        $config1->setAppId('findby_app1');
        $config1->setAppSecret('secret1');
        $config1->setValid(true);

        $config2 = new QQOAuth2Config();
        $config2->setAppId('findby_app2');
        $config2->setAppSecret('secret2');
        $config2->setValid(true);

        $config3 = new QQOAuth2Config();
        $config3->setAppId('findby_app3');
        $config3->setAppSecret('secret3');
        $config3->setValid(false);

        $em->persist($config1);
        $em->persist($config2);
        $em->persist($config3);
        $em->flush();

        // 测试 findBy 方法查找有效配置
        $validConfigs = $this->repository->findBy(['valid' => true]);
        $this->assertIsArray($validConfigs);
        $this->assertGreaterThanOrEqual(2, count($validConfigs));

        foreach ($validConfigs as $config) {
            $this->assertTrue($config->isValid());
        }

        // 测试 findBy 方法查找无效配置
        $invalidConfigs = $this->repository->findBy(['valid' => false]);
        $this->assertIsArray($invalidConfigs);
        $this->assertGreaterThanOrEqual(1, count($invalidConfigs));

        foreach ($invalidConfigs as $config) {
            $this->assertFalse($config->isValid());
        }
    }

    public function testFindOneByMethod(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        $config = new QQOAuth2Config();
        $config->setAppId('findone_test_app');
        $config->setAppSecret('findone_test_secret');
        $config->setValid(true);

        $em->persist($config);
        $em->flush();

        // 测试 findOneBy 方法
        $foundConfig = $this->repository->findOneBy(['appId' => 'findone_test_app']);

        $this->assertNotNull($foundConfig);
        $this->assertEquals('findone_test_app', $foundConfig->getAppId());
        $this->assertEquals('findone_test_secret', $foundConfig->getAppSecret());
        $this->assertTrue($foundConfig->isValid());

        // 测试查找不存在的记录
        $notFoundConfig = $this->repository->findOneBy(['appId' => 'non_existent_app']);
        $this->assertNull($notFoundConfig);
    }

    // 更完整的 findBy 测试

    // 更完整的 findOneBy 测试

    // 更完整的 findAll 测试

    // 测试 IS NULL 查询
    public function testFindByWithNullScopeShouldReturnMatchingEntities(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建有 scope 和无 scope 的配置
        $configWithScope = new QQOAuth2Config();
        $configWithScope->setAppId('with_scope_app');
        $configWithScope->setAppSecret('secret1');
        $configWithScope->setScope('read,write');

        $configWithoutScope = new QQOAuth2Config();
        $configWithoutScope->setAppId('without_scope_app');
        $configWithoutScope->setAppSecret('secret2');
        // scope 默认为 null

        $em->persist($configWithScope);
        $em->persist($configWithoutScope);
        $em->flush();

        // 查找 scope 为 null 的记录
        $nullScopeConfigs = $this->repository->findBy(['scope' => null]);

        $this->assertIsArray($nullScopeConfigs);
        $this->assertGreaterThanOrEqual(1, count($nullScopeConfigs));

        foreach ($nullScopeConfigs as $config) {
            $this->assertNull($config->getScope());
        }
    }

    public function testCountWithNullScopeShouldReturnCorrectNumber(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建有 scope 和无 scope 的配置
        $configWithScope = new QQOAuth2Config();
        $configWithScope->setAppId('count_with_scope');
        $configWithScope->setAppSecret('secret1');
        $configWithScope->setScope('read');

        $configWithoutScope1 = new QQOAuth2Config();
        $configWithoutScope1->setAppId('count_without_scope1');
        $configWithoutScope1->setAppSecret('secret2');

        $configWithoutScope2 = new QQOAuth2Config();
        $configWithoutScope2->setAppId('count_without_scope2');
        $configWithoutScope2->setAppSecret('secret3');

        $em->persist($configWithScope);
        $em->persist($configWithoutScope1);
        $em->persist($configWithoutScope2);
        $em->flush();

        // 统计 scope 为 null 的记录数
        $nullScopeCount = $this->repository->count(['scope' => null]);

        $this->assertGreaterThanOrEqual(2, $nullScopeCount);
    }

    // 测试 findOneBy 排序逻辑 - 基础仓库类默认不支持排序，但应该返回有效结果
    public function testFindOneByWithOrderByShouldStillReturnValidResult(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建多个有效配置
        $config1 = new QQOAuth2Config();
        $config1->setAppId('findone_order1');
        $config1->setAppSecret('secret1');
        $config1->setValid(true);

        $config2 = new QQOAuth2Config();
        $config2->setAppId('findone_order2');
        $config2->setAppSecret('secret2');
        $config2->setValid(true);

        $em->persist($config1);
        $em->persist($config2);
        $em->flush();

        // 使用 orderBy 参数查找，即使基础实现可能不支持排序
        $result = $this->repository->findOneBy(['valid' => true], ['id' => 'DESC']);

        $this->assertNotNull($result);
        $this->assertTrue($result->isValid());
    }

    // 更多 IS NULL 查询测试
    public function testFindOneByWithNullScopeShouldReturnMatchingEntity(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建有 scope 和无 scope 的配置
        $configWithScope = new QQOAuth2Config();
        $configWithScope->setAppId('findone_with_scope');
        $configWithScope->setAppSecret('secret1');
        $configWithScope->setScope('read,write');

        $configWithoutScope = new QQOAuth2Config();
        $configWithoutScope->setAppId('findone_without_scope');
        $configWithoutScope->setAppSecret('secret2');
        // scope 默认为 null

        $em->persist($configWithScope);
        $em->persist($configWithoutScope);
        $em->flush();

        // 查找第一个 scope 为 null 的记录
        $nullScopeConfig = $this->repository->findOneBy(['scope' => null]);

        $this->assertNotNull($nullScopeConfig);
        $this->assertNull($nullScopeConfig->getScope());
        $this->assertEquals('findone_without_scope', $nullScopeConfig->getAppId());
    }

    public function testFindOneByWithOrderBySortingLogicShouldReturnLastRecord(): void
    {
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        // 创建多个配置以测试排序
        $config1 = new QQOAuth2Config();
        $config1->setAppId('sort_test_app1');
        $config1->setAppSecret('secret1');
        $config1->setValid(true);

        $config2 = new QQOAuth2Config();
        $config2->setAppId('sort_test_app2');
        $config2->setAppSecret('secret2');
        $config2->setValid(true);

        $em->persist($config1);
        $em->flush();

        // 稍微延迟确保时间不同
        usleep(1000);

        $em->persist($config2);
        $em->flush();

        // 测试按ID降序排序，应该返回最后创建的记录
        $lastConfig = $this->repository->findOneBy(['valid' => true], ['id' => 'DESC']);

        $this->assertNotNull($lastConfig);
        $this->assertEquals('sort_test_app2', $lastConfig->getAppId());
        $this->assertTrue($lastConfig->isValid());
    }

    protected function onSetUp(): void
    {
        $container = self::getContainer();
        $repository = $container->get(QQOAuth2ConfigRepository::class);
        $this->assertInstanceOf(QQOAuth2ConfigRepository::class, $repository);
        $this->repository = $repository;

        // 清理数据库，确保测试隔离
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM ' . QQOAuth2Config::class)->execute();

        // 检查当前测试是否需要 DataFixtures 数据
        $currentTest = $this->name();
        if ('testCountWithDataFixtureShouldReturnGreaterThanZero' === $currentTest) {
            // 为 count 测试创建测试数据
            $config = new QQOAuth2Config();
            $config->setAppId('test_count_app');
            $config->setAppSecret('test_count_secret');
            $config->setValid(true);
            $em->persist($config);
            $em->flush();
        }

        // 清除缓存
        $this->repository->clearCache();
    }

    protected function createNewEntity(): object
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id_' . uniqid());
        $config->setAppSecret('test_app_secret_' . uniqid());
        $config->setScope('read,write');
        $config->setValid(true);

        return $config;
    }

    /**
     * @return ServiceEntityRepository<QQOAuth2Config>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
