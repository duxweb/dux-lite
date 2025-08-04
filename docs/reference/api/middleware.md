# 中间件

中间件是处理 HTTP 请求的过滤器，在请求到达控制器前后执行额外逻辑。DuxLite 基于 PSR-15 标准实现了完整的中间件系统。

## 核心中间件

### AUTH 认证中间件

用于验证用户身份和令牌：

```php
use Core\Auth\AuthMiddleware;

// 推荐在 App.php 中注册路由时定义中间件
// 在 App.php 的 register 方法中：
public function register(Bootstrap $app): void
{
    // 注册路由应用并添加中间件
    $apiRoute = new Route('/api', 'api', new AuthMiddleware('api'));
    \Core\App::route()->set('api', $apiRoute);
}

// 在控制器中无需重复定义中间件
#[RouteGroup(
    app: 'api',
    route: '/auth'
)]
class AuthController
{
    // 自动应用认证中间件
}
```

#### 工作原理

1. 从 `Authorization` 请求头提取 JWT token
2. 验证 token 签名和有效性
3. 检查用户状态
4. 将认证信息注入请求属性

#### 获取认证信息

```php
public function profile(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 获取认证用户信息
    $auth = $request->getAttribute('auth');
    
    return send($response, '获取成功', [
        'user_id' => $auth['id'],
        'username' => $auth['username']
    ]);
}
```

#### 跳过认证

```php
#[Route(['GET'], '/public/info', '', null, false)]
public function publicInfo(): ResponseInterface
{
    // 无需认证的公开接口
    return send($response, '公开信息');
}
```

### API 签名中间件

用于验证 API 请求的签名，防止篡改：

```php
use Core\Api\ApiMiddleware;

// 推荐在 App.php 中注册路由时定义中间件
// 在 App.php 的 register 方法中：
public function register(Bootstrap $app): void
{
    // 注册路由应用并添加中间件
    $apiRoute = new Route('/api', 'api', new ApiMiddleware('api'));
    \Core\App::route()->set('api', $apiRoute);
}

// 在控制器中无需重复定义中间件
#[RouteGroup(
    app: 'api',
    route: '/secure'
)]
class SecureApiController
{
    // 自动应用签名验证中间件
}
```

#### 签名验证流程

1. 提取请求参数和时间戳
2. 按算法生成签名
3. 对比请求中的签名
4. 验证时间戳防重放

#### 签名生成示例

```php
// 客户端签名生成
$params = [
    'timestamp' => time(),
    'nonce' => uniqid(),
    'data' => $requestData
];

ksort($params);
$signString = http_build_query($params);
$signature = hash_hmac('sha256', $signString, $secretKey);

// 添加到请求头
$headers = [
    'X-Api-Signature' => $signature,
    'X-Api-Timestamp' => $params['timestamp'],
    'X-Api-Nonce' => $params['nonce']
];
```

### 多语言中间件

自动检测和设置用户语言：

```php
use Core\Middleware\LangMiddleware;

// 全局中间件，自动加载
// 不需要手动添加
```

#### 语言检测顺序

1. 查询参数：`?lang=zh-CN`
2. 请求头：`Accept-Language: zh-CN,en;q=0.9`
3. 默认语言：`zh-CN`

#### 支持的语言

- `zh-CN`：简体中文
- `en-US`：英语
- `ja-JP`：日语

#### 在控制器中使用

```php
public function getMessage(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 自动本地化消息
    $message = trans('common.welcome');
    
    return send($response, $message);
}
```

## 中间件使用

### 路由组中间件

推荐在 App.php 中注册路由时定义中间件，而不是在注解中使用：

```php
// 在 App.php 的 register 方法中：
public function register(Bootstrap $app): void
{
    // 注册路由应用并添加中间件
    $adminRoute = new Route('/admin', 'admin',
        new AuthMiddleware('admin'),
        new PermissionMiddleware('admin', 'Admin')
    );
    \Core\App::route()->set('admin', $adminRoute);
}

// 在控制器中无需重复定义中间件
#[RouteGroup(
    app: 'admin',
    route: '/api'
)]
class AdminApiController
{
    // 自动应用认证和权限中间件
}
```

### 资源路由中间件

推荐在 App.php 中注册路由时定义中间件，而不是在注解中使用：

```php
// 在 App.php 的 register 方法中：
public function register(Bootstrap $app): void
{
    // 注册路由应用并添加中间件
    $apiRoute = new Route('/api', 'api', new AuthMiddleware('api'));
    \Core\App::route()->set('api', $apiRoute);
}

// 在资源控制器中无需重复定义中间件
#[Resource(
    app: 'api',
    route: '/users',
    name: 'users'
)]
class UserController extends Resources
{
    // 所有 CRUD 操作都需要认证
}
```

### 跳过中间件

```php
// 跳过认证
#[Route(['GET'], '/api/status', '', null, false)]
public function status(): ResponseInterface
{
    return send($response, 'ok');
}

// 跳过权限检查
#[Action(['GET'], '/public', can: false)]
public function publicList(): ResponseInterface
{
    // 需要认证但不需要权限
    return send($response, 'success');
}
```

## 自定义中间件

### 创建中间件

```php
<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CustomMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request, 
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // 请求前处理
        $request = $request->withAttribute('custom_data', 'value');
        
        // 继续处理
        $response = $handler->handle($request);
        
        // 响应后处理
        return $response->withHeader('X-Custom', 'processed');
    }
}
```

### 限流中间件示例

```php
class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $maxRequests = 100,
        private int $timeWindow = 3600
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $clientIp = $this->getClientIp($request);
        $key = "rate_limit:{$clientIp}";
        
        $count = \Core\App::cache()->get($key, 0);
        
        if ($count >= $this->maxRequests) {
            throw new \Core\Handlers\ExceptionBusiness('请求过于频繁', 429);
        }
        
        \Core\App::cache()->set($key, $count + 1, $this->timeWindow);
        
        return $handler->handle($request);
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}
```

### 使用自定义中间件

推荐在 App.php 中注册路由时定义中间件，而不是在注解中使用：

```php
// 在 App.php 的 register 方法中：
public function register(Bootstrap $app): void
{
    // 注册路由应用并添加中间件
    $apiRoute = new Route('/api', 'api',
        new AuthMiddleware('api'),
        new RateLimitMiddleware(100, 3600)  // 100次请求/小时
    );
    \Core\App::route()->set('api', $apiRoute);
}

// 在控制器中无需重复定义中间件
#[RouteGroup(
    app: 'api',
    route: '/limited'
)]
class LimitedApiController
{
    // 自动应用认证和限流中间件
}
```

## 中间件执行顺序

中间件按照洋葱模型执行：

```
请求 → CORS → 语言 → 认证 → 权限 → 控制器 → 权限 ← 认证 ← 语言 ← CORS ← 响应
```

### 推荐顺序

```php
// 1. CORS 处理（最外层）
CorsMiddleware::class,

// 2. 语言处理  
LangMiddleware::class,

// 3. API 签名验证
ApiMiddleware::class,

// 4. 认证验证
AuthMiddleware::class,

// 5. 权限检查（最内层）
PermissionMiddleware::class
```

## 内置中间件

### CORS 中间件

```php
// 自动处理跨域请求
class CorsMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // 处理 OPTIONS 预检请求
        if ($request->getMethod() === 'OPTIONS') {
            $response = \Core\App::di()->get('response_factory')->createResponse(200);
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
        
        $response = $handler->handle($request);
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }
}
```

## 常见问题

### 认证中间件不生效

检查清单：
1. 中间件是否添加到路由组
2. JWT 密钥配置是否正确  
3. 请求头格式：`Authorization: Bearer <token>`
4. Token 是否过期

### 权限检查失败

检查清单：
1. 认证中间件是否在权限中间件之前
2. 用户权限数组是否包含所需权限
3. 权限名称是否与路由名称匹配

### 中间件执行顺序

理解要点：
1. 后添加的中间件先执行（LIFO）
2. 请求和响应按相反顺序经过中间件
3. 确保认证在权限检查之前

通过合理配置和使用中间件，可以构建安全高效的 API 系统。