<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ConfigurationException;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2Exception;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2StateRepository;
use Tourze\QQConnectOAuth2Bundle\Service\QQStateManager;

/**
 * @internal
 */
#[CoversClass(QQStateManager::class)]
final class QQStateManagerTest extends TestCase
{
    /** @var MockObject&QQOAuth2StateRepository */
    private QQOAuth2StateRepository $mockStateRepository;

    /** @var MockObject&EntityManagerInterface */
    private EntityManagerInterface $mockEntityManager;

    /** @var MockObject&UrlGeneratorInterface */
    private UrlGeneratorInterface $mockUrlGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var MockObject&QQOAuth2StateRepository $mockStateRepository */
        $mockStateRepository = $this->createMock(QQOAuth2StateRepository::class);
        $this->mockStateRepository = $mockStateRepository;

        /** @var MockObject&EntityManagerInterface $mockEntityManager */
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $this->mockEntityManager = $mockEntityManager;

        /** @var MockObject&UrlGeneratorInterface $mockUrlGenerator */
        $mockUrlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->mockUrlGenerator = $mockUrlGenerator;
    }

    public function testGenerateAuthorizationUrlWithoutSessionIdShouldGenerateValidUrl(): void
    {
        // Arrange
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');
        $config->setAppSecret('test_app_secret');
        $config->setScope('get_user_info');

        $this->mockUrlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('qq_oauth2_callback', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/callback')
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(QQOAuth2State::class))
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('flush')
        ;

        $stateManager = new QQStateManager(
            $this->mockStateRepository,
            $this->mockEntityManager,
            $this->mockUrlGenerator
        );

        // Act
        $url = $stateManager->generateAuthorizationUrl($config);

        // Assert
        $this->assertStringStartsWith('https://graph.qq.com/oauth2.0/authorize?', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('client_id=test_app_id', $url);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fexample.com%2Fcallback', $url);
        $this->assertStringContainsString('scope=get_user_info', $url);
        $this->assertStringContainsString('state=', $url);
    }

    public function testGenerateAuthorizationUrlWithSessionIdShouldSetSessionId(): void
    {
        // Arrange
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');
        $config->setAppSecret('test_app_secret');

        $this->mockUrlGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('https://example.com/callback')
        ;

        $capturedState = null;
        $this->mockEntityManager
            ->expects($this->once())
            ->method('persist')
            ->with(self::callback(function (QQOAuth2State $state) use (&$capturedState) {
                $capturedState = $state;

                return true;
            }))
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('flush')
        ;

        $stateManager = new QQStateManager(
            $this->mockStateRepository,
            $this->mockEntityManager,
            $this->mockUrlGenerator
        );

        // Act
        $url = $stateManager->generateAuthorizationUrl($config, 'test_session_id');

        // Assert
        $this->assertNotNull($capturedState);
        $this->assertEquals('test_session_id', $capturedState->getSessionId());
    }

    public function testGenerateAuthorizationUrlWithDefaultScopeShouldUseDefaultScope(): void
    {
        // Arrange
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');
        $config->setAppSecret('test_app_secret');
        // Scope not set, should use default

        $this->mockUrlGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('https://example.com/callback')
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('persist')
        ;
        $this->mockEntityManager
            ->expects($this->once())
            ->method('flush')
        ;

        $stateManager = new QQStateManager(
            $this->mockStateRepository,
            $this->mockEntityManager,
            $this->mockUrlGenerator
        );

        // Act
        $url = $stateManager->generateAuthorizationUrl($config);

        // Assert
        $this->assertStringContainsString('scope=get_user_info', $url);
    }

    public function testValidateAndMarkStateAsUsedWithValidStateShouldReturnState(): void
    {
        // Arrange
        $state = 'valid_state_123';
        $mockStateEntity = $this->createMock(QQOAuth2State::class);
        $mockStateEntity->method('isValid')->willReturn(true);
        $mockStateEntity->expects($this->once())->method('markAsUsed');

        $this->mockStateRepository
            ->expects($this->once())
            ->method('findValidState')
            ->with($state)
            ->willReturn($mockStateEntity)
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('persist')
            ->with($mockStateEntity)
        ;

        $this->mockEntityManager
            ->expects($this->once())
            ->method('flush')
        ;

        $stateManager = new QQStateManager(
            $this->mockStateRepository,
            $this->mockEntityManager
        );

        // Act
        $result = $stateManager->validateAndMarkStateAsUsed($state);

        // Assert
        $this->assertSame($mockStateEntity, $result);
    }

    public function testValidateAndMarkStateAsUsedWithNullStateShouldThrowException(): void
    {
        // Arrange
        $state = 'invalid_state';

        $this->mockStateRepository
            ->expects($this->once())
            ->method('findValidState')
            ->with($state)
            ->willReturn(null)
        ;

        $stateManager = new QQStateManager(
            $this->mockStateRepository,
            $this->mockEntityManager
        );

        // Act & Assert
        $this->expectException(QQOAuth2Exception::class);
        $this->expectExceptionMessage('Invalid or expired state');

        $stateManager->validateAndMarkStateAsUsed($state);
    }

    public function testValidateAndMarkStateAsUsedWithInvalidStateShouldThrowException(): void
    {
        // Arrange
        $state = 'expired_state';
        $mockStateEntity = $this->createMock(QQOAuth2State::class);
        $mockStateEntity->method('isValid')->willReturn(false);

        $this->mockStateRepository
            ->expects($this->once())
            ->method('findValidState')
            ->with($state)
            ->willReturn($mockStateEntity)
        ;

        $stateManager = new QQStateManager(
            $this->mockStateRepository,
            $this->mockEntityManager
        );

        // Act & Assert
        $this->expectException(QQOAuth2Exception::class);
        $this->expectExceptionMessage('Invalid or expired state');

        $stateManager->validateAndMarkStateAsUsed($state);
    }

    public function testCleanupExpiredStatesShouldReturnCount(): void
    {
        // Arrange
        $expectedCount = 5;

        $this->mockStateRepository
            ->expects($this->once())
            ->method('cleanupExpiredStates')
            ->willReturn($expectedCount)
        ;

        $stateManager = new QQStateManager(
            $this->mockStateRepository,
            $this->mockEntityManager
        );

        // Act
        $result = $stateManager->cleanupExpiredStates();

        // Assert
        $this->assertEquals($expectedCount, $result);
    }

    public function testGenerateRedirectUriWithoutUrlGeneratorShouldThrowException(): void
    {
        // Arrange
        $stateManager = new QQStateManager(
            $this->mockStateRepository,
            $this->mockEntityManager,
            null
        );

        // Act & Assert
        $this->expectException(QQOAuth2ConfigurationException::class);
        $this->expectExceptionMessage('UrlGeneratorInterface is required to generate authorization URL');

        $stateManager->generateRedirectUri();
    }

    public function testGenerateRedirectUriWithUrlGeneratorShouldReturnAbsoluteUrl(): void
    {
        // Arrange
        $expectedUrl = 'https://example.com/oauth2/callback';

        $this->mockUrlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('qq_oauth2_callback', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn($expectedUrl)
        ;

        $stateManager = new QQStateManager(
            $this->mockStateRepository,
            $this->mockEntityManager,
            $this->mockUrlGenerator
        );

        // Act
        $result = $stateManager->generateRedirectUri();

        // Assert
        $this->assertEquals($expectedUrl, $result);
    }

    public function testGenerateAuthorizationUrlWithoutUrlGeneratorShouldThrowException(): void
    {
        // Arrange
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');

        $stateManager = new QQStateManager(
            $this->mockStateRepository,
            $this->mockEntityManager,
            null
        );

        // Act & Assert
        $this->expectException(QQOAuth2ConfigurationException::class);

        $stateManager->generateAuthorizationUrl($config);
    }

    public function testGenerateAuthorizationUrlShouldGenerateUniqueState(): void
    {
        // Arrange
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id');

        $this->mockUrlGenerator
            ->expects($this->exactly(2))
            ->method('generate')
            ->willReturn('https://example.com/callback')
        ;

        $this->mockEntityManager
            ->expects($this->exactly(2))
            ->method('persist')
        ;
        $this->mockEntityManager
            ->expects($this->exactly(2))
            ->method('flush')
        ;

        $stateManager = new QQStateManager(
            $this->mockStateRepository,
            $this->mockEntityManager,
            $this->mockUrlGenerator
        );

        // Act
        $url1 = $stateManager->generateAuthorizationUrl($config);
        $url2 = $stateManager->generateAuthorizationUrl($config);

        // Assert
        $this->assertNotEquals($url1, $url2);

        // Extract state parameter from both URLs
        $query1String = parse_url($url1, PHP_URL_QUERY);
        $query2String = parse_url($url2, PHP_URL_QUERY);

        $this->assertIsString($query1String);
        $this->assertIsString($query2String);

        parse_str($query1String, $query1);
        parse_str($query2String, $query2);

        $this->assertIsArray($query1);
        $this->assertIsArray($query2);
        $this->assertArrayHasKey('state', $query1);
        $this->assertArrayHasKey('state', $query2);
        $this->assertIsString($query1['state']);
        $this->assertIsString($query2['state']);

        $this->assertNotEquals($query1['state'], $query2['state']);
        $this->assertEquals(32, strlen($query1['state'])); // 32 chars hex string
        $this->assertEquals(32, strlen($query2['state'])); // 32 chars hex string
    }
}
