<?php

namespace Tourze\QQConnectOAuth2Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2StateRepository;

#[ORM\Entity(repositoryClass: QQOAuth2StateRepository::class)]
#[ORM\Table(name: 'qq_oauth2_state', options: ['comment' => 'QQ OAuth2状态表'])]
class QQOAuth2State implements \Stringable
{
    use TimestampableAware;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, options: ['comment' => 'OAuth状态值'])]
    #[IndexColumn]
    private string $state;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '会话ID'])]
    private ?string $sessionId = null;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '元数据'])]
    private ?array $metadata = null;


    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ['comment' => '过期时间'])]
    #[IndexColumn]
    private \DateTime $expireTime;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否已使用'])]
    private bool $used = false;
    
    #[ORM\ManyToOne(targetEntity: QQOAuth2Config::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private QQOAuth2Config $config;

    public function __construct(string $state, QQOAuth2Config $config, int $ttl = 600)
    {
        $this->state = $state;
        $this->config = $config;
        $now = new \DateTime();
        $this->expireTime = (clone $now)->modify(sprintf('+%d seconds', $ttl));
    }

    public function __toString(): string
    {
        return sprintf('OAuth State: %s', $this->state);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }


    public function getExpireTime(): \DateTime
    {
        return $this->expireTime;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function markAsUsed(): self
    {
        $this->used = true;
        return $this;
    }

    public function isValid(): bool
    {
        return !$this->used && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expireTime < new \DateTime();
    }
    
    public function getConfig(): QQOAuth2Config
    {
        return $this->config;
    }
    
    public function setConfig(QQOAuth2Config $config): self
    {
        $this->config = $config;
        return $this;
    }
}