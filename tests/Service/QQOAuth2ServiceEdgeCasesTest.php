<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2StateRepository;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

class QQOAuth2ServiceEdgeCasesTest extends TestCase
{
    private MockObject|HttpClientInterface $httpClient;
    private MockObject|QQOAuth2ConfigRepository $configRepository;
    private MockObject|QQOAuth2StateRepository $stateRepository;
    private MockObject|QQOAuth2UserRepository $userRepository;
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|UrlGeneratorInterface $urlGenerator;
    private QQOAuth2Service $service;

    public function testHandleCallbackWithExpiredState(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret');

        $expiredState = new QQOAuth2State('expired_state', $config, -1); // Already expired

        $this->stateRepository->expects($this->once())
            ->method('findValidState')
            ->with('expired_state')
            ->willReturn($expiredState);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired state');

        $this->service->handleCallback('test_code', 'expired_state');
    }

    public function testHandleCallbackWithUsedState(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret');

        $usedState = new QQOAuth2State('used_state', $config);
        $usedState->markAsUsed();

        $this->stateRepository->expects($this->once())
            ->method('findValidState')
            ->with('used_state')
            ->willReturn($usedState);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired state');

        $this->service->handleCallback('test_code', 'used_state');
    }

    public function testHandleCallbackWithNetworkError(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret');

        $state = new QQOAuth2State('test_state', $config);

        $this->stateRepository->expects($this->once())
            ->method('findValidState')
            ->willReturn($state);

        $this->entityManager->expects($this->any())
            ->method('persist');
        $this->entityManager->expects($this->any())
            ->method('flush');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('https://example.com/callback');

        $exception = $this->createMock(TransportExceptionInterface::class);
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException($exception);

        $this->expectException(TransportExceptionInterface::class);

        $this->service->handleCallback('test_code', 'test_state');
    }

    public function testHandleCallbackWithInvalidTokenResponse(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret');

        $state = new QQOAuth2State('test_state', $config);

        $this->stateRepository->expects($this->once())
            ->method('findValidState')
            ->willReturn($state);

        $this->entityManager->expects($this->any())
            ->method('persist');
        $this->entityManager->expects($this->any())
            ->method('flush');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('https://example.com/callback');

        // Mock error response from QQ
        $tokenResponse = $this->createMock(ResponseInterface::class);
        $tokenResponse->method('getContent')
            ->willReturn('error=invalid_grant&error_description=Invalid authorization code');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($tokenResponse);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to exchange code for token: invalid_grant - Invalid authorization code');

        $this->service->handleCallback('invalid_code', 'test_state');
    }

    public function testHandleCallbackWithMalformedOpenidResponse(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret');

        $state = new QQOAuth2State('test_state', $config);

        $this->stateRepository->expects($this->once())
            ->method('findValidState')
            ->willReturn($state);

        $this->entityManager->expects($this->any())
            ->method('persist');
        $this->entityManager->expects($this->any())
            ->method('flush');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('https://example.com/callback');

        // Mock token response
        $tokenResponse = $this->createMock(ResponseInterface::class);
        $tokenResponse->method('getContent')
            ->willReturn('access_token=test_token&expires_in=7200');

        // Mock malformed openid response
        $openidResponse = $this->createMock(ResponseInterface::class);
        $openidResponse->method('getContent')
            ->willReturn('not_a_jsonp_response');

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($tokenResponse, $openidResponse);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid openid response format');

        $this->service->handleCallback('test_code', 'test_state');
    }

    public function testHandleCallbackWithInvalidJsonInOpenidResponse(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret');

        $state = new QQOAuth2State('test_state', $config);

        $this->stateRepository->expects($this->once())
            ->method('findValidState')
            ->willReturn($state);

        $this->entityManager->expects($this->any())
            ->method('persist');
        $this->entityManager->expects($this->any())
            ->method('flush');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('https://example.com/callback');

        // Mock token response
        $tokenResponse = $this->createMock(ResponseInterface::class);
        $tokenResponse->method('getContent')
            ->willReturn('access_token=test_token&expires_in=7200');

        // Mock openid response with invalid JSON
        $openidResponse = $this->createMock(ResponseInterface::class);
        $openidResponse->method('getContent')
            ->willReturn('callback( {invalid json} );');

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($tokenResponse, $openidResponse);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse openid response');

        $this->service->handleCallback('test_code', 'test_state');
    }

    public function testGetUserInfoWithNonExistentUser(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByOpenid')
            ->with('non_existent_openid')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not found');

        $this->service->getUserInfo('non_existent_openid');
    }

    public function testGetUserInfoWithExpiredTokenAndNoRefreshToken(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');

        $user = new QQOAuth2User('test_openid', 'expired_token', -1, $config); // Expired
        // No refresh token set

        $this->userRepository->expects($this->once())
            ->method('findByOpenid')
            ->with('test_openid')
            ->willReturn($user);

        // Mock user info response
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'ret' => 0,
                'nickname' => 'Test User'
            ]));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->entityManager->expects($this->once())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->getUserInfo('test_openid');

        $this->assertEquals('Test User', $result['nickname']);
    }

    public function testGetUserInfoWithApiError(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');

        $user = new QQOAuth2User('test_openid', 'test_token', 7200, $config);

        $this->userRepository->expects($this->once())
            ->method('findByOpenid')
            ->willReturn($user);

        // Mock error response from QQ API
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'ret' => 1002,
                'msg' => 'Invalid access token'
            ]));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to get user info: 1002 - Invalid access token');

        $this->service->getUserInfo('test_openid');
    }

    public function testRefreshTokenWithNetworkError(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret');

        $user = new QQOAuth2User('test_openid', 'old_token', 3600, $config);
        $user->setRefreshToken('refresh_token');

        $this->userRepository->expects($this->once())
            ->method('findByOpenid')
            ->willReturn($user);

        $exception = $this->createMock(TransportExceptionInterface::class);
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException($exception);

        $result = $this->service->refreshToken('test_openid');

        $this->assertFalse($result);
    }

    public function testCleanupExpiredStatesWithLargeDataset(): void
    {
        // Test that cleanup can handle large number of expired states
        $this->stateRepository->expects($this->once())
            ->method('cleanupExpiredStates')
            ->willReturn(10000); // Simulate cleaning up 10,000 expired states

        $count = $this->service->cleanupExpiredStates();

        $this->assertEquals(10000, $count);
    }

    public function testGenerateAuthorizationUrlWithEmptyScope(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret');
        // No scope set

        $this->configRepository->expects($this->once())
            ->method('findValidConfig')
            ->willReturn($config);

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('https://example.com/callback');

        $this->entityManager->expects($this->once())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $url = $this->service->generateAuthorizationUrl();

        // Should use default scope
        $this->assertStringContainsString('scope=get_user_info', $url);
    }

    public function testHandleCallbackWithMissingUserInfoFields(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_secret');

        $state = new QQOAuth2State('test_state', $config);

        $this->stateRepository->expects($this->once())
            ->method('findValidState')
            ->willReturn($state);

        $this->entityManager->expects($this->any())
            ->method('persist');
        $this->entityManager->expects($this->any())
            ->method('flush');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('https://example.com/callback');

        // Mock responses
        $tokenResponse = $this->createMock(ResponseInterface::class);
        $tokenResponse->method('getContent')
            ->willReturn('access_token=test_token&expires_in=7200');

        $openidResponse = $this->createMock(ResponseInterface::class);
        $openidResponse->method('getContent')
            ->willReturn('callback( {"client_id":"test_app_id","openid":"test_openid"} );');

        // User info response with minimal fields
        $userInfoResponse = $this->createMock(ResponseInterface::class);
        $userInfoResponse->method('getContent')
            ->willReturn(json_encode([
                'ret' => 0,
                // Only ret field, all other fields missing
            ]));

        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnOnConsecutiveCalls($tokenResponse, $openidResponse, $userInfoResponse);

        $user = new QQOAuth2User('test_openid', 'test_token', 7200, $config);
        $this->userRepository->expects($this->once())
            ->method('updateOrCreate')
            ->willReturn($user);

        $result = $this->service->handleCallback('test_code', 'test_state');

        $this->assertInstanceOf(QQOAuth2User::class, $result);
        $this->assertNull($result->getNickname());
        $this->assertNull($result->getAvatar());
    }

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->configRepository = $this->createMock(QQOAuth2ConfigRepository::class);
        $this->stateRepository = $this->createMock(QQOAuth2StateRepository::class);
        $this->userRepository = $this->createMock(QQOAuth2UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->service = new QQOAuth2Service(
            $this->httpClient,
            $this->configRepository,
            $this->stateRepository,
            $this->userRepository,
            $this->entityManager,
            $this->urlGenerator
        );
    }
}