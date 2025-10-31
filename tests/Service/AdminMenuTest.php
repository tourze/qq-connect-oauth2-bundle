<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Tests\Service;

use Knp\Menu\FactoryInterface;
use Knp\Menu\MenuFactory;
use Knp\Menu\MenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;
use Tourze\QQConnectOAuth2Bundle\Service\AdminMenu;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private AdminMenu $adminMenu;

    private FactoryInterface $factory;

    protected function onSetUp(): void
    {
        $this->factory = new MenuFactory();
        // 从容器获取服务以符合规范
        $this->adminMenu = self::getService(AdminMenu::class);
    }

    public function testInvokeCreatesThirdPartyMenu(): void
    {
        $rootItem = new MenuItem('root', $this->factory);

        ($this->adminMenu)($rootItem);

        $this->assertTrue($rootItem->hasChildren());
        $this->assertNotNull($rootItem->getChild('第三方集成'));

        $thirdPartyMenu = $rootItem->getChild('第三方集成');
        $this->assertNotNull($thirdPartyMenu->getChild('QQ OAuth2'));

        $qqOAuth2Menu = $thirdPartyMenu->getChild('QQ OAuth2');
        $this->assertNotNull($qqOAuth2Menu->getChild('配置管理'));
        $this->assertNotNull($qqOAuth2Menu->getChild('用户管理'));
        $this->assertNotNull($qqOAuth2Menu->getChild('状态管理'));

        // Test menu URIs - 验证不为空即可
        $this->assertNotNull($qqOAuth2Menu->getChild('配置管理')->getUri());
        $this->assertNotNull($qqOAuth2Menu->getChild('用户管理')->getUri());
        $this->assertNotNull($qqOAuth2Menu->getChild('状态管理')->getUri());

        // Test menu icons
        $this->assertEquals('fab fa-qq', $qqOAuth2Menu->getAttribute('icon'));
        $this->assertEquals('fas fa-cog', $qqOAuth2Menu->getChild('配置管理')->getAttribute('icon'));
        $this->assertEquals('fas fa-users', $qqOAuth2Menu->getChild('用户管理')->getAttribute('icon'));
        $this->assertEquals('fas fa-clock', $qqOAuth2Menu->getChild('状态管理')->getAttribute('icon'));
    }

    public function testInvokeWithExistingThirdPartyMenu(): void
    {
        $rootItem = new MenuItem('root', $this->factory);
        $rootItem->addChild('第三方集成');

        ($this->adminMenu)($rootItem);

        $this->assertTrue($rootItem->hasChildren());
        $this->assertNotNull($rootItem->getChild('第三方集成'));

        $thirdPartyMenu = $rootItem->getChild('第三方集成');
        $this->assertNotNull($thirdPartyMenu->getChild('QQ OAuth2'));

        $qqOAuth2Menu = $thirdPartyMenu->getChild('QQ OAuth2');
        $this->assertNotNull($qqOAuth2Menu->getChild('配置管理'));
        $this->assertNotNull($qqOAuth2Menu->getChild('用户管理'));
        $this->assertNotNull($qqOAuth2Menu->getChild('状态管理'));
    }

    public function testInvokeWithExistingQQOAuth2Menu(): void
    {
        $rootItem = new MenuItem('root', $this->factory);
        $thirdPartyMenu = $rootItem->addChild('第三方集成');
        $qqOAuth2Menu = $thirdPartyMenu->addChild('QQ OAuth2');
        $qqOAuth2Menu->setAttribute('icon', 'fab fa-qq');

        ($this->adminMenu)($rootItem);

        $thirdPartyMenu = $rootItem->getChild('第三方集成');
        $this->assertNotNull($thirdPartyMenu);
        $qqOAuth2Menu = $thirdPartyMenu->getChild('QQ OAuth2');
        $this->assertNotNull($qqOAuth2Menu);
        $this->assertNotNull($qqOAuth2Menu->getChild('配置管理'));
        $this->assertNotNull($qqOAuth2Menu->getChild('用户管理'));
        $this->assertNotNull($qqOAuth2Menu->getChild('状态管理'));

        // Verify the icon is preserved
        $this->assertEquals('fab fa-qq', $qqOAuth2Menu->getAttribute('icon'));
    }
}
