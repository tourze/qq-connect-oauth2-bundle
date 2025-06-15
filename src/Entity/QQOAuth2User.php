<?php

namespace Tourze\QQConnectOAuth2Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;

#[ORM\Entity(repositoryClass: QQOAuth2UserRepository::class)]
#[ORM\Table(name: 'qq_oauth2_user', options: ['comment' => 'QQ OAuth2用户表'])]
class QQOAuth2User implements \Stringable
{
    use TimestampableAware;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, options: ['comment' => 'QQ OpenID'])]
    #[IndexColumn]
    private string $openid;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => 'QQ UnionID'])]
    private ?string $unionid = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '昵称'])]
    private ?string $nickname = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '头像地址'])]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, options: ['comment' => '性别'])]
    private ?string $gender = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '省份'])]
    private ?string $province = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '城市'])]
    private ?string $city = null;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '访问令牌'])]
    private string $accessToken;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '刷新令牌'])]
    private ?string $refreshToken = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '过期时间（秒）'])]
    private int $expiresIn;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ['comment' => '令牌更新时间'])]
    private \DateTime $tokenUpdateTime;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '关联用户引用'])]
    #[IndexColumn]
    private ?string $userReference = null;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '原始数据'])]
    private ?array $rawData = null;
    
    #[ORM\ManyToOne(targetEntity: QQOAuth2Config::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private QQOAuth2Config $config;


    public function __construct(string $openid, string $accessToken, int $expiresIn, QQOAuth2Config $config)
    {
        $this->openid = $openid;
        $this->accessToken = $accessToken;
        $this->expiresIn = $expiresIn;
        $this->config = $config;
        $this->tokenUpdateTime = new \DateTime();
    }

    public function __toString(): string
    {
        return sprintf('QQ User: %s (%s)', $this->nickname ?: 'Unknown', $this->openid);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOpenid(): string
    {
        return $this->openid;
    }

    public function getUnionid(): ?string
    {
        return $this->unionid;
    }

    public function setUnionid(?string $unionid): self
    {
        $this->unionid = $unionid;
        return $this;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setNickname(?string $nickname): self
    {
        $this->nickname = $nickname;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): self
    {
        $this->gender = $gender;
        return $this;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): self
    {
        $this->province = $province;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;
        $this->tokenUpdateTime = new \DateTime();
        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function setExpiresIn(int $expiresIn): self
    {
        $this->expiresIn = $expiresIn;
        return $this;
    }

    public function getTokenUpdateTime(): \DateTime
    {
        return $this->tokenUpdateTime;
    }

    public function getUserReference(): ?string
    {
        return $this->userReference;
    }

    public function setUserReference(?string $userReference): self
    {
        $this->userReference = $userReference;
        return $this;
    }

    public function getRawData(): ?array
    {
        return $this->rawData;
    }

    public function setRawData(?array $rawData): self
    {
        $this->rawData = $rawData;
        return $this;
    }


    public function isTokenExpired(): bool
    {
        $expiresAt = (clone $this->tokenUpdateTime)->modify(sprintf('+%d seconds', $this->expiresIn));
        return $expiresAt < new \DateTime();
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