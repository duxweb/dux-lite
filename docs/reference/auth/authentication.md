# 认证系统

DuxLite 基于 JWT 提供简洁的用户认证机制，支持 Header 和 Cookie 两种方式传递令牌。

## 认证配置

使用应用配置中的 `app.secret` 作为 JWT 签名密钥：

```toml
# config/app.toml
[app]
secret = "your-jwt-secret-key"
```

## Auth 类

### 生成 JWT 令牌

```php
use Core\Auth\Auth;

// 生成访问令牌，默认过期时间 24 小时
$token = Auth::token('member', [
    'id' => $user->id,
    'username' => $user->username,
    'role' => $user->role
], 86400);

// 返回格式："Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

### 解码 JWT 令牌

```php
use Core\Auth\Auth;

// 从请求中解码令牌
$payload = Auth::decode($request, 'member');

if ($payload) {
    echo $payload['id'];        // 用户ID
    echo $payload['username'];  // 用户名
    echo $payload['sub'];       // 应用标识（member）
    echo $payload['exp'];       // 过期时间
}
```

### 令牌来源支持

```php
// 自动检测：优先 Header，然后 Cookie
$payload = Auth::decode($request, 'member', 'auto');

// 仅从 Authorization Header 获取
$payload = Auth::decode($request, 'member', 'header');

// 仅从 Cookie 获取
$payload = Auth::decode($request, 'member', 'cookie');
```

## 认证中间件

### 基础使用

```php
use Core\Auth\AuthMiddleware;

// 创建认证中间件
$authMiddleware = new AuthMiddleware(
    app: 'member',              // 应用标识
    callback: null,             // 可选：令牌处理回调
    error: null                 // 可选：错误处理回调
);

// 在路由中使用
$app->add($authMiddleware);
```

### 中间件特性

1. **自动令牌续期**：当令牌剩余时间不足 2/3 时自动续期
2. **来源自适应**：根据令牌来源（Header/Cookie）选择对应的续期方式
3. **请求属性注入**：将认证信息注入到 `auth` 和 `app` 属性中

### 令牌续期机制

```php
// Header 续期：通过 Authorization 响应头返回新令牌
$response = $response->withHeader("Authorization", "Bearer $newToken");

// Cookie 续期：通过 Set-Cookie 响应头设置新令牌
$cookieHeader = sprintf(
    'token=%s; Path=/; HttpOnly; SameSite=Lax',
    urlencode("Bearer $newToken")
);
$response = $response->withAddedHeader('Set-Cookie', $cookieHeader);
```

## 实际应用

### 登录控制器

```php
class AuthController
{
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        
        // 验证用户凭证
        $user = User::query()->where('username', $data['username'])->first();
        if (!$user || !password_verify($data['password'], $user->password)) {
            throw new ExceptionBusiness('用户名或密码错误', 401);
        }
        
        // 生成令牌
        $token = Auth::token('member', [
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role ?? 'user'
        ], 86400); // 24小时过期
        
        return send($response, '登录成功', [
            'token' => $token,
            'user' => $user->transform()
        ]);
    }
    
    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $auth = $request->getAttribute('auth');
        
        if (!$auth) {
            throw new ExceptionBusiness('未认证', 401);
        }
        
        $user = User::find($auth['id']);
        if (!$user) {
            throw new ExceptionBusiness('用户不存在', 404);
        }
        
        return send($response, '获取成功', [
            'user' => $user->transform()
        ]);
    }
}
```

### 路由配置

```php
use Core\Auth\AuthMiddleware;

// 公开路由
$app->post('/auth/login', [AuthController::class, 'login']);

// 需要认证的路由
$app->group('/api', function (RouteCollectorProxy $group) {
    $group->get('/me', [AuthController::class, 'me']);
    $group->get('/profile', [UserController::class, 'profile']);
})->add(new AuthMiddleware('member'));
```

### 路由级认证控制

使用 `#[Action]` 注解中的 `auth` 参数：

```php
#[Resource(app: 'member', route: '/api/users')]
class UserController extends Resources
{
    // 需要认证
    #[Action(['GET'], '/', name: 'list')]
    public function index(...) { }
    
    // 无需认证
    #[Action(['POST'], '/register', name: 'register', auth: false)]  
    public function register(...) { }
}
```

## 令牌格式

### JWT Payload 结构

```json
{
  "sub": "member",           // 应用标识
  "iat": 1640995200,         // 签发时间
  "exp": 1641081600,         // 过期时间
  "id": 123,                 // 用户ID（自定义）
  "username": "john",        // 用户名（自定义）
  "role": "user"             // 用户角色（自定义）
}
```

### 令牌传递方式

**Authorization Header（推荐）：**
```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Cookie 方式：**
```http
Cookie: token=Bearer%20eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

## 最佳实践

### 1. 令牌安全

```php
// ✅ 推荐：使用强密钥
'app.secret' => 'your-very-long-and-random-secret-key-at-least-32-chars'

// ✅ 推荐：合理的过期时间
$accessToken = Auth::token('member', $payload, 3600);    // 1小时
$refreshToken = Auth::token('refresh', $payload, 86400); // 24小时（刷新令牌）
```

### 2. 错误处理

```php
// 统一认证错误处理
$authMiddleware = new AuthMiddleware(
    app: 'member',
    error: function($exception) use ($response) {
        return send($response, '认证失败', null, [], 401);
    }
);
```

### 3. 多应用支持

```php
// 用户端认证
$memberAuth = new AuthMiddleware('member');

// 管理端认证  
$adminAuth = new AuthMiddleware('admin');

// API 端认证
$apiAuth = new AuthMiddleware('api');
```

### 4. 令牌刷新

```php
public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    $refreshPayload = Auth::decode($request, 'refresh');
    
    if (!$refreshPayload) {
        throw new ExceptionBusiness('刷新令牌无效', 401);
    }
    
    // 生成新的访问令牌
    $newToken = Auth::token('member', [
        'id' => $refreshPayload['id'],
        'username' => $refreshPayload['username'],
        'role' => $refreshPayload['role']
    ], 3600);
    
    return send($response, '令牌刷新成功', ['token' => $newToken]);
}
```

DuxLite 认证系统简单易用，基于成熟的 JWT 标准，适合大多数应用场景的身份验证需求。