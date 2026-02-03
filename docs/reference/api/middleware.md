# API 中间件

API 路由中间件用于处理 HTTP 请求的安全验证、权限控制等功能。DuxLite 框架提供了完整的中间件系统。

## 中间件注册

中间件推荐在 App.php 中注册路由时添加，控制器中无需重复定义：

```php
// 在 App.php 的 register 方法中：
public function register(Bootstrap $app): void
{
    // 注册 API 路由并添加认证中间件
    $apiRoute = new Route('/api', 'api', new AuthMiddleware('api'));
    \Core\App::route()->set('api', $apiRoute);
}
```

## AUTH 认证中间件

验证用户身份和令牌，优先从 `Authorization` 请求头提取 JWT token，未找到时尝试 `Cookie: token`。

```php
use Core\Auth\AuthMiddleware;

// App.php 中注册
$apiRoute = new Route('/api', 'api', new AuthMiddleware('api'));
```

**获取认证信息：**
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

**跳过认证：**
```php
#[Route(methods: 'GET', route: '/public/info', auth: false)]
public function publicInfo(): ResponseInterface
{
    return send($response, '公开信息');
}
```

## API 签名中间件

验证 API 请求的签名，防止数据篡改和重放攻击。

```php
use Core\Api\ApiMiddleware;

// App.php 中注册
$apiRoute = new Route('/api', 'api', new ApiMiddleware($callback));
```

**请求头字段：**
- `AccessKey`：API 密钥标识
- `Content-MD5`：HMAC-SHA256 签名值  
- `Content-Date`：Unix 时间戳

**签名算法：**
1. 获取请求路径 + 查询字符串 + 时间戳
2. 用换行符连接：`路径\n查询字符串\n时间戳`
3. 使用 HMAC-SHA256 生成签名
4. 验证时间窗口（60秒误差）

## 权限中间件

检查用户权限，配合认证中间件使用。

```php
use Core\Permission\PermissionMiddleware;

// App.php 中注册多个中间件
$adminRoute = new Route('/admin', 'admin',
    new AuthMiddleware('admin'),
    new PermissionMiddleware('admin', \App\Models\Admin::class)
);
```

**跳过权限检查：**
```php
#[Action(methods: 'GET', route: '/list', can: false)]
public function list(): ResponseInterface
{
    // 需要认证但不需要权限
    return send($response, 'success');
}
```

## 多语言中间件

自动检测和设置用户语言，全局中间件无需手动添加。

**检测来源：**
1. 请求头：`Accept-Language: zh-CN,en;q=0.9`
2. 默认语言：`en-US`

**使用方式：**
```php
public function getMessage(): ResponseInterface 
{
    $message = trans('common.welcome');
    return send($response, $message);
}
```

## 中间件组合

多个中间件按顺序执行，推荐顺序：

```php
public function register(Bootstrap $app): void
{
    $apiRoute = new Route('/api', 'api',
        new AuthMiddleware('api'),           // 1. 认证
        new PermissionMiddleware('api', \App\Models\User::class), // 2. 权限
        new ApiMiddleware($callback)         // 3. 签名验证
    );
    \Core\App::route()->set('api', $apiRoute);
}
```

## 常见问题

**认证失败：**
- 检查 JWT 密钥配置
- 确认请求头格式：`Authorization: Bearer <token>`
- 验证 token 是否过期

**权限检查失败：**
- 确保认证中间件在权限中间件之前
- 检查用户权限数组是否包含所需权限

**签名验证失败：**
- 检查请求头字段是否正确
- 确认签名算法实现与服务器一致
- 验证时间戳是否在允许范围内
