<?php

namespace Tourze\QQConnectOAuth2Bundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;
use Tourze\QQConnectOAuth2Bundle\Exception\QQOAuth2ConfigurationException;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2StateRepository;

/**
 * 状态和安全管理 - 专注CSRF防护和状态管理
 */
class QQStateManager
{
    private const AUTHORIZE_URL = 'https://graph.qq.com/oauth2.0/authorize';

    public function __construct(
        private QQOAuth2StateRepository $stateRepository,
        private EntityManagerInterface $entityManager,
        private ?UrlGeneratorInterface $urlGenerator = null,
    ) {
    }

    /**
     * 生成授权URL
     */
    public function generateAuthorizationUrl(QQOAuth2Config $config, ?string $sessionId = null): string
    {
        $state = bin2hex(random_bytes(16));
        $stateEntity = new QQOAuth2State();
        $stateEntity->setState($state);
        $stateEntity->setConfig($config);

        if (null !== $sessionId) {
            $stateEntity->setSessionId($sessionId);
        }

        $this->entityManager->persist($stateEntity);
        $this->entityManager->flush();

        $redirectUri = $this->generateRedirectUri();

        $params = [
            'response_type' => 'code',
            'client_id' => $config->getAppId(),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => $config->getScope() ?? 'get_user_info',
        ];

        return self::AUTHORIZE_URL . '?' . http_build_query($params);
    }

    /**
     * 验证状态并标记为已使用
     */
    public function validateAndMarkStateAsUsed(string $state): QQOAuth2State
    {
        $stateEntity = $this->stateRepository->findValidState($state);
        if (null === $stateEntity || !$stateEntity->isValid()) {
            throw new QQOAuth2ConfigurationException('Invalid or expired state');
        }

        $stateEntity->markAsUsed();
        $this->entityManager->persist($stateEntity);
        $this->entityManager->flush();

        return $stateEntity;
    }

    /**
     * 清理过期状态
     */
    public function cleanupExpiredStates(): int
    {
        return $this->stateRepository->cleanupExpiredStates();
    }

    /**
     * 生成回调重定向URI
     */
    public function generateRedirectUri(): string
    {
        if (null === $this->urlGenerator) {
            throw new QQOAuth2ConfigurationException('UrlGeneratorInterface is required to generate authorization URL');
        }

        return $this->urlGenerator->generate('qq_oauth2_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
