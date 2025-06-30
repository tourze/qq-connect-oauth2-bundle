<?php

namespace Tourze\QQConnectOAuth2Bundle\Service;

use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\RouteCollection;
use Tourze\QQConnectOAuth2Bundle\Controller\QQOAuth2CallbackController;
use Tourze\QQConnectOAuth2Bundle\Controller\QQOAuth2LoginController;
use Tourze\RoutingAutoLoaderBundle\Service\RoutingAutoLoaderInterface;

#[AutoconfigureTag(name: 'routing.loader')]
class AttributeControllerLoader extends Loader implements RoutingAutoLoaderInterface
{
    private AttributeRouteControllerLoader $controllerLoader;

    public function __construct()
    {
        parent::__construct();
        $this->controllerLoader = new AttributeRouteControllerLoader();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        return $this->autoload();
    }

    public function autoload(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->addCollection($this->controllerLoader->load(QQOAuth2LoginController::class));
        $collection->addCollection($this->controllerLoader->load(QQOAuth2CallbackController::class));
        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'attribute';
    }

    public function getType(): string
    {
        return 'attribute';
    }
}

