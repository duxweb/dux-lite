# 中间件机制

中间件是处理 HTTP 请求的过滤器，基于 PSR-15 标准实现，在请求到达控制器前后执行额外逻辑。

## 中间件概述

### 执行流程

```
HTTP请求 → 中间件1 → 中间件2 → 控制器 → 中间件2 → 中间件1 → HTTP响应
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

### 常用中间件类型

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

#### 2. 限流中间件

```php
class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private int $maxRequests = 100, private int $timeWindow = 3600) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $clientIp = $this->getClientIp($request);
        $key = "rate_limit:{$clientIp}";
        
        $count = \Core\App::cache()->get($key);
        if ($count === null) {
            $count = 0;
        }
        
        if ($count >= $this->maxRequests) {
            throw new \Core\Exception\TooManyRequestsException();
        }
        
        \Core\App::cache()->set($key, $count + 1, $this->timeWindow);
        
        return $handler->handle($request);
    }
}
```

## 使用中间件

### 在模块中注册中间件

开发者在自己模块的 `App.php` 中注册中间件：

```php
<?php
namespace App\Web;

use Core\App\AppExtend;
use Core\Bootstrap;
use Core\Route\Route;
use App\Web\Middleware\AuthMiddleware;
use App\Web\Middleware\LogMiddleware;

class App extends AppExtend
{
    public function register(Bootstrap $bootstrap): void
    {
        // 注册 API 路由并添加认证中间件
        $apiRoute = new Route('/api', 'api', new AuthMiddleware('api'));
        \Core\App::route()->set('api', $apiRoute);
        
        // 注册管理员路由并添加多个中间件
        $adminRoute = new Route('/admin', 'admin',
            new AuthMiddleware('admin'),
            new PermissionMiddleware('admin', 'Admin'),
            new LogMiddleware()
        );
        \Core\App::route()->set('admin', $adminRoute);
    }
}
```

### 中间件组合

多个中间件按顺序执行，推荐顺序：

```php
public function register(Bootstrap $bootstrap): void
{
    $apiRoute = new Route('/api', 'api',
        new AuthMiddleware('api'),           // 1. 认证
        new PermissionMiddleware('api', 'User'), // 2. 权限
        new ApiMiddleware($callback)         // 3. 签名验证
    );
    \Core\App::route()->set('api', $apiRoute);
}
```

### 全局中间件

也可以在模块中注册全局中间件：

```php
public function init(Bootstrap $bootstrap): void
{
    // 注册全局中间件
    $bootstrap->web->addMiddleware(new LogMiddleware());
    $bootstrap->web->addMiddleware(new CorsMiddleware());
}
```

## 路由级别控制

### 跳过认证

```php
#[Route(methods: 'GET', route: '/public/info', auth: false)]
public function publicInfo(): ResponseInterface
{
    return send($response, '公开信息');
}
```

### 跳过权限检查

```php
#[Action(methods: 'GET', route: '/list', can: false)]
public function list(): ResponseInterface
{
    // 需要认证但不需要权限
    return send($response, 'success');
}
```

## 中间件执行顺序

中间件按注册顺序执行，遵循"洋葱模型"：

```php
// 注册顺序
$route = new Route('/api', 'api',
    new LogMiddleware(),     // 3. 最外层
    new AuthMiddleware(),   // 2. 中间层  
    new CorsMiddleware()    // 1. 最内层
);

// 实际执行顺序
// 请求: Log → Auth → Cors → 控制器
// 响应: 控制器 → Cors → Auth → Log
```

## 获取认证信息

```php
public function profile(ServerRequestInterface $request): ResponseInterface 
{
    $auth = $request->getAttribute('auth');
    return send($response, '获取成功', [
        'user_id' => $auth['id'],
        'username' => $auth['username']
    ]);
}
```

## 最佳实践

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

通过合理使用中间件，可以实现关注点分离，让代码更加模块化和可维护。