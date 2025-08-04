# 路由系统

DuxLite 基于 SlimPHP 构建了简洁的路由系统。通过注解定义路由，让 API 开发变得简单直观。

## 基本概念

### 设计理念

- **注解驱动**：使用 PHP 8 注解定义路由
- **自动发现**：框架自动扫描和注册路由
- **中间件支持**：路由级别的中间件
- **参数约束**：路径参数的类型约束

### 核心组件

- **Route 注解**：定义单个路由
- **RouteGroup 注解**：定义路由组

## 路由注册

在使用路由注解之前，需要先注册应用以便框架能够扫描和加载路由。

### 应用注册

创建应用类继承 `AppExtend`：

```php
<?php

namespace App;

use Core\App\AppExtend;
use Core\Bootstrap;

class App extends AppExtend
{
    public function register(Bootstrap $app): void
    {
        // 注册路由应用
        \Core\App::route()->set('api', new \Core\Route\Route());
        \Core\App::route()->set('web', new \Core\Route\Route());
    }
}
```

### 配置注册

在 `config/app.toml` 中注册应用：

```toml
# 应用注册
registers = [
    "App\\App"
]
```

这样框架就会自动扫描带有路由注解的控制器并注册路由。

## 基础路由

### 简单路由

```php
use Core\Route\Attribute\Route;

class ApiController
{
    #[Route(methods: 'GET', route: '/api/hello')]
    public function hello($request, $response, $args)
    {
        return send($response, 'Hello World');
    }

    #[Route(methods: 'POST', route: '/api/users')]
    public function createUser($request, $response, $args)
    {
        $data = $request->getParsedBody();
        return send($response, '用户创建成功', $data);
    }

    // 多个 HTTP 方法
    #[Route(methods: ['GET', 'POST'], route: '/api/flexible')]
    public function flexibleMethod($request, $response, $args)
    {
        $method = $request->getMethod();
        return send($response, "处理 {$method} 请求");
    }
}
```

### 带参数的路由

```php
class ApiController
{
    #[Route(methods: 'GET', route: '/api/users/{id}')]
    public function getUser($request, $response, $args)
    {
        $userId = $args['id'];
        return send($response, '用户详情', ['id' => $userId]);
    }

    #[Route(methods: 'GET', route: '/api/posts/{id}/comments/{comment_id}')]
    public function getComment($request, $response, $args)
    {
        return send($response, '评论详情', [
            'post_id' => $args['id'],
            'comment_id' => $args['comment_id']
        ]);
    }
}
```

### 命名路由

```php
class ApiController
{
    #[Route(methods: 'GET', route: '/api/users/{id}', name: 'api.users.show')]
    public function getUser($request, $response, $args)
    {
        return send($response, '用户详情');
    }

    #[Route(methods: 'POST', route: '/api/users', name: 'api.users.store')]
    public function createUser($request, $response, $args)
    {
        return send($response, '用户创建成功');
    }
}
```

### 认证控制

```php
class ApiController
{
    // 需要认证（默认）
    #[Route(methods: 'GET', route: '/api/profile')]
    public function getProfile($request, $response, $args)
    {
        return send($response, '用户资料');
    }

    // 无需认证
    #[Route(methods: 'GET', route: '/api/public', name: '', middleware: null, auth: false)]
    public function publicApi($request, $response, $args)
    {
        return send($response, '公开接口');
    }
}
```

**Route 注解参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| `methods` | `array\|string` | HTTP 方法数组 |
| `route` | `string` | 路由路径 |
| `name` | `string` | 路由名称（可选） |
| `app` | `string\|null` | 路由注册名（可选） |
| `auth` | `bool` | 是否需要认证（默认 true） |

## 路由组

### 基础路由组

```php
use Core\Route\Attribute\RouteGroup;

#[RouteGroup(
    app: 'api',
    route: '/api/v1',
    name: 'api.v1'
)]
class ApiV1Controller
{
    #[Route(methods: 'GET', route: '/users')]
    public function getUsers($request, $response, $args)
    {
        // 实际路径：GET /api/v1/users
        // 路由名称：api.v1.users
        return send($response, '用户列表');
    }

    #[Route(methods: 'GET', route: '/posts')]
    public function getPosts($request, $response, $args)
    {
        // 实际路径：GET /api/v1/posts
        // 路由名称：api.v1.posts
        return send($response, '文章列表');
    }
}
```

### 带中间件的路由组

```php
use Core\Auth\AuthMiddleware;
use Core\Permission\PermissionMiddleware;

#[RouteGroup(
    app: 'api',
    route: '/api/admin',
    name: 'admin',
    middleware: [AuthMiddleware::class, PermissionMiddleware::class]
)]
class AdminController
{
    #[Route(methods: 'GET', route: '/users')]
    public function getUsers($request, $response, $args)
    {
        // 自动应用 AuthMiddleware 和 PermissionMiddleware 中间件
        return send($response, '管理员用户列表');
    }

    #[Route(methods: 'PUT', route: '/settings')]
    public function updateSettings($request, $response, $args)
    {
        // 自动应用 AuthMiddleware 和 PermissionMiddleware 中间件
        return send($response, '设置更新成功');
    }
}
```

### 认证控制

```php
#[RouteGroup(
    app: 'api',
    route: '/api/public',
    name: 'public',
    auth: false  // 组内路由默认无需认证
)]
class PublicController
{
    #[Route(methods: 'GET', route: '/info')]
    public function getInfo($request, $response, $args)
    {
        // 无需认证
        return send($response, '公开信息');
    }

    #[Route(methods: 'GET', route: '/profile', name: null, auth: true)]  // 覆盖组设置，需要认证
    public function getProfile($request, $response, $args)
    {
        // 需要认证
        return send($response, '用户资料');
    }
}
```

**RouteGroup 注解参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| `app` | `string` | 路由注册名 |
| `route` | `string` | 路由前缀 |
| `name` | `string` | 路由名称前缀（可选） |
| `middleware` | `array` | 中间件数组（可选） |
| `auth` | `bool` | 是否需要认证（默认 true） |

## 中间件

DuxLite 中的中间件通过 RouteGroup 来应用，Route 注解本身不支持单独的中间件参数。

### 路由组中间件

```php
use Core\Auth\AuthMiddleware;

#[RouteGroup(
    app: 'api',
    route: '/api/auth',
    middleware: [AuthMiddleware::class]
)]
class AuthController
{
    #[Route(methods: 'GET', route: '/profile')]
    public function getProfile($request, $response, $args)
    {
        // 自动应用 auth 中间件
        return send($response, '用户资料');
    }
}

use Core\Auth\AuthMiddleware;
use Core\Permission\PermissionMiddleware;
use App\Middleware\ThrottleMiddleware;

#[RouteGroup(
    app: 'api',
    route: '/api/admin',
    middleware: [AuthMiddleware::class, PermissionMiddleware::class, ThrottleMiddleware::class]
)]
class AdminController
{
    #[Route(methods: 'GET', route: '/users')]
    public function getUsers($request, $response, $args)
    {
        // 自动应用 auth, admin, throttle 中间件
        return send($response, '管理员用户列表');
    }
}
```

## 命名路由

### 定义命名路由

```php
class ApiController
{
    #[Route(methods: 'GET', route: '/api/users/{id}', name: 'api.users.show')]
    public function getUser($request, $response, $args)
    {
        return send($response, '用户详情');
    }
}
```

### 生成 URL

```php
// 生成 URL
$userUrl = url('api.users.show', ['id' => 123]);
// 结果：/api/users/123

// 在响应中使用
class UserController
{
    public function getUser($request, $response, $args)
    {
        $userId = $args['id'];
        
        $data = [
            'id' => $userId,
            'links' => [
                'self' => url('api.users.show', ['id' => $userId]),
                'edit' => url('api.users.edit', ['id' => $userId])
            ]
        ];
        
        return send($response, '用户详情', $data);
    }
}
```

## 请求处理

### 获取请求数据

```php
class ApiController
{
    #[Route(methods: 'GET', route: '/api/search')]
    public function search($request, $response, $args)
    {
        // 查询参数
        $params = $request->getQueryParams();
        $keyword = $params['q'] ?? '';
        
        return send($response, '搜索结果', ['keyword' => $keyword]);
    }

    #[Route(methods: 'POST', route: '/api/users')]
    public function createUser($request, $response, $args)
    {
        // POST 数据
        $data = $request->getParsedBody();
        
        return send($response, '用户创建成功', $data);
    }

    #[Route(methods: 'POST', route: '/api/upload')]
    public function upload($request, $response, $args)
    {
        // 上传文件
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        
        if (!$file) {
            throw new ExceptionBusiness('请选择文件');
        }
        
        return send($response, '上传成功');
    }
}
```

## 响应处理

### 标准响应

```php
class ApiController
{
    public function getUsers($request, $response, $args)
    {
        // 成功响应
        return send($response, '获取成功', $users);
    }

    public function createUser($request, $response, $args)
    {
        // 创建成功
        return send($response, '创建成功', $user, [], 201);
    }
}
```


## 完整示例

以下是一个完整的用户 API 控制器示例，展示了路由定义、数据验证和异常处理：

```php
use Core\Route\Attribute\Route;
use Core\Route\Attribute\RouteGroup;
use Core\Handlers\ExceptionBusiness;
use Core\Handlers\ExceptionNotFound;
use Core\Handlers\ExceptionValidator;

#[RouteGroup(
    app: 'api',
    route: '/api/v1',
    name: 'api.v1'
)]
class UserApiController
{
    // 获取用户列表
    #[Route(methods: 'GET', route: '/users')]
    public function index($request, $response, $args)
    {
        $users = User::all();
        return send($response, '获取成功', $users);
    }

    // 获取单个用户
    #[Route(methods: 'GET', route: '/users/{id}')]
    public function show($request, $response, $args)
    {
        $user = User::find($args['id']);
        
        if (!$user) {
            throw new ExceptionNotFound('用户不存在');
        }
        
        return send($response, '获取成功', $user);
    }

    // 创建用户
    #[Route(methods: 'POST', route: '/users')]
    public function store($request, $response, $args)
    {
        $data = $request->getParsedBody();
        
        // 数据验证
        $validator = new Validator($data, [
            'username' => ['required', 'minLength:3'],
            'email' => ['required', 'email']
        ]);
        
        if (!$validator->validate()) {
            throw new ExceptionValidator($validator->errors());
        }
        
        // 检查邮箱是否已存在
        if (User::where('email', $data['email'])->exists()) {
            throw new ExceptionBusiness('邮箱已被使用');
        }
        
        $user = User::create($data);
        return send($response, '创建成功', $user, [], 201);
    }

    // 更新用户
    #[Route(methods: 'PUT', route: '/users/{id}')]
    public function update($request, $response, $args)
    {
        $user = User::find($args['id']);
        
        if (!$user) {
            throw new ExceptionNotFound('用户不存在');
        }
        
        $data = $request->getParsedBody();
        $user->update($data);
        
        return send($response, '更新成功', $user);
    }

    // 删除用户
    #[Route(methods: 'DELETE', route: '/users/{id}')]
    public function destroy($request, $response, $args)
    {
        $user = User::find($args['id']);
        
        if (!$user) {
            throw new ExceptionNotFound('用户不存在');
        }
        
        // 检查是否可以删除
        if ($user->is_admin) {
            throw new ExceptionBusiness('不能删除管理员用户');
        }
        
        $user->delete();
        return send($response, '删除成功');
    }
}
```

> **异常处理说明**：示例中使用了 DuxLite 的异常系统来处理各种错误情况。框架会自动将异常转换为对应的 HTTP 响应。更多异常处理详情请参考：[异常处理系统](/reference/core/exceptions)

## 最佳实践

### 1. 路由组织

```php
// ✅ 推荐：按功能分组
#[RouteGroup(app: 'api', route: '/api/users', name: 'api.users')]
class UserController {}

// ❌ 避免：混合功能
class MixedController
{
    #[Route(methods: 'GET', route: '/api/users')]
    public function getUsers() {}
    
    #[Route(methods: 'GET', route: '/api/orders')]  // 不相关
    public function getOrders() {}
}
```

### 2. 参数验证

```php
// ✅ 推荐：在方法内进行参数验证
#[Route(methods: 'GET', route: '/api/users/{id}')]
public function getUser($request, $response, $args)
{
    // 验证参数类型
    if (!is_numeric($args['id'])) {
        throw new ExceptionBusiness('用户ID必须为数字');
    }
    
    $userId = (int) $args['id']; // 安全转换
}
```

### 3. 异常处理

```php
// ✅ 推荐：直接抛出异常，让框架处理
if (!$user) {
    throw new ExceptionNotFound('用户不存在');
}

if ($user->status === 'banned') {
    throw new ExceptionBusiness('用户已被禁用');
}

if (!$validator->validate()) {
    throw new ExceptionValidator($validator->errors());
}
```

> 详细的异常处理机制请参考：[异常处理系统](/reference/core/exceptions)

### 4. 中间件顺序

```php
// ✅ 推荐：在 RouteGroup 中合理安排中间件顺序
use Core\Auth\AuthMiddleware;
use Core\Permission\PermissionMiddleware;
use App\Middleware\ThrottleMiddleware;

#[RouteGroup(
    app: 'api',
    route: '/api/admin',
    middleware: [
        AuthMiddleware::class,      // 先认证
        PermissionMiddleware::class,     // 再授权
        ThrottleMiddleware::class   // 最后限流
    ]
)]
class AdminController
{
    #[Route(methods: 'POST', route: '/users')]
    public function createUser($request, $response, $args)
    {
        // 自动应用所有中间件
    }
}
```

### 5. 响应格式

```php
// ✅ 推荐：统一格式
return send($response, '操作成功', $data);
return send($response, '创建成功', $data, [], 201);
```

DuxLite 的路由系统简单易用，通过注解就能快速定义 API 路由，让开发更加高效。