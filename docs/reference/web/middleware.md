# 中间件系统

DuxLite 基于 PSR-15 标准的中间件系统，提供安全、高效的请求处理机制。

## 基本概念

### 执行流程

中间件遵循"洋葱模型"，按注册顺序执行：

```
请求 → 中间件1 → 中间件2 → 控制器 → 中间件2 → 中间件1 → 响应
```

### 主要用途

- **请求预处理** - 认证、授权、数据验证
- **响应后处理** - 添加头信息、日志记录
- **条件控制** - 根据条件决定是否继续处理

## 创建中间件

### 基本结构

```php
<?php
namespace App\Web\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 请求前处理
        $token = $request->getHeaderLine('Authorization');
        
        if (empty($token)) {
            throw new \Core\Exception\UnauthorizedException('Token required');
        }
        
        // 验证 Token 并添加用户信息
        $user = $this->validateToken($token);
        $request = $request->withAttribute('user', $user);
        
        // 继续处理请求
        $response = $handler->handle($request);
        
        // 响应后处理
        return $response->withHeader('X-Auth-User', $user->name);
    }

    private function validateToken(string $token): object
    {
        // Token 验证逻辑
        return (object)['id' => 1, 'name' => 'User'];
    }
}
```

### 中间件类型

#### 1. 认证中间件

```php
class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extractToken($request);
        
        if (!$token || !$this->validateToken($token)) {
            throw new \Core\Exception\UnauthorizedException();
        }
        
        return $handler->handle($request);
    }
}
```

#### 2. 权限中间件

```php
class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(private string $permission) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');
        
        if (!$user || !$user->hasPermission($this->permission)) {
            throw new \Core\Exception\ForbiddenException();
        }
        
        return $handler->handle($request);
    }
}
```

#### 3. 限流中间件

```php
class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private int $maxRequests = 100, private int $timeWindow = 3600) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $clientIp = $this->getClientIp($request);
        $key = "rate_limit:{$clientIp}";
        
        $count = \Core\App::cache()->get($key, 0);
        
        if ($count >= $this->maxRequests) {
            throw new \Core\Exception\TooManyRequestsException();
        }
        
        \Core\App::cache()->set($key, $count + 1, $this->timeWindow);
        
        return $handler->handle($request);
    }
}
```

#### 4. 日志中间件

```php
class LogMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);
        
        // 记录请求
        \Core\App::log()->info('Request started', [
            'method' => $request->getMethod(),
            'uri' => (string)$request->getUri(),
            'ip' => $this->getClientIp($request),
        ]);
        
        $response = $handler->handle($request);
        
        // 记录响应
        $duration = microtime(true) - $startTime;
        \Core\App::log()->info('Request completed', [
            'status' => $response->getStatusCode(),
            'duration' => round($duration * 1000, 2) . 'ms',
        ]);
        
        return $response;
    }
}
```

## 使用中间件

### 全局中间件

在 `Bootstrap.php` 中注册全局中间件：

```php
public function loadRoute(): void
{
    // 添加全局中间件
    $this->web->addMiddleware(new CorsMiddleware());
    $this->web->addMiddleware(new LogMiddleware());
    
    // 其他配置...
}
```

### 路由中间件

#### 在路由注解中使用

```php
use Core\Attribute\Route;

class UserController
{
    #[Route(methods: 'GET', pattern: '/users', middleware: ['auth'])]
    public function index(): ResponseInterface
    {
        // 需要认证的路由
    }

    #[Route(methods: 'POST', pattern: '/users', middleware: ['auth', 'admin'])]
    public function store(): ResponseInterface
    {
        // 需要认证和管理员权限的路由
    }
}
```

#### 在资源控制器中使用

```php
use Core\Attribute\Resource;

#[Resource(app: 'web', route: '/admin', middleware: [AuthMiddleware::class, PermissionMiddleware::class])]
class AdminController extends Resources
{
    // 所有方法都会应用这些中间件
}
```

### 条件中间件

```php
class ConditionalMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 只在 API 路由中执行
        if (str_starts_with($request->getUri()->getPath(), '/api/')) {
            // API 特定逻辑
            $request = $request->withHeader('X-API-Request', 'true');
        }
        
        return $handler->handle($request);
    }
}
```

## 内置中间件

### 1. CORS 中间件

```php
// 自动处理跨域请求
class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // OPTIONS 预检请求处理
        if ($request->getMethod() === 'OPTIONS') {
            return $this->buildCorsResponse();
        }
        
        $response = $handler->handle($request);
        
        return $this->addCorsHeaders($response);
    }
}
```

### 2. 语言中间件

```php
// 自动检测和设置语言
class LangMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $lang = $this->detectLanguage($request);
        \Core\App::trans()->setLocale($lang);
        
        return $handler->handle($request);
    }
}
```

### 3. 错误处理中间件

```php
// 统一错误处理和响应格式化
class ErrorMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            return $this->handleException($e, $request);
        }
    }
}
```

## 安全中间件

### AuthMiddleware 认证中间件

**构造函数参数**：
- `app` - 应用标识符，用于验证 JWT 中的 `sub` 字段
- `callback` - Token 更新回调函数（可选）

```php
use Core\Auth\AuthMiddleware;

// 基本使用
new AuthMiddleware('user_app');

// 带回调的使用
new AuthMiddleware('user_app', function($oldToken, $newToken) {
    // Token 更新回调
});
```

#### 工作流程

1. **提取令牌**：从 `Authorization` 请求头提取 JWT token
2. **解码验证**：使用应用密钥验证 token 签名和有效性
3. **应用匹配**：验证 token 中的应用标识符
4. **用户验证**：检查用户状态和权限
5. **自动续期**：在适当时机生成新 token
6. **请求增强**：将认证信息注入请求属性

#### 自动续期机制

```php
// 续期触发条件
$expire = $token["exp"] - $token["iat"];           // 总有效期
$renewalTime = $token["iat"] + round($expire / 3); // 续期时间点
$currentTime = time();

if ($renewalTime <= $currentTime) {
    // 生成新令牌
    $token["exp"] = $currentTime + $expire;
    $newToken = JWT::encode($token, $secret, 'HS256');

    // 通过响应头返回
    $response = $response->withHeader("Authorization", "Bearer $newToken");

    // 执行回调
    if ($callback !== null) {
        $callback($oldTokenStr, $newToken);
    }
}
```

#### 跳过认证

```php
use Core\Route\Attribute\Route;

class PublicController
{
    #[Route('GET', '/public/api', auth: false)]
    public function publicApi(): ResponseInterface
    {
        // 跳过认证检查
        return send($response, 'success', $data);
    }
}
```

#### 认证信息获取

```php
public function profile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    // 获取认证信息（由中间件注入）
    $auth = $request->getAttribute('auth');
    $app = $request->getAttribute('app');

    return send($response, 'success', [
        'user_id' => $auth['id'],
        'username' => $auth['username'],
        'app' => $app
    ]);
}
```

### PermissionMiddleware 权限中间件

**构造函数参数**：
- `name` - 权限名称前缀
- `model` - 用户模型类名，用于获取用户权限

```php
use Core\Permission\PermissionMiddleware;

// 基本使用
new PermissionMiddleware('users', User::class);
```

#### 权限检查流程

1. **认证检查**：确保用户已通过认证
2. **路由解析**：获取当前路由名称
3. **权限获取**：从用户模型获取权限列表
4. **权限匹配**：检查路由名称是否在用户权限中
5. **访问控制**：允许或拒绝访问

#### 跳过权限检查

```php
use Core\Resources\Attribute\Action;

class UserController extends Resources
{
    #[Action(['GET'], '/public', can: false)]
    public function publicList(): ResponseInterface
    {
        // 跳过权限检查
        return send($response, 'success', $data);
    }
}
```

#### 资源路由自动集成

```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users', middleware: [AuthMiddleware::class, PermissionMiddleware::class])]
class UserController extends Resources
{
    protected string $model = User::class;

    // 自动检查如下权限：
    // - admin.users.list
    // - admin.users.show
    // - admin.users.create
    // - admin.users.edit
    // - admin.users.delete
}
```

## 中间件配置

### 手动配置中间件

```php
use Slim\Routing\RouteCollectorProxy;

// 单个路由配置
$app->get('/api/profile', ProfileController::class . ':show')
   ->add(new AuthMiddleware('user_app'));

// 路由组配置
$app->group('/api/admin', function (RouteCollectorProxy $group) {
    $group->get('/users', UserController::class . ':list');
    $group->post('/users', UserController::class . ':create');
})
->add(new PermissionMiddleware('users', User::class))
->add(new AuthMiddleware('admin_app'));
```

### 编程式路由配置

```php
use Core\Route\Route;

// 创建带中间件的路由
$route = new Route('/admin', 'admin',
    new AuthMiddleware('admin'),
    new PermissionMiddleware('admin', AdminUser::class)
);

// 注册路由
App::route()->set('admin', $route);
```

### 全局中间件配置

```php
public function loadRoute(): void
{
    $this->web->addBodyParsingMiddleware();
    $this->web->addRoutingMiddleware();

    // 全局安全中间件
    $this->web->addMiddleware(new LangMiddleware);
    $this->web->addMiddleware(new CorsMiddleware);

    // 错误处理中间件
    $errorMiddleware = $this->web->addErrorMiddleware(App::$debug, true, true);
    $errorHandler = new ErrorHandler(/*...*/);
    $errorMiddleware->setDefaultErrorHandler($errorHandler);
}
```

## 中间件执行顺序

### 推荐的执行顺序

```php
// 1. 错误处理（最外层）
$app->addErrorMiddleware(true, true, true);

// 2. CORS 处理
$app->addMiddleware(new CorsMiddleware);

// 3. 语言处理
$app->addMiddleware(new LangMiddleware);

// 4. 路由中间件
$app->addRoutingMiddleware();

// 5. 认证中间件（路由级别）
// 通过路由配置或资源注解自动添加

// 6. 权限中间件（路由级别）
// 通过路由配置或资源注解自动添加

// 7. 业务逻辑处理（控制器）
```

### 中间件栈示例

```
Request
  ↓
ErrorMiddleware
  ↓
CorsMiddleware
  ↓
LangMiddleware
  ↓
RoutingMiddleware
  ↓
AuthMiddleware('admin')
  ↓
PermissionMiddleware('users', User::class)
  ↓
UserController::list()
  ↓
PermissionMiddleware
  ↓
AuthMiddleware
  ↓
RoutingMiddleware
  ↓
LangMiddleware
  ↓
CorsMiddleware
  ↓
ErrorMiddleware
  ↓
Response
```

## 中间件管道

### 执行顺序

中间件按注册顺序执行，遵循"洋葱模型"：

```php
// 注册顺序
$app->addMiddleware(new LogMiddleware());     // 3. 最外层
$app->addMiddleware(new AuthMiddleware());   // 2. 中间层  
$app->addMiddleware(new CorsMiddleware());   // 1. 最内层

// 实际执行顺序
// 请求: Log -> Auth -> Cors -> 控制器
// 响应: 控制器 -> Cors -> Auth -> Log
```

### 中间件组合

```php
class MiddlewareStack
{
    public static function apiStack(): array
    {
        return [
            new CorsMiddleware(),
            new LogMiddleware(),
            new AuthMiddleware(),
            new RateLimitMiddleware(1000, 3600),
        ];
    }
    
    public static function adminStack(): array
    {
        return [
            ...self::apiStack(),
            new PermissionMiddleware('admin'),
        ];
    }
}
```

## 中间件参数

### 参数化中间件

```php
class CacheMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $ttl = 3600,
        private string $driver = 'default'
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cacheKey = $this->generateCacheKey($request);
        $cache = \Core\App::cache($this->driver);
        
        // 尝试从缓存获取响应
        if ($cachedResponse = $cache->get($cacheKey)) {
            return $this->createResponseFromCache($cachedResponse);
        }
        
        $response = $handler->handle($request);
        
        // 缓存响应
        if ($response->getStatusCode() === 200) {
            $cache->set($cacheKey, $this->serializeResponse($response), $this->ttl);
        }
        
        return $response;
    }
}
```

## 中间件最佳实践

### 1. 单一职责

```php
// ✅ 好的做法：单一职责
class AuthMiddleware implements MiddlewareInterface
{
    // 只负责认证
}

class LogMiddleware implements MiddlewareInterface  
{
    // 只负责日志
}

// ❌ 避免：多重职责
class AuthLogMiddleware implements MiddlewareInterface
{
    // 既做认证又做日志
}
```

### 2. 无状态设计

```php
// ✅ 好的做法：无状态
class RateLimitMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 使用外部存储（Redis/Database）保存状态
        $count = \Core\App::cache()->get("rate_limit:{$ip}");
    }
}

// ❌ 避免：有状态
class BadRateLimitMiddleware implements MiddlewareInterface
{
    private array $requestCounts = []; // 状态数据
}
```

### 3. 合理的异常处理

```php
class SafeMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // 中间件逻辑
            return $handler->handle($request);
        } catch (\Exception $e) {
            // 记录错误但不阻断请求
            \Core\App::log()->error('Middleware error', ['error' => $e->getMessage()]);
            return $handler->handle($request);
        }
    }
}
```

### 4. 性能考虑

```php
class OptimizedMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 快速路径：避免不必要的处理
        if ($this->shouldSkip($request)) {
            return $handler->handle($request);
        }
        
        // 延迟初始化昂贵的操作
        $service = $this->getService();
        
        return $handler->handle($request);
    }
}
```

## 自定义中间件示例

### API限流中间件

```php
class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $maxRequests = 100,
        private int $windowSeconds = 3600
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $key = $this->getRateLimitKey($request);
        $current = Cache::get($key, 0);

        if ($current >= $this->maxRequests) {
            return $this->rateLimitExceededResponse();
        }

        // 增加请求计数
        Cache::put($key, $current + 1, $this->windowSeconds);

        $response = $handler->handle($request);

        // 添加限流响应头
        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string)($this->maxRequests - $current - 1))
            ->withHeader('X-RateLimit-Reset', (string)(time() + $this->windowSeconds));
    }

    private function getRateLimitKey(ServerRequestInterface $request): string
    {
        $auth = $request->getAttribute('auth');  
        $userId = $auth['id'] ?? 'anonymous';
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';

        return "rate_limit:{$userId}:{$ip}:" . floor(time() / $this->windowSeconds);
    }

    private function rateLimitExceededResponse(): ResponseInterface
    {
        $response = App::di()->get('response_factory')->createResponse(429);
        $response->getBody()->write(json_encode([
            'code' => 429,
            'message' => 'Too Many Requests',
            'data' => null
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

## 中间件调试

### 调试中间件执行

```php
class DebugMiddleware implements MiddlewareInterface
{
    public function __construct(private string $name) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = microtime(true);
        error_log("MIDDLEWARE START: {$this->name}");

        $response = $handler->handle($request);

        $duration = (microtime(true) - $start) * 1000;
        error_log("MIDDLEWARE END: {$this->name} ({$duration}ms)");

        return $response;
    }
}

// 使用调试中间件
$app->group('/api', function($group) {
    // 业务路由
})
->add(new DebugMiddleware('Permission'))
->add(new PermissionMiddleware('users', User::class))
->add(new DebugMiddleware('Auth'))
->add(new AuthMiddleware('admin'));
```

## 故障排除

### 常见中间件问题

**Q: 认证中间件不生效**
```
检查清单：
1. 中间件是否正确添加到路由
2. 中间件执行顺序是否正确
3. JWT 密钥配置是否正确
4. 请求头格式是否正确（Bearer token）
```

**Q: 权限检查失败**
```
检查清单：
1. 用户模型是否包含 permission 属性
2. 权限名称是否与路由名称匹配
3. 认证中间件是否在权限中间件之前执行
4. 用户权限数组是否包含所需权限
```

**Q: 中间件执行顺序问题**
```
解决方案：
1. 理解 Slim 中间件的洋葱模型
2. 后添加的中间件先执行（LIFO）
3. 使用调试中间件跟踪执行顺序
4. 确保认证在权限检查之前
```

**Q: 性能问题**
```
优化建议：
1. 使用缓存避免重复验证
2. 提前检查跳过不必要的处理
3. 避免在中间件中执行重型操作
4. 使用异步处理非关键操作
```

通过合理使用中间件，可以实现关注点分离，让代码更加模块化和可维护。