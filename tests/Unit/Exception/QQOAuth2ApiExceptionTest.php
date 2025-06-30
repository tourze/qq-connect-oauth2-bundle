<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ApiException;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2Exception;

class QQOAuth2ApiExceptionTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $message = 'API request failed';
        $code = 500;
        $previous = new \Exception('Previous exception');
        $apiEndpoint = 'https://graph.qq.com/user/get_user_info';
        $apiResponse = [
            'ret' => -1,
            'msg' => 'system error',
            'data' => null
        ];
        
        $exception = new QQOAuth2ApiException($message, $code, $previous, $apiEndpoint, $apiResponse);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals($apiEndpoint, $exception->getApiEndpoint());
        $this->assertEquals($apiResponse, $exception->getApiResponse());
        
        // Verify context is set correctly
        $expectedContext = [
            'api_endpoint' => $apiEndpoint,
            'api_response' => $apiResponse,
        ];
        $this->assertEquals($expectedContext, $exception->getContext());
    }
    
    public function testConstructorWithMinimalParameters(): void
    {
        $exception = new QQOAuth2ApiException();
        
        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getApiEndpoint());
        $this->assertNull($exception->getApiResponse());
        
        $expectedContext = [
            'api_endpoint' => null,
            'api_response' => null,
        ];
        $this->assertEquals($expectedContext, $exception->getContext());
    }
    
    public function testGetApiEndpoint(): void
    {
        $endpoint = 'https://graph.qq.com/oauth2.0/token';
        $exception = new QQOAuth2ApiException('Error', 0, null, $endpoint);
        
        $this->assertEquals($endpoint, $exception->getApiEndpoint());
    }
    
    public function testGetApiResponse(): void
    {
        $response = [
            'error' => 100000,
            'error_description' => 'invalid client_id'
        ];
        $exception = new QQOAuth2ApiException('Error', 0, null, null, $response);
        
        $this->assertEquals($response, $exception->getApiResponse());
    }
    
    public function testExtendsQQOAuth2Exception(): void
    {
        $exception = new QQOAuth2ApiException();
        $this->assertInstanceOf(QQOAuth2Exception::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
    
    public function testWithRealWorldScenario(): void
    {
        $apiEndpoint = 'https://graph.qq.com/user/get_user_info';
        $apiResponse = [
            'ret' => 1002,
            'msg' => 'The access token has expired',
            'is_lost' => 0
        ];
        $message = sprintf('QQ API Error %d: %s', $apiResponse['ret'], $apiResponse['msg']);
        
        $exception = new QQOAuth2ApiException($message, $apiResponse['ret'], null, $apiEndpoint, $apiResponse);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(1002, $exception->getCode());
        $this->assertEquals($apiEndpoint, $exception->getApiEndpoint());
        $this->assertEquals($apiResponse, $exception->getApiResponse());
    }
}