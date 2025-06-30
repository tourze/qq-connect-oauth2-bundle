<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Tourze\QQConnectOAuth2Bundle\Controller\QQOAuth2LoginController;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

class QQOAuth2LoginControllerTest extends TestCase
{
    private QQOAuth2Service&MockObject $oauth2Service;
    private QQOAuth2LoginController $controller;

    public function testInvokeGeneratesRedirectResponse(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('getId')->willReturn('test_session_id');

        $request = Request::create('/qq-oauth2/login', 'GET');
        $request->setSession($session);

        $authUrl = 'https://graph.qq.com/oauth2.0/authorize?client_id=test&redirect_uri=callback&state=123';

        $this->oauth2Service->expects($this->once())
            ->method('generateAuthorizationUrl')
            ->with('test_session_id')
            ->willReturn($authUrl);

        $response = ($this->controller)($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($authUrl, $response->getTargetUrl());
    }

    public function testInvokeWithServiceException(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('getId')->willReturn('test_session_id');

        $request = Request::create('/qq-oauth2/login', 'GET');
        $request->setSession($session);

        $exception = new \RuntimeException('Service error');
        $this->oauth2Service->expects($this->once())
            ->method('generateAuthorizationUrl')
            ->willThrowException($exception);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Service error');

        ($this->controller)($request);
    }

    public function testInvokeWithEmptySessionId(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('getId')->willReturn('');

        $request = Request::create('/qq-oauth2/login', 'GET');
        $request->setSession($session);

        $authUrl = 'https://graph.qq.com/oauth2.0/authorize?client_id=test&redirect_uri=callback&state=123';

        $this->oauth2Service->expects($this->once())
            ->method('generateAuthorizationUrl')
            ->with('')
            ->willReturn($authUrl);

        $response = ($this->controller)($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($authUrl, $response->getTargetUrl());
    }

    protected function setUp(): void
    {
        $this->oauth2Service = $this->createMock(QQOAuth2Service::class);
        $this->controller = new QQOAuth2LoginController($this->oauth2Service);
    }
} 