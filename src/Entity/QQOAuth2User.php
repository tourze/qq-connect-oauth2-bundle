<?php

namespace Tourze\QQConnectOAuth2Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
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
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => 'QQ UnionID'])]
    #[Assert\Length(max: 255)]
    private ?string $unionid = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '昵称'])]
    #[Assert\Length(max: 255)]
    private ?string $nickname = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '头像地址'])]
    #[Assert\Length(max: 500)]
    #[Assert\Url(message: '头像地址必须是有效的URL')]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, options: ['comment' => '性别'])]
    #[Assert\Length(max: 10)]
    private ?string $gender = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '省份'])]
    #[Assert\Length(max: 255)]
    private ?string $province = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '城市'])]
    #[Assert\Length(max: 255)]
    private ?string $city = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '刷新令牌'])]
    #[Assert\Length(max: 65535)]
    private ?string $refreshToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '令牌更新时间'])]
    #[Assert\NotNull]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private \DateTimeImmutable $tokenUpdateTime;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '关联用户引用'])]
    #[IndexColumn]
    #[Assert\Length(max: 255)]
    private ?string $userReference = null;

    /**
     * @var array<int|string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '原始数据'])]
    #[Assert\Type(type: 'array')]
    private ?array $rawData = null;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '访问令牌'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 65535)]
    private string $accessToken;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '过期时间（秒）'])]
    #[Assert\Positive]
    private int $expiresIn;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, options: ['comment' => 'QQ OpenID'])]
    #[IndexColumn]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $openid = '';

    #[ORM\ManyToOne(targetEntity: QQOAuth2Config::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?QQOAuth2Config $config = null;

    public function __construct()
    {
        $this->tokenUpdateTime = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('QQ User: %s (%s)', $this->nickname ?? 'Unknown', $this->openid);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOpenid(): string
    {
        return $this->openid;
    }

    public function setOpenid(string $openid): void
    {
        $this->openid = $openid;
    }

    public function getUnionid(): ?string
    {
        return $this->unionid;
    }

    public function setUnionid(?string $unionid): void
    {
        $this->unionid = $unionid;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setNickname(?string $nickname): void
    {
        $this->nickname = $nickname;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): void
    {
        $this->avatar = $avatar;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): void
    {
        $this->gender = $gender;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): void
    {
        $this->province = $province;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
        $this->tokenUpdateTime = new \DateTimeImmutable();
    }

    public function updateToken(string $accessToken, int $expiresIn): void
    {
        $this->accessToken = $accessToken;
        $this->expiresIn = $expiresIn;
        $this->tokenUpdateTime = new \DateTimeImmutable();
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function setExpiresIn(int $expiresIn): void
    {
        $this->expiresIn = $expiresIn;
    }

    public function getTokenUpdateTime(): \DateTimeImmutable
    {
        return $this->tokenUpdateTime;
    }

    public function setTokenUpdateTime(\DateTimeInterface $tokenUpdateTime): void
    {
        $this->tokenUpdateTime = $tokenUpdateTime instanceof \DateTimeImmutable ? $tokenUpdateTime : \DateTimeImmutable::createFromInterface($tokenUpdateTime);
    }

    public function getUserReference(): ?string
    {
        return $this->userReference;
    }

    public function setUserReference(?string $userReference): void
    {
        $this->userReference = $userReference;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function getRawData(): ?array
    {
        return $this->rawData;
    }

    /**
     * @param array<int|string, mixed>|null $rawData
     */
    public function setRawData(?array $rawData): void
    {
        $this->rawData = $rawData;
    }

    public function isTokenExpired(): bool
    {
        $expiresAt = (clone $this->tokenUpdateTime)->modify(sprintf('+%d seconds', $this->expiresIn));

        return $expiresAt < new \DateTimeImmutable();
    }

    public function getConfig(): ?QQOAuth2Config
    {
        return $this->config;
    }

    public function setConfig(QQOAuth2Config $config): void
    {
        $this->config = $config;
    }
}
