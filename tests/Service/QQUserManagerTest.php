<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ApiException;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2Exception;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;
use Tourze\QQConnectOAuth2Bundle\Service\QQApiClient;
use Tourze\QQConnectOAuth2Bundle\Service\QQTokenManager;
use Tourze\QQConnectOAuth2Bundle\Service\QQUserManager;

/**
 * @internal
 */
#[CoversClass(QQUserManager::class)]
final class QQUserManagerTest extends TestCase
{
    /** @var MockObject&QQApiClient */
    private QQApiClient $mockApiClient;

    /** @var MockObject&QQTokenManager */
    private QQTokenManager $mockTokenManager;

    /** @var MockObject&QQOAuth2UserRepository */
    private QQOAuth2UserRepository $mockUserRepository;

    /** @var MockObject&EntityManagerInterface */
    private EntityManagerInterface $mockEntityManager;

    private QQUserManager $userManager;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var MockObject&QQApiClient $mockApiClient */
        $mockApiClient = $this->createMock(QQApiClient::class);
        $this->mockApiClient = $mockApiClient;

        /** @var MockObject&QQTokenManager $mockTokenManager */
        $mockTokenManager = $this->createMock(QQTokenManager::class);
        $this->mockTokenManager = $mockTokenManager;

        /** @var MockObject&QQOAuth2UserRepository $mockUserRepository */
        $mockUserRepository = $this->createMock(QQOAuth2UserRepository::class);
        $this->mockUserRepository = $mockUserRepository;

        /** @var MockObject&EntityManagerInterface $mockEntityManager */
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $this->mockEntityManager = $mockEntityManager;

        $this->userManager = new QQUserManager(
            $this->mockApiClient,
            $this->mockTokenManager,
            $this->mockUserRepository,
            $this->mockEntityManager
        );
    }

    public function testGetUserInfoWithValidUserAndCachedDataShouldReturnCachedData(): void
    {
        // Arrange
        $openid = 'test_openid';
        $cachedData = ['nickname' => 'test_user', 'avatar' => 'test_avatar.jpg'];

        $mockUser = $this->createMock(QQOAuth2User::class);
        $mockUser->method('isTokenExpired')->willReturn(false);
        $mockUser->method('getRawData')->willReturn($cachedData);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('findByOpenid')
            ->with($openid)
            ->willReturn($mockUser)
        ;

        // Act
        $result = $this->userManager->getUserInfo($openid);

        // Assert
        $this->assertEquals($cachedData, $result);
    }

    public function testGetUserInfoWithNonExistentUserShouldThrowException(): void
    {
        // Arrange
        $openid = 'non_existent_openid';

        $this->mockUserRepository
            ->expects($this->once())
            ->method('findByOpenid')
            ->with($openid)
            ->willReturn(null)
        ;

        // Act & Assert
        $this->expectException(QQOAuth2Exception::class);
        $this->expectExceptionMessage('User not found');

        $this->userManager->getUserInfo($openid);
    }

    public function testGetUserInfoWithForceRefreshShouldFetchNewData(): void
    {
        // Arrange
        $openid = 'test_openid';
        $cachedData = ['nickname' => 'old_user'];
        $freshData = ['nickname' => 'new_user', 'avatar' => 'new_avatar.jpg'];

        $mockUser = $this->createMock(QQOAuth2User::class);
        $mockUser->method('isTokenExpired')->willReturn(false);
        $mockUser->method('getRawData')->willReturn($cachedData);
        $mockUser->method('getRefreshToken')->willReturn('refresh_token');
        $mockUser->method('getAccessToken')->willReturn('access_token');

        $mockConfig = $this->createMock(QQOAuth2Config::class);
        $mockConfig->method('getAppId')->willReturn('app_id');
        $mockUser->method('getConfig')->willReturn($mockConfig);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('findByOpenid')
            ->with($openid)
            ->willReturn($mockUser)
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('makeRequest')
            ->willReturn(['content' => json_encode(array_merge($freshData, ['ret' => 0]))])
        ;

        $mockUser->expects($this->once())
            ->method('setRawData')
            ->with(array_merge($freshData, ['ret' => 0]))
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('persist')
            ->with($mockUser)
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('flush')
        ;

        // Act
        $result = $this->userManager->getUserInfo($openid, true);

        // Assert
        $this->assertEquals(array_merge($freshData, ['ret' => 0]), $result);
    }

    public function testFetchUserInfoSuccessShouldReturnUserData(): void
    {
        // Arrange
        $accessToken = 'test_access_token';
        $appId = 'test_app_id';
        $openid = 'test_openid';
        $userData = ['nickname' => 'test_user', 'ret' => 0];

        $this->mockApiClient
            ->expects($this->once())
            ->method('getDefaultHeaders')
            ->with('application/json')
            ->willReturn(['Accept' => 'application/json'])
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('makeRequest')
            ->with(
                'user info request',
                'https://graph.qq.com/user/get_user_info',
                self::callback(function (array $requestOptions) use ($accessToken, $appId, $openid) {
                    $query = $requestOptions['query'] ?? [];
                    if (!is_array($query)) {
                        return false;
                    }

                    return ($query['access_token'] ?? null) === $accessToken
                        && ($query['oauth_consumer_key'] ?? null) === $appId
                        && ($query['openid'] ?? null) === $openid;
                }),
                self::callback(function (array $context) use ($appId) {
                    $contextAppId = $context['app_id'] ?? null;
                    $contextOpenid = $context['openid'] ?? null;

                    return $contextAppId === $appId
                        && is_string($contextOpenid) && str_contains($contextOpenid, '***');
                })
            )
            ->willReturn(['content' => json_encode($userData)])
        ;

        // Act
        $result = $this->userManager->fetchUserInfo($accessToken, $appId, $openid);

        // Assert
        $this->assertEquals($userData, $result);
    }

    public function testFetchUserInfoWithApiErrorShouldThrowException(): void
    {
        // Arrange
        $accessToken = 'test_access_token';
        $appId = 'test_app_id';
        $openid = 'test_openid';
        $errorData = ['ret' => 1, 'msg' => 'API Error'];

        $this->mockApiClient
            ->method('getDefaultHeaders')
            ->willReturn(['Accept' => 'application/json'])
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('makeRequest')
            ->willReturn(['content' => json_encode($errorData)])
        ;

        // Act & Assert
        $this->expectException(QQOAuth2ApiException::class);
        $this->expectExceptionMessage('Failed to get user info: 1 - API Error');

        $this->userManager->fetchUserInfo($accessToken, $appId, $openid);
    }

    public function testUpdateOrCreateUserWithNewUserShouldCreateUser(): void
    {
        // Arrange
        $userData = [
            'openid' => 'new_openid',
            'access_token' => 'access_token',
            'expires_in' => 3600,
            'nickname' => 'new_user',
        ];

        $mockConfig = $this->createMock(QQOAuth2Config::class);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('findByOpenid')
            ->with($userData['openid'])
            ->willReturn(null)
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(QQOAuth2User::class))
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('flush')
        ;

        // Act
        $result = $this->userManager->updateOrCreateUser($userData, $mockConfig);

        // Assert
        $this->assertInstanceOf(QQOAuth2User::class, $result);
    }

    public function testUpdateOrCreateUserWithExistingUserShouldUpdateUser(): void
    {
        // Arrange
        $userData = [
            'openid' => 'existing_openid',
            'access_token' => 'new_access_token',
            'expires_in' => 7200,
            'nickname' => 'updated_user',
        ];

        $mockConfig = $this->createMock(QQOAuth2Config::class);
        $mockUser = $this->createMock(QQOAuth2User::class);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('findByOpenid')
            ->with($userData['openid'])
            ->willReturn($mockUser)
        ;

        $mockUser
            ->expects($this->once())
            ->method('setAccessToken')
            ->with($userData['access_token'])
        ;

        $mockUser
            ->expects($this->once())
            ->method('setExpiresIn')
            ->with($userData['expires_in'])
        ;

        $mockUser
            ->expects($this->once())
            ->method('setRawData')
            ->with($userData)
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('persist')
            ->with($mockUser)
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('flush')
        ;

        // Act
        $result = $this->userManager->updateOrCreateUser($userData, $mockConfig);

        // Assert
        $this->assertSame($mockUser, $result);
    }

    public function testMergeUserDataShouldMergeAllArrays(): void
    {
        // Arrange
        $tokenData = [
            'access_token' => 'token123',
            'expires_in' => '3600',
            'complex_value' => ['nested' => 'data'],
        ];
        $openidData = [
            'openid' => 'openid123',
            'client_id' => 'client123',
        ];
        $userInfo = [
            'nickname' => 'testuser',
            'avatar' => 'avatar.jpg',
            'nested_array' => ['key' => 'value'],
        ];

        // Act
        $result = $this->userManager->mergeUserData($tokenData, $openidData, $userInfo);

        // Assert
        $expected = [
            'access_token' => 'token123',
            'expires_in' => '3600',
            'complex_value' => ['nested' => 'data'],
            'openid' => 'openid123',
            'client_id' => 'client123',
            'nickname' => 'testuser',
            'avatar' => 'avatar.jpg',
            'nested_array' => ['key' => 'value'],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testFetchUserInfoWithMalformedJsonShouldThrowException(): void
    {
        // Arrange
        $accessToken = 'test_access_token';
        $appId = 'test_app_id';
        $openid = 'test_openid';

        $this->mockApiClient
            ->method('getDefaultHeaders')
            ->willReturn(['Accept' => 'application/json'])
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('makeRequest')
            ->willReturn(['content' => 'invalid json {'])
        ;

        // Act & Assert
        $this->expectException(QQOAuth2ApiException::class);
        $this->expectExceptionMessage('Failed to parse JSON response');

        $this->userManager->fetchUserInfo($accessToken, $appId, $openid);
    }

    public function testGetUserInfoWithExpiredTokenShouldRefreshToken(): void
    {
        // Arrange
        $openid = 'test_openid';
        $freshData = ['nickname' => 'refreshed_user'];

        $mockUser = $this->createMock(QQOAuth2User::class);
        $mockUser->method('isTokenExpired')->willReturn(true);
        $mockUser->method('getRefreshToken')->willReturn('refresh_token');
        $mockUser->method('getRawData')->willReturn(null);
        $mockUser->method('getAccessToken')->willReturn('access_token');

        $mockConfig = $this->createMock(QQOAuth2Config::class);
        $mockConfig->method('getAppId')->willReturn('app_id');
        $mockUser->method('getConfig')->willReturn($mockConfig);

        $mockRefreshedUser = $this->createMock(QQOAuth2User::class);
        $mockRefreshedUser->method('getAccessToken')->willReturn('new_access_token');
        $mockRefreshedUser->method('getConfig')->willReturn($mockConfig);

        $this->mockUserRepository
            ->expects($this->exactly(2))
            ->method('findByOpenid')
            ->with($openid)
            ->willReturnOnConsecutiveCalls($mockUser, $mockRefreshedUser)
        ;

        $this->mockTokenManager
            ->expects($this->once())
            ->method('refreshToken')
            ->with($openid)
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('makeRequest')
            ->willReturn(['content' => json_encode(array_merge($freshData, ['ret' => 0]))])
        ;

        $mockRefreshedUser->expects($this->once())
            ->method('setRawData')
            ->with(array_merge($freshData, ['ret' => 0]))
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('persist')
            ->with($mockRefreshedUser)
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('flush')
        ;

        // Act
        $result = $this->userManager->getUserInfo($openid);

        // Assert
        $this->assertEquals(array_merge($freshData, ['ret' => 0]), $result);
    }

    public function testGetUserInfoWithExpiredTokenButNoRefreshTokenShouldUseCachedData(): void
    {
        // Arrange
        $openid = 'test_openid';
        $cachedData = ['nickname' => 'cached_user'];

        $mockUser = $this->createMock(QQOAuth2User::class);
        $mockUser->method('isTokenExpired')->willReturn(false);
        $mockUser->method('getRefreshToken')->willReturn(null);
        $mockUser->method('getRawData')->willReturn($cachedData);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('findByOpenid')
            ->with($openid)
            ->willReturn($mockUser)
        ;

        $this->mockTokenManager
            ->expects($this->never())
            ->method('refreshToken')
        ;

        // Act
        $result = $this->userManager->getUserInfo($openid);

        // Assert
        $this->assertEquals($cachedData, $result);
    }

    public function testGetUserInfoWithTokenRefreshFailureShouldThrowException(): void
    {
        // Arrange
        $openid = 'test_openid';

        $mockUser = $this->createMock(QQOAuth2User::class);
        $mockUser->method('isTokenExpired')->willReturn(true);
        $mockUser->method('getRefreshToken')->willReturn('refresh_token');
        $mockUser->method('getRawData')->willReturn(null);

        $this->mockUserRepository
            ->expects($this->exactly(2))
            ->method('findByOpenid')
            ->with($openid)
            ->willReturnOnConsecutiveCalls($mockUser, null)
        ;

        $this->mockTokenManager
            ->expects($this->once())
            ->method('refreshToken')
            ->with($openid)
        ;

        // Act & Assert
        $this->expectException(QQOAuth2Exception::class);
        $this->expectExceptionMessage('User not found after token refresh');

        $this->userManager->getUserInfo($openid);
    }
}
