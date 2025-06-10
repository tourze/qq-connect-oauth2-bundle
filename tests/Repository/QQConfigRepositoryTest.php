<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\QQConnectOAuth2Bundle;
use Tourze\QQConnectOAuth2Bundle\Repository\QQConfigRepository;

/**
 * QQ配置Repository集成测试
 */
class QQConfigRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private QQConfigRepository $repository;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new IntegrationTestKernel('test', true, [
            QQConnectOAuth2Bundle::class => ['all' => true],
        ]);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->repository = $this->entityManager->getRepository(QQOAuth2Config::class);

        // 创建数据库结构
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        $schemaTool->createSchema([$this->entityManager->getClassMetadata(QQOAuth2Config::class)]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    public function testFindByName(): void
    {
        // 创建测试数据
        $config = $this->createTestConfig('test_config', 'dev');
        $this->entityManager->persist($config);
        $this->entityManager->flush();

        // 测试按名称查找
        $found = $this->repository->findByName('test_config');
        self::assertNotNull($found);
        self::assertSame('test_config', $found->getName());
    }

    public function testFindByEnvironment(): void
    {
        // 创建不同环境的测试数据
        $devConfig = $this->createTestConfig('dev_config', 'dev');
        $prodConfig = $this->createTestConfig('prod_config', 'prod');

        $this->entityManager->persist($devConfig);
        $this->entityManager->persist($prodConfig);
        $this->entityManager->flush();

        // 测试按环境查找
        $devConfigs = $this->repository->findByEnvironment('dev');
        self::assertCount(1, $devConfigs);
        self::assertSame('dev', $devConfigs[0]->getEnvironment());

        $prodConfigs = $this->repository->findByEnvironment('prod');
        self::assertCount(1, $prodConfigs);
        self::assertSame('prod', $prodConfigs[0]->getEnvironment());
    }

    public function testFindActiveByEnvironment(): void
    {
        // 创建激活和非激活的配置
        $activeConfig = $this->createTestConfig('active_config', 'dev');
        $activeConfig->setValid(true);

        $inactiveConfig = $this->createTestConfig('inactive_config', 'dev');
        $inactiveConfig->setValid(false);

        $this->entityManager->persist($activeConfig);
        $this->entityManager->persist($inactiveConfig);
        $this->entityManager->flush();

        // 测试只查找激活的配置
        $activeConfigs = $this->repository->findActiveByEnvironment('dev');
        self::assertCount(1, $activeConfigs);
        self::assertTrue($activeConfigs[0]->isValid());
    }

    public function testFindByAppIdAndEnvironment(): void
    {
        // 创建测试数据
        $config = $this->createTestConfig('test_config', 'dev');
        $config->setAppId('123456789');

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        // 测试按App ID和环境查找
        $found = $this->repository->findByAppIdAndEnvironment('123456789', 'dev');
        self::assertNotNull($found);
        self::assertSame('123456789', $found->getAppId());
    }

    public function testExistsByName(): void
    {
        // 创建测试数据
        $config = $this->createTestConfig('exists_test', 'dev');
        $this->entityManager->persist($config);
        $this->entityManager->flush();

        // 测试存在性检查
        self::assertTrue($this->repository->existsByName('exists_test'));
        self::assertFalse($this->repository->existsByName('not_exists'));
    }

    public function testExistsByAppIdAndEnvironment(): void
    {
        // 创建测试数据
        $config = $this->createTestConfig('test_config', 'dev');
        $config->setAppId('987654321');

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        // 测试App ID存在性检查
        self::assertTrue($this->repository->existsByAppIdAndEnvironment('987654321', 'dev'));
        self::assertFalse($this->repository->existsByAppIdAndEnvironment('987654321', 'prod'));
        self::assertFalse($this->repository->existsByAppIdAndEnvironment('111111111', 'dev'));
    }

    public function testCountByEnvironment(): void
    {
        // 创建多个同环境配置
        $config1 = $this->createTestConfig('config1', 'test');
        $config2 = $this->createTestConfig('config2', 'test');

        $this->entityManager->persist($config1);
        $this->entityManager->persist($config2);
        $this->entityManager->flush();

        // 测试按环境统计
        $count = $this->repository->countByEnvironment('test');
        self::assertSame(2, $count);

        $count = $this->repository->countByEnvironment('prod');
        self::assertSame(0, $count);
    }

    public function testFindDefaultByEnvironment(): void
    {
        // 创建配置，使用不同的排序权重
        $config1 = $this->createTestConfig('config1', 'dev');
        $config1->setSortOrder(10);

        $config2 = $this->createTestConfig('config2', 'dev');
        $config2->setSortOrder(5);  // 更小的排序值，优先级更高

        $this->entityManager->persist($config1);
        $this->entityManager->persist($config2);
        $this->entityManager->flush();

        // 测试获取默认配置（最小排序值）
        $defaultConfig = $this->repository->findDefaultByEnvironment('dev');
        self::assertNotNull($defaultConfig);
        self::assertSame('config2', $defaultConfig->getName());
        self::assertSame(5, $defaultConfig->getSortOrder());
    }

    private function createTestConfig(string $name, string $environment): QQOAuth2Config
    {
        return new QQOAuth2Config(
            name: $name,
            appId: '123456789',
            appKey: 'test_app_key',
            redirectUri: 'http://localhost:8000/callback',
            environment: $environment
        );
    }
}
