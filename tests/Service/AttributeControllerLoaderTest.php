<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\QQConnectOAuth2Bundle\Service\AttributeControllerLoader;

/**
 * @internal
 */
#[CoversClass(AttributeControllerLoader::class)]
#[RunTestsInSeparateProcesses] final class AttributeControllerLoaderTest extends AbstractIntegrationTestCase
{
    private AttributeControllerLoader $loader;

    public function testLoad(): void
    {
        $routes = $this->loader->load('Tourze\QQConnectOAuth2Bundle\Controller\\');

        $this->assertNotNull($routes);
        $this->assertGreaterThan(0, $routes->count());

        // 验证登录路由存在
        $loginRoute = $routes->get('qq_oauth2_login');
        $this->assertNotNull($loginRoute);
        $this->assertEquals('/qq-oauth2/login', $loginRoute->getPath());
        $this->assertEquals(['GET'], $loginRoute->getMethods());

        // 验证回调路由存在
        $callbackRoute = $routes->get('qq_oauth2_callback');
        $this->assertNotNull($callbackRoute);
        $this->assertEquals('/qq-oauth2/callback', $callbackRoute->getPath());
        $this->assertEquals(['GET'], $callbackRoute->getMethods());
    }

    public function testSupportsValidResource(): void
    {
        $this->assertFalse($this->loader->supports('Tourze\QQConnectOAuth2Bundle\Controller\\'));
        $this->assertTrue($this->loader->supports('Tourze\QQConnectOAuth2Bundle\Controller\\', 'attribute'));
    }

    public function testSupportsInvalidResource(): void
    {
        $this->assertFalse($this->loader->supports('SomeOtherNamespace\\'));
        $this->assertFalse($this->loader->supports('Tourze\QQConnectOAuth2Bundle\Controller\\', 'yaml'));
        $this->assertFalse($this->loader->supports('not-a-namespace'));
    }

    public function testGetTypeIsAttribute(): void
    {
        // AttributeControllerLoader 专门处理 attribute 类型的路由
        $this->assertEquals('attribute', $this->loader->getType());
        $this->assertTrue($this->loader->supports('Tourze\QQConnectOAuth2Bundle\Controller\\', 'attribute'));
    }

    public function testSetResolver(): void
    {
        $container = self::getContainer();
        $resolver = $container->get('routing.resolver');
        $this->assertInstanceOf(LoaderResolverInterface::class, $resolver);
        $this->loader->setResolver($resolver);

        $this->assertEquals($resolver, $this->loader->getResolver());
    }

    public function testSetAndGetResolver(): void
    {
        $container = self::getContainer();
        $resolver = $container->get('routing.resolver');
        $this->assertInstanceOf(LoaderResolverInterface::class, $resolver);
        $this->loader->setResolver($resolver);

        $this->assertSame($resolver, $this->loader->getResolver());
    }

    public function testLoadDoesNotValidateResource(): void
    {
        // 我们的实现总是返回固定的路由集合，不验证资源有效性
        $routes = $this->loader->load('InvalidNamespace');
        $this->assertNotNull($routes);
        $this->assertGreaterThan(0, $routes->count());
    }

    public function testLoadWithNonExistentNamespace(): void
    {
        $routes = $this->loader->load('NonExistent\Namespace\\');

        $this->assertNotNull($routes);
        // 我们的实现总是加载固定的控制器路由，所以路由数量 > 0
        $this->assertGreaterThan(0, $routes->count());
    }

    public function testAutoload(): void
    {
        $collection = $this->loader->autoload();

        $this->assertNotNull($collection);
        $this->assertGreaterThan(0, $collection->count());

        // 验证QQ OAuth2相关路由存在
        $hasQQRoutes = false;
        foreach ($collection as $route) {
            if (false !== strpos($route->getPath(), 'qq-oauth2')) {
                $hasQQRoutes = true;
                break;
            }
        }
        $this->assertTrue($hasQQRoutes);
    }

    protected function onSetUp(): void
    {
        $container = self::getContainer();
        $loader = $container->get(AttributeControllerLoader::class);
        $this->assertInstanceOf(AttributeControllerLoader::class, $loader);
        $this->loader = $loader;
    }
}
