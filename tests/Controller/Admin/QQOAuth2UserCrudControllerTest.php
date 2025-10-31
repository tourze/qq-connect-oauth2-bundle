<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\QQConnectOAuth2Bundle\Controller\Admin\QQOAuth2UserCrudController;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;

/**
 * @internal
 */
#[CoversClass(QQOAuth2UserCrudController::class)]
#[RunTestsInSeparateProcesses]
final class QQOAuth2UserCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    protected function getEntityFqcn(): string
    {
        return QQOAuth2User::class;
    }

    protected function getControllerService(): QQOAuth2UserCrudController
    {
        return self::getService(QQOAuth2UserCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        return [
            'id' => ['ID'],
            'openid' => ['QQ OpenID'],
            'nickname' => ['昵称'],
            'config' => ['关联配置'],
            'token_update_time' => ['令牌更新时间'],
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
            'openid' => ['openid'],
            'access_token' => ['accessToken'],
            'expires_in' => ['expiresIn'],
            'config' => ['config'],
            'nickname' => ['nickname'],
            'gender' => ['gender'],
            'province' => ['province'],
            'city' => ['city'],
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        return [
            'nickname' => ['nickname'],
            'gender' => ['gender'],
            'user_reference' => ['userReference'],
        ];
    }

    public function testGetEntityFqcn(): void
    {
        $this->assertSame(QQOAuth2User::class, QQOAuth2UserCrudController::getEntityFqcn());
    }

    public function testIndexPageAccessibleForAuthenticatedAdmin(): void
    {
        $client = self::createClientWithDatabase();
        $client->loginUser($this->createAdminUser());

        $client->request('GET', '/admin/qq-oauth2/user');

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful());
    }

    public function testNewPageAccessibleForAuthenticatedAdmin(): void
    {
        $client = self::createClientWithDatabase();
        $client->loginUser($this->createAdminUser());

        $client->request('GET', '/admin/qq-oauth2/user/new');

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

        // Create a test user
        $user = new QQOAuth2User();
        $user->setOpenid('test_openid');
        $user->setConfig($config);
        $user->updateToken('test_access_token', 7200);
        $user->setNickname('Test User');

        $entityManager->persist($user);
        $entityManager->flush();

        $client->request('GET', '/admin/qq-oauth2/user/' . $user->getId() . '/edit');

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful());
    }

    public function testValidationErrors(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client);

        // Create a test config first for the relationship
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');
        $config->setAppSecret('test_app_secret');
        $config->setScope('get_user_info');
        $config->setValid(true);

        $entityManager = self::getEntityManager();
        $entityManager->persist($config);
        $entityManager->flush();

        // 创建一个无效的用户实体进行验证测试
        $user = new QQOAuth2User();
        // 不设置必填字段 openid、accessToken、config
        $user->setConfig($config);

        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get('validator');
        $violations = $validator->validate($user);

        // 验证应该有验证错误（openid和accessToken字段为空）
        $this->assertGreaterThan(0, $violations->count(), 'Expected validation errors for invalid user data');

        // 验证特定字段的错误
        $openidViolations = $validator->validateProperty($user, 'openid');
        $this->assertGreaterThan(0, $openidViolations->count(), 'openid field should not be blank');
    }
}
