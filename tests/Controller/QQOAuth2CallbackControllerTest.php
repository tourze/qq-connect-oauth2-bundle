<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use Tourze\QQConnectOAuth2Bundle\Controller\QQOAuth2CallbackController;

/**
 * @internal
 */
#[CoversClass(QQOAuth2CallbackController::class)]
#[RunTestsInSeparateProcesses]
final class QQOAuth2CallbackControllerTest extends AbstractWebTestCase
{
    public function testCallbackWithError(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/qq-oauth2/callback', [
            'error' => 'access_denied',
            'error_description' => 'User denied access',
        ]);

        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('OAuth2 Error: User denied access', $content);
    }

    public function testCallbackWithMissingParameters(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/qq-oauth2/callback');

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Invalid callback parameters', $content);
    }

    public function testCallbackWithMalformedParameters(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/qq-oauth2/callback', [
            'code' => 'invalid-code-with-special-chars@#$',
            'state' => 'invalid-state',
        ]);

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Malformed callback parameters', $content);
    }

    public function testCallbackWithValidParametersButNoConfig(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/qq-oauth2/callback', [
            'code' => 'validCode123',
            'state' => 'a1b2c3d4e5f6789012345678901234ab',
        ]);

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Login failed: Authentication error', $content);
    }

    public function testUnauthenticatedAccess(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/qq-oauth2/callback', [
            'code' => 'validCode123',
            'state' => 'a1b2c3d4e5f6789012345678901234ab',
        ]);

        $response = $client->getResponse();
        $this->assertNotNull($response);
        $this->assertInstanceOf(Response::class, $response);
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request($method, '/qq-oauth2/callback');
    }
}
