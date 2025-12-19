<?php

namespace Tourze\QQConnectOAuth2Bundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

final class QQConnectOAuth2Extension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }

    public function getAlias(): string
    {
        return 'qq_connect_o_auth2';
    }
}
