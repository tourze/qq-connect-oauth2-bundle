<?php

namespace Tourze\QQConnectOAuth2Bundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

#[Route('/qq-oauth2', name: 'qq_oauth2_')]
class QQOAuth2Controller extends AbstractController
{
    public function __construct(
        private QQOAuth2Service $oauth2Service
    ) {
    }

    #[Route('/login', name: 'login', methods: ['GET'])]
    public function login(Request $request): RedirectResponse
    {
        try {
            $sessionId = $request->getSession()->getId();
            $authUrl = $this->oauth2Service->generateAuthorizationUrl($sessionId);
            
            return new RedirectResponse($authUrl);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to initialize QQ login: ' . $e->getMessage(), 0, $e);
        }
    }

    #[Route('/callback', name: 'callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        
        if (!$code || !$state) {
            return new Response('Invalid callback parameters', Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->oauth2Service->handleCallback($code, $state);
            
            // Here you can integrate with your application's user system
            // For example, create or update local user, set authentication, etc.
            
            return new Response(sprintf('Successfully logged in as %s', $user->getNickname() ?: $user->getOpenid()));
        } catch (\Exception $e) {
            return new Response('Login failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}