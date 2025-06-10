# QQ Connect OAuth2 Bundle - 测试计划

## 📝 测试计划概览

本测试计划涵盖了 QQ Connect OAuth2 Bundle 的所有组件，包括单元测试和集成测试。

## 🧪 测试用例列表

### 📦 Entity 测试

| 测试文件 | 被测类 | 关注问题/场景 | 完成情况 | 测试通过 |
|---------|-------|-------------|---------|---------|
| `tests/Unit/Entity/QQOAuth2ConfigTest.php` | `QQOAuth2Config` | 字段验证、约束、Stringable、时间戳 | ⏳ 待开始 | ❌ |

### 📚 Repository 测试

| 测试文件 | 被测类 | 关注问题/场景 | 完成情况 | 测试通过 |
|---------|-------|-------------|---------|---------|
| `tests/Unit/Repository/QQConfigRepositoryTest.php` | `QQConfigRepository` | 查询方法、统计方法、存在性检查 | ⏳ 待开始 | ❌ |
| `tests/Integration/Repository/QQConfigRepositoryTest.php` | `QQConfigRepository` | 数据库集成、实际查询操作 | ⚠️ 需重构 | ❌ |

### 🎯 DTO 测试

| 测试文件 | 被测类 | 关注问题/场景 | 完成情况 | 测试通过 |
|---------|-------|-------------|---------|---------|
| `tests/Unit/DTO/OAuth2TokenTest.php` | `OAuth2Token` | 令牌有效性、过期检查、剩余时间计算 | ⏳ 待开始 | ❌ |
| `tests/Unit/DTO/QQUserInfoTest.php` | `QQUserInfo` | 用户信息解析、头像选择、VIP状态 | ⏳ 待开始 | ❌ |

### ⚙️ Service 测试

| 测试文件 | 被测类 | 关注问题/场景 | 完成情况 | 测试通过 |
|---------|-------|-------------|---------|---------|
| `tests/Unit/Service/QQApiClientTest.php` | `QQApiClient` | API调用、错误处理、重试机制 | ⏳ 待开始 | ❌ |
| `tests/Unit/Service/OAuth2ServiceTest.php` | `OAuth2Service` | 授权流程、CSRF保护、会话管理 | ⏳ 待开始 | ❌ |

### 🎮 Controller 测试

| 测试文件 | 被测类 | 关注问题/场景 | 完成情况 | 测试通过 |
|---------|-------|-------------|---------|---------|
| `tests/Unit/Controller/SimpleQQControllerTest.php` | `SimpleQQController` | 路由处理、请求响应、错误处理 | ⏳ 待开始 | ❌ |
| `tests/Integration/Controller/SimpleQQControllerTest.php` | `SimpleQQController` | 完整HTTP请求流程、集成测试 | ⏳ 待开始 | ❌ |

### 🔧 Bundle 测试

| 测试文件 | 被测类 | 关注问题/场景 | 完成情况 | 测试通过 |
|---------|-------|-------------|---------|---------|
| `tests/Unit/QQConnectOAuth2BundleTest.php` | `QQConnectOAuth2Bundle` | Bundle加载、配置处理 | ⏳ 待开始 | ❌ |

## 🎯 测试覆盖重点

### 🔒 安全性测试

- CSRF状态参数验证
- 会话安全性
- 输入验证和清理
- 异常处理安全性

### 🌐 网络层测试

- HTTP客户端错误处理
- 超时处理
- 重试机制
- API响应格式处理

### 📊 数据层测试

- 实体验证约束
- 数据库查询准确性
- 索引优化验证
- 时间戳自动管理

### 🔄 业务逻辑测试

- OAuth2授权流程完整性
- 令牌生命周期管理
- 用户信息获取和解析
- 多配置支持

## 📋 测试执行命令

```bash
# 执行所有测试
./vendor/bin/phpunit packages/qq-connect-oauth2-bundle/tests

# 执行单元测试
./vendor/bin/phpunit packages/qq-connect-oauth2-bundle/tests/Unit

# 执行集成测试
./vendor/bin/phpunit packages/qq-connect-oauth2-bundle/tests/Integration
```

## 📈 进度统计

- **总计测试文件**: 9
- **已完成**: 0 ✅
- **进行中**: 0 🔄
- **待开始**: 8 ⏳
- **需重构**: 1 ⚠️

## 📝 注意事项

1. 所有测试必须使用标准的 `Tourze\IntegrationTestKernel\IntegrationTestKernel`
2. 不允许引入额外的第三方测试工具
3. 测试必须独立且可重复执行
4. 集成测试需要真实的数据库操作验证
5. 所有测试用例名称必须使用英文
