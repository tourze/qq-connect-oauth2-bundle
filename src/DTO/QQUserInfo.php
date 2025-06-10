<?php

namespace Tourze\QQConnectOAuth2Bundle\DTO;

/**
 * QQ用户信息数据传输对象
 */
class QQUserInfo implements \Stringable
{
    public function __construct(
        private readonly string $openId,
        private readonly ?string $nickname = null,
        private readonly ?string $figureUrl = null,
        private readonly ?string $figureUrl1 = null,
        private readonly ?string $figureUrl2 = null,
        private readonly ?string $figureUrlQq1 = null,
        private readonly ?string $figureUrlQq2 = null,
        private readonly ?string $gender = null,
        private readonly ?string $province = null,
        private readonly ?string $city = null,
        private readonly ?string $year = null,
        private readonly ?string $constellation = null,
        private readonly ?int $isYellowVip = null,
        private readonly ?int $isYellowYearVip = null,
        private readonly ?int $yellowVipLevel = null,
        private readonly ?int $isYellowHighVip = null
    ) {}

    public function getOpenId(): string
    {
        return $this->openId;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function getFigureUrl(): ?string
    {
        return $this->figureUrl;
    }

    public function getFigureUrl1(): ?string
    {
        return $this->figureUrl1;
    }

    public function getFigureUrl2(): ?string
    {
        return $this->figureUrl2;
    }

    public function getFigureUrlQq1(): ?string
    {
        return $this->figureUrlQq1;
    }

    public function getFigureUrlQq2(): ?string
    {
        return $this->figureUrlQq2;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getYear(): ?string
    {
        return $this->year;
    }

    public function getConstellation(): ?string
    {
        return $this->constellation;
    }

    public function getIsYellowVip(): ?int
    {
        return $this->isYellowVip;
    }

    public function getIsYellowYearVip(): ?int
    {
        return $this->isYellowYearVip;
    }

    public function getYellowVipLevel(): ?int
    {
        return $this->yellowVipLevel;
    }

    public function getIsYellowHighVip(): ?int
    {
        return $this->isYellowHighVip;
    }

    /**
     * 获取最佳头像URL（优先选择高清头像）
     */
    public function getBestAvatarUrl(): ?string
    {
        return $this->figureUrlQq2 
            ?: $this->figureUrlQq1 
            ?: $this->figureUrl2 
            ?: $this->figureUrl1 
            ?: $this->figureUrl;
    }

    /**
     * 获取地理位置信息
     */
    public function getLocation(): ?string
    {
        if (empty($this->province) && empty($this->city)) {
            return null;
        }

        return trim(($this->province ?? '') . ' ' . ($this->city ?? ''));
    }

    /**
     * 检查是否为黄钻用户
     */
    public function isYellowVipUser(): bool
    {
        return $this->isYellowVip === 1 || $this->isYellowYearVip === 1 || $this->isYellowHighVip === 1;
    }

    /**
     * 获取性别描述
     */
    public function getGenderDesc(): ?string
    {
        return match ($this->gender) {
            '男' => '男',
            '女' => '女',
            default => null
        };
    }

    public function __toString(): string
    {
        return sprintf(
            '%s (OpenID: %s)',
            $this->nickname ?: 'Unknown',
            $this->openId
        );
    }

    /**
     * 从QQ API响应创建QQUserInfo实例
     */
    public static function fromApiResponse(array $response, string $openId): self
    {
        return new self(
            openId: $openId,
            nickname: $response['nickname'] ?? null,
            figureUrl: $response['figureurl'] ?? null,
            figureUrl1: $response['figureurl_1'] ?? null,
            figureUrl2: $response['figureurl_2'] ?? null,
            figureUrlQq1: $response['figureurl_qq_1'] ?? null,
            figureUrlQq2: $response['figureurl_qq_2'] ?? null,
            gender: $response['gender'] ?? null,
            province: $response['province'] ?? null,
            city: $response['city'] ?? null,
            year: $response['year'] ?? null,
            constellation: $response['constellation'] ?? null,
            isYellowVip: isset($response['is_yellow_vip']) ? (int)$response['is_yellow_vip'] : null,
            isYellowYearVip: isset($response['is_yellow_year_vip']) ? (int)$response['is_yellow_year_vip'] : null,
            yellowVipLevel: isset($response['yellow_vip_level']) ? (int)$response['yellow_vip_level'] : null,
            isYellowHighVip: isset($response['is_yellow_high_vip']) ? (int)$response['is_yellow_high_vip'] : null
        );
    }

    /**
     * 转换为数组格式
     */
    public function toArray(): array
    {
        return [
            'open_id' => $this->openId,
            'nickname' => $this->nickname,
            'figure_url' => $this->figureUrl,
            'figure_url_1' => $this->figureUrl1,
            'figure_url_2' => $this->figureUrl2,
            'figure_url_qq_1' => $this->figureUrlQq1,
            'figure_url_qq_2' => $this->figureUrlQq2,
            'gender' => $this->gender,
            'province' => $this->province,
            'city' => $this->city,
            'year' => $this->year,
            'constellation' => $this->constellation,
            'is_yellow_vip' => $this->isYellowVip,
            'is_yellow_year_vip' => $this->isYellowYearVip,
            'yellow_vip_level' => $this->yellowVipLevel,
            'is_yellow_high_vip' => $this->isYellowHighVip,
            'best_avatar_url' => $this->getBestAvatarUrl(),
            'location' => $this->getLocation(),
        ];
    }
}
