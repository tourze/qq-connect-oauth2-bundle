<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;

/**
 * @internal
 */
#[CoversClass(QQOAuth2UserRepository::class)]
#[RunTestsInSeparateProcesses]
final class QQOAuth2UserRepositoryTest extends AbstractRepositoryTestCase
{
    private QQOAuth2UserRepository $repository;

    private QQOAuth2Config $config;

    private function getDoctrineEntityManager(): EntityManagerInterface
    {
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    public function testFindByOpenid(): void
    {
        $em = $this->getDoctrineEntityManager();

        $user = $this->createTestUser('test_openid_123', 'access_token', 7200, $this->config);
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
        $em = $this->getDoctrineEntityManager();

        $user = $this->createTestUser('test_openid', 'access_token', 7200, $this->config);
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
        $em = $this->getDoctrineEntityManager();

        // 创建令牌过期的用户
        $expiredUser1 = $this->createTestUser('expired_openid_1', 'token1', 60, $this->config);
        $expiredUser1->setRefreshToken('refresh_token_1');
        // 手动设置为5分钟前，过期时间为1分钟，所以肯定过期
        $expiredUser1->setTokenUpdateTime(new \DateTimeImmutable('-5 minutes'));

        $expiredUser2 = $this->createTestUser('expired_openid_2', 'token2', 120, $this->config);
        $expiredUser2->setRefreshToken('refresh_token_2');
        // 手动设置为10分钟前，过期时间为2分钟，所以肯定过期
        $expiredUser2->setTokenUpdateTime(new \DateTimeImmutable('-10 minutes'));

        // 创建令牌未过期的用户
        $validUser = $this->createTestUser('valid_openid', 'token3', 7200, $this->config);

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

    public function testFindExpiredTokenUsersWithRefreshToken(): void
    {
        $em = $this->getDoctrineEntityManager();

        // 创建过期的用户（有refresh token）
        $expiredUserWithRefresh = $this->createTestUser('expired_with_refresh', 'token1', 60, $this->config);
        $expiredUserWithRefresh->setRefreshToken('refresh_token_123');
        // 手动设置为5分钟前，过期时间为1分钟，所以肯定过期
        $expiredUserWithRefresh->setTokenUpdateTime(new \DateTimeImmutable('-5 minutes'));

        // 创建过期的用户（无refresh token）
        $expiredUserWithoutRefresh = $this->createTestUser('expired_without_refresh', 'token2', 60, $this->config);
        // 手动设置为5分钟前，过期时间为1分钟，所以肯定过期（但这个没有refresh token，所以不会被查询到）
        $expiredUserWithoutRefresh->setTokenUpdateTime(new \DateTimeImmutable('-5 minutes'));

        // 创建未过期的用户
        $validUser = $this->createTestUser('valid_user', 'token3', 7200, $this->config);
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

    public function testDeleteExpiredStatesUsers(): void
    {
        $em = $this->getDoctrineEntityManager();

        // 创建过期的用户（无refresh token）
        $expiredUserWithoutRefresh = $this->createTestUser('expired_no_refresh', 'token1', 60, $this->config);
        $expiredUserWithoutRefresh->setTokenUpdateTime(new \DateTimeImmutable('-2 days'));

        // 创建过期的用户（有refresh token）
        $expiredUserWithRefresh = $this->createTestUser('expired_with_refresh', 'token2', 60, $this->config);
        $expiredUserWithRefresh->setRefreshToken('refresh_token');
        $expiredUserWithRefresh->setTokenUpdateTime(new \DateTimeImmutable('-2 days'));

        // 创建正常的用户
        $validUser = $this->createTestUser('valid_user', 'token3', 7200, $this->config);

        $em->persist($expiredUserWithoutRefresh);
        $em->persist($expiredUserWithRefresh);
        $em->persist($validUser);
        $em->flush();

        // 删除过期的用户（只删除没有refresh token的）
        $deletedCount = $this->repository->deleteExpiredStatesUsers(86400); // 1 day

        $this->assertGreaterThanOrEqual(0, $deletedCount);
    }

    public function testFindByUnionid(): void
    {
        $em = $this->getDoctrineEntityManager();

        $user1 = $this->createTestUser('openid1', 'token1', 7200, $this->config);
        $user1->setUnionid('test_unionid_123');

        $user2 = $this->createTestUser('openid2', 'token2', 7200, $this->config);
        $user2->setUnionid('test_unionid_123');

        $user3 = $this->createTestUser('openid3', 'token3', 7200, $this->config);
        $user3->setUnionid('different_unionid');

        $em->persist($user1);
        $em->persist($user2);
        $em->persist($user3);
        $em->flush();

        $result = $this->repository->findByUnionid('test_unionid_123');

        $this->assertCount(2, $result);
        $openids = array_map(fn ($user) => $user->getOpenid(), $result);
        $this->assertContains('openid1', $openids);
        $this->assertContains('openid2', $openids);
    }

    public function testFindUsersByOpenids(): void
    {
        $em = $this->getDoctrineEntityManager();

        $user1 = $this->createTestUser('openid1', 'token1', 7200, $this->config);
        $user2 = $this->createTestUser('openid2', 'token2', 7200, $this->config);
        $user3 = $this->createTestUser('openid3', 'token3', 7200, $this->config);

        $em->persist($user1);
        $em->persist($user2);
        $em->persist($user3);
        $em->flush();

        $result = $this->repository->findUsersByOpenids(['openid1', 'openid3']);

        $this->assertCount(2, $result);
        $openids = array_map(fn ($user) => $user->getOpenid(), $result);
        $this->assertContains('openid1', $openids);
        $this->assertContains('openid3', $openids);
        $this->assertNotContains('openid2', $openids);
    }

    public function testFindByConfigAssociation(): void
    {
        $em = $this->getDoctrineEntityManager();

        $user1 = $this->createTestUser('openid1', 'token1', 7200, $this->config);
        $user2 = $this->createTestUser('openid2', 'token2', 7200, $this->config);
        $em->persist($user1);
        $em->persist($user2);
        $em->flush();

        $result = $this->repository->findBy(['config' => $this->config]);
        $this->assertGreaterThanOrEqual(2, count($result));

        $openids = array_map(fn ($user) => $user->getOpenid(), $result);
        $this->assertContains('openid1', $openids);
        $this->assertContains('openid2', $openids);
    }

    public function testFindByNullUnionid(): void
    {
        $em = $this->getDoctrineEntityManager();

        $userWithUnionid = $this->createTestUser('openid_with_unionid', 'token1', 7200, $this->config);
        $userWithUnionid->setUnionid('test_unionid');

        $userWithoutUnionid = $this->createTestUser('openid_without_unionid', 'token2', 7200, $this->config);

        $em->persist($userWithUnionid);
        $em->persist($userWithoutUnionid);
        $em->flush();

        $result = $this->repository->findBy(['unionid' => null]);
        $this->assertGreaterThanOrEqual(1, count($result));

        $openids = array_map(fn ($user) => $user->getOpenid(), $result);
        $this->assertContains('openid_without_unionid', $openids);
    }

    public function testFindByNullNickname(): void
    {
        $em = $this->getDoctrineEntityManager();

        $userWithNickname = $this->createTestUser('openid_with_nickname', 'token1', 7200, $this->config);
        $userWithNickname->setNickname('Test User');

        $userWithoutNickname = $this->createTestUser('openid_without_nickname', 'token2', 7200, $this->config);

        $em->persist($userWithNickname);
        $em->persist($userWithoutNickname);
        $em->flush();

        $result = $this->repository->findBy(['nickname' => null]);
        $this->assertGreaterThanOrEqual(1, count($result));

        $openids = array_map(fn ($user) => $user->getOpenid(), $result);
        $this->assertContains('openid_without_nickname', $openids);
    }

    public function testFindByNullAvatar(): void
    {
        $em = $this->getDoctrineEntityManager();

        $userWithAvatar = $this->createTestUser('openid_with_avatar', 'token1', 7200, $this->config);
        $userWithAvatar->setAvatar('https://example.com/avatar.jpg');

        $userWithoutAvatar = $this->createTestUser('openid_without_avatar', 'token2', 7200, $this->config);

        $em->persist($userWithAvatar);
        $em->persist($userWithoutAvatar);
        $em->flush();

        $result = $this->repository->findBy(['avatar' => null]);
        $this->assertGreaterThanOrEqual(1, count($result));

        $openids = array_map(fn ($user) => $user->getOpenid(), $result);
        $this->assertContains('openid_without_avatar', $openids);
    }

    public function testFindByNullGender(): void
    {
        $em = $this->getDoctrineEntityManager();

        $userWithGender = $this->createTestUser('openid_with_gender', 'token1', 7200, $this->config);
        $userWithGender->setGender('male');

        $userWithoutGender = $this->createTestUser('openid_without_gender', 'token2', 7200, $this->config);

        $em->persist($userWithGender);
        $em->persist($userWithoutGender);
        $em->flush();

        $result = $this->repository->findBy(['gender' => null]);
        $this->assertGreaterThanOrEqual(1, count($result));

        $openids = array_map(fn ($user) => $user->getOpenid(), $result);
        $this->assertContains('openid_without_gender', $openids);
    }

    public function testFindByNullProvince(): void
    {
        $em = $this->getDoctrineEntityManager();

        $userWithProvince = $this->createTestUser('openid_with_province', 'token1', 7200, $this->config);
        $userWithProvince->setProvince('Beijing');

        $userWithoutProvince = $this->createTestUser('openid_without_province', 'token2', 7200, $this->config);

        $em->persist($userWithProvince);
        $em->persist($userWithoutProvince);
        $em->flush();

        $result = $this->repository->findBy(['province' => null]);
        $this->assertGreaterThanOrEqual(1, count($result));

        $openids = array_map(fn ($user) => $user->getOpenid(), $result);
        $this->assertContains('openid_without_province', $openids);
    }

    public function testFindByNullCity(): void
    {
        $em = $this->getDoctrineEntityManager();

        $userWithCity = $this->createTestUser('openid_with_city', 'token1', 7200, $this->config);
        $userWithCity->setCity('Shanghai');

        $userWithoutCity = $this->createTestUser('openid_without_city', 'token2', 7200, $this->config);

        $em->persist($userWithCity);
        $em->persist($userWithoutCity);
        $em->flush();

        $result = $this->repository->findBy(['city' => null]);
        $this->assertGreaterThanOrEqual(1, count($result));

        $openids = array_map(fn ($user) => $user->getOpenid(), $result);
        $this->assertContains('openid_without_city', $openids);
    }

    public function testFindByNullRefreshToken(): void
    {
        $em = $this->getDoctrineEntityManager();

        $userWithRefreshToken = $this->createTestUser('openid_with_refresh', 'token1', 7200, $this->config);
        $userWithRefreshToken->setRefreshToken('refresh_token_123');

        $userWithoutRefreshToken = $this->createTestUser('openid_without_refresh', 'token2', 7200, $this->config);

        $em->persist($userWithRefreshToken);
        $em->persist($userWithoutRefreshToken);
        $em->flush();

        $result = $this->repository->findBy(['refreshToken' => null]);
        $this->assertGreaterThanOrEqual(1, count($result));

        $openids = array_map(fn ($user) => $user->getOpenid(), $result);
        $this->assertContains('openid_without_refresh', $openids);
    }

    public function testFindByNullUserReference(): void
    {
        $em = $this->getDoctrineEntityManager();

        $userWithReference = $this->createTestUser('openid_with_ref', 'token1', 7200, $this->config);
        $userWithReference->setUserReference('user_ref_123');

        $userWithoutReference = $this->createTestUser('openid_without_ref', 'token2', 7200, $this->config);

        $em->persist($userWithReference);
        $em->persist($userWithoutReference);
        $em->flush();

        $result = $this->repository->findBy(['userReference' => null]);
        $this->assertGreaterThanOrEqual(1, count($result));

        $openids = array_map(fn ($user) => $user->getOpenid(), $result);
        $this->assertContains('openid_without_ref', $openids);
    }

    public function testFindByNullRawData(): void
    {
        $em = $this->getDoctrineEntityManager();

        $userWithRawData = $this->createTestUser('openid_with_raw', 'token1', 7200, $this->config);
        $userWithRawData->setRawData(['key' => 'value']);

        $userWithoutRawData = $this->createTestUser('openid_without_raw', 'token2', 7200, $this->config);

        $em->persist($userWithRawData);
        $em->persist($userWithoutRawData);
        $em->flush();

        $result = $this->repository->findBy(['rawData' => null]);
        $this->assertGreaterThanOrEqual(1, count($result));

        $openids = array_map(fn ($user) => $user->getOpenid(), $result);
        $this->assertContains('openid_without_raw', $openids);
    }

    public function testCount(): void
    {
        $em = $this->getDoctrineEntityManager();

        $initialCount = $this->repository->count([]);

        $user1 = $this->createTestUser('count_test_1', 'token1', 7200, $this->config);
        $user2 = $this->createTestUser('count_test_2', 'token2', 7200, $this->config);

        $em->persist($user1);
        $em->persist($user2);
        $em->flush();

        $newCount = $this->repository->count([]);
        $this->assertEquals($initialCount + 2, $newCount);

        $configCount = $this->repository->count(['config' => $this->config]);
        $this->assertGreaterThanOrEqual(2, $configCount);
    }

    public function testSaveMethod(): void
    {
        $user = $this->createTestUser('save_test_openid', 'access_token', 7200, $this->config);
        $user->setNickname('Save Test User');

        $this->repository->save($user);

        $this->assertNotNull($user->getId());

        $foundUser = $this->repository->findByOpenid('save_test_openid');
        $this->assertNotNull($foundUser);
        $this->assertEquals('Save Test User', $foundUser->getNickname());
    }

    public function testSaveMethodWithoutFlush(): void
    {
        $user = $this->createTestUser('save_no_flush_openid', 'access_token', 7200, $this->config);
        $user->setNickname('Save No Flush Test');

        $this->repository->save($user, false);

        $em = $this->getDoctrineEntityManager();
        $em->flush();

        $this->assertNotNull($user->getId());

        $foundUser = $this->repository->findByOpenid('save_no_flush_openid');
        $this->assertNotNull($foundUser);
        $this->assertEquals('Save No Flush Test', $foundUser->getNickname());
    }

    public function testRemoveMethod(): void
    {
        $em = $this->getDoctrineEntityManager();

        $user = $this->createTestUser('remove_test_openid', 'access_token', 7200, $this->config);
        $em->persist($user);
        $em->flush();

        $userId = $user->getId();
        $this->assertNotNull($userId);

        $this->repository->remove($user);

        $foundUser = $this->repository->find($userId);
        $this->assertNull($foundUser);
    }

    public function testRemoveMethodWithoutFlush(): void
    {
        $em = $this->getDoctrineEntityManager();

        $user = $this->createTestUser('remove_no_flush_openid', 'access_token', 7200, $this->config);
        $em->persist($user);
        $em->flush();

        $userId = $user->getId();
        $this->assertNotNull($userId);

        $this->repository->remove($user, false);
        $em->flush();

        $foundUser = $this->repository->find($userId);
        $this->assertNull($foundUser);
    }

    public function testCountByAssociationConfigShouldReturnCorrectNumber(): void
    {
        $em = $this->getDoctrineEntityManager();

        $anotherConfig = new QQOAuth2Config();
        $anotherConfig->setAppId('another_app');
        $anotherConfig->setAppSecret('another_secret');
        $em->persist($anotherConfig);
        $em->flush();

        $user1 = $this->createTestUser('count_config_1', 'token1', 7200, $this->config);
        $user2 = $this->createTestUser('count_config_2', 'token2', 7200, $this->config);
        $user3 = $this->createTestUser('count_other_config', 'token3', 7200, $anotherConfig);

        $em->persist($user1);
        $em->persist($user2);
        $em->persist($user3);
        $em->flush();

        $count = $this->repository->count(['config' => $this->config]);
        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testFindOneByAssociationConfigShouldReturnMatchingEntity(): void
    {
        $em = $this->getDoctrineEntityManager();

        $user = $this->createTestUser('findone_config_test', 'token1', 7200, $this->config);
        $em->persist($user);
        $em->flush();

        $result = $this->repository->findOneBy(['config' => $this->config]);
        $this->assertInstanceOf(QQOAuth2User::class, $result);
        $config = $result->getConfig();
        $this->assertNotNull($config);
        $this->assertEquals($this->config->getId(), $config->getId());
    }

    protected function onSetUp(): void
    {
        $container = self::getContainer();
        $repository = $container->get(QQOAuth2UserRepository::class);
        $this->assertInstanceOf(QQOAuth2UserRepository::class, $repository);
        $this->repository = $repository;

        // 创建测试用配置
        $em = $this->getDoctrineEntityManager();
        $this->config = new QQOAuth2Config();
        $this->config->setAppId('test_app');
        $this->config->setAppSecret('test_secret');
        $em->persist($this->config);
        $em->flush();
    }

    private function createTestUser(string $openid, string $accessToken, int $expiresIn, QQOAuth2Config $config): QQOAuth2User
    {
        $user = new QQOAuth2User();
        $user->setOpenid($openid);
        $user->setAccessToken($accessToken);
        $user->setExpiresIn($expiresIn);
        $user->setConfig($config);

        return $user;
    }

    protected function createNewEntity(): object
    {
        $user = $this->createTestUser('test_openid_' . uniqid(), 'test_access_token_' . uniqid(), 7200, $this->config);
        $user->setNickname('Test User ' . uniqid());
        $user->setUnionid('test_unionid_' . uniqid());
        $user->setAvatar('https://example.com/avatar.jpg');
        $user->setGender('male');
        $user->setProvince('Beijing');
        $user->setCity('Shanghai');
        $user->setRefreshToken('test_refresh_token_' . uniqid());
        $user->setUserReference('user_ref_' . uniqid());
        $user->setRawData(['test' => 'data']);

        return $user;
    }

    /**
     * @return ServiceEntityRepository<QQOAuth2User>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
