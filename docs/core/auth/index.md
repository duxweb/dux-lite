# 认证与权限

DuxLite 框架提供了完整的认证和权限管理解决方案，基于现代化的 JWT 标准实现安全可靠的用户认证，并配备灵活的权限控制机制。

## 功能特性

- **JWT 认证**：基于 JSON Web Token 的无状态认证
- **自动续期**：智能 token 续期机制
- **权限管理**：细粒度的权限控制系统
- **中间件支持**：开箱即用的认证和权限中间件
- **资源自动化**：资源路由自动注册认证和权限控制
- **灵活配置**：支持多应用、多场景的认证需求

## 核心特性：资源路由自动安全

**重要特性**：使用资源路由时，认证和权限控制会自动注册，无需手动创建：

```php
use Core\Resources\Attribute\Resource;
use Core\Resources\Action\Resources;

#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    protected string $model = User::class;

    // ✨ 自动功能：
    // - 自动添加 AuthMiddleware('admin') 认证中间件
    // - 自动添加 PermissionMiddleware('users', User::class) 权限中间件
    // - 自动生成权限：admin.users.list, admin.users.create 等
    // - 自动注册路由和权限到系统
}
```

这种设计让开发变得极其简单，您只需要关注业务逻辑，安全控制由框架自动处理。

## 文档导航

### [身份验证](./authentication.md)
JWT 身份认证系统的完整指南：
- Auth 类的使用方法
- Token 生成和验证
- 认证中间件集成
- 多应用认证支持
- 实际应用示例

### [权限管理](./authorization.md)
基于角色的权限控制系统：
- 权限定义和组织
- 用户权限模型设计
- 权限中间件使用
- 运行时权限检查
- 权限最佳实践

### [JWT 令牌](./jwt.md)
JWT 技术细节和深入使用：
- JWT 结构和原理
- 签名算法和安全性
- 载荷设计最佳实践
- 性能优化策略
- 调试和监控工具

### [安全中间件](./middleware.md)
安全中间件的配置和自定义：
- 认证和权限中间件详解
- 中间件执行顺序
- 自定义安全中间件
- 中间件最佳实践
- 调试和故障排除

### 快速开始

#### 1. 配置密钥

在配置文件中设置应用密钥：

```toml
[app]
secret = "your-secret-key-here"
```

#### 2. 生成 Token

```php
use Core\Auth\Auth;

$token = Auth::token('user_app', [
    'id' => 123,
    'username' => 'john',
    'role' => 'user'
], 86400); // 24小时过期
```

#### 3. 使用认证中间件

```php
use Core\Auth\AuthMiddleware;

$app->group('/api', function($group) {
    // 受保护的路由
})->add(new AuthMiddleware('user_app'));
```

#### 4. 权限控制

```php
use Core\Permission\PermissionMiddleware;

$app->group('/admin', function($group) {
    // 需要权限的路由
})
->add(new PermissionMiddleware('admin', User::class))
->add(new AuthMiddleware('admin_app'));
```

## 核心概念

### JWT 认证流程

1. **用户登录** → 验证凭据 → 生成 JWT token
2. **发送请求** → 携带 token → 中间件验证
3. **自动续期** → token 即将过期 → 返回新 token
4. **访问资源** → 认证成功 → 处理业务逻辑

### 权限系统架构

```
应用 (App)
├── 权限组 (Permission Group)
│   ├── 权限项 (Permission Item)
│   ├── 权限项 (Permission Item)
│   └── ...
├── 权限组 (Permission Group)
└── ...
```

### 中间件执行顺序

建议的中间件执行顺序：

1. **错误处理** - ErrorHandlingMiddleware
2. **CORS** - CorsMiddleware
3. **认证** - AuthMiddleware
4. **权限** - PermissionMiddleware
5. **业务逻辑** - Controller

## 安全建议

- 使用足够复杂的密钥（至少 32 位随机字符）
- 设置合理的 token 过期时间
- 启用 HTTPS 传输
- 定期更新密钥
- 记录安全相关日志

## 示例应用

### 用户认证 API

```php
// 登录
POST /auth/login
{
    "username": "john",
    "password": "secret"
}

// 响应
{
    "token": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "user": { "id": 123, "username": "john" }
}

// 访问受保护资源
GET /api/profile
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

### 权限控制示例

```php
// 用户权限配置
$permission = new Permission();
$permission->setApp('admin');

$permission->group('user')->resources();     // 用户管理
$permission->group('article')->resources();  // 文章管理
$permission->group('setting')->add('view');  // 系统设置查看
```

通过这套认证系统，您可以快速构建安全可靠的应用程序，同时保持代码的简洁和可维护性。

更详细的使用说明请参考 [认证系统文档](./authentication.md)。