<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ApiException;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;
use Tourze\QQConnectOAuth2Bundle\Service\QQApiClient;
use Tourze\QQConnectOAuth2Bundle\Service\QQTokenManager;

/**
 * @internal
 */
#[CoversClass(QQTokenManager::class)]
final class QQTokenManagerTest extends TestCase
{
    /** @var MockObject&QQApiClient */
    private QQApiClient $mockApiClient;

    /** @var MockObject&QQOAuth2UserRepository */
    private QQOAuth2UserRepository $mockUserRepository;

    /** @var MockObject&EntityManagerInterface */
    private EntityManagerInterface $mockEntityManager;

    /** @var MockObject&LoggerInterface */
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var MockObject&QQApiClient $mockApiClient */
        $mockApiClient = $this->createMock(QQApiClient::class);
        $this->mockApiClient = $mockApiClient;

        /** @var MockObject&QQOAuth2UserRepository $mockUserRepository */
        $mockUserRepository = $this->createMock(QQOAuth2UserRepository::class);
        $this->mockUserRepository = $mockUserRepository;

        /** @var MockObject&EntityManagerInterface $mockEntityManager */
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $this->mockEntityManager = $mockEntityManager;

        /** @var MockObject&LoggerInterface $mockLogger */
        $mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockLogger = $mockLogger;
    }

    public function testExchangeCodeForTokenWithSuccessfulResponseShouldReturnTokenData(): void
    {
        // Arrange
        $code = 'auth_code_123';
        $appId = 'test_app_id';
        $appSecret = 'test_app_secret';
        $redirectUri = 'https://example.com/callback';
        $responseContent = 'access_token=test_token&expires_in=7200&refresh_token=test_refresh';

        $this->mockApiClient
            ->expects($this->once())
            ->method('getDefaultHeaders')
            ->with('application/x-www-form-urlencoded')
            ->willReturn(['Accept' => 'application/x-www-form-urlencoded'])
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('makeRequest')
            ->with(
                'token exchange',
                'https://graph.qq.com/oauth2.0/token',
                [
                    'query' => [
                        'grant_type' => 'authorization_code',
                        'client_id' => $appId,
                        'client_secret' => $appSecret,
                        'code' => $code,
                        'redirect_uri' => $redirectUri,
                    ],
                    'headers' => ['Accept' => 'application/x-www-form-urlencoded'],
                ],
                ['app_id' => $appId, 'redirect_uri' => $redirectUri]
            )
            ->willReturn(['content' => $responseContent, 'status_code' => 200])
        ;

        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act
        $result = $tokenManager->exchangeCodeForToken($code, $appId, $appSecret, $redirectUri);

        // Assert
        $this->assertEquals('test_token', $result['access_token']);
        $this->assertEquals('7200', $result['expires_in']);
        $this->assertEquals('test_refresh', $result['refresh_token']);
    }

    public function testExchangeCodeForTokenWithApiErrorShouldThrowException(): void
    {
        // Arrange
        $responseContent = 'error=invalid_grant&error_description=Invalid+authorization+code';

        $this->mockApiClient
            ->expects($this->once())
            ->method('getDefaultHeaders')
            ->willReturn(['Accept' => 'application/x-www-form-urlencoded'])
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('makeRequest')
            ->willReturn(['content' => $responseContent, 'status_code' => 400])
        ;

        $this->mockLogger
            ->expects($this->once())
            ->method('warning')
            ->with('QQ OAuth2 token exchange API error', self::arrayHasKey('error'))
        ;

        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act & Assert
        $this->expectException(QQOAuth2ApiException::class);
        $this->expectExceptionMessage('Failed to exchange code for token: invalid_grant - Invalid authorization code');

        $tokenManager->exchangeCodeForToken('invalid_code', 'app_id', 'secret', 'redirect');
    }

    public function testExchangeCodeForTokenWithMissingAccessTokenShouldThrowException(): void
    {
        // Arrange
        $responseContent = 'expires_in=7200'; // Missing access_token

        $this->mockApiClient
            ->expects($this->once())
            ->method('getDefaultHeaders')
            ->willReturn(['Accept' => 'application/x-www-form-urlencoded'])
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('makeRequest')
            ->willReturn(['content' => $responseContent, 'status_code' => 200])
        ;

        $this->mockLogger
            ->expects($this->once())
            ->method('error')
            ->with('QQ OAuth2 no access token received', self::arrayHasKey('response'))
        ;

        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act & Assert
        $this->expectException(QQOAuth2ApiException::class);
        $this->expectExceptionMessage('No access token received from QQ API');

        $tokenManager->exchangeCodeForToken('code', 'app_id', 'secret', 'redirect');
    }

    public function testGetOpenidWithSuccessfulResponseShouldReturnOpenidData(): void
    {
        // Arrange
        $accessToken = 'test_access_token';
        $responseContent = 'callback( {"client_id":"test_app_id","openid":"test_openid"} );';

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
                'openid request',
                'https://graph.qq.com/oauth2.0/me',
                [
                    'query' => ['access_token' => $accessToken],
                    'headers' => ['Accept' => 'application/json'],
                ]
            )
            ->willReturn(['content' => $responseContent, 'status_code' => 200])
        ;

        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act
        $result = $tokenManager->getOpenid($accessToken);

        // Assert
        $this->assertEquals('test_app_id', $result['client_id']);
        $this->assertEquals('test_openid', $result['openid']);
    }

    public function testGetOpenidWithInvalidFormatShouldThrowException(): void
    {
        // Arrange
        $responseContent = 'invalid_response_format';

        $this->mockApiClient
            ->expects($this->once())
            ->method('getDefaultHeaders')
            ->willReturn(['Accept' => 'application/json'])
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('makeRequest')
            ->willReturn(['content' => $responseContent, 'status_code' => 200])
        ;

        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act & Assert
        $this->expectException(QQOAuth2ApiException::class);
        $this->expectExceptionMessage('Invalid openid response format');

        $tokenManager->getOpenid('test_token');
    }

    public function testGetOpenidWithApiErrorShouldThrowException(): void
    {
        // Arrange
        $responseContent = 'callback( {"error":"invalid_token","error_description":"Invalid access token"} );';

        $this->mockApiClient
            ->expects($this->once())
            ->method('getDefaultHeaders')
            ->willReturn(['Accept' => 'application/json'])
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('makeRequest')
            ->willReturn(['content' => $responseContent, 'status_code' => 200])
        ;

        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act & Assert
        $this->expectException(QQOAuth2ApiException::class);
        $this->expectExceptionMessage('Failed to get openid: invalid_token - Invalid access token');

        $tokenManager->getOpenid('invalid_token');
    }

    public function testRefreshTokenWithNonExistentUserShouldReturnFalse(): void
    {
        // Arrange
        $openid = 'non_existent_openid';

        $this->mockUserRepository
            ->expects($this->once())
            ->method('findByOpenid')
            ->with($openid)
            ->willReturn(null)
        ;

        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act
        $result = $tokenManager->refreshToken($openid);

        // Assert
        $this->assertFalse($result);
    }

    public function testRefreshTokenWithNoRefreshTokenShouldReturnFalse(): void
    {
        // Arrange
        $openid = 'test_openid';
        $mockUser = $this->createMock(QQOAuth2User::class);
        $mockUser->method('getRefreshToken')->willReturn(null);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('findByOpenid')
            ->with($openid)
            ->willReturn($mockUser)
        ;

        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act
        $result = $tokenManager->refreshToken($openid);

        // Assert
        $this->assertFalse($result);
    }

    public function testRefreshTokenWithValidUserShouldPerformRefresh(): void
    {
        // Arrange
        $openid = 'test_openid';
        $mockConfig = $this->createMock(QQOAuth2Config::class);
        $mockConfig->method('getAppId')->willReturn('test_app_id');
        $mockConfig->method('getAppSecret')->willReturn('test_secret');

        $mockUser = $this->createMock(QQOAuth2User::class);
        $mockUser->method('getRefreshToken')->willReturn('test_refresh_token');
        $mockUser->method('getConfig')->willReturn($mockConfig);

        $responseContent = 'access_token=new_token&expires_in=7200';

        $this->mockUserRepository
            ->expects($this->once())
            ->method('findByOpenid')
            ->with($openid)
            ->willReturn($mockUser)
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('getDefaultHeaders')
            ->willReturn(['Accept' => 'application/x-www-form-urlencoded'])
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('makeRequest')
            ->willReturn(['content' => $responseContent, 'status_code' => 200])
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

        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act
        $result = $tokenManager->refreshToken($openid);

        // Assert
        $this->assertTrue($result);
    }

    public function testRefreshExpiredTokensWithNoExpiredUsersShouldReturnZero(): void
    {
        // Arrange
        $this->mockUserRepository
            ->expects($this->once())
            ->method('findExpiredTokenUsers')
            ->willReturn([])
        ;

        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act
        $result = $tokenManager->refreshExpiredTokens();

        // Assert
        $this->assertEquals(0, $result);
    }

    public function testBulkUpdateTokensWithEmptyArrayShouldReturnZero(): void
    {
        // Arrange
        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act
        $result = $tokenManager->bulkUpdateTokens([]);

        // Assert
        $this->assertEquals(0, $result);
    }

    public function testBulkUpdateTokensWithValidDataShouldUpdateUsers(): void
    {
        // Arrange
        $userData = [
            [
                'openid' => 'test_openid_1',
                'access_token' => 'token1',
                'expires_in' => 7200,
            ],
            [
                'openid' => 'test_openid_2',
                'access_token' => 'token2',
                'expires_in' => 3600,
                'refresh_token' => 'refresh2',
            ],
        ];

        $mockUser1 = $this->createMock(QQOAuth2User::class);

        $mockUser2 = $this->createMock(QQOAuth2User::class);

        $this->mockUserRepository
            ->expects($this->exactly(2))
            ->method('findByOpenid')
            ->willReturnOnConsecutiveCalls($mockUser1, $mockUser2)
        ;

        $this->mockEntityManager
            ->expects($this->exactly(2))
            ->method('persist')
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('flush')
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('clear')
        ;

        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act
        $result = $tokenManager->bulkUpdateTokens($userData);

        // Assert
        $this->assertEquals(2, $result);
    }

    public function testBulkUpdateTokensWithNonExistentUserShouldSkipUser(): void
    {
        // Arrange
        $userData = [
            [
                'openid' => 'non_existent_openid',
                'access_token' => 'token1',
                'expires_in' => 7200,
            ],
        ];

        $this->mockUserRepository
            ->expects($this->once())
            ->method('findByOpenid')
            ->with('non_existent_openid')
            ->willReturn(null)
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('flush')
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('clear')
        ;

        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act
        $result = $tokenManager->bulkUpdateTokens($userData);

        // Assert
        $this->assertEquals(0, $result);
    }

    public function testRefreshTokenWithErrorResponseShouldLogAndReturnFalse(): void
    {
        // Arrange
        $openid = 'test_openid';
        $mockConfig = $this->createMock(QQOAuth2Config::class);
        $mockConfig->method('getAppId')->willReturn('test_app_id');
        $mockConfig->method('getAppSecret')->willReturn('test_secret');

        $mockUser = $this->createMock(QQOAuth2User::class);
        $mockUser->method('getRefreshToken')->willReturn('invalid_refresh_token');
        $mockUser->method('getConfig')->willReturn($mockConfig);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('findByOpenid')
            ->willReturn($mockUser)
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('getDefaultHeaders')
            ->willReturn(['Accept' => 'application/x-www-form-urlencoded'])
        ;

        $this->mockApiClient
            ->expects($this->once())
            ->method('makeRequest')
            ->willThrowException(new QQOAuth2ApiException('Failed to exchange code for token: invalid_grant - '))
        ;

        $this->mockLogger
            ->expects($this->once())
            ->method('error')
            ->with('QQ OAuth2 refresh token error', self::anything())
        ;

        $tokenManager = new QQTokenManager(
            $this->mockApiClient,
            $this->mockUserRepository,
            $this->mockEntityManager,
            $this->mockLogger
        );

        // Act
        $result = $tokenManager->refreshToken($openid);

        // Assert
        $this->assertFalse($result);
    }
}
