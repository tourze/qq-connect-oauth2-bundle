<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\QQConnectOAuth2Bundle\Controller\Admin\QQOAuth2StateCrudController;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;

/**
 * @internal
 */
#[CoversClass(QQOAuth2StateCrudController::class)]
#[RunTestsInSeparateProcesses]
final class QQOAuth2StateCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    protected function getEntityFqcn(): string
    {
        return QQOAuth2State::class;
    }

    protected function getControllerService(): QQOAuth2StateCrudController
    {
        return self::getService(QQOAuth2StateCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        return [
            'id' => ['ID'],
            'state' => ['OAuth状态值'],
            'config' => ['关联配置'],
            'expire_time' => ['过期时间'],
            'used' => ['是否已使用'],
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
            'state' => ['state'],
            'session_id' => ['sessionId'],
            'config' => ['config'],
            'expire_time' => ['expireTime'],
            'used' => ['used'],
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        return [
            'session_id' => ['sessionId'],
            'expire_time' => ['expireTime'],
            'used' => ['used'],
        ];
    }

    public function testIndexPageAccessibleForAuthenticatedAdmin(): void
    {
        $client = self::createClientWithDatabase();
        $client->loginUser($this->createAdminUser());

        $client->request('GET', '/admin/qq-oauth2/state');

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful());
    }

    public function testNewPageAccessibleForAuthenticatedAdmin(): void
    {
        $client = self::createClientWithDatabase();
        $client->loginUser($this->createAdminUser());

        $client->request('GET', '/admin/qq-oauth2/state/new');

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

        // Create a test config first
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');
        $config->setAppSecret('test_app_secret');
        $config->setScope('get_user_info');
        $config->setValid(true);

        $entityManager = self::getEntityManager();
        $entityManager->persist($config);
        $entityManager->flush();

        // Create a test state
        $state = new QQOAuth2State();
        $state->setState('test_state_value');
        $state->setConfig($config);
        $state->setSessionId('test_session_id');

        $entityManager->persist($state);
        $entityManager->flush();

        $client->request('GET', '/admin/qq-oauth2/state/' . $state->getId() . '/edit');

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful());
    }

    public function testValidationErrors(): void
    {
        $client = self::createAuthenticatedClient();

        // Create a test config first for the relationship
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');
        $config->setAppSecret('test_app_secret');
        $config->setScope('get_user_info');
        $config->setValid(true);

        $entityManager = self::getEntityManager();
        $entityManager->persist($config);
        $entityManager->flush();

        // 创建一个无效的状态实体进行验证测试
        $state = new QQOAuth2State();
        // 不设置必填字段 state 和 config
        $state->setConfig($config);
        // 仅设置 config，不设置 state

        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get('validator');
        $violations = $validator->validate($state);

        // 验证应该有验证错误（state字段为空）
        $this->assertGreaterThan(0, $violations->count(), 'Expected validation errors for invalid state data');

        // 验证特定字段的错误
        $stateViolations = $validator->validateProperty($state, 'state');
        $this->assertGreaterThan(0, $stateViolations->count(), 'state field should not be blank');
    }
}
