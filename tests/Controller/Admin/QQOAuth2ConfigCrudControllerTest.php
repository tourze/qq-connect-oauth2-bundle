<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\QQConnectOAuth2Bundle\Controller\Admin\QQOAuth2ConfigCrudController;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

/**
 * @internal
 */
#[CoversClass(QQOAuth2ConfigCrudController::class)]
#[RunTestsInSeparateProcesses]
final class QQOAuth2ConfigCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    protected function getEntityFqcn(): string
    {
        return QQOAuth2Config::class;
    }

    protected function getControllerService(): QQOAuth2ConfigCrudController
    {
        return self::getService(QQOAuth2ConfigCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        return [
            'id' => ['ID'],
            'app_id' => ['QQ应用ID'],
            'valid' => ['是否启用'],
            'created_at' => ['创建时间'],
            'updated_at' => ['更新时间'],
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        return [
            'app_id' => ['appId'],
            'app_secret' => ['appSecret'],
            'scope' => ['scope'],
            'valid' => ['valid'],
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        return [
            'app_id' => ['appId'],
            'app_secret' => ['appSecret'],
            'scope' => ['scope'],
            'valid' => ['valid'],
        ];
    }

    public function testGetEntityFqcn(): void
    {
        $this->assertSame(QQOAuth2Config::class, QQOAuth2ConfigCrudController::getEntityFqcn());
    }

    public function testIndexPageAccessibleForAuthenticatedAdmin(): void
    {
        $client = self::createClientWithDatabase();
        $client->loginUser($this->createAdminUser());

        $client->request('GET', '/admin/qq-oauth2/config');

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful());
    }

    public function testNewPageAccessibleForAuthenticatedAdmin(): void
    {
        $client = self::createClientWithDatabase();
        $client->loginUser($this->createAdminUser());

        $client->request('GET', '/admin/qq-oauth2/config/new');

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful());
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Create', $content);
    }

    public function testEditPageAccessibleForAuthenticatedAdmin(): void
    {
        $client = self::createClientWithDatabase();
        $client->loginUser($this->createAdminUser());

        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');
        $config->setAppSecret('test_app_secret');
        $config->setScope('get_user_info');
        $config->setValid(true);

        $entityManager = self::getEntityManager();
        $entityManager->persist($config);
        $entityManager->flush();

        $client->request('GET', '/admin/qq-oauth2/config/' . $config->getId() . '/edit');

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful());
    }

    public function testValidationErrors(): void
    {
        $client = self::createAuthenticatedClient();

        // 创建一个无效的配置实体进行验证测试
        $config = new QQOAuth2Config();
        // 不设置必填字段 appId 和 appSecret

        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get('validator');
        $violations = $validator->validate($config);

        // 验证应该有验证错误
        $this->assertGreaterThan(0, $violations->count(), 'Expected validation errors for invalid config data');

        // 验证特定字段的错误
        $appIdViolations = $validator->validateProperty($config, 'appId');
        $this->assertGreaterThan(0, $appIdViolations->count(), 'appId should not be blank');

        $appSecretViolations = $validator->validateProperty($config, 'appSecret');
        $this->assertGreaterThan(0, $appSecretViolations->count(), 'appSecret should not be blank');
    }
}
