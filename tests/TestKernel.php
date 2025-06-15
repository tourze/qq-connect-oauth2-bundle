<?php

namespace Tourze\QQConnectOAuth2Bundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Tourze\DoctrineIndexedBundle\DoctrineIndexedBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\QQConnectOAuth2Bundle\QQConnectOAuth2Bundle;
use Tourze\RoutingAutoLoaderBundle\RoutingAutoLoaderBundle;

class TestKernel extends BaseKernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new SecurityBundle(),
            new DoctrineIndexedBundle(),
            new DoctrineTimestampBundle(),
            new RoutingAutoLoaderBundle(),
            new QQConnectOAuth2Bundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            // Framework configuration
            $container->prependExtensionConfig('framework', [
                'secret' => 'TEST_SECRET',
                'test' => true,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => [
                    'log' => true,
                ],
                'cache' => [
                    'app' => 'cache.adapter.array',
                ],
                'router' => [
                    'resource' => '%kernel.project_dir%/config/routes.yaml',
                    'type' => 'yaml',
                ],
                'session' => [
                    'handler_id' => null,
                    'cookie_secure' => 'auto',
                    'cookie_samesite' => 'lax',
                    'storage_factory_id' => 'session.storage.factory.mock_file',
                ],
            ]);

            // Doctrine configuration
            $container->prependExtensionConfig('doctrine', [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'url' => 'sqlite:///:memory:',
                ],
                'orm' => [
                    'auto_generate_proxy_classes' => true,
                    'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                    'auto_mapping' => true,
                    'mappings' => [
                        'QQConnectOAuth2Bundle' => [
                            'type' => 'attribute',
                            'dir' => '%kernel.project_dir%/src/Entity',
                            'prefix' => 'Tourze\QQConnectOAuth2Bundle\Entity',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);

            // Security configuration
            $container->prependExtensionConfig('security', [
                'password_hashers' => [
                    InMemoryUser::class => 'auto',
                ],
                'providers' => [
                    'users_in_memory' => [
                        'memory' => [],
                    ],
                ],
                'firewalls' => [
                    'dev' => [
                        'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                        'security' => false,
                    ],
                    'main' => [
                        'lazy' => true,
                        'provider' => 'users_in_memory',
                    ],
                ],
            ]);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/qq-oauth2-test/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/qq-oauth2-test/log';
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }
}