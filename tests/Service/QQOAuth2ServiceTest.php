<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2StateRepository;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

class QQOAuth2ServiceTest extends TestCase
{
    private MockObject|HttpClientInterface $httpClient;
    private MockObject|QQOAuth2ConfigRepository $configRepository;
    private MockObject|QQOAuth2StateRepository $stateRepository;
    private MockObject|QQOAuth2UserRepository $userRepository;
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|UrlGeneratorInterface $urlGenerator;
    private QQOAuth2Service $service;

    public function testGenerateAuthorizationUrl(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setScope('get_user_info');

        $this->configRepository->expects($this->once())
            ->method('findValidConfig')
            ->willReturn($config);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(QQOAuth2State::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('qq_oauth2_callback', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/callback');

        $url = $this->service->generateAuthorizationUrl();

        $this->assertStringContainsString('https://graph.qq.com/oauth2.0/authorize', $url);
        $this->assertStringContainsString('client_id=test_app_id', $url);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fexample.com%2Fcallback', $url);
        $this->assertStringContainsString('scope=get_user_info', $url);
        $this->assertStringContainsString('state=', $url);
    }

    public function testGenerateAuthorizationUrlWithoutConfig(): void
    {
        $this->configRepository->expects($this->once())
            ->method('findValidConfig')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No valid QQ OAuth2 configuration found');

        $this->service->generateAuthorizationUrl();
    }

    public function testHandleCallbackSuccess(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_app_secret');

        $state = new QQOAuth2State('test_state', $config);

        $this->stateRepository->expects($this->once())
            ->method('findValidState')
            ->with('test_state')
            ->willReturn($state);

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('qq_oauth2_callback', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/callback');

        // Mock access token response
        $tokenResponse = $this->createMock(ResponseInterface::class);
        $tokenResponse->expects($this->once())
            ->method('getContent')
            ->willReturn('access_token=test_token&expires_in=7200&refresh_token=refresh_token');

        // Mock openid response
        $openidResponse = $this->createMock(ResponseInterface::class);
        $openidResponse->expects($this->once())
            ->method('getContent')
            ->willReturn('callback( {"client_id":"test_app_id","openid":"test_openid"} );');

        // Mock user info response
        $userInfoResponse = $this->createMock(ResponseInterface::class);
        $userInfoResponse->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'ret' => 0,
                'nickname' => 'Test User',
                'figureurl_qq_2' => 'https://example.com/avatar.jpg',
                'gender' => '男',
                'province' => '北京',
                'city' => '北京'
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
        $this->assertEquals('test_openid', $result->getOpenid());
    }

    public function testHandleCallbackInvalidState(): void
    {
        $this->stateRepository->expects($this->once())
            ->method('findValidState')
            ->with('invalid_state')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired state');

        $this->service->handleCallback('test_code', 'invalid_state');
    }

    public function testGetUserInfoWithValidToken(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');

        $user = new QQOAuth2User('test_openid', 'test_token', 7200, $config);

        $this->userRepository->expects($this->once())
            ->method('findByOpenid')
            ->with('test_openid')
            ->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'ret' => 0,
                'nickname' => 'Updated User',
                'figureurl_qq_2' => 'https://example.com/new_avatar.jpg'
            ]));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->getUserInfo('test_openid');

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['ret']);
        $this->assertEquals('Updated User', $result['nickname']);
    }

    public function testRefreshTokenSuccess(): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id')
            ->setAppSecret('test_app_secret');

        $user = new QQOAuth2User('test_openid', 'old_token', 3600, $config);
        $user->setRefreshToken('refresh_token');

        $this->userRepository->expects($this->once())
            ->method('findByOpenid')
            ->with('test_openid')
            ->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('access_token=new_token&expires_in=7200&refresh_token=new_refresh_token');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->refreshToken('test_openid');

        $this->assertTrue($result);
        $this->assertEquals('new_token', $user->getAccessToken());
        $this->assertEquals(7200, $user->getExpiresIn());
        $this->assertEquals('new_refresh_token', $user->getRefreshToken());
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