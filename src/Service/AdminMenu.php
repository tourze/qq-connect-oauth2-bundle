<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Service;

use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;

#[Autoconfigure(public: true)]
readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(private LinkGeneratorInterface $linkGenerator)
    {
    }

    public function __invoke(ItemInterface $item): void
    {
        // 获取或创建第三方集成菜单
        $thirdPartyMenu = $item->getChild('第三方集成');
        if (null === $thirdPartyMenu) {
            $thirdPartyMenu = $item->addChild('第三方集成');
        }

        // $thirdPartyMenu 不可能为 null，因为 addChild() 总是返回 ItemInterface

        // 获取或创建QQ OAuth2菜单
        $qqOAuth2Menu = $thirdPartyMenu->getChild('QQ OAuth2');
        if (null === $qqOAuth2Menu) {
            $qqOAuth2Menu = $thirdPartyMenu->addChild('QQ OAuth2');
            $qqOAuth2Menu->setAttribute('icon', 'fab fa-qq');
        }

        // $qqOAuth2Menu 不可能为 null，因为 addChild() 总是返回 ItemInterface

        // 添加子菜单项
        $configMenuItem = $qqOAuth2Menu->addChild('配置管理');
        $configMenuItem->setUri($this->linkGenerator->getCurdListPage(QQOAuth2Config::class));
        $configMenuItem->setAttribute('icon', 'fas fa-cog');

        $userMenuItem = $qqOAuth2Menu->addChild('用户管理');
        $userMenuItem->setUri($this->linkGenerator->getCurdListPage(QQOAuth2User::class));
        $userMenuItem->setAttribute('icon', 'fas fa-users');

        $stateMenuItem = $qqOAuth2Menu->addChild('状态管理');
        $stateMenuItem->setUri($this->linkGenerator->getCurdListPage(QQOAuth2State::class));
        $stateMenuItem->setAttribute('icon', 'fas fa-clock');
    }
}
