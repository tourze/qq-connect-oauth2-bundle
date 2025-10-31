<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tourze\QQConnectOAuth2Bundle\DataFixtures\QQOAuth2ConfigFixtures;
use Tourze\QQConnectOAuth2Bundle\DataFixtures\QQOAuth2StateFixtures;
use Tourze\QQConnectOAuth2Bundle\DataFixtures\QQOAuth2UserFixtures;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    // Register DataFixtures for dev environment
    $services->set(QQOAuth2ConfigFixtures::class)
        ->tag('doctrine.fixture.orm')
    ;

    $services->set(QQOAuth2StateFixtures::class)
        ->tag('doctrine.fixture.orm')
    ;

    $services->set(QQOAuth2UserFixtures::class)
        ->tag('doctrine.fixture.orm')
    ;
};
