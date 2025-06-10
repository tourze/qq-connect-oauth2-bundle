<?php

namespace Tourze\QQConnectOAuth2Bundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

/**
 * QQ互联OAuth2配置示例数据
 */
class QQOAuth2ConfigFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 开发环境配置
        $devConfig = new QQOAuth2Config(
            name: 'dev_default',
            appId: '123456789',
            appKey: 'your_dev_app_key_here',
            redirectUri: 'http://localhost:8000/qq-auth/callback/dev_default',
            environment: 'dev'
        );
        $devConfig->setDescription('开发环境默认QQ互联配置');
        $devConfig->setSortOrder(1);

        // 测试环境配置
        $testConfig = new QQOAuth2Config(
            name: 'test_default',
            appId: '987654321',
            appKey: 'your_test_app_key_here',
            redirectUri: 'https://test.example.com/qq-auth/callback/test_default',
            environment: 'test'
        );
        $testConfig->setDescription('测试环境默认QQ互联配置');
        $testConfig->setSortOrder(1);

        // 生产环境配置
        $prodConfig = new QQOAuth2Config(
            name: 'prod_main',
            appId: '111222333',
            appKey: 'your_prod_app_key_here',
            redirectUri: 'https://www.example.com/qq-auth/callback/prod_main',
            environment: 'prod'
        );
        $prodConfig->setDescription('生产环境主QQ互联配置');
        $prodConfig->setSortOrder(1);

        // 多应用配置示例
        $mobileConfig = new QQOAuth2Config(
            name: 'mobile_app',
            appId: '444555666',
            appKey: 'your_mobile_app_key_here',
            redirectUri: 'https://m.example.com/qq-auth/callback/mobile_app',
            environment: 'prod'
        );
        $mobileConfig->setDescription('移动端专用QQ互联配置');
        $mobileConfig->setScope('get_user_info,get_user_profile');
        $mobileConfig->setSortOrder(2);

        $manager->persist($devConfig);
        $manager->persist($testConfig);
        $manager->persist($prodConfig);
        $manager->persist($mobileConfig);

        $manager->flush();
    }
}
