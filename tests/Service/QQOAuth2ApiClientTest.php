<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2ApiClient;

/**
 * @internal
 */
#[CoversClass(QQOAuth2ApiClient::class)]
final class QQOAuth2ApiClientTest extends TestCase
{
    private QQOAuth2ApiClient $apiClient;

    /** @var MockObject&HttpClientInterface */
    private HttpClientInterface $httpClient;

    /** @var MockObject&LoggerInterface */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        /** @var MockObject&HttpClientInterface $httpClient */
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $httpClient;

        /** @var MockObject&LoggerInterface $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $this->logger = $logger;

        $this->apiClient = new QQOAuth2ApiClient($this->httpClient, $this->logger);
    }

    public function testExchangeCodeForTokenSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('access_token=test_token&expires_in=7200');
        $response->method('getStatusCode')->willReturn(200);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response)
        ;

        $result = $this->apiClient->exchangeCodeForToken('test_code', 'test_app_id', 'test_secret', 'http://test.com/callback');

        $this->assertArrayHasKey('access_token', $result);
        $this->assertEquals('test_token', $result['access_token']);
    }

    public function testGetOpenidSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('callback({"openid":"test_openid","client_id":"test_app_id"});');
        $response->method('getStatusCode')->willReturn(200);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response)
        ;

        $result = $this->apiClient->getOpenid('test_access_token');

        $this->assertArrayHasKey('openid', $result);
        $this->assertEquals('test_openid', $result['openid']);
    }

    public function testFetchUserInfoSuccess(): void
    {
        $userInfoData = ['ret' => 0, 'nickname' => 'test_user', 'figureurl_qq_1' => 'http://test.com/avatar.jpg'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(json_encode($userInfoData));
        $response->method('getStatusCode')->willReturn(200);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response)
        ;

        $result = $this->apiClient->fetchUserInfo('test_access_token', 'test_app_id', 'test_openid');

        $this->assertEquals(0, $result['ret']);
        $this->assertEquals('test_user', $result['nickname']);
    }

    public function testRefreshTokenSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('access_token=new_token&expires_in=7200&refresh_token=new_refresh_token');
        $response->method('getStatusCode')->willReturn(200);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response)
        ;

        $result = $this->apiClient->refreshToken('test_refresh_token', 'test_app_id', 'test_secret');

        $this->assertArrayHasKey('access_token', $result);
        $this->assertEquals('new_token', $result['access_token']);
    }
}
