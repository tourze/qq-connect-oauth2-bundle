<?php

namespace Tourze\QQConnectOAuth2Bundle\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

final class QQOAuth2LoginController extends AbstractController
{
    public function __construct(
        private QQOAuth2Service $oauth2Service,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Route(path: '/qq-oauth2/login', name: 'qq_oauth2_login', methods: ['GET'])]
    public function __invoke(Request $request): RedirectResponse|Response
    {
        try {
            $sessionId = $request->getSession()->getId();
            $authUrl = $this->oauth2Service->generateAuthorizationUrl($sessionId);

            return new RedirectResponse($authUrl);
        } catch (\Exception $e) {
            $this->logger?->error('QQ OAuth2 login initialization failed', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp(),
            ]);

            return new Response('Login failed: Configuration error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
