# 身份验证

DuxLite 提供了基于 JWT (JSON Web Token) 的身份验证系统，实现无状态的用户认证机制。

::: tip 响应函数说明
本文档中的示例使用 `send()` 函数来返回 JSON 响应，这是 DuxLite 框架提供的响应辅助函数。详细的响应处理请参考 [请求与响应](../request-response.md) 文档。
:::

## 认证概述

身份验证系统主要包含以下核心组件：

- **Auth 类**：JWT token 的生成和解码
- **AuthMiddleware**：自动处理认证验证
- **自动续期**：智能 token 刷新机制
- **多应用支持**：支持不同应用的独立认证

## 配置要求

### 应用密钥设置

在配置文件中设置应用密钥：

```toml
# config/use.toml
[app]
secret = "your-secret-key-here"
```

**重要安全建议**：
- 密钥长度至少 32 位随机字符
- 使用强随机生成的密钥
- 定期更换密钥
- 不要在代码中硬编码密钥

## Auth 类使用

### 生成 Token

`Core\Auth\Auth` 类提供了简单易用的 token 生成方法：

```php
use Core\Auth\Auth;

// 基本用法
$token = Auth::token('user_app', [
    'id' => 123,
    'username' => 'john'
]);

// 自定义过期时间（秒）
$token = Auth::token('user_app', [
    'id' => 123,
    'role' => 'admin'
], 3600); // 1小时

// 完整参数示例
$params = [
    'id' => 123,
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'role' => 'admin',
    'department' => 'IT'
];
$token = Auth::token('admin_app', $params, 86400); // 24小时
```

**返回格式**：生成的 token 格式为 `Bearer <jwt_string>`

### Token 载荷结构

JWT token 包含以下标准字段：

| 字段 | 说明 | 示例 |
|------|------|------|
| `sub` | 应用标识符 | `user_app` |
| `iat` | 签发时间戳 | `1234567890` |
| `exp` | 过期时间戳 | `1234654290` |
| 自定义字段 | 您传入的业务数据 | `id`, `username`, `role` 等 |

### 解码 Token

```php
use Core\Auth\Auth;
use Psr\Http\Message\ServerRequestInterface;

public function verifyToken(ServerRequestInterface $request): ?array
{
    // 从请求头中解码 token
    $payload = Auth::decode($request, 'user_app');

    if ($payload) {
        $userId = $payload['id'];
        $username = $payload['username'];
        $role = $payload['role'] ?? 'user';

        // 认证成功，返回用户信息
        return [
            'user_id' => $userId,
            'username' => $username,
            'role' => $role
        ];
    }

    // 认证失败
    return null;
}
```

## 认证中间件

### 自动认证处理

DuxLite 的认证中间件 `AuthMiddleware` 提供了自动化的认证处理：

```php
use Core\Auth\AuthMiddleware;

// 手动使用认证中间件（一般不需要）
$app->group('/api/user', function (RouteCollectorProxy $group) {
    $group->get('/profile', ProfileController::class . ':show');
    $group->post('/update', ProfileController::class . ':update');
})->add(new AuthMiddleware('user_app'));
```

### 资源路由认证配置

**重要说明**：使用资源路由时，认证中间件需要手动添加：

```php
use Core\Resources\Attribute\Resource;
use Core\Resources\Action\Resources;
use Core\Auth\AuthMiddleware;

#[Resource(
    app: 'admin',
    route: '/admin/users',
    name: 'users',
    middleware: [AuthMiddleware::class]  // 需要手动添加认证中间件
)]
class UserController extends Resources
{
    protected string $model = User::class;

    // ✅ 自动创建：权限（admin.users.*）
    // ❌ 需要手动添加：认证中间件
}
```

### 中间件工作流程

认证中间件的执行流程：

1. **请求拦截**：检查 `Authorization` 请求头
2. **Token 验证**：解码并验证 JWT token
3. **应用匹配**：验证 token 中的应用标识符
4. **自动续期**：token 接近过期时生成新 token
5. **请求增强**：将认证信息注入到请求属性

### 获取认证信息

在控制器中获取当前认证用户：

```php
public function getCurrentUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    // 获取认证信息（由中间件自动注入）
    $auth = $request->getAttribute('auth');
    $appName = $request->getAttribute('app');

    if (!$auth) {
        throw new ExceptionBusiness('未认证', 401);
    }

    $userId = $auth['id'];
    $username = $auth['username'];
    $role = $auth['role'] ?? 'user';

    // 获取完整用户信息
    $user = User::find($userId);

    return send($response, 'success', [
        'user' => $user->toArray(),
        'auth_info' => [
            'app' => $appName,
            'role' => $role
        ]
    ]);
}
```

## 跳过认证

### 路由级别跳过

在注解路由中跳过认证：

```php
use Core\Route\Attribute\Route;

class PublicController
{
    #[Route('GET', '/public/info', auth: false)]
    public function publicInfo(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // 此路由跳过认证检查
        return send($response, 'success', ['message' => 'Public information']);
    }

    #[Route('GET', '/public/config', auth: false)]
    public function getConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return send($response, 'success', [
            'app_name' => 'DuxLite',
            'version' => '2.0'
        ]);
    }
}
```

### 资源路由跳过

对于资源控制器的特定方法：

```php
use Core\Resources\Attribute\Action;

#[Resource(app: 'api', route: '/api/posts', name: 'posts')]
class PostController extends Resources
{
    protected string $model = Post::class;

    // 标准资源路由需要认证

    #[Action(['GET'], '/public', auth: false)]
    public function publicPosts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // 公开的文章列表，无需认证
        $posts = Post::where('status', 'published')->get();
        return send($response, 'success', $posts->toArray());
    }
}
```

## Token 自动续期

### 智能续期机制

AuthMiddleware 包含智能的 token 续期机制，能够根据用户的登录方式自动选择合适的续期方式：

- **续期条件**：当 token 剩余有效期小于总有效期的 1/3 时
- **自动处理**：中间件自动生成新 token
- **智能续期方式**：
  - **Header 登录**：新 token 通过 `Authorization` 响应头返回
  - **Cookie 登录**：新 token 通过 `Set-Cookie` 响应头设置
- **无感知续期**：客户端可透明处理续期，特别是 Cookie 登录完全自动化

### 登录方式与续期方式

#### Header 登录方式

```php
// 前端登录时获取 token
const response = await fetch('/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username: 'admin', password: 'password' })
});
const { token } = await response.json();

// 后续请求携带 token
const apiResponse = await fetch('/api/data', {
    headers: { 'Authorization': token }
});

// 检查续期（新 token 在响应头中）
const newToken = apiResponse.headers.get('Authorization');
if (newToken) {
    localStorage.setItem('token', newToken);
}
```

#### Cookie 登录方式

```php
// 服务器端设置 cookie
$cookieHeader = sprintf(
    'token=%s; Path=/; HttpOnly; SameSite=Lax; Max-Age=%d',
    urlencode($token),
    3600
);
$response = $response->withAddedHeader('Set-Cookie', $cookieHeader);
```

```javascript
// 前端登录（服务器自动设置 cookie）
await fetch('/login', {
    method: 'POST',
    credentials: 'include', // 重要：包含 cookie
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username: 'admin', password: 'password' })
});

// 后续请求（自动携带 cookie，续期也自动处理）
const apiResponse = await fetch('/api/data', {
    credentials: 'include'
});
// 无需手动处理续期，cookie 会自动更新
```

### 续期回调处理

```php
use Core\Auth\AuthMiddleware;

// 带续期回调的中间件
$authMiddleware = new AuthMiddleware('user_app', function($oldToken, $newToken) {
    if ($newToken) {
        // 记录续期日志
        error_log("Token renewed for user, old: $oldToken, new: $newToken");

        // 可以在这里执行其他续期逻辑
        // 比如更新用户最后活跃时间等
    }
});
```

## 实际应用示例

### 用户登录认证

```php
use Core\Auth\Auth;
use Core\Handlers\ExceptionBusiness;

class AuthController
{
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        // 验证用户凭据
        $user = User::where('username', $username)->first();
        if (!$user || !password_verify($password, $user->password)) {
            throw new ExceptionBusiness('用户名或密码错误', 401);
        }

        // 检查用户状态
        if ($user->status !== 'active') {
            throw new ExceptionBusiness('账户已被禁用', 403);
        }

        // 生成认证 token
        $token = Auth::token('user_app', [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role
        ], 86400); // 24小时有效期

        // 记录登录日志
        LoginLog::create([
            'user_id' => $user->id,
            'ip' => $request->getServerParams()['REMOTE_ADDR'],
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'login_at' => now()
        ]);

        return send($response, '登录成功', [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role
            ],
            'expires_in' => 86400
        ]);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $auth = $request->getAttribute('auth');

        // 记录登出日志
        if ($auth) {
            LoginLog::create([
                'user_id' => $auth['id'],
                'action' => 'logout',
                'ip' => $request->getServerParams()['REMOTE_ADDR'],
                'logout_at' => now()
            ]);
        }

        return send($response, '登出成功');
    }

    public function refreshToken(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $auth = $request->getAttribute('auth');

        // 生成新的 token
        $newToken = Auth::token('user_app', [
            'id' => $auth['id'],
            'username' => $auth['username'],
            'email' => $auth['email'],
            'role' => $auth['role']
        ], 86400);

        return send($response, 'Token 刷新成功', [
            'token' => $newToken,
            'expires_in' => 86400
        ]);
    }
}
```

### 多应用认证

```php
// 用户前端应用
$userToken = Auth::token('user_app', [
    'id' => $user->id,
    'type' => 'user'
], 86400);

// 管理后台应用
$adminToken = Auth::token('admin_app', [
    'id' => $admin->id,
    'type' => 'admin',
    'permissions' => $admin->permissions
], 28800); // 8小时

// API 应用
$apiToken = Auth::token('api_app', [
    'client_id' => $client->id,
    'scope' => ['read', 'write']
], 3600); // 1小时
```

## 最佳实践

### 1. 安全配置

```php
// ✅ 推荐的配置
[app]
secret = "your-very-long-and-random-secret-key-at-least-32-chars"

// ✅ 合理的过期时间设置
$webToken = Auth::token('web', $data, 86400);    // 24小时 - Web应用
$mobileToken = Auth::token('mobile', $data, 604800); // 7天 - 移动应用
$apiToken = Auth::token('api', $data, 3600);     // 1小时 - API应用
```

### 2. 错误处理

```php
public function handleAuth(ServerRequestInterface $request): array
{
    try {
        $payload = Auth::decode($request, 'user_app');

        if (!$payload) {
            throw new ExceptionBusiness('认证失败', 401);
        }

        // 验证用户是否仍然有效
        $user = User::find($payload['id']);
        if (!$user || $user->status !== 'active') {
            throw new ExceptionBusiness('用户账户无效', 401);
        }

        return $payload;

    } catch (\Firebase\JWT\ExpiredException $e) {
        throw new ExceptionBusiness('Token 已过期', 401);
    } catch (\Firebase\JWT\SignatureInvalidException $e) {
        throw new ExceptionBusiness('Token 签名无效', 401);
    } catch (\Exception $e) {
        throw new ExceptionBusiness('认证失败', 401);
    }
}
```

### 3. 客户端集成

```javascript
// 前端 Token 处理示例
class AuthManager {
    constructor() {
        this.token = localStorage.getItem('auth_token');
    }

    setToken(token) {
        this.token = token;
        localStorage.setItem('auth_token', token);
    }

    // 自动处理 token 续期
    async request(url, options = {}) {
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };

        if (this.token) {
            headers['Authorization'] = this.token;
        }

        const response = await fetch(url, {
            ...options,
            headers
        });

        // 检查是否有新的 token
        const newToken = response.headers.get('Authorization');
        if (newToken) {
            this.setToken(newToken);
        }

        return response;
    }
}
```

## 故障排除

### 常见认证问题

**Q: Token 认证失败**
```
检查清单：
1. app.secret 配置是否正确
2. Authorization 头格式：Bearer <token>
3. token 是否过期
4. 应用标识符是否匹配
5. 用户账户状态是否正常
```

**Q: 自动续期不工作**
```
检查清单：
1. 响应头是否包含新的 Authorization
2. 客户端是否正确处理新 token
3. 续期回调函数是否正确执行
4. token 剩余时间是否符合续期条件
```

**Q: 多应用认证冲突**
```
解决方案：
1. 确保不同应用使用不同的标识符
2. 检查 token 中的 sub 字段
3. 避免在同一客户端混用不同应用的 token
```

身份验证是安全系统的基础，通过 DuxLite 的认证系统，您可以轻松实现安全可靠的用户身份验证功能。