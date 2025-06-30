<?php

namespace Tourze\QQConnectOAuth2Bundle;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\QQConnectOAuth2Bundle\DependencyInjection\QQConnectOAuth2Extension;

class QQConnectOAuth2Bundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new QQConnectOAuth2Extension();
    }
}
