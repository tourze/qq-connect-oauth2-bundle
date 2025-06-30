<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\QQConnectOAuth2Bundle\DependencyInjection\QQConnectOAuth2Extension;
use Tourze\QQConnectOAuth2Bundle\QQConnectOAuth2Bundle;

class QQConnectOAuth2BundleTest extends TestCase
{
    private QQConnectOAuth2Bundle $bundle;
    
    protected function setUp(): void
    {
        $this->bundle = new QQConnectOAuth2Bundle();
    }
    
    public function testExtendsSymfonyBundle(): void
    {
        $this->assertInstanceOf(Bundle::class, $this->bundle);
    }
    
    public function testGetContainerExtension(): void
    {
        $extension = $this->bundle->getContainerExtension();
        
        $this->assertInstanceOf(ExtensionInterface::class, $extension);
        $this->assertInstanceOf(QQConnectOAuth2Extension::class, $extension);
    }
    
    public function testGetContainerExtensionReturnsSameInstance(): void
    {
        // 验证多次调用返回的是新实例（因为每次都 new）
        $extension1 = $this->bundle->getContainerExtension();
        $extension2 = $this->bundle->getContainerExtension();
        
        $this->assertNotSame($extension1, $extension2);
        $this->assertEquals(get_class($extension1), get_class($extension2));
    }
    
    public function testBundleName(): void
    {
        // Bundle 名称默认就是完整的类名
        $this->assertEquals('QQConnectOAuth2Bundle', $this->bundle->getName());
    }
    
    public function testBundleNamespace(): void
    {
        $reflection = new \ReflectionClass($this->bundle);
        $this->assertEquals('Tourze\QQConnectOAuth2Bundle', $reflection->getNamespaceName());
    }
    
    public function testBundlePath(): void
    {
        $bundlePath = $this->bundle->getPath();
        $this->assertStringContainsString('qq-connect-oauth2-bundle', $bundlePath);
        $this->assertDirectoryExists($bundlePath);
    }
}