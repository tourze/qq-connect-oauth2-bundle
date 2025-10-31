<?php

namespace Tourze\QQConnectOAuth2Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;

#[ORM\Entity(repositoryClass: QQOAuth2ConfigRepository::class)]
#[ORM\Table(name: 'qq_oauth2_config', options: ['comment' => 'QQ OAuth2配置表'])]
class QQOAuth2Config implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => 'QQ应用ID'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $appId = '';

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => 'QQ应用密钥'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $appSecret = '';

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '授权范围'])]
    #[Assert\Length(max: 65535)]
    private ?string $scope = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\Type(type: 'bool')]
    private bool $valid = true;

    public function __toString(): string
    {
        return sprintf('QQ OAuth2 Config #%d (%s)', $this->id ?? 0, $this->appId);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    public function getAppSecret(): string
    {
        return $this->appSecret;
    }

    public function setAppSecret(string $appSecret): void
    {
        $this->appSecret = $appSecret;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(?string $scope): void
    {
        $this->scope = $scope;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }
}
