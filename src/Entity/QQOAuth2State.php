<?php

namespace Tourze\QQConnectOAuth2Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
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
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '会话ID'])]
    #[Assert\Length(max: 255)]
    private ?string $sessionId = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '元数据'])]
    #[Assert\Type(type: 'array')]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '过期时间'])]
    #[IndexColumn]
    #[Assert\NotNull]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private \DateTimeImmutable $expireTime;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否已使用'])]
    #[Assert\Type(type: 'bool')]
    private bool $used = false;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, options: ['comment' => 'OAuth状态值'])]
    #[IndexColumn]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $state = '';

    #[ORM\ManyToOne(targetEntity: QQOAuth2Config::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?QQOAuth2Config $config = null;

    public function __construct()
    {
        $this->expireTime = new \DateTimeImmutable();
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

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getExpireTime(): \DateTimeImmutable
    {
        return $this->expireTime;
    }

    public function setExpireTime(\DateTimeInterface $expireTime): void
    {
        $this->expireTime = $expireTime instanceof \DateTimeImmutable ? $expireTime : \DateTimeImmutable::createFromInterface($expireTime);
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function markAsUsed(): void
    {
        $this->used = true;
    }

    public function isValid(): bool
    {
        return !$this->used && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expireTime < new \DateTimeImmutable();
    }

    public function getConfig(): ?QQOAuth2Config
    {
        return $this->config;
    }

    public function setConfig(QQOAuth2Config $config): void
    {
        $this->config = $config;
    }

    public function setExpireTimeFromTtl(int $ttl): void
    {
        $now = new \DateTimeImmutable();
        $this->expireTime = $now->modify(sprintf('+%d seconds', $ttl));
    }
}
