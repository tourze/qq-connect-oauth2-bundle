<?php

namespace Tourze\QQConnectOAuth2Bundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

class QQOAuth2LoginController extends AbstractController
{
    public function __construct(
        private QQOAuth2Service $oauth2Service
    ) {
    }

    #[Route(path: '/qq-oauth2/login', name: 'qq_oauth2_login', methods: ['GET'])]
    public function __invoke(Request $request): RedirectResponse
    {
        try {
            $sessionId = $request->getSession()->getId();
            $authUrl = $this->oauth2Service->generateAuthorizationUrl($sessionId);
            
            return new RedirectResponse($authUrl);
        } catch (\Exception $e) {
            throw $e;
        }
    }
}