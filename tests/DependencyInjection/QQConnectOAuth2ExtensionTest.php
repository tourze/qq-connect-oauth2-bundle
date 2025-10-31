<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use Tourze\QQConnectOAuth2Bundle\DependencyInjection\QQConnectOAuth2Extension;

/**
 * @internal
 */
#[CoversClass(QQConnectOAuth2Extension::class)]
final class QQConnectOAuth2ExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private QQConnectOAuth2Extension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = new QQConnectOAuth2Extension();
        $this->container = new ContainerBuilder();
    }

    public function testLoad(): void
    {
        $this->container->setParameter('kernel.environment', 'test');
        $configs = [];
        $this->extension->load($configs, $this->container);

        // 验证主要服务被注册
        $this->assertTrue($this->container->hasDefinition('Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service'));
        $this->assertTrue($this->container->hasDefinition('Tourze\QQConnectOAuth2Bundle\Service\AttributeControllerLoader'));

        // 验证命令被注册
        $this->assertTrue($this->container->hasDefinition('Tourze\QQConnectOAuth2Bundle\Command\QQOAuth2ConfigCommand'));
        $this->assertTrue($this->container->hasDefinition('Tourze\QQConnectOAuth2Bundle\Command\QQOAuth2CleanupCommand'));
        $this->assertTrue($this->container->hasDefinition('Tourze\QQConnectOAuth2Bundle\Command\QQOAuth2RefreshTokenCommand'));

        // 验证Repository被注册
        $this->assertTrue($this->container->hasDefinition('Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository'));
        $this->assertTrue($this->container->hasDefinition('Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2StateRepository'));
        $this->assertTrue($this->container->hasDefinition('Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository'));
    }

    public function testGetAlias(): void
    {
        $this->assertEquals('qq_connect_o_auth2', $this->extension->getAlias());
    }
}
