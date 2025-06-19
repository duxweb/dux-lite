# 安全中间件

DuxLite 提供了一套完整的安全中间件，包括认证中间件、权限中间件等，用于保护应用程序的安全性。

## 中间件概述

### 安全中间件架构

DuxLite 的安全中间件采用洋葱模型，按层次处理请求：

```
请求 → CORS → 认证 → 权限 → 业务逻辑 → 权限 ← 认证 ← CORS ← 响应
```

### 核心安全中间件

- **AuthMiddleware**：身份认证中间件
- **PermissionMiddleware**：权限验证中间件
- **CorsMiddleware**：跨域资源共享中间件
- **LangMiddleware**：语言处理中间件

## AuthMiddleware 认证中间件

### 基本功能

认证中间件是安全系统的第一道防线，负责验证用户身份：

```php
use Core\Auth\AuthMiddleware;

// 基本使用
new AuthMiddleware('user_app');

// 带回调的使用
new AuthMiddleware('user_app', function($oldToken, $newToken) {
    // Token 更新回调
});
```

### 构造函数参数

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `app` | `string` | ✅ | 应用标识符，用于验证 JWT 中的 `sub` 字段 |
| `callback` | `\Closure\|null` | ❌ | Token 更新回调函数 |

### 工作流程

1. **提取令牌**：从 `Authorization` 请求头提取 JWT token
2. **解码验证**：使用应用密钥验证 token 签名和有效性
3. **应用匹配**：验证 token 中的应用标识符
4. **用户验证**：检查用户状态和权限
5. **自动续期**：在适当时机生成新 token
6. **请求增强**：将认证信息注入请求属性

### 自动续期机制

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

### 跳过认证

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

### 资源路由中间件配置

**重要说明**：资源路由只会自动创建权限，中间件需要手动添加：

```php
use Core\Resources\Attribute\Resource;
use Core\Auth\AuthMiddleware;
use Core\Permission\PermissionMiddleware;

#[Resource(
    app: 'admin',
    route: '/admin/users',
    name: 'users',
    middleware: [AuthMiddleware::class, PermissionMiddleware::class]  // 手动添加中间件
)]
class UserController extends Resources
{
    // ✅ 自动创建：权限（admin.users.*）
    // ❌ 需要手动添加：认证和权限中间件
}
```

### 认证信息获取

在控制器中获取认证信息：

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

## PermissionMiddleware 权限中间件

### 基本功能

权限中间件在认证之后执行，验证用户是否有访问特定资源的权限：

```php
use Core\Permission\PermissionMiddleware;

// 基本使用
new PermissionMiddleware('users', User::class);
```

### 构造函数参数

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `name` | `string` | ✅ | 权限名称前缀 |
| `model` | `string` | ✅ | 用户模型类名，用于获取用户权限 |

### 权限检查流程

1. **认证检查**：确保用户已通过认证
2. **路由解析**：获取当前路由名称
3. **权限获取**：从用户模型获取权限列表
4. **权限匹配**：检查路由名称是否在用户权限中
5. **访问控制**：允许或拒绝访问

### 权限检查逻辑

```php
public function __invoke(Request $request, RequestHandler $handler): Response
{
    // 检查是否跳过认证
    $auth = Attribute::getRequestParams($request, "auth");
    if ($auth !== null && !$auth) {
        return $handler->handle($request);
    }

    // 检查是否跳过权限
    $can = Attribute::getRequestParams($request, "can");
    if ($can !== null && !$can) {
        return $handler->handle($request);
    }

    // 获取路由名称
    $route = RouteContext::fromRequest($request)->getRoute();
    $routeName = $route->getName();

    // 执行权限检查
    Can::check($request, $this->model, $routeName);

    return $handler->handle($request);
}
```

### 跳过权限检查

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

### 资源路由自动集成

**重要特性**：资源路由会自动配置权限中间件：

```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    protected string $model = User::class;

    // 自动添加 PermissionMiddleware('users', User::class)
    // 自动检查如下权限：
    // - admin.users.list
    // - admin.users.show
    // - admin.users.create
    // - admin.users.edit
    // - admin.users.delete
}
```

## 中间件配置和使用

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

在 `Bootstrap.php` 中配置全局中间件：

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

```php
// 实际的中间件执行栈
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

## 自定义安全中间件

### 创建认证中间件

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CustomAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $app,
        private array $options = []
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // 获取令牌
        $token = $this->extractToken($request);

        if (!$token) {
            return $this->unauthorizedResponse();
        }

        // 验证令牌
        $payload = $this->validateToken($token);

        if (!$payload) {
            return $this->unauthorizedResponse();
        }

        // 注入认证信息
        $request = $request->withAttribute('auth', $payload);
        $request = $request->withAttribute('app', $this->app);

        return $handler->handle($request);
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $authorization = $request->getHeaderLine('Authorization');

        if (preg_match('/Bearer\s+(.*)$/i', $authorization, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function validateToken(string $token): ?array
    {
        try {
            return Auth::decode($token, $this->app);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function unauthorizedResponse(): ResponseInterface
    {
        $response = App::di()->get('response_factory')->createResponse(401);
        $response->getBody()->write(json_encode([
            'code' => 401,
            'message' => 'Unauthorized',
            'data' => null
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

### 创建API限流中间件

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

## 中间件最佳实践

### 1. 中间件设计原则

```php
// ✅ 单一职责
class AuthenticationMiddleware implements MiddlewareInterface
{
    // 只处理认证，不处理权限、日志等
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 专注于认证逻辑
        return $handler->handle($request);
    }
}

// ❌ 混合职责
class MixedMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 不要在一个中间件中处理多个不相关的功能
        $this->authenticate($request);
        $this->logRequest($request);
        $this->checkPermissions($request);
        return $handler->handle($request);
    }
}
```

### 2. 性能优化

```php
class OptimizedAuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // ✅ 提前检查，避免不必要的处理
        if ($this->shouldSkip($request)) {
            return $handler->handle($request);
        }

        // ✅ 使用缓存避免重复验证
        $token = $this->extractToken($request);
        $cacheKey = 'auth:' . hash('sha256', $token);

        $payload = Cache::get($cacheKey);
        if (!$payload) {
            $payload = $this->validateToken($token);
            if ($payload) {
                Cache::put($cacheKey, $payload, 300); // 缓存5分钟
            }
        }

        if (!$payload) {
            return $this->unauthorizedResponse();
        }

        return $handler->handle($request->withAttribute('auth', $payload));
    }
}
```

### 3. 错误处理

```php
class SafeAuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $token = $this->extractToken($request);
            $payload = $this->validateToken($token);

            if (!$payload) {
                return $this->unauthorizedResponse();
            }

            return $handler->handle($request->withAttribute('auth', $payload));

        } catch (\Firebase\JWT\ExpiredException $e) {
            return $this->createErrorResponse(401, 'Token expired');
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return $this->createErrorResponse(401, 'Invalid token signature');
        } catch (\Exception $e) {
            // 记录错误但不暴露详细信息
            error_log('Auth middleware error: ' . $e->getMessage());
            return $this->createErrorResponse(401, 'Authentication failed');
        }
    }
}
```

### 4. 测试友好的设计

```php
class TestableAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $app,
        private AuthService $authService,
        private LoggerInterface $logger
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extractToken($request);

        if (!$this->isValidToken($token)) {
            $this->logger->warning("Invalid token attempt", ['token' => substr($token, 0, 10)]);
            return $this->unauthorizedResponse();
        }

        $payload = $this->authService->validateToken($token, $this->app);

        return $handler->handle($request->withAttribute('auth', $payload));
    }

    // 提取为独立方法，便于测试
    protected function extractToken(ServerRequestInterface $request): ?string
    {
        return str_replace('Bearer ', '', $request->getHeaderLine('Authorization'));
    }

    protected function isValidToken(?string $token): bool
    {
        return !empty($token) && strlen($token) > 10;
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

### 中间件链分析

```php
class MiddlewareAnalyzer
{
    public static function analyzeStack(array $middlewares): array
    {
        return array_map(function($middleware) {
            return [
                'class' => get_class($middleware),
                'memory_usage' => memory_get_usage(),
                'timestamp' => microtime(true)
            ];
        }, $middlewares);
    }
}
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

通过合理使用和配置安全中间件，您可以构建一个安全、高效、可维护的应用程序安全防护体系。