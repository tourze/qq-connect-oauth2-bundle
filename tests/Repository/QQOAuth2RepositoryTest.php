<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Repository;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2StateRepository;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;

class QQOAuth2RepositoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ManagerRegistry $managerRegistry;

    public function testConfigRepository(): void
    {
        $repo = new QQOAuth2ConfigRepository($this->managerRegistry);

        // Create and save config
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret')
            ->setScope('get_user_info');

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        // Test findValidConfig
        $found = $repo->findValidConfig();
        $this->assertNotNull($found);
        $this->assertEquals('test_app_id', $found->getAppId());

        // Test findByAppId
        $found = $repo->findByAppId('test_app_id');
        $this->assertNotNull($found);
        $this->assertEquals('test_secret', $found->getAppSecret());

        // Test with invalid config
        $config->setValid(false);
        $this->entityManager->flush();

        $found = $repo->findValidConfig();
        $this->assertNull($found);
    }

    public function testStateRepository(): void
    {
        // Create config first
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')->setAppSecret('secret');
        $this->entityManager->persist($config);

        $repo = new QQOAuth2StateRepository($this->managerRegistry);

        // Create states
        $validState = new QQOAuth2State('valid_state', $config, 600);
        $expiredState = new QQOAuth2State('expired_state', $config, -1);
        $usedState = new QQOAuth2State('used_state', $config);
        $usedState->markAsUsed();

        $this->entityManager->persist($validState);
        $this->entityManager->persist($expiredState);
        $this->entityManager->persist($usedState);
        $this->entityManager->flush();

        // Test findValidState
        $found = $repo->findValidState('valid_state');
        $this->assertNotNull($found);
        $this->assertTrue($found->isValid());

        $found = $repo->findValidState('expired_state');
        $this->assertNull($found); // Should not find expired state

        $found = $repo->findValidState('used_state');
        $this->assertNull($found); // Should not find used state

        // Test cleanupExpiredStates
        $cleaned = $repo->cleanupExpiredStates();
        $this->assertEquals(1, $cleaned); // Should clean up 1 expired state

        // Test findBySessionId
        $validState->setSessionId('session_123');
        $this->entityManager->flush();

        $states = $repo->findBySessionId('session_123');
        $this->assertCount(1, $states);
        $this->assertEquals('valid_state', $states[0]->getState());
    }

    public function testUserRepository(): void
    {
        // Create config first
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')->setAppSecret('secret');
        $this->entityManager->persist($config);

        $repo = new QQOAuth2UserRepository($this->managerRegistry);

        // Test updateOrCreate - create new user
        $userData = [
            'openid' => 'test_openid',
            'access_token' => 'test_token',
            'expires_in' => 7200,
            'refresh_token' => 'refresh_token',
            'nickname' => 'Test User',
            'figureurl_qq_2' => 'https://example.com/avatar.jpg',
            'gender' => '男',
            'province' => '北京',
            'city' => '北京',
        ];

        $user = $repo->updateOrCreate($userData, $config);
        $this->assertNotNull($user->getId());
        $this->assertEquals('test_openid', $user->getOpenid());
        $this->assertEquals('Test User', $user->getNickname());

        // Test updateOrCreate - update existing user
        $userData['access_token'] = 'new_token';
        $userData['nickname'] = 'Updated User';

        $updatedUser = $repo->updateOrCreate($userData, $config);
        $this->assertEquals($user->getId(), $updatedUser->getId()); // Same user
        $this->assertEquals('new_token', $updatedUser->getAccessToken());
        $this->assertEquals('Updated User', $updatedUser->getNickname());

        // Test other repository methods
        $found = $repo->findByOpenid('test_openid');
        $this->assertNotNull($found);
        $this->assertEquals('test_openid', $found->getOpenid());

        $found = $repo->findByOpenid('non_existent');
        $this->assertNull($found);

        // Test findByUnionid
        $user->setUnionid('test_unionid');
        $this->entityManager->flush();

        $users = $repo->findByUnionid('test_unionid');
        $this->assertCount(1, $users);
        $this->assertEquals('test_openid', $users[0]->getOpenid());

        // Test findByUserReference
        $user->setUserReference('user_123');
        $this->entityManager->flush();

        $users = $repo->findByUserReference('user_123');
        $this->assertCount(1, $users);
        $this->assertEquals('test_openid', $users[0]->getOpenid());
    }

    public function testEntityRelationships(): void
    {
        // Enable foreign key constraints for SQLite
        $connection = $this->entityManager->getConnection();
        if ($connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        }

        // Create config
        $config = new QQOAuth2Config();
        $config->setAppId('test_app')->setAppSecret('secret');
        $this->entityManager->persist($config);

        // Create state and user linked to config
        $state = new QQOAuth2State('test_state', $config);
        $user = new QQOAuth2User('test_openid', 'token', 7200, $config);

        $this->entityManager->persist($state);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Verify relationships
        $this->assertSame($config, $state->getConfig());
        $this->assertSame($config, $user->getConfig());

        // Test cascade delete
        $stateId = $state->getId();
        $userId = $user->getId();

        $this->entityManager->remove($config);
        $this->entityManager->flush();

        // Clear entity manager to force fresh queries
        $this->entityManager->clear();

        // State and user should be deleted due to cascade
        $this->assertNull($this->entityManager->find(QQOAuth2State::class, $stateId));
        $this->assertNull($this->entityManager->find(QQOAuth2User::class, $userId));
    }

    protected function setUp(): void
    {
        // Create in-memory SQLite database
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([
            __DIR__ . '/../../src/Entity'
        ]));
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Proxy');

        $this->entityManager = new EntityManager($connection, $config);

        // Create mock ManagerRegistry
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->managerRegistry->method('getManagerForClass')
            ->willReturn($this->entityManager);
        $this->managerRegistry->method('getManager')
            ->willReturn($this->entityManager);

        // Create schema
        $schemaTool = new SchemaTool($this->entityManager);
        $classes = [
            $this->entityManager->getClassMetadata(QQOAuth2Config::class),
            $this->entityManager->getClassMetadata(QQOAuth2State::class),
            $this->entityManager->getClassMetadata(QQOAuth2User::class),
        ];
        $schemaTool->createSchema($classes);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
    }
}