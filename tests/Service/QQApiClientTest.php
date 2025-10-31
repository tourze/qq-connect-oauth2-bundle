<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ApiException;
use Tourze\QQConnectOAuth2Bundle\Service\QQApiClient;

/**
 * @internal
 */
#[CoversClass(QQApiClient::class)]
final class QQApiClientTest extends TestCase
{
    /** @var MockObject&HttpClientInterface */
    private HttpClientInterface $mockHttpClient;

    /** @var MockObject&LoggerInterface */
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var MockObject&HttpClientInterface $mockHttpClient */
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $this->mockHttpClient = $mockHttpClient;

        /** @var MockObject&LoggerInterface $mockLogger */
        $mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockLogger = $mockLogger;
    }

    public function testMakeRequestWithSuccessfulResponseShouldReturnContentAndStatusCode(): void
    {
        // Arrange
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn('test_content');
        $mockResponse->method('getStatusCode')->willReturn(200);

        $this->mockHttpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://example.com/api', [
                'timeout' => 30,
                'query' => ['param' => 'value'],
            ])
            ->willReturn($mockResponse)
        ;

        $client = new QQApiClient($this->mockHttpClient, $this->mockLogger);

        // Act
        $result = $client->makeRequest(
            'test operation',
            'https://example.com/api',
            ['query' => ['param' => 'value']],
            ['extra' => 'context']
        );

        // Assert
        $this->assertEquals(['content' => 'test_content', 'status_code' => 200], $result);
    }

    public function testMakeRequestWithHttpExceptionShouldThrowQQOAuth2ApiException(): void
    {
        // Arrange
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(400);

        $httpException = $this->createMock(HttpExceptionInterface::class);
        $httpException->method('getResponse')->willReturn($mockResponse);

        $this->mockHttpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException($httpException)
        ;

        $client = new QQApiClient($this->mockHttpClient, $this->mockLogger);

        // Act & Assert
        $this->expectException(QQOAuth2ApiException::class);
        $this->expectExceptionMessage('Failed to communicate with QQ API for test operation');

        $client->makeRequest(
            'test operation',
            'https://example.com/api',
            ['query' => ['param' => 'value']]
        );
    }

    public function testMakeRequestWithNetworkErrorShouldThrowQQOAuth2ApiException(): void
    {
        // Arrange
        $networkException = new \RuntimeException('Network timeout');

        $this->mockHttpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException($networkException)
        ;

        $client = new QQApiClient($this->mockHttpClient, $this->mockLogger);

        // Act & Assert
        $this->expectException(QQOAuth2ApiException::class);
        $this->expectExceptionMessage('Network error during test operation');

        $client->makeRequest(
            'test operation',
            'https://example.com/api',
            ['query' => ['param' => 'value']]
        );
    }

    public function testMakeRequestWithoutLoggerShouldNotCrash(): void
    {
        // Arrange
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn('test_content');
        $mockResponse->method('getStatusCode')->willReturn(200);

        $this->mockHttpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse)
        ;

        $client = new QQApiClient($this->mockHttpClient, null);

        // Act
        $result = $client->makeRequest(
            'test operation',
            'https://example.com/api',
            ['query' => ['param' => 'value']]
        );

        // Assert
        $this->assertEquals(['content' => 'test_content', 'status_code' => 200], $result);
    }

    public function testGetDefaultHeadersWithDefaultAcceptShouldReturnJsonHeaders(): void
    {
        // Arrange
        $client = new QQApiClient($this->mockHttpClient);

        // Act
        $headers = $client->getDefaultHeaders();

        // Assert
        $expected = [
            'User-Agent' => 'QQConnectOAuth2Bundle/1.0',
            'Accept' => 'application/json',
        ];
        $this->assertEquals($expected, $headers);
    }

    public function testGetDefaultHeadersWithCustomAcceptShouldReturnCustomHeaders(): void
    {
        // Arrange
        $client = new QQApiClient($this->mockHttpClient);

        // Act
        $headers = $client->getDefaultHeaders('application/x-www-form-urlencoded');

        // Assert
        $expected = [
            'User-Agent' => 'QQConnectOAuth2Bundle/1.0',
            'Accept' => 'application/x-www-form-urlencoded',
        ];
        $this->assertEquals($expected, $headers);
    }

    public function testMakeRequestWithRequestOptionsMergesShouldMergeWithDefaults(): void
    {
        // Arrange
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getContent')->willReturn('test_content');
        $mockResponse->method('getStatusCode')->willReturn(200);

        $this->mockHttpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://example.com/api', [
                'timeout' => 30,
                'query' => ['param' => 'value'],
                'headers' => ['Custom-Header' => 'value'],
            ])
            ->willReturn($mockResponse)
        ;

        $client = new QQApiClient($this->mockHttpClient);

        // Act
        $result = $client->makeRequest(
            'test operation',
            'https://example.com/api',
            [
                'query' => ['param' => 'value'],
                'headers' => ['Custom-Header' => 'value'],
            ]
        );

        // Assert
        $this->assertEquals(['content' => 'test_content', 'status_code' => 200], $result);
    }
}
