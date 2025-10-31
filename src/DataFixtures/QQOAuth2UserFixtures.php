<?php

namespace Tourze\QQConnectOAuth2Bundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;

class QQOAuth2UserFixtures extends Fixture implements DependentFixtureInterface
{
    public const USER_1_REFERENCE = 'user-1';
    public const USER_2_REFERENCE = 'user-2';
    public const USER_EXPIRED_REFERENCE = 'user-expired';

    public function load(ObjectManager $manager): void
    {
        $config = $this->getReference(QQOAuth2ConfigFixtures::CONFIG_1_REFERENCE, QQOAuth2Config::class);

        $user1 = new QQOAuth2User();
        $user1->setOpenid('test_openid_123456789');
        $user1->setConfig($config);
        $user1->updateToken('test_access_token_abcdef123456', 7200);
        $user1->setUnionid('test_unionid_union123');
        $user1->setNickname('测试用户1');
        $user1->setAvatar('https://images.unsplash.com/photo-1633332755192-727a05c4013d?w=400');
        $user1->setGender('男');
        $user1->setProvince('广东');
        $user1->setCity('深圳');
        $user1->setRefreshToken('test_refresh_token_xyz789');
        $user1->setUserReference('user_ref_1001');
        $user1->setRawData([
            'nickname' => '测试用户1',
            'figureurl_qq_1' => 'https://images.unsplash.com/photo-1633332755192-727a05c4013d?w=400',
            'gender' => '男',
            'province' => '广东',
            'city' => '深圳',
        ]);

        $manager->persist($user1);

        $user2 = new QQOAuth2User();
        $user2->setOpenid('test_openid_987654321');
        $user2->setConfig($config);
        $user2->updateToken('test_access_token_ghijkl789012', 3600);
        $user2->setUnionid('test_unionid_union456');
        $user2->setNickname('测试用户2');
        $user2->setAvatar('https://images.unsplash.com/photo-1494790108755-2616b612b593?w=400');
        $user2->setGender('女');
        $user2->setProvince('北京');
        $user2->setCity('北京');
        $user2->setUserReference('user_ref_1002');
        $user2->setRawData([
            'nickname' => '测试用户2',
            'figureurl_qq_1' => 'https://images.unsplash.com/photo-1494790108755-2616b612b593?w=400',
            'gender' => '女',
            'province' => '北京',
            'city' => '北京',
        ]);

        $manager->persist($user2);

        $expiredUser = new QQOAuth2User();
        $expiredUser->setOpenid('expired_openid_111222333');
        $expiredUser->setConfig($config);
        $expiredUser->updateToken('expired_access_token_abc123', -3600);
        $expiredUser->setNickname('过期用户');
        $expiredUser->setRefreshToken('expired_refresh_token_xyz');

        $manager->persist($expiredUser);

        $manager->flush();

        $this->addReference(self::USER_1_REFERENCE, $user1);
        $this->addReference(self::USER_2_REFERENCE, $user2);
        $this->addReference(self::USER_EXPIRED_REFERENCE, $expiredUser);
    }

    public function getDependencies(): array
    {
        return [
            QQOAuth2ConfigFixtures::class,
        ];
    }
}
