<?php

namespace Tourze\QQConnectOAuth2Bundle\DTO;

/**
 * OAuth2令牌数据传输对象
 */
class OAuth2Token implements \Stringable
{
    public function __construct(
        private readonly string $accessToken,
        private readonly string $tokenType = 'Bearer',
        private readonly ?int $expiresIn = null,
        private readonly ?string $refreshToken = null,
        private readonly ?string $scope = null,
        private readonly ?\DateTimeInterface $expiresAt = null
    ) {}

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getTokenType(): string
    {
        return $this->tokenType;
    }

    public function getExpiresIn(): ?int
    {
        return $this->expiresIn;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    /**
     * 检查令牌是否已过期
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new \DateTimeImmutable();
    }

    /**
     * 检查令牌是否有效（未过期且有访问令牌）
     */
    public function isValid(): bool
    {
        return !empty($this->accessToken) && !$this->isExpired();
    }

    /**
     * 获取剩余有效时间（秒）
     */
    public function getRemainingTime(): ?int
    {
        if ($this->expiresAt === null) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $remaining = $this->expiresAt->getTimestamp() - $now->getTimestamp();

        return max(0, $remaining);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s %s (expires: %s)',
            $this->tokenType,
            substr($this->accessToken, 0, 10) . '...',
            $this->expiresAt?->format('Y-m-d H:i:s') ?? 'never'
        );
    }

    /**
     * 从QQ API响应创建OAuth2Token实例
     */
    public static function fromApiResponse(array $response): self
    {
        $expiresAt = null;
        if (isset($response['expires_in']) && is_numeric($response['expires_in'])) {
            $expiresAt = (new \DateTimeImmutable())->modify('+' . $response['expires_in'] . ' seconds');
        }

        return new self(
            accessToken: $response['access_token'] ?? '',
            tokenType: $response['token_type'] ?? 'Bearer',
            expiresIn: isset($response['expires_in']) ? (int)$response['expires_in'] : null,
            refreshToken: $response['refresh_token'] ?? null,
            scope: $response['scope'] ?? null,
            expiresAt: $expiresAt
        );
    }

    /**
     * 转换为数组格式
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
            'refresh_token' => $this->refreshToken,
            'scope' => $this->scope,
            'expires_at' => $this->expiresAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
