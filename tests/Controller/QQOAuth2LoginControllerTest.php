<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use Tourze\QQConnectOAuth2Bundle\Controller\QQOAuth2LoginController;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;

/**
 * @internal
 */
#[CoversClass(QQOAuth2LoginController::class)]
#[RunTestsInSeparateProcesses]
final class QQOAuth2LoginControllerTest extends AbstractWebTestCase
{
    public function testLoginWithoutConfig(): void
    {
        $client = self::createClientWithDatabase();

        // 清除所有配置确保没有有效配置
        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $em = $doctrine->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config')->execute();
        $em->flush();

        // 清除配置缓存
        $configRepo = $container->get('Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository');
        $this->assertInstanceOf(QQOAuth2ConfigRepository::class, $configRepo);
        $configRepo->clearCache();

        $client->request('GET', '/qq-oauth2/login');

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    public function testRouteConfiguration(): void
    {
        $client = self::createClientWithDatabase();

        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request('POST', '/qq-oauth2/login');
    }

    public function testControllerIsCallable(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/qq-oauth2/login');

        $response = $client->getResponse();
        $this->assertNotNull($response);
        $this->assertInstanceOf(Response::class, $response);
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request($method, '/qq-oauth2/login');
    }
}
