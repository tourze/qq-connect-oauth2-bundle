<?php

namespace Tourze\QQConnectOAuth2Bundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineIpBundle\Attribute\CreateIpColumn;
use Tourze\DoctrineIpBundle\Attribute\UpdateIpColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Attribute\CreatedByColumn;
use Tourze\DoctrineUserBundle\Attribute\UpdatedByColumn;

/**
 * QQ互联OAuth2配置实体
 */
#[ORM\Entity(repositoryClass: \Tourze\QQConnectOAuth2Bundle\Repository\QQConfigRepository::class)]
#[ORM\Table(name: 'qq_oauth2_config', options: ['comment' => 'QQ互联OAuth2配置表'])]
#[ORM\Index(columns: ['name', 'environment'], name: 'idx_qq_oauth2_config_name_env')]
#[ORM\Index(columns: ['environment', 'valid'], name: 'idx_qq_oauth2_config_env_valid')]
#[ORM\Index(columns: ['app_id'], name: 'idx_qq_oauth2_config_app_id')]
#[UniqueEntity(fields: ['name'], message: '配置名称已存在')]
#[UniqueEntity(fields: ['appId', 'environment'], message: '该环境下APP ID已存在')]
class QQOAuth2Config implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private readonly int $id;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 100, unique: true, options: ['comment' => '配置名称，用于标识不同的QQ应用配置'])]
    #[Assert\NotBlank(message: '配置名称不能为空')]
    #[Assert\Length(max: 100, maxMessage: '配置名称长度不能超过100个字符')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_-]+$/', message: '配置名称只能包含字母、数字、下划线和短横线')]
    private string $name;

    #[IndexColumn]
    #[TrackColumn]
    #[ORM\Column(type: Types::STRING, length: 32, options: ['comment' => 'QQ互联应用ID'])]
    #[Assert\NotBlank(message: 'APP ID不能为空')]
    #[Assert\Length(max: 32, maxMessage: 'APP ID长度不能超过32个字符')]
    #[Assert\Regex(pattern: '/^\d+$/', message: 'APP ID只能包含数字')]
    private string $appId;

    #[ORM\Column(type: Types::STRING, length: 128, options: ['comment' => 'QQ互联应用密钥'])]
    #[Assert\NotBlank(message: 'APP Key不能为空')]
    #[Assert\Length(max: 128, maxMessage: 'APP Key长度不能超过128个字符')]
    private string $appKey;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '授权回调地址'])]
    #[Assert\NotBlank(message: '回调地址不能为空')]
    #[Assert\Url(message: '回调地址格式不正确')]
    #[Assert\Length(max: 500, maxMessage: '回调地址长度不能超过500个字符')]
    private string $redirectUri;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '授权范围，多个用逗号分隔', 'default' => 'get_user_info'])]
    #[Assert\Length(max: 100, maxMessage: '授权范围长度不能超过100个字符')]
    private ?string $scope = 'get_user_info';

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 10, options: ['comment' => '环境标识：dev/test/prod'])]
    #[Assert\NotBlank(message: '环境标识不能为空')]
    #[Assert\Choice(choices: ['dev', 'test', 'prod'], message: '环境标识必须是：dev、test、prod之一')]
    private string $environment;

    #[IndexColumn]
    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '配置是否启用', 'default' => true])]
    private bool $valid = true;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '配置描述'])]
    #[Assert\Length(max: 500, maxMessage: '配置描述长度不能超过500个字符')]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '排序权重，数值越小优先级越高', 'default' => 0])]
    #[Assert\Range(min: 0, max: 999, notInRangeMessage: '排序权重必须在0-999之间')]
    private int $sortOrder = 0;



    #[CreatedByColumn]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '创建人ID'])]
    private ?int $createdBy = null;

    #[UpdatedByColumn]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '更新人ID'])]
    private ?int $updatedBy = null;

    #[CreateIpColumn]
    #[ORM\Column(type: Types::STRING, length: 45, nullable: true, options: ['comment' => '创建IP地址'])]
    private ?string $createdFromIp = null;

    #[UpdateIpColumn]
    #[ORM\Column(type: Types::STRING, length: 45, nullable: true, options: ['comment' => '更新IP地址'])]
    private ?string $updatedFromIp = null;

    public function __construct(
        string $name,
        string $appId,
        string $appKey,
        string $redirectUri,
        string $environment
    ) {
        $this->name = $name;
        $this->appId = $appId;
        $this->appKey = $appKey;
        $this->redirectUri = $redirectUri;
        $this->environment = $environment;
    }

    public function __toString(): string
    {
        return sprintf('[%s] %s (%s)', $this->environment, $this->name, $this->appId);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
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

    public function getAppKey(): string
    {
        return $this->appKey;
    }

    public function setAppKey(string $appKey): self
    {
        $this->appKey = $appKey;
        return $this;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function setRedirectUri(string $redirectUri): self
    {
        $this->redirectUri = $redirectUri;
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

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function setEnvironment(string $environment): self
    {
        $this->environment = $environment;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }



    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?int $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    public function getCreatedFromIp(): ?string
    {
        return $this->createdFromIp;
    }

    public function setCreatedFromIp(?string $createdFromIp): self
    {
        $this->createdFromIp = $createdFromIp;
        return $this;
    }

    public function getUpdatedFromIp(): ?string
    {
        return $this->updatedFromIp;
    }

    public function setUpdatedFromIp(?string $updatedFromIp): self
    {
        $this->updatedFromIp = $updatedFromIp;
        return $this;
    }

    /**
     * 检查配置是否可用
     */
    public function isUsable(): bool
    {
        return $this->valid && !empty($this->appId) && !empty($this->appKey) && !empty($this->redirectUri);
    }

    /**
     * 获取显示名称
     */
    public function getDisplayName(): string
    {
        return $this->description ?: $this->name;
    }
}
