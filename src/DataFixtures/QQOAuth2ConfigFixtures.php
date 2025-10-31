<?php

namespace Tourze\QQConnectOAuth2Bundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

class QQOAuth2ConfigFixtures extends Fixture
{
    public const CONFIG_1_REFERENCE = 'config-1';
    public const CONFIG_2_REFERENCE = 'config-2';

    public function load(ObjectManager $manager): void
    {
        $config = new QQOAuth2Config();
        $config->setAppId('test_app_id_123456');
        $config->setAppSecret('test_app_secret_abcdef');
        $config->setScope('get_user_info,list_album,upload_pic,check_page_fans');
        $config->setValid(true);

        $manager->persist($config);

        $config2 = new QQOAuth2Config();
        $config2->setAppId('test_app_id_789012');
        $config2->setAppSecret('test_app_secret_ghijkl');
        $config2->setScope('get_user_info');
        $config2->setValid(false);

        $manager->persist($config2);

        $manager->flush();

        $this->addReference(self::CONFIG_1_REFERENCE, $config);
        $this->addReference(self::CONFIG_2_REFERENCE, $config2);
    }
}
