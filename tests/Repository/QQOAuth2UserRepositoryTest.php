<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Repository;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;
use Tourze\QQConnectOAuth2Bundle\Tests\TestKernel;

class QQOAuth2UserRepositoryTest extends KernelTestCase
{
    private QQOAuth2UserRepository $repository;
    private QQOAuth2Config $config;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testFindByOpenid(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        $user = new QQOAuth2User('test_openid_123', 'access_token', 7200, $this->config);
        $user->setNickname('Test User');
        $em->persist($user);
        $em->flush();

        $result = $this->repository->findByOpenid('test_openid_123');

        $this->assertNotNull($result);
        $this->assertEquals('test_openid_123', $result->getOpenid());
        $this->assertEquals('Test User', $result->getNickname());
    }

    public function testFindByOpenidNotFound(): void
    {
        $result = $this->repository->findByOpenid('non_existent_openid');
        $this->assertNull($result);
    }

    public function testFindByUserReference(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        $user = new QQOAuth2User('test_openid', 'access_token', 7200, $this->config);
        $user->setUserReference('user_ref_123');
        $em->persist($user);
        $em->flush();

        $result = $this->repository->findByUserReference('user_ref_123');

        $this->assertCount(1, $result);
        $this->assertEquals('test_openid', $result[0]->getOpenid());
        $this->assertEquals('user_ref_123', $result[0]->getUserReference());
    }

    public function testFindByUserReferenceNotFound(): void
    {
        $result = $this->repository->findByUserReference('non_existent_ref');
        $this->assertCount(0, $result);
    }

    public function testFindExpiredTokenUsers(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // 创建令牌过期的用户
        $expiredUser1 = new QQOAuth2User('expired_openid_1', 'token1', 60, $this->config);
        $expiredUser1->setRefreshToken('refresh_token_1');
        // 手动设置为5分钟前，过期时间为1分钟，所以肯定过期
        $expiredUser1->setTokenUpdateTime(new \DateTimeImmutable('-5 minutes'));

        $expiredUser2 = new QQOAuth2User('expired_openid_2', 'token2', 120, $this->config);
        $expiredUser2->setRefreshToken('refresh_token_2');
        // 手动设置为10分钟前，过期时间为2分钟，所以肯定过期
        $expiredUser2->setTokenUpdateTime(new \DateTimeImmutable('-10 minutes'));

        // 创建令牌未过期的用户
        $validUser = new QQOAuth2User('valid_openid', 'token3', 7200, $this->config);

        $em->persist($expiredUser1);
        $em->persist($expiredUser2);
        $em->persist($validUser);
        $em->flush();

        // 确保实体状态被正确保存
        $em->clear();

        $expiredUsers = $this->repository->findExpiredTokenUsers();

        $this->assertGreaterThanOrEqual(2, count($expiredUsers));
        $openids = [];
        foreach ($expiredUsers as $user) {
            $openids[] = $user->getOpenid();
        }
        $this->assertContains('expired_openid_1', $openids);
        $this->assertContains('expired_openid_2', $openids);
    }

    public function testUpdateOrCreate(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        $userData = [
            'openid' => 'test_openid_create',
            'access_token' => 'new_access_token',
            'expires_in' => 7200,
            'nickname' => 'Created User',
            'figureurl_qq_2' => 'http://example.com/avatar.jpg',
        ];

        // 第一次调用应该创建新用户
        $user = $this->repository->updateOrCreate($userData, $this->config);

        $this->assertNotNull($user->getId());
        $this->assertEquals('test_openid_create', $user->getOpenid());
        $this->assertEquals('new_access_token', $user->getAccessToken());
        $this->assertEquals('Created User', $user->getNickname());
        $this->assertEquals('http://example.com/avatar.jpg', $user->getAvatar());

        // 第二次调用应该更新现有用户
        $updatedUserData = [
            'openid' => 'test_openid_create',
            'access_token' => 'updated_access_token',
            'expires_in' => 3600,
            'nickname' => 'Updated User',
            'figureurl_qq_2' => 'http://example.com/new_avatar.jpg',
        ];

        $updatedUser = $this->repository->updateOrCreate($updatedUserData, $this->config);

        $this->assertEquals($user->getId(), $updatedUser->getId()); // 同一个用户
        $this->assertEquals('updated_access_token', $updatedUser->getAccessToken());
        $this->assertEquals('Updated User', $updatedUser->getNickname());
        $this->assertEquals('http://example.com/new_avatar.jpg', $updatedUser->getAvatar());

        // 验证数据库中只有一个用户
        $allUsers = $this->repository->findAll();
        $this->assertCount(1, $allUsers);
    }

    public function testFindExpiredTokenUsersWithRefreshToken(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // 创建过期的用户（有refresh token）
        $expiredUserWithRefresh = new QQOAuth2User('expired_with_refresh', 'token1', 60, $this->config);
        $expiredUserWithRefresh->setRefreshToken('refresh_token_123');
        // 手动设置为5分钟前，过期时间为1分钟，所以肯定过期
        $expiredUserWithRefresh->setTokenUpdateTime(new \DateTimeImmutable('-5 minutes'));

        // 创建过期的用户（无refresh token）
        $expiredUserWithoutRefresh = new QQOAuth2User('expired_without_refresh', 'token2', 60, $this->config);
        // 手动设置为5分钟前，过期时间为1分钟，所以肯定过期（但这个没有refresh token，所以不会被查询到）
        $expiredUserWithoutRefresh->setTokenUpdateTime(new \DateTimeImmutable('-5 minutes'));

        // 创建未过期的用户
        $validUser = new QQOAuth2User('valid_user', 'token3', 7200, $this->config);
        $validUser->setRefreshToken('refresh_token_456');

        $em->persist($expiredUserWithRefresh);
        $em->persist($expiredUserWithoutRefresh);
        $em->persist($validUser);
        $em->flush();

        // 确保实体状态被正确保存
        $em->clear();

        $expiredUsersWithRefresh = $this->repository->findExpiredTokenUsers();

        $this->assertGreaterThanOrEqual(1, count($expiredUsersWithRefresh));
        // 验证至少包含过期且有refresh token的用户
        $openids = [];
        foreach ($expiredUsersWithRefresh as $user) {
            $openids[] = $user->getOpenid();
        }
        $this->assertContains('expired_with_refresh', $openids);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->repository = $container->get(QQOAuth2UserRepository::class);
        $em = $container->get('doctrine')->getManager();

        // 创建数据库schema
        $schemaTool = new SchemaTool($em);
        $classes = [
            $em->getClassMetadata(QQOAuth2Config::class),
            $em->getClassMetadata(QQOAuth2User::class),
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