<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\QQConnectOAuth2Bundle\Controller\QQOAuth2CallbackController;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

class QQOAuth2CallbackControllerTest extends TestCase
{
    private QQOAuth2Service&MockObject $oauth2Service;
    private LoggerInterface&MockObject $logger;
    private QQOAuth2CallbackController $controller;

    public function testInvokeWithOAuthError(): void
    {
        $request = Request::create('/qq-oauth2/callback', 'GET', [
            'error' => 'access_denied',
            'error_description' => 'User denied the request'
        ]);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('QQ OAuth2 error response');

        $response = ($this->controller)($request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('OAuth2 Error: User denied the request', $response->getContent());
    }

    public function testInvokeWithMissingParameters(): void
    {
        $request = Request::create('/qq-oauth2/callback', 'GET');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Invalid QQ OAuth2 callback parameters');

        $response = ($this->controller)($request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Invalid callback parameters', $response->getContent());
    }

    public function testInvokeWithMalformedParameters(): void
    {
        $request = Request::create('/qq-oauth2/callback', 'GET', [
            'code' => 'invalid code!',
            'state' => 'invalid-state'
        ]);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Malformed QQ OAuth2 callback parameters');

        $response = ($this->controller)($request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Malformed callback parameters', $response->getContent());
    }

    public function testInvokeWithValidParametersSuccess(): void
    {
        $request = Request::create('/qq-oauth2/callback', 'GET', [
            'code' => 'valid_authorization_code',
            'state' => '1234567890abcdef1234567890abcdef'
        ]);

        $user = $this->createMock(QQOAuth2User::class);
        $user->method('getOpenid')->willReturn('test_openid');
        $user->method('getNickname')->willReturn('Test User');

        $this->oauth2Service->expects($this->once())
            ->method('handleCallback')
            ->with('valid_authorization_code', '1234567890abcdef1234567890abcdef')
            ->willReturn($user);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('QQ OAuth2 login successful');

        $response = ($this->controller)($request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Successfully logged in as Test User', $response->getContent());
    }

    public function testInvokeWithServiceException(): void
    {
        $request = Request::create('/qq-oauth2/callback', 'GET', [
            'code' => 'valid_authorization_code',
            'state' => '1234567890abcdef1234567890abcdef'
        ]);

        $this->oauth2Service->expects($this->once())
            ->method('handleCallback')
            ->willThrowException(new \Exception('Service error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('QQ OAuth2 login failed');

        $response = ($this->controller)($request);

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertStringContainsString('Login failed: Authentication error', $response->getContent());
    }

    public function testInvokeWithNullLogger(): void
    {
        $controller = new QQOAuth2CallbackController($this->oauth2Service, null);
        $request = Request::create('/qq-oauth2/callback', 'GET', [
            'error' => 'access_denied'
        ]);

        $response = $controller($request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    protected function setUp(): void
    {
        $this->oauth2Service = $this->createMock(QQOAuth2Service::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->controller = new QQOAuth2CallbackController($this->oauth2Service, $this->logger);
    }
} 