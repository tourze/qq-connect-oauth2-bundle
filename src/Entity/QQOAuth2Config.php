<?php

namespace Tourze\QQConnectOAuth2Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;

#[ORM\Entity(repositoryClass: QQOAuth2ConfigRepository::class)]
#[ORM\Table(name: 'qq_oauth2_config', options: ['comment' => 'QQ OAuth2配置表'])]
class QQOAuth2Config implements \Stringable
{
    use TimestampableAware;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => 'QQ应用ID'])]
    private string $appId = '';

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => 'QQ应用密钥'])]
    private string $appSecret = '';


    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '授权范围'])]
    private ?string $scope = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
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

    public function setAppId(string $appId): self
    {
        $this->appId = $appId;
        return $this;
    }

    public function getAppSecret(): string
    {
        return $this->appSecret;
    }

    public function setAppSecret(string $appSecret): self
    {
        $this->appSecret = $appSecret;
        return $this;
    }


    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(?string $scope): self
    {
        $this->scope = $scope;
        return $this;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): self
    {
        $this->valid = $valid;
        return $this;
    }

}