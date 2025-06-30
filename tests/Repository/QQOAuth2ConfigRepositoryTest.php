<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Repository;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;
use Tourze\QQConnectOAuth2Bundle\Tests\TestKernel;

class QQOAuth2ConfigRepositoryTest extends KernelTestCase
{
    private QQOAuth2ConfigRepository $repository;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testFindValidConfig(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // 创建有效配置
        $validConfig = new QQOAuth2Config();
        $validConfig->setAppId('valid_app_id')
            ->setAppSecret('valid_secret')
            ->setValid(true);

        // 创建无效配置
        $invalidConfig = new QQOAuth2Config();
        $invalidConfig->setAppId('invalid_app_id')
            ->setAppSecret('invalid_secret')
            ->setValid(false);

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
        $em = $container->get('doctrine')->getManager();

        $config = new QQOAuth2Config();
        $config->setAppId('test_app_123')
            ->setAppSecret('test_secret');

        $em->persist($config);
        $em->flush();

        $result = $this->repository->findByAppId('test_app_123');

        $this->assertNotNull($result);
        $this->assertEquals('test_app_123', $result->getAppId());
        $this->assertEquals('test_secret', $result->getAppSecret());
    }

    public function testFindByAppIdNotFound(): void
    {
        $result = $this->repository->findByAppId('non_existent');
        $this->assertNull($result);
    }

    public function testFindValidConfigWithMultipleConfigs(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // 创建多个配置
        $config1 = new QQOAuth2Config();
        $config1->setAppId('app1')->setAppSecret('secret1')->setValid(true);

        $config2 = new QQOAuth2Config();
        $config2->setAppId('app2')->setAppSecret('secret2')->setValid(false);

        $config3 = new QQOAuth2Config();
        $config3->setAppId('app3')->setAppSecret('secret3')->setValid(true);

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

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->repository = $container->get(QQOAuth2ConfigRepository::class);

        // 创建数据库schema
        $em = $container->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);
        $classes = [$em->getClassMetadata(QQOAuth2Config::class)];
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
    }
} 