<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Tourze\QQConnectOAuth2Bundle\DependencyInjection\QQConnectOAuth2Extension;

class QQConnectOAuth2ExtensionTest extends TestCase
{
    private QQConnectOAuth2Extension $extension;
    private ContainerBuilder $container;

    public function testLoadServicesDefinitions(): void
    {
        $this->extension->load([], $this->container);

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

    public function testServicesAreAutoconfigured(): void
    {
        $this->extension->load([], $this->container);

        // 验证服务使用自动配置
        $serviceDefinition = $this->container->getDefinition('Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service');
        $this->assertTrue($serviceDefinition->isAutoconfigured());
        $this->assertTrue($serviceDefinition->isAutowired());

        // 验证命令有正确的标签
        $commandDefinition = $this->container->getDefinition('Tourze\QQConnectOAuth2Bundle\Command\QQOAuth2ConfigCommand');
        $this->assertTrue($commandDefinition->isAutoconfigured());
        $this->assertTrue($commandDefinition->isAutowired());
    }

    public function testRepositoriesAreAutoconfigured(): void
    {
        $this->extension->load([], $this->container);

        $configRepoDefinition = $this->container->getDefinition('Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository');
        $this->assertTrue($configRepoDefinition->isAutoconfigured());
        $this->assertTrue($configRepoDefinition->isAutowired());

        $stateRepoDefinition = $this->container->getDefinition('Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2StateRepository');
        $this->assertTrue($stateRepoDefinition->isAutoconfigured());
        $this->assertTrue($stateRepoDefinition->isAutowired());

        $userRepoDefinition = $this->container->getDefinition('Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository');
        $this->assertTrue($userRepoDefinition->isAutoconfigured());
        $this->assertTrue($userRepoDefinition->isAutowired());
    }

    public function testGetAlias(): void
    {
        $this->assertEquals('qq_connect_oauth2', $this->extension->getAlias());
    }

    public function testLoadWithEmptyConfig(): void
    {
        $this->extension->load([], $this->container);

        // 即使没有配置，也应该成功加载服务
        $this->assertTrue($this->container->hasDefinition('Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service'));
    }

    public function testCompilerPassesAreAdded(): void
    {
        $this->extension->load([], $this->container);

        // 验证自动配置被正确应用
        $serviceDefinition = $this->container->getDefinition('Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service');
        $this->assertTrue($serviceDefinition->isPublic());
    }

    protected function setUp(): void
    {
        $this->extension = new QQConnectOAuth2Extension();
        $this->container = new ContainerBuilder(new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.debug' => true,
        ]));
    }
} 