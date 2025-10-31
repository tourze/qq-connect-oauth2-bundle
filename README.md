# QQConnectOAuth2Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/qq-connect-oauth2-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/qq-connect-oauth2-bundle)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg?style=flat-square)](https://www.php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-%3E%3D7.3-green.svg?style=flat-square)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-103%20passed-green.svg?style=flat-square)](#)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg?style=flat-square)](#)

A Symfony bundle for integrating QQ Connect OAuth2 authentication into your application.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Usage](#usage)
- [CLI Commands](#cli-commands)
- [Entities](#entities)
- [Advanced Usage](#advanced-usage)
- [Security](#security)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Features

- Complete QQ OAuth2 flow implementation
- Entity-based configuration management with automatic timestamp tracking
- Automatic route registration via RoutingAutoLoaderBundle
- Automatic redirect URI generation from routing
- Token refresh support via CLI commands
- User information retrieval
- CLI commands for configuration and maintenance
- Multiple QQ app support (each user/state linked to specific config)

## Installation

```bash
composer require tourze/qq-connect-oauth2-bundle
```

### Requirements

- PHP >= 8.1
- Symfony >= 7.3
- Doctrine ORM
- Symfony HttpClient

## Quick Start

1. Install the bundle:
```bash
composer require tourze/qq-connect-oauth2-bundle
```

2. Update your database schema:
```bash
php bin/console doctrine:schema:update --force
```

3. Create QQ OAuth2 configuration:
```bash
php bin/console qq-oauth2:config create \
    --app-id="YOUR_APP_ID" \
    --app-secret="YOUR_APP_SECRET" \
    --scope="get_user_info"
```

4. Use in your template:
```html
<a href="{{ path('qq_oauth2_login') }}">Login with QQ</a>
```

## Configuration

### Routes

The bundle automatically registers the following routes:

- `/qq-oauth2/login` - Initiate QQ login
- `/qq-oauth2/callback` - OAuth callback handler

**Note**: The redirect URI is automatically generated based on your routing configuration. 
Make sure your QQ application is configured with the correct callback URL: 
`https://yourdomain.com/qq-oauth2/callback`

### Bundle Dependencies

This bundle automatically includes and configures:

- Tourze DoctrineTimestampBundle - for automatic timestamp management
- Tourze DoctrineIndexedBundle - for automatic index management
- Tourze BundleDependency - for proper bundle dependency resolution

## Usage

### Basic Login Flow

```php
// In your controller or template
<a href="{{ path('qq_oauth2_login') }}">Login with QQ</a>
```

### Get User Information

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

### Refresh Tokens via Command

```bash
# Refresh a specific user's token
php bin/console qq-oauth2:refresh-token test_openid

# Refresh all expired tokens
php bin/console qq-oauth2:refresh-token --all

# Dry run to see what would be refreshed
php bin/console qq-oauth2:refresh-token --all --dry-run
```

## CLI Commands

### Manage configurations

```bash
# List all configurations
php bin/console qq-oauth2:config list

# Update configuration
php bin/console qq-oauth2:config update --id=1 --enabled=false

# Delete configuration
php bin/console qq-oauth2:config delete --id=1

# Clean up expired states
php bin/console qq-oauth2:cleanup
```

## Entities

The bundle provides three main entities:

1. **QQOAuth2Config** - Stores OAuth application configuration (App ID, App Secret, etc.)
2. **QQOAuth2State** - Manages OAuth state for security (linked to QQOAuth2Config)
3. **QQOAuth2User** - Stores QQ user information and tokens (linked to QQOAuth2Config)

## Advanced Usage

### Custom Event Handling

You can listen to OAuth events by creating custom event listeners:

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
        // Handle successful authentication
    }
}
```

### Extended Configuration

For complex scenarios, you can extend the service:

```php
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

class CustomQQOAuth2Service extends QQOAuth2Service
{
    public function customUserInfoProcessing(array $userInfo): array
    {
        // Add custom processing logic
        return $userInfo;
    }
}
```

## Security

This bundle implements several security measures:

- **State Parameter**: Prevents CSRF attacks during OAuth flow
- **Token Validation**: Validates all tokens received from QQ
- **Secure Storage**: User tokens are stored securely in the database
- **Automatic Cleanup**: Expired states are automatically cleaned up

### Security Best Practices

1. Always use HTTPS in production
2. Regularly clean up expired tokens using the provided commands
3. Monitor for suspicious OAuth activities
4. Keep your QQ application secrets secure

## Testing

```bash
vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for details.

## License

MIT