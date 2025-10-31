<?php

namespace Tourze\QQConnectOAuth2Bundle\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

final class QQOAuth2CallbackController extends AbstractController
{
    public function __construct(
        private QQOAuth2Service $oauth2Service,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Route(path: '/qq-oauth2/callback', name: 'qq_oauth2_callback', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        $error = $request->query->get('error');

        // Check for OAuth error response
        if (null !== $error) {
            $errorDescription = $request->query->get('error_description', 'Unknown error');
            $this->logger?->warning('QQ OAuth2 error response', [
                'error' => $error,
                'error_description' => $errorDescription,
                'ip' => $request->getClientIp(),
            ]);

            return new Response(sprintf('OAuth2 Error: %s', $errorDescription), Response::HTTP_BAD_REQUEST);
        }

        // Validate required parameters
        if (null === $code || null === $state) {
            $this->logger?->warning('Invalid QQ OAuth2 callback parameters', [
                'has_code' => null !== $code && '' !== $code,
                'has_state' => null !== $state && '' !== $state,
                'ip' => $request->getClientIp(),
            ]);

            return new Response('Invalid callback parameters', Response::HTTP_BAD_REQUEST);
        }

        // Validate parameter format
        if (0 === preg_match('/^[a-zA-Z0-9_-]+$/', (string) $code) || 0 === preg_match('/^[a-fA-F0-9]{32}$/', (string) $state)) {
            $this->logger?->warning('Malformed QQ OAuth2 callback parameters', [
                'ip' => $request->getClientIp(),
            ]);

            return new Response('Malformed callback parameters', Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->oauth2Service->handleCallback((string) $code, (string) $state);

            $this->logger?->info('QQ OAuth2 login successful', [
                'openid' => $user->getOpenid(),
                'nickname' => $user->getNickname(),
                'ip' => $request->getClientIp(),
            ]);

            // Here you can integrate with your application's user system
            // For example, create or update local user, set authentication, etc.

            return new Response(sprintf('Successfully logged in as %s', $user->getNickname() ?? $user->getOpenid()));
        } catch (\Exception $e) {
            $this->logger?->error('QQ OAuth2 login failed', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp(),
                'code_prefix' => substr((string) $code, 0, 8) . '...',
                'state' => $state,
            ]);

            return new Response('Login failed: Authentication error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
