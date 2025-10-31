<?php

namespace Tourze\QQConnectOAuth2Bundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;

class QQOAuth2StateFixtures extends Fixture implements DependentFixtureInterface
{
    public const STATE_1_REFERENCE = 'state-1';
    public const STATE_2_REFERENCE = 'state-2';
    public const STATE_EXPIRED_REFERENCE = 'state-expired';

    public function load(ObjectManager $manager): void
    {
        $config = $this->getReference(QQOAuth2ConfigFixtures::CONFIG_1_REFERENCE, QQOAuth2Config::class);

        $state1 = new QQOAuth2State();
        $state1->setState('test_state_123abc');
        $state1->setConfig($config);
        $state1->setExpireTimeFromTtl(3600);
        $state1->setSessionId('test_session_id_1');
        $state1->setMetadata(['redirect_uri' => 'https://images.unsplash.com/callback']);

        $manager->persist($state1);

        $state2 = new QQOAuth2State();
        $state2->setState('test_state_456def');
        $state2->setConfig($config);
        $state2->setExpireTimeFromTtl(1800);
        $state2->setSessionId('test_session_id_2');
        $state2->setMetadata(['redirect_uri' => 'https://images.unsplash.com/other']);

        $manager->persist($state2);

        $expiredState = new QQOAuth2State();
        $expiredState->setState('expired_state_789ghi');
        $expiredState->setConfig($config);
        $expiredState->setExpireTimeFromTtl(-3600);
        $expiredState->setSessionId('expired_session_id');

        $manager->persist($expiredState);

        $manager->flush();

        $this->addReference(self::STATE_1_REFERENCE, $state1);
        $this->addReference(self::STATE_2_REFERENCE, $state2);
        $this->addReference(self::STATE_EXPIRED_REFERENCE, $expiredState);
    }

    public function getDependencies(): array
    {
        return [
            QQOAuth2ConfigFixtures::class,
        ];
    }
}
