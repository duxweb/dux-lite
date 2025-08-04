# 路由系统

DuxLite 的路由系统基于 SlimPHP，支持注解和编程式两种定义方式，提供完整的 RESTful 路由、中间件和权限集成。

## 核心概念

### 设计理念

- **注解优先**：推荐使用注解定义路由，代码更清晰
- **清晰分层**：支持路由分组和资源嵌套
- **中间件支持**：灵活的中间件系统
- **权限集成**：自动生成路由名称，与权限系统深度集成

### 路由类型

1. **基础路由**：`#[Route]` 单个路由定义
2. **路由组**：`#[RouteGroup]` 批量路由管理
3. **资源路由**：`#[Resource]` RESTful 资源控制器
4. **编程式路由**：代码方式定义路由

## 注解路由系统

### 基础路由注解

#### 使用方法

```php
use Core\Attribute\Route;

class UserController
{
    #[Route(methods: 'GET', pattern: '/users')]
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return send($response, 'ok', ['users' => []]);
    }
    
    #[Route(methods: 'POST', pattern: '/users')]
    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return send($response, 'ok', ['message' => '创建成功']);
    }
    
    #[Route(methods: ['GET', 'POST'], pattern: '/users/{id}')]
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        return send($response, 'ok', ['user' => ['id' => $id]]);
    }
}
```

#### API 定义

```php
#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        array|string $methods,     // HTTP 方法：'GET', 'POST', ['GET', 'POST']
        string       $pattern,     // 路由模式：'/users', '/users/{id}'
        string       $name = '',   // 路由名称（可选）
        ?bool        $auth = null, // 是否需要认证
        bool         $can = true,  // 是否需要权限检查
    ) {}
}
```

### 路由组注解

#### 使用方法

```php
use Core\Attribute\RouteGroup;

#[RouteGroup(pattern: '/api/v1', middleware: ['cors'])]
class ApiController
{
    #[Route(methods: 'GET', pattern: '/users')]
    public function users() {} // 实际路由：GET /api/v1/users
    
    #[Route(methods: 'GET', pattern: '/posts')]
    public function posts() {} // 实际路由：GET /api/v1/posts
}
```

#### API 定义

```php
#[Attribute(Attribute::TARGET_CLASS)]
class RouteGroup
{
    public function __construct(
        string $pattern = '',              // 路由前缀
        array  $middleware = [],           // 中间件数组
        string $name = '',                 // 路由组名称
        ?bool  $auth = null,              // 认证设置
        bool   $can = true,               // 权限设置
    ) {}
}
```

### 资源路由注解

#### 使用方法

```php
use Core\Attribute\Resource;
use Core\Attribute\Action;
use Core\Controller\Resources;

#[Resource(app: 'api', route: '/users')]
class UserController extends Resources
{
    protected string $model = User::class;
    
    // 自动提供标准 RESTful 路由：
    // GET    /users        list()    列表
    // POST   /users        create()  创建
    // GET    /users/{id}   show()    详情
    // PUT    /users/{id}   store()   更新
    // DELETE /users/{id}   delete()  删除
    
    // 自定义方法
    #[Action(methods: 'POST', route: '/batch')]
    public function batch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return send($response, 'ok', ['message' => '批量操作完成']);
    }
}
```

#### API 定义

```php
#[Attribute(Attribute::TARGET_CLASS)]
class Resource
{
    public function __construct(
        string      $app,                  // 路由注册名
        string      $route,                // 路由前缀
        string      $name = '',            // 资源名称
        array|false $actions = [],         // 启用的方法
        array       $middleware = [],      // 中间件数组
        bool        $softDelete = false,   // 软删除支持
    ) {}
}

#[Attribute(Attribute::TARGET_METHOD)]
class Action
{
    public function __construct(
        array|string $methods,             // HTTP 方法
        string       $route,               // 路由模式
        string       $name = '',           // 方法名称
        ?bool        $auth = null,         // 认证设置
        bool         $can = true,          // 权限设置
    ) {}
}
```

## 编程式路由

### 基础用法

```php
// config/route.php
use Core\Route\Route;

$route = new Route();

// 基础路由
$route->get('/hello', function ($request, $response, $args) {
    return send($response, 'ok', ['message' => 'Hello World']);
});

// 控制器路由
$route->get('/users', UserController::class . ':index');
$route->post('/users', UserController::class . ':create');

// 路由组
$api = $route->group('/api/v1');
$api->get('/users', UserController::class . ':index');
$api->post('/users', UserController::class . ':create');

// 带中间件的路由组
$authApi = $route->group('/auth', middleware: ['auth']);
$authApi->get('/profile', ProfileController::class . ':show');

return $route;
```

### Route 类 API

```php
class Route
{
    // HTTP 方法路由
    public function get(string $pattern, callable|string $handler): RouteInterface
    public function post(string $pattern, callable|string $handler): RouteInterface
    public function put(string $pattern, callable|string $handler): RouteInterface
    public function delete(string $pattern, callable|string $handler): RouteInterface
    public function patch(string $pattern, callable|string $handler): RouteInterface
    public function options(string $pattern, callable|string $handler): RouteInterface
    
    // 通用路由
    public function map(array $methods, string $pattern, callable|string $handler): RouteInterface
    
    // 路由组
    public function group(string $pattern, array $middleware = []): RouteCollectorProxyInterface
    
    // 资源路由
    public function resources(string $pattern, string $controller, array $options = []): void
}
```

## 路由模式规范

### 基本模式

```php
'/users'              // 静态路由
'/users/{id}'         // 必需参数
'/users/{id:[0-9]+}'  // 参数约束（正则）
'/posts[/{id}]'       // 可选参数
'/files/{path:.*}'    // 通配符参数
```

### 参数约束

| 约束 | 说明 | 示例 |
|------|------|------|
| `{id:[0-9]+}` | 数字ID | `/users/{id:[0-9]+}` |
| `{slug:[a-z-]+}` | URL slug | `/posts/{slug:[a-z-]+}` |
| `{path:.*}` | 路径通配符 | `/files/{path:.*}` |
| `{name:[a-zA-Z]+}` | 字母名称 | `/category/{name:[a-zA-Z]+}` |

## 路由中间件

### 中间件配置

#### 注解方式

```php
use Core\Auth\AuthMiddleware;
use Core\Permission\PermissionMiddleware;

// 路由组中间件
#[RouteGroup(pattern: '/admin', middleware: [AuthMiddleware::class])]
class AdminController {}

// 资源控制器中间件
#[Resource(
    app: 'api',
    route: '/users',
    middleware: [
        AuthMiddleware::class,
        PermissionMiddleware::class
    ]
)]
class UserController extends Resources {}
```

#### 编程式方式

```php
// 全局中间件
$route->middleware([CorsMiddleware::class]);

// 路由组中间件
$admin = $route->group('/admin', middleware: [AuthMiddleware::class]);
```

### 中间件执行顺序

1. **全局中间件**：应用级别的中间件
2. **路由组中间件**：路由组级别的中间件
3. **路由中间件**：单个路由的中间件
4. **控制器中间件**：控制器级别的中间件

## 权限系统集成

### 路由名称生成规则

DuxLite 自动为路由生成名称，用于权限检查：

```php
// 注解路由
#[Route(methods: 'GET', pattern: '/admin/users')]
// 生成路由名：admin.users.index

// 资源控制器
#[Resource(app: 'admin', route: '/users')]
// 生成路由名：
// admin.users.list    (GET /users)
// admin.users.create  (POST /users)
// admin.users.show    (GET /users/{id})
// admin.users.store   (PUT /users/{id})
// admin.users.delete  (DELETE /users/{id})
```

### 权限检查

```php
// 自动权限检查
#[Route(methods: 'GET', pattern: '/admin/users', can: true)]
public function index() {} // 需要 admin.users.index 权限

// 跳过权限检查
#[Route(methods: 'GET', pattern: '/public/info', can: false)]
public function info() {} // 不需要权限

// 认证但不检查权限
#[Route(methods: 'GET', pattern: '/user/profile', auth: true, can: false)]
public function profile() {} // 需要登录但不检查具体权限
```

## 路由调试和管理

### 查看所有路由

```bash
# 查看所有注册的路由
php dux route:list

# 搜索特定路由
php dux route:list | grep users

# 查看详细信息
php dux route:list --verbose
```

### 输出示例

```
+--------+-------------------+-------------------+-------------+
| Method | Pattern           | Name              | Middleware  |
+--------+-------------------+-------------------+-------------+
| GET    | /api/users        | api.users.list    | auth        |
| POST   | /api/users        | api.users.create  | auth        |
| GET    | /api/users/{id}   | api.users.show    | auth        |
| PUT    | /api/users/{id}   | api.users.store   | auth        |
| DELETE | /api/users/{id}   | api.users.delete  | auth        |
+--------+-------------------+-------------------+-------------+
```

## URL 生成

### 生成 URL

```php
// 通过路由名生成 URL
$url = url('api.users.show', ['id' => 123]);
// 结果：/api/users/123

// 带查询参数
$url = url('api.users.list', [], ['page' => 2, 'limit' => 10]);
// 结果：/api/users?page=2&limit=10
```

### URL 生成 API

```php
/**
 * 生成路由 URL
 * @param string $name 路由名称
 * @param array $params 路由参数
 * @param array $query 查询参数
 * @return string
 */
function url(string $name, array $params = [], array $query = []): string
```

## 最佳实践

### 1. 路由组织

```php
// ✅ 推荐：按功能模块组织
#[RouteGroup(pattern: '/api/v1')]
class ApiController
{
    #[Route(methods: 'GET', pattern: '/users')]
    public function users() {}
    
    #[Route(methods: 'GET', pattern: '/posts')]
    public function posts() {}
}

// ❌ 不推荐：所有路由放在一个控制器
class MainController
{
    #[Route(methods: 'GET', pattern: '/users')]
    public function users() {}
    
    #[Route(methods: 'GET', pattern: '/posts')]
    public function posts() {}
    
    #[Route(methods: 'GET', pattern: '/orders')]
    public function orders() {}
}
```

### 2. 参数验证

```php
#[Route(methods: 'GET', pattern: '/users/{id:[0-9]+}')]
public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
{
    $id = (int) $args['id']; // 类型转换
    
    if ($id <= 0) {
        throw new ExceptionBusiness('无效的用户ID');
    }
    
    // 业务逻辑...
}
```

### 3. 中间件使用

```php
// ✅ 推荐：在路由组级别应用中间件
#[RouteGroup(pattern: '/admin', middleware: [AuthMiddleware::class])]
class AdminController
{
    // 所有方法都自动应用 auth 中间件
}

// ❌ 不推荐：每个路由单独配置
class AdminController
{
    #[Route(methods: 'GET', pattern: '/admin/users', middleware: [AuthMiddleware::class])]
    public function users() {}
    
    #[Route(methods: 'GET', pattern: '/admin/posts', middleware: [AuthMiddleware::class])]
    public function posts() {}
}
```

### 4. RESTful 资源

```php
// ✅ 推荐：使用资源控制器
#[Resource(app: 'api', route: '/users')]
class UserController extends Resources
{
    protected string $model = User::class;
    
    // 自动提供完整的 CRUD 操作
}

// ✅ 也可以：手动定义 RESTful 路由
#[RouteGroup(pattern: '/api')]
class UserController
{
    #[Route(methods: 'GET', pattern: '/users')]
    public function index() {}
    
    #[Route(methods: 'POST', pattern: '/users')]
    public function store() {}
    
    #[Route(methods: 'GET', pattern: '/users/{id}')]
    public function show() {}
    
    #[Route(methods: 'PUT', pattern: '/users/{id}')]
    public function update() {}
    
    #[Route(methods: 'DELETE', pattern: '/users/{id}')]
    public function destroy() {}
}
```

通过合理使用 DuxLite 的路由系统，可以构建清晰、可维护的 Web 应用程序路由结构。