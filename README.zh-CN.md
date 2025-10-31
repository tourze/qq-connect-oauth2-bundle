# QQConnectOAuth2Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/qq-connect-oauth2-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/qq-connect-oauth2-bundle)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg?style=flat-square)](https://www.php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-%3E%3D7.3-green.svg?style=flat-square)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-103%20passed-green.svg?style=flat-square)](#)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg?style=flat-square)](#)

用于在 Symfony 应用程序中集成 QQ 互联 OAuth2 认证的 Bundle。

## 目录

- [功能特性](#功能特性)
- [安装](#安装)
- [快速开始](#快速开始)
- [配置](#配置)
- [使用方法](#使用方法)
- [命令行工具](#命令行工具)
- [实体](#实体)
- [高级用法](#高级用法)
- [安全性](#安全性)
- [测试](#测试)
- [贡献](#贡献)
- [许可证](#许可证)

## 功能特性

- 完整的 QQ OAuth2 流程实现
- 基于实体的配置管理，支持自动时间戳跟踪
- 通过 RoutingAutoLoaderBundle 自动注册路由
- 基于路由配置自动生成重定向 URI
- 通过命令行支持令牌刷新
- 用户信息检索
- 配置和维护的命令行工具
- 多个 QQ 应用支持（每个用户/状态链接到特定配置）

## 安装

```bash
composer require tourze/qq-connect-oauth2-bundle
```

### 系统要求

- PHP >= 8.1
- Symfony >= 7.3
- Doctrine ORM
- Symfony HttpClient

## 快速开始

1. 安装Bundle：
```bash
composer require tourze/qq-connect-oauth2-bundle
```

2. 更新数据库结构：
```bash
php bin/console doctrine:schema:update --force
```

3. 创建 QQ OAuth2 配置：
```bash
php bin/console qq-oauth2:config create \
    --app-id="YOUR_APP_ID" \
    --app-secret="YOUR_APP_SECRET" \
    --scope="get_user_info"
```

4. 在模板中使用：
```html
<a href="{{ path('qq_oauth2_login') }}">使用 QQ 登录</a>
```

## 配置

### 路由

Bundle 自动注册以下路由：

- `/qq-oauth2/login` - 发起 QQ 登录
- `/qq-oauth2/callback` - OAuth 回调处理器

**注意**: 重定向 URI 基于路由配置自动生成。
确保你的 QQ 应用配置了正确的回调 URL：
`https://yourdomain.com/qq-oauth2/callback`

### Bundle 依赖

此 Bundle 自动包含并配置：

- Tourze DoctrineTimestampBundle - 自动时间戳管理
- Tourze DoctrineIndexedBundle - 自动索引管理  
- Tourze BundleDependency - 正确的Bundle依赖解析

## 使用方法

### 基本登录流程

```php
// 在你的控制器或模板中
<a href="{{ path('qq_oauth2_login') }}">使用 QQ 登录</a>
```

### 获取用户信息

```php
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

class YourController
{
    public function __construct(
        private QQOAuth2Service $qqOAuth2Service
    ) {}

    public function getUserInfo(string $openid): array
    {
        return $this->qqOAuth2Service->getUserInfo($openid);
    }
}
```

### 通过命令刷新令牌

```bash
# 刷新特定用户的令牌
php bin/console qq-oauth2:refresh-token test_openid

# 刷新所有过期的令牌
php bin/console qq-oauth2:refresh-token --all

# 试运行查看将要刷新的内容
php bin/console qq-oauth2:refresh-token --all --dry-run
```

## 命令行工具

### 管理配置

```bash
# 列出所有配置
php bin/console qq-oauth2:config list

# 更新配置
php bin/console qq-oauth2:config update --id=1 --enabled=false

# 删除配置
php bin/console qq-oauth2:config delete --id=1

# 清理过期状态
php bin/console qq-oauth2:cleanup
```

## 实体

Bundle 提供三个主要实体：

1. **QQOAuth2Config** - 存储 OAuth 应用配置（App ID、App Secret 等）
2. **QQOAuth2State** - 管理 OAuth 状态以确保安全（链接到 QQOAuth2Config）
3. **QQOAuth2User** - 存储 QQ 用户信息和令牌（链接到 QQOAuth2Config）

## 高级用法

### 自定义事件处理

你可以通过创建自定义事件监听器来监听 OAuth 事件：

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class QQOAuthEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'qq_oauth.user_authenticated' => 'onUserAuthenticated',
        ];
    }

    public function onUserAuthenticated($event): void
    {
        // 处理成功认证
    }
}
```

### 扩展配置

对于复杂场景，你可以扩展服务：

```php
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

class CustomQQOAuth2Service extends QQOAuth2Service
{
    public function customUserInfoProcessing(array $userInfo): array
    {
        // 添加自定义处理逻辑
        return $userInfo;
    }
}
```

## 安全性

此 Bundle 实现了多项安全措施：

- **状态参数**: 防止 OAuth 流程中的 CSRF 攻击
- **令牌验证**: 验证从 QQ 接收的所有令牌
- **安全存储**: 用户令牌安全存储在数据库中
- **自动清理**: 过期状态自动清理

### 安全最佳实践

1. 生产环境中始终使用 HTTPS
2. 使用提供的命令定期清理过期令牌
3. 监控可疑的 OAuth 活动
4. 保护你的 QQ 应用密钥安全

## 测试

```bash
vendor/bin/phpunit
```

## 贡献

详细信息请参见 [CONTRIBUTING.md](CONTRIBUTING.md)。

## 更新日志

详细信息请参见 [CHANGELOG.md](CHANGELOG.md)。

## 许可证

MIT
