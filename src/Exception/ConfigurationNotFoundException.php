<?php

namespace Tourze\QQConnectOAuth2Bundle\Exception;

/**
 * 配置不存在异常
 */
class ConfigurationNotFoundException extends QQOAuth2Exception
{
    public static function forName(string $configName): self
    {
        return self::withContext(
            "QQ OAuth2 configuration not found: {$configName}",
            ['config_name' => $configName],
            404
        );
    }

    public static function forEnvironment(string $environment): self
    {
        return self::withContext(
            "QQ OAuth2 configuration not found for environment: {$environment}",
            ['environment' => $environment],
            404
        );
    }

    public static function forNameAndEnvironment(string $configName, string $environment): self
    {
        return self::withContext(
            "QQ OAuth2 configuration not found: {$configName} in environment: {$environment}",
            ['config_name' => $configName, 'environment' => $environment],
            404
        );
    }
}
