# QQConnectOAuth2Bundle

A Symfony bundle for integrating QQ Connect OAuth2 authentication into your application.

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

## Configuration

### 1. Update your database schema

```bash
php bin/console doctrine:schema:update --force
```

### 2. Create QQ OAuth2 configuration

```bash
php bin/console qq-oauth2:config create \
    --app-id="YOUR_APP_ID" \
    --app-secret="YOUR_APP_SECRET" \
    --scope="get_user_info"
```

**Note**: The redirect URI is automatically generated based on your routing configuration. Make sure your QQ application is configured with the correct callback URL: `https://yourdomain.com/qq-oauth2/callback`

### 3. Routes

The bundle automatically registers the following routes:

- `/qq-oauth2/login` - Initiate QQ login
- `/qq-oauth2/callback` - OAuth callback handler

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

## Testing

```bash
vendor/bin/phpunit
```

## License

MIT