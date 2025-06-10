# QQ Connect OAuth2 Bundle

一个用于Symfony应用的QQ互联OAuth2授权Bundle，提供完整的第三方登录解决方案。

## 功能特性

- ✅ 完整的QQ互联OAuth2.0授权码流程
- ✅ 支持获取QQ用户基础信息（昵称、头像、OpenID等）
- ✅ 数据库实体管理QQ互联配置（APP ID、APP Key、回调URL等）
- ✅ 多环境、多应用配置支持
- ✅ 完善的异常处理和日志记录
- ✅ State参数防CSRF攻击机制
- ✅ 自动重试和错误恢复机制
- ✅ 符合PSR-1、PSR-4、PSR-12规范
- ✅ 支持Symfony 6.4+ 框架

## 安装

### 1. 通过Composer安装

```bash
composer require tourze/qq-connect-oauth2-bundle
```

### 2. 启用Bundle

在 `config/bundles.php` 中添加：

```php
return [
    // ... 其他bundles
    Tourze\QQConnectOAuth2Bundle\QQConnectOAuth2Bundle::class => ['all' => true],
];
```

### 3. 创建数据库表

运行迁移命令创建QQ配置表：

```bash
# 如果使用Doctrine Migrations
php bin/console doctrine:migrations:migrate

# 或者直接创建表结构
php bin/console doctrine:schema:update --force
```

### 4. 配置路由（可选）

如果需要自定义路由，可以在 `config/routes.yaml` 中添加：

```yaml
qq_oauth2:
    resource: '@QQConnectOAuth2Bundle/Controller/'
    type: attribute
```

## 配置说明

### QQ互联应用配置

在数据库中创建QQ互联配置记录：

```php
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

$config = new QQOAuth2Config(
    name: 'default',                                    // 配置名称
    appId: 'your_qq_app_id',                           // QQ互联应用ID
    appKey: 'your_qq_app_key',                         // QQ互联应用密钥
    redirectUri: 'https://your-domain.com/qq-auth/callback/default',
    environment: 'prod'                                 // 环境：dev/test/prod
);

$config->setDescription('生产环境默认QQ互联配置');
$config->setScope('get_user_info');                   // 授权范围

$entityManager->persist($config);
$entityManager->flush();
```

### 示例数据

可以使用提供的Fixtures加载示例数据：

```bash
php bin/console doctrine:fixtures:load --append
```

## 使用方法

### 1. 基本用法

#### 发起QQ登录授权

```php
use Tourze\QQConnectOAuth2Bundle\Contract\OAuth2ServiceInterface;

class LoginController
{
    public function __construct(
        private readonly OAuth2ServiceInterface $oauth2Service
    ) {}

    public function qqLogin(): Response
    {
        $configName = 'default';  // 配置名称
        $state = $this->oauth2Service->generateState();
        
        $authUrl = $this->oauth2Service->getAuthorizationUrl($configName, $state);
        
        return $this->redirect($authUrl);
    }
}
```

#### 处理QQ登录回调

```php
public function qqCallback(Request $request): Response
{
    $configName = 'default';
    $code = $request->query->get('code');
    $state = $request->query->get('state');
    
    try {
        // 获取访问令牌
        $accessToken = $this->oauth2Service->getAccessToken($configName, $code, $state);
        
        // 获取OpenID
        $openId = $this->oauth2Service->getOpenId($accessToken);
        
        // 获取用户信息
        $userInfo = $this->oauth2Service->getUserInfo($configName, $accessToken, $openId);
        
        // 处理用户登录逻辑
        // ...
        
        return new JsonResponse([
            'success' => true,
            'openId' => $openId,
            'userInfo' => [
                'nickname' => $userInfo->getNickname(),
                'avatar' => $userInfo->getBestAvatar(),
                'gender' => $userInfo->getGender(),
                'location' => $userInfo->getLocation(),
                'isVip' => $userInfo->isVip(),
            ]
        ]);
        
    } catch (\Exception $e) {
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 400);
    }
}
```

### 2. 使用内置控制器

Bundle提供了简化的控制器，可以直接使用：

#### 发起授权

```
GET /qq-auth/login/{configName}
```

例如：`https://your-domain.com/qq-auth/login/default`

#### 处理回调

```
GET /qq-auth/callback/{configName}
```

例如：QQ回调到 `https://your-domain.com/qq-auth/callback/default`

### 3. 高级用法

#### 多环境配置

```php
// 开发环境
$devConfig = new QQOAuth2Config(
    name: 'dev_default',
    appId: 'dev_app_id',
    appKey: 'dev_app_key',
    redirectUri: 'http://localhost:8000/qq-auth/callback/dev_default',
    environment: 'dev'
);

// 生产环境  
$prodConfig = new QQOAuth2Config(
    name: 'prod_main',
    appId: 'prod_app_id', 
    appKey: 'prod_app_key',
    redirectUri: 'https://www.example.com/qq-auth/callback/prod_main',
    environment: 'prod'
);
```

#### 多应用配置

```php
// 主站配置
$mainConfig = new QQOAuth2Config(
    name: 'main_site',
    appId: 'main_app_id',
    appKey: 'main_app_key',
    redirectUri: 'https://www.example.com/qq-auth/callback/main_site',
    environment: 'prod'
);

// 移动端配置
$mobileConfig = new QQOAuth2Config(
    name: 'mobile_app',
    appId: 'mobile_app_id',
    appKey: 'mobile_app_key', 
    redirectUri: 'https://m.example.com/qq-auth/callback/mobile_app',
    environment: 'prod'
);
```

## API文档

### OAuth2ServiceInterface

| 方法 | 说明 | 参数 | 返回值 |
|------|------|------|--------|
| `getAuthorizationUrl()` | 生成QQ授权URL | `$configName`, `$state`, `$scope?` | `string` |
| `getAccessToken()` | 获取访问令牌 | `$configName`, `$code`, `$state` | `string` |
| `getOpenId()` | 获取用户OpenID | `$accessToken` | `string` |
| `getUserInfo()` | 获取用户信息 | `$configName`, `$accessToken`, `$openId` | `QQUserInfo` |
| `validateState()` | 验证state参数 | `$state` | `bool` |
| `generateState()` | 生成state参数 | - | `string` |

### QQUserInfo DTO

| 属性/方法 | 说明 | 类型 |
|-----------|------|------|
| `getNickname()` | 用户昵称 | `string` |
| `getBestAvatar()` | 最佳头像URL | `string` |
| `getGender()` | 性别 | `string` |
| `getProvince()` | 省份 | `string` |
| `getCity()` | 城市 | `string` |
| `getLocation()` | 完整地址 | `string` |
| `isVip()` | 是否QQ会员 | `bool` |
| `isYellowVip()` | 是否黄钻用户 | `bool` |

### OAuth2Token DTO

| 属性/方法 | 说明 | 类型 |
|-----------|------|------|
| `getAccessToken()` | 访问令牌 | `string` |
| `getExpiresIn()` | 过期时间（秒） | `int` |
| `getRefreshToken()` | 刷新令牌 | `string` |
| `isValid()` | 令牌是否有效 | `bool` |
| `getRemainingTime()` | 剩余有效时间 | `int` |

## 异常处理

Bundle提供了完整的异常体系：

```php
use Tourze\QQConnectOAuth2Bundle\Exception\ConfigurationNotFoundException;
use Tourze\QQConnectOAuth2Bundle\Exception\InvalidStateException;
use Tourze\QQConnectOAuth2Bundle\Exception\AccessTokenException;
use Tourze\QQConnectOAuth2Bundle\Exception\ApiException;

try {
    $accessToken = $this->oauth2Service->getAccessToken($configName, $code, $state);
} catch (ConfigurationNotFoundException $e) {
    // 配置未找到
} catch (InvalidStateException $e) {
    // State参数验证失败
} catch (AccessTokenException $e) {
    // 访问令牌获取失败
} catch (ApiException $e) {
    // API调用失败
}
```

## 安全性

- ✅ **CSRF防护**：使用state参数防止跨站请求伪造攻击
- ✅ **会话管理**：State参数安全存储在用户会话中
- ✅ **输入验证**：所有用户输入都经过验证和过滤
- ✅ **错误处理**：敏感信息不会在错误消息中泄露
- ✅ **日志记录**：完整的操作日志，便于安全审计

## 性能优化

- ✅ **连接复用**：HTTP客户端连接池复用
- ✅ **自动重试**：网络失败时自动重试，指数退避策略
- ✅ **超时控制**：合理的网络超时设置
- ✅ **数据库索引**：配置表优化索引提升查询性能

## 开发调试

### 启用调试日志

在 `config/packages/dev/monolog.yaml` 中添加：

```yaml
monolog:
    handlers:
        qq_oauth2:
            type: stream
            path: '%kernel.logs_dir%/qq_oauth2.log'
            level: debug
            channels: ['qq_oauth2']
```

### 测试环境配置

QQ互联提供了测试环境，可以在开发时使用：

```php
$testConfig = new QQOAuth2Config(
    name: 'test_config',
    appId: 'test_app_id',
    appKey: 'test_app_key', 
    redirectUri: 'http://localhost:8000/qq-auth/callback/test_config',
    environment: 'dev'
);
```

## 故障排除

### 常见问题

1. **State参数验证失败**
   - 检查session配置是否正确
   - 确保cookie设置允许跨域

2. **获取用户信息失败**
   - 检查APP ID和APP Key是否正确
   - 验证回调URL是否与QQ互联后台配置一致

3. **网络请求超时**
   - 检查服务器网络连接
   - 可以适当增加超时时间设置

### 日志查看

```bash
# 查看QQ OAuth2相关日志
tail -f var/log/dev.log | grep qq_oauth2

# 查看所有错误日志
tail -f var/log/dev.log | grep ERROR
```

## 许可证

本项目基于 MIT 许可证开源。详情请查看 [LICENSE](LICENSE) 文件。

## 贡献

欢迎提交Issues和Pull Requests来改进这个Bundle。

## 支持

如果您在使用过程中遇到问题，请通过以下方式获取支持：

1. 查看本文档的故障排除部分
2. 在GitHub上提交Issue
3. 查看QQ互联官方文档：https://wiki.connect.qq.com/
