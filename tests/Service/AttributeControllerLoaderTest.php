<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\RouteCollection;
use Tourze\QQConnectOAuth2Bundle\Service\AttributeControllerLoader;

class AttributeControllerLoaderTest extends TestCase
{
    private AttributeControllerLoader $loader;

    public function testLoad(): void
    {
        $routes = $this->loader->load('Tourze\QQConnectOAuth2Bundle\Controller\\');

        $this->assertInstanceOf(RouteCollection::class, $routes);
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
        $resolver = $this->createMock(LoaderResolverInterface::class);
        $this->loader->setResolver($resolver);

        $this->assertEquals($resolver, $this->loader->getResolver());
    }

    public function testSetAndGetResolver(): void
    {
        $resolver = $this->createMock(LoaderResolverInterface::class);
        $this->loader->setResolver($resolver);

        $this->assertSame($resolver, $this->loader->getResolver());
    }

    public function testLoadDoesNotValidateResource(): void
    {
        // 我们的实现总是返回固定的路由集合，不验证资源有效性
        $routes = $this->loader->load('InvalidNamespace');
        $this->assertInstanceOf(RouteCollection::class, $routes);
        $this->assertGreaterThan(0, $routes->count());
    }

    public function testLoadWithNonExistentNamespace(): void
    {
        $routes = $this->loader->load('NonExistent\Namespace\\');

        $this->assertInstanceOf(RouteCollection::class, $routes);
        // 我们的实现总是加载固定的控制器路由，所以路由数量 > 0
        $this->assertGreaterThan(0, $routes->count());
    }

    public function testImportNotSupported(): void
    {
        $childLoader = $this->createMock(LoaderInterface::class);
        $childLoader->method('supports')->willReturn(true);
        $childLoader->method('load')->willReturn(new RouteCollection());

        $resolver = $this->createMock(LoaderResolverInterface::class);
        $resolver->method('resolve')->willReturn($childLoader);

        $this->loader->setResolver($resolver);

        // 这个方法主要测试import功能，虽然在当前实现中可能不会被使用
        $result = $this->loader->import('some-resource');
        $this->assertInstanceOf(RouteCollection::class, $result);
    }

    protected function setUp(): void
    {
        $this->loader = new AttributeControllerLoader();
    }
}
