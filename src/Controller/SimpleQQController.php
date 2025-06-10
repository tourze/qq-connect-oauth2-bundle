<?php

namespace Tourze\QQConnectOAuth2Bundle\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\QQConnectOAuth2Bundle\Contract\OAuth2ServiceInterface;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2Exception;

/**
 * 简化的QQ互联OAuth2控制器
 */
#[Route('/qq-auth', name: 'qq_auth_')]
class SimpleQQController extends AbstractController
{
    public function __construct(
        private readonly OAuth2ServiceInterface $oauth2Service,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * 发起QQ授权
     */
    #[Route('/login/{configName}', name: 'login', methods: ['GET'])]
    public function login(string $configName, Request $request): RedirectResponse
    {
        try {
            $state = $this->oauth2Service->generateState();
            $scope = $request->query->get('scope');
            $authUrl = $this->oauth2Service->getAuthorizationUrl($configName, $state, $scope);

            $this->logger->info('QQ授权开始', ['configName' => $configName]);

            return new RedirectResponse($authUrl);
        } catch (QQOAuth2Exception $e) {
            $this->logger->error('QQ授权失败', ['error' => $e->getMessage()]);
            throw $this->createNotFoundException('配置不存在');
        }
    }

    /**
     * QQ授权回调
     */
    #[Route('/callback/{configName}', name: 'callback', methods: ['GET'])]
    public function callback(string $configName, Request $request): JsonResponse
    {
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        $error = $request->query->get('error');

        if ($error) {
            return $this->json(['success' => false, 'error' => $error]);
        }

        if (!$code || !$state) {
            return $this->json(['success' => false, 'error' => '参数缺失']);
        }

        try {
            $accessToken = $this->oauth2Service->getAccessToken($configName, $code, $state);
            $openId = $this->oauth2Service->getOpenId($accessToken);
            $userInfo = $this->oauth2Service->getUserInfo($configName, $accessToken, $openId);

            $this->logger->info('QQ登录成功', ['openId' => $openId]);

            return $this->json([
                'success' => true,
                'data' => [
                    'openId' => $openId,
                    'userInfo' => $userInfo->toArray()
                ]
            ]);
        } catch (QQOAuth2Exception $e) {
            $this->logger->error('QQ回调处理失败', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
