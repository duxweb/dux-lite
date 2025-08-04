# 路由系统

DuxLite 提供了强大且灵活的路由系统，基于 SlimPHP 路由器，支持注解路由、路由组、资源路由和中间件等高级功能。

## 设计理念

DuxLite 路由系统采用**注解驱动的显式路由配置**设计：

- **注解优先**：推荐使用 PHP 8+ 属性注解定义路由
- **清晰分层**：区分普通路由组 (`RouteGroup`) 和资源路由组 (`Resource`)
- **中间件支持**：灵活的中间件配置和继承
- **RESTful 友好**：内置 RESTful 资源路由支持
- **命名路由**：自动生成路由名称，便于 URL 生成

## 路由定义方式

DuxLite 提供两种路由定义方式：

1. **注解路由**（推荐）：使用 PHP 8+ 属性注解，直观且类型安全
2. **编程式路由**：传统的路由定义方式，灵活且适合动态路由

## 编程式路由

### 1. 基础路由定义

使用 `Route` 类创建传统的编程式路由：

```php
// 源码位置：src/Route/Route.php
use Core\Route\Route;
use Core\Auth\AuthMiddleware;

// 创建路由实例
$route = new Route('/api', 'api', new AuthMiddleware('api'));

// 定义 GET 路由
$route->get('/users', 'UserController:index', 'list');

// 定义 POST 路由
$route->post('/users', 'UserController:create', 'create');

// 定义 PUT 路由
$route->put('/users/{id}', 'UserController:edit', 'edit');

// 定义 DELETE 路由
$route->delete('/users/{id}', 'UserController:delete', 'delete');

// 定义 PATCH 路由
$route->patch('/users/{id}', 'UserController:store', 'store');

// 定义 OPTIONS 路由
$route->options('/users', 'UserController:options', 'options');

// 定义支持任意方法的路由
$route->any('/webhook', 'WebhookController:handle', 'webhook');

// 使用 map 方法定义多方法路由
$route->map(['GET', 'POST'], '/search', 'SearchController:handle', 'search');
```

**路由方法参数说明：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `pattern` | `string` | ✅ | 路由路径，支持参数和正则约束 |
| `callable` | `string\|callable` | ✅ | 控制器方法（如 `UserController:index`）或回调函数 |
| `name` | `string` | ✅ | 路由名称，用于 URL 生成 |
| `priority` | `int` | ❌ | 路由优先级，数字越小优先级越高，默认 0 |

### 2. 路由分组

使用 `group` 方法创建路由分组：

```php
use Core\Permission\PermissionMiddleware;

// 创建主路由
$route = new Route('/api', 'api');

// 创建管理员路由组
$adminGroup = $route->group('/admin', 'admin',
    new AuthMiddleware('admin'),
    new PermissionMiddleware('admin', 'Admin')
);

// 在分组内定义路由
$adminGroup->get('/dashboard', 'AdminController:dashboard', 'dashboard');
$adminGroup->get('/users', 'AdminController:users', 'users');
$adminGroup->post('/settings', 'AdminController:updateSettings', 'updateSettings');

// 路径解析结果：
// GET  /api/admin/dashboard   -> AdminController:dashboard (admin.dashboard)
// GET  /api/admin/users       -> AdminController:users     (admin.users)
// POST /api/admin/settings    -> AdminController:settings  (admin.updateSettings)

// 创建嵌套分组
$userGroup = $adminGroup->group('/users', 'users');
$userGroup->get('/{id}', 'AdminController:showUser', 'show');
$userGroup->delete('/{id}', 'AdminController:deleteUser', 'delete');

// 嵌套路径结果：
// GET    /api/admin/users/{id} -> AdminController:showUser    (admin.users.show)
// DELETE /api/admin/users/{id} -> AdminController:deleteUser  (admin.users.delete)
```

### 3. RESTful 资源路由

使用 `resources` 方法快速创建 RESTful 路由：

```php
// 创建完整的资源路由
$route = new Route('/api', 'api');
$userGroup = $route->group('/users', 'users');

// 生成标准 RESTful 路由
$userGroup->resources('UserController');

// 等价于以下路由定义：
// GET    /api/users           -> UserController:list      (users.list)
// GET    /api/users/{id}      -> UserController:show      (users.show)
// POST   /api/users           -> UserController:create    (users.create)
// PUT    /api/users/{id}      -> UserController:edit      (users.edit)
// PATCH  /api/users/{id}      -> UserController:store     (users.store)
// DELETE /api/users/{id}      -> UserController:delete    (users.delete)
// DELETE /api/users           -> UserController:deleteMany (users.deleteMany)

// 只启用部分操作
$postGroup = $route->group('/posts', 'posts');
$postGroup->resources('PostController', ['list', 'show', 'create']);

// 启用软删除操作
$commentGroup = $route->group('/comments', 'comments');
$commentGroup->resources('CommentController', ['list', 'show', 'delete'], true);
// 额外生成：
// DELETE /api/comments/{id}/trash   -> CommentController:trash   (comments.trash)
// PUT    /api/comments/{id}/restore -> CommentController:restore (comments.restore)

// 禁用资源路由
$configGroup = $route->group('/config', 'config');
$configGroup->resources('ConfigController', false); // 不生成任何资源路由
```

**resources 方法参数：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `class` | `string` | ✅ | 控制器类名 |
| `actions` | `array\|false` | ❌ | 启用的操作列表，默认全部，`false` 禁用 |
| `softDelete` | `bool` | ❌ | 是否启用软删除操作，默认 `false` |

### 4. 路由注册

编程式路由需要手动注册到应用中：

```php
// 在应用扩展类中注册
use Core\App\AppExtend;
use Core\Bootstrap;

class ApiApp extends AppExtend
{
    public function register(Bootstrap $bootstrap): void
    {
        // 创建路由实例
        $route = new Route('/api', 'api', new AuthMiddleware('api'));

        // 定义路由
        $route->get('/health', 'HealthController:check', 'health');

        $userGroup = $route->group('/users', 'users');
        $userGroup->resources('UserController');

        // 注册到路由系统
        App::route()->set('api', $route);
    }
}

// 或者在 Bootstrap 中直接定义
class Bootstrap
{
    public static function register(): void
    {
        // 注册路由应用
        $webRoute = new Route('/', 'web');
        $webRoute->get('/', 'IndexController:index', 'home');
        $webRoute->get('/about', 'IndexController:about', 'about');

        App::route()->set('web', $webRoute);
    }
}
```

### 5. 路由中间件

编程式路由支持灵活的中间件配置：

```php
use Core\Middleware\CorsMiddleware;
use Core\Auth\AuthMiddleware;
use Core\Permission\PermissionMiddleware;

// 1. 构造函数中添加全局中间件
$route = new Route('/api', 'api',
    new CorsMiddleware(),
    new AuthMiddleware('api')
);

// 2. 分组中间件
$adminGroup = $route->group('/admin', 'admin',
    new PermissionMiddleware('admin', 'Admin')
);

// 3. 单个路由中间件（通过 map 方法）
$route->map(['GET', 'POST'], '/upload', 'UploadController:handle', 'upload', [
    new \App\Middleware\FileUploadMiddleware(),
    new \App\Middleware\FileSizeMiddleware(10 * 1024 * 1024) // 10MB限制
]);
```

### 6. 路由优先级

使用优先级控制路由匹配顺序：

```php
// 高优先级路由（数字越小优先级越高）
$route->get('/users/admin', 'AdminController:adminUsers', 'adminUsers', 1);

// 普通优先级路由
$route->get('/users/{id}', 'UserController:show', 'show', 100);

// 匹配顺序：
// 1. /users/admin     -> AdminController:adminUsers （优先级 1）
// 2. /users/{id}      -> UserController:show        （优先级 100）
```

### 7. 回调函数路由

除了控制器，还支持直接使用回调函数：

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// 简单回调
$route->get('/ping', function (ServerRequestInterface $request, ResponseInterface $response) {
    return send($response, ['message' => 'pong']);
}, 'ping');

// 带参数的回调
$route->get('/hello/{name}', function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
    $name = $args['name'];
    return send($response, ['message' => "Hello, $name!"]);
}, 'hello');

// 复杂业务逻辑回调
$route->post('/webhook/{provider}', function (ServerRequestInterface $request, ResponseInterface $response, array $args) {
    $provider = $args['provider'];
    $body = $request->getBody()->getContents();

    // 处理 webhook 逻辑
    $result = match($provider) {
        'github' => handleGithubWebhook($body),
        'gitlab' => handleGitlabWebhook($body),
        default => ['error' => 'Unsupported provider']
    };

    return send($response, $result);
}, 'webhook');
```

### 8. 与现有路由应用集成

编程式路由可以与现有注册的路由应用集成：

```php
// 获取已注册的路由应用
$route = App::route()->get('api');

// 在现有应用中添加新路由
$route->get('/version', 'ApiController:version', 'version');
$route->post('/feedback', 'FeedbackController:store', 'feedback');

// 创建新的路由组
$v2Group = $route->group('/v2', 'v2');
$v2Group->get('/users', 'V2\UserController:index', 'users');
$v2Group->resources('V2\PostController');

// 与注解路由混合使用
// 假设已有注解定义的路由，可以通过编程式方式扩展
$adminRoute = App::route()->get('admin');
$reportGroup = $adminRoute->group('/reports', 'reports',
    new AuthMiddleware('admin'),
    new PermissionMiddleware('reports', 'Admin')
);
$reportGroup->get('/export', 'ReportController:export', 'export');
```

## 路由名称和权限系统

### 路由名自动生成规则

DuxLite 的路由名称生成规则与权限系统紧密关联，理解这个规则对于权限配置非常重要。

#### 1. 注解路由命名规则

**基础路由命名：**
```php
// 源码位置：src/Route/Register.php parseClass() 方法
// 命名规则：lcfirst(倒数第3层目录) + "." + lcfirst(类名)

// 示例 1：App\Admin\Controller\UserController:index
// 解析：倒数第3层=Admin, 类名=UserController, 方法=index
// 自动生成组名：admin.userController
// 最终路由名：admin.userController + "." + lcfirst(index) = admin.userController.index

// 示例 2：App\Api\Controller\PostController:show
// 解析：倒数第3层=Api, 类名=PostController, 方法=show
// 自动生成组名：api.postController
// 最终路由名：api.postController.show
```

**RouteGroup 路由命名：**
```php
#[RouteGroup(app: 'admin', route: '/admin', name: 'admin')]
class UserController
{
    #[Route('GET', '/users')]
    public function index() {} // 路由名：admin.index

    #[Route('GET', '/users/{id}')]
    public function show() {}  // 路由名：admin.show
}

// 如果 RouteGroup 未指定 name，则自动生成：
// App\Admin\Controller\UserController -> admin.userController
// 这时路由名会是：admin.userController.index, admin.userController.show

// 明确指定 name 的好处是简化路由名，便于权限管理
```

**Resource 资源路由命名：**
```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    // 自动生成的路由名：
    // users.list      -> GET    /admin/users
    // users.show      -> GET    /admin/users/{id}
    // users.create    -> POST   /admin/users
    // users.edit      -> PUT    /admin/users/{id}
    // users.store     -> PATCH  /admin/users/{id}
    // users.delete    -> DELETE /admin/users/{id}
    // users.deleteMany -> DELETE /admin/users
    // users.trash     -> DELETE /admin/users/{id}/trash (启用软删除时)
    // users.restore   -> PUT    /admin/users/{id}/restore (启用软删除时)
}
```

**Action 自定义操作命名：**
```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    #[Action(['GET'], '/export', name: 'export')]
    public function export() {} // 路由名：users.export

    #[Action(['POST'], '/import')]  // 未指定 name
    public function importUsers() {} // 路由名：users.importUsers（根据方法名生成）
}
```

#### 2. 编程式路由命名

```php
$route = new Route('/admin', 'admin');

// 直接指定路由名
$route->get('/users', 'UserController:index', 'users');
// 路由名：admin.users

// 分组路由命名
$userGroup = $route->group('/users', 'users');
$userGroup->get('/', 'UserController:index', 'list');
// 路由名：admin.users.list

// 资源路由命名
$userGroup->resources('UserController');
// 路由名：admin.users.list, admin.users.show, admin.users.create 等
```

### 权限系统集成

路由名称直接作为权限标识符使用，权限检查流程如下：

#### 1. 权限检查机制

```php
// 源码位置：src/Permission/Can.php
// 权限检查流程：
// 1. 获取当前路由名称（如：admin.users.create）
// 2. 查询用户模型的 permission 属性
// 3. 检查路由名是否在用户权限数组中

class Can
{
    public static function check(ServerRequestInterface $request, string $model, string $name): void
    {
        // 获取用户认证信息
        $auth = $request->getAttribute("auth");
        $uid = $auth['id'];

        // 查询用户权限
        $userInfo = (new $model)->query()->find($uid);
        $permission = $userInfo->permission; // 用户权限数组

        // 检查路由权限
        if ($permission && !in_array($name, $permission)) {
            throw new ExceptionBusiness('The user does not have permission', 403);
        }
    }
}
```

#### 2. 用户模型权限属性

```php
// 用户模型必须包含 permission 属性
class User extends Model
{
    // 方式 1：使用 JSON 字段存储权限数组
    protected $casts = [
        'permission' => 'array'
    ];

    // 方式 2：通过访问器返回权限数组
    public function getPermissionAttribute($value)
    {
        return json_decode($value, true) ?? [];
    }

    // 方式 3：关联权限表
    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function getPermissionAttribute()
    {
        return $this->permissions()->pluck('name')->toArray();
    }
}
```

#### 3. 权限数组示例

```php
// 用户权限数组格式（存储在用户模型的 permission 字段）
$userPermissions = [
    // 用户管理权限
    'admin.users.list',      // 用户列表
    'admin.users.show',      // 用户详情
    'admin.users.create',    // 创建用户
    'admin.users.edit',      // 编辑用户
    'admin.users.delete',    // 删除用户
    'admin.users.export',    // 导出用户

    // 文章管理权限
    'admin.posts.list',      // 文章列表
    'admin.posts.create',    // 创建文章
    'admin.posts.edit',      // 编辑文章

    // API 权限
    'api.users.list',        // API 用户列表
    'api.users.show',        // API 用户详情
];
```

#### 4. 权限管理最佳实践

**权限分组管理：**
```php
// 按模块分组权限
$permissions = [
    'user_management' => [
        'admin.users.list',
        'admin.users.create',
        'admin.users.edit',
        'admin.users.delete'
    ],
    'content_management' => [
        'admin.posts.list',
        'admin.posts.create',
        'admin.posts.edit',
        'admin.posts.delete'
    ],
    'api_access' => [
        'api.users.list',
        'api.users.show',
        'api.posts.list'
    ]
];
```

**角色权限配置：**
```php
class RoleSeeder
{
    public function run()
    {
        // 管理员角色 - 拥有所有权限
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->permissions = [
            'admin.users.list', 'admin.users.create', 'admin.users.edit', 'admin.users.delete',
            'admin.posts.list', 'admin.posts.create', 'admin.posts.edit', 'admin.posts.delete',
            'api.users.list', 'api.users.show'
        ];

        // 编辑角色 - 只有内容管理权限
        $editorRole = Role::create(['name' => 'editor']);
        $editorRole->permissions = [
            'admin.posts.list', 'admin.posts.create', 'admin.posts.edit'
        ];

        // API 用户角色 - 只有 API 访问权限
        $apiRole = Role::create(['name' => 'api_user']);
        $apiRole->permissions = [
            'api.users.list', 'api.users.show'
        ];
    }
}
```

**权限检查助手方法：**
```php
class User extends Model
{
    // 检查用户是否有指定权限
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permission ?? []);
    }

    // 检查用户是否有模块权限
    public function hasModulePermission(string $module): bool
    {
        $pattern = $module . '.';
        return collect($this->permission ?? [])
            ->contains(fn($perm) => str_starts_with($perm, $pattern));
    }

    // 获取用户在指定模块的权限
    public function getModulePermissions(string $module): array
    {
        $pattern = $module . '.';
        return collect($this->permission ?? [])
            ->filter(fn($perm) => str_starts_with($perm, $pattern))
            ->toArray();
    }
}

// 使用示例
$user = User::find(1);
if ($user->hasPermission('admin.users.delete')) {
    // 用户有删除权限
}

if ($user->hasModulePermission('admin.users')) {
    // 用户有用户管理模块权限
}
```

### 权限命令工具

使用命令行查看权限列表：

```bash
# 查看所有权限
php dux permission:list

# 查看指定应用权限
php dux permission:list admin
```

输出示例：
```
permissions admin
+------------------+
| Name             |
+------------------+
| group:users      |
| admin.users.list |
| admin.users.show |
| admin.users.create |
| admin.users.edit |
| admin.users.delete |
+------------------+
```

::: warning 权限配置注意事项
1. **路由名一致性**：确保权限数组中的路由名与实际生成的路由名完全一致
2. **命名规范**：遵循 DuxLite 的自动命名规则，避免手动指定冲突的路由名
3. **权限继承**：考虑使用权限分组和角色继承，避免重复配置
4. **调试工具**：使用 `php dux route:list` 和 `php dux permission:list` 命令调试路由名和权限配置
:::

## 注解路由系统

DuxLite 推荐使用注解路由，它比传统路由配置更加直观和类型安全。

### 1. 基础路由注解

使用 `#[Route]` 注解定义单个路由：

```php
// 源码位置：src/Route/Attribute/Route.php
use Core\Route\Attribute\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    #[Route('GET', '/users')]
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // 获取用户列表
        return send($response, ['users' => []]);
    }

    #[Route(['GET', 'POST'], '/users/{id}')]
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        return send($response, ['user' => "User $id"]);
    }

    #[Route('POST', '/users')]
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // 创建用户
        return send($response, ['message' => '用户创建成功']);
    }
}
```

**Route 注解参数：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `methods` | `array\|string` | ✅ | HTTP 方法：GET、POST、PUT、DELETE、PATCH、OPTIONS |
| `route` | `string` | ✅ | 路由路径，支持参数和正则约束 |
| `name` | `string` | ❌ | 路由名称，默认自动生成 |
| `auth` | `bool` | ❌ | 是否需要认证，默认 `true`（仅在 `RouteGroup` 内使用） |

### 2. 路由组注解 (`RouteGroup`)

使用 `#[RouteGroup]` 注解定义路由组，适用于普通的分组路由：

```php
// 源码位置：src/Route/Attribute/RouteGroup.php
use Core\Route\Attribute\RouteGroup;
use Core\Route\Attribute\Route;
use Core\Auth\AuthMiddleware;

#[RouteGroup(
    app: 'admin',                              // 必需：路由应用名
    route: '/admin',                           // 必需：路由前缀
    name: 'admin',                             // 可选：组名称
    middleware: [AuthMiddleware::class],       // 可选：中间件类名
    auth: true                                 // 可选：是否需要认证
)]
class AdminController
{
    #[Route('GET', '/dashboard')]
    public function dashboard(): ResponseInterface
    {
        // 实际路径：/admin/dashboard
        // 路由名称：admin.dashboard
        // 继承组的 auth: true，需要认证
        return send($response, ['data' => 'dashboard']);
    }

    #[Route('GET', '/users', auth: false)]
    public function users(): ResponseInterface
    {
        // 实际路径：/admin/users
        // 路由名称：admin.users
        // 覆盖组设置，不需要认证
        return send($response, ['users' => []]);
    }

    #[Route('POST', '/settings')]
    public function updateSettings(): ResponseInterface
    {
        // 实际路径：/admin/settings
        // 路由名称：admin.updateSettings
        // 继承组的 auth: true，需要认证
        return send($response, ['message' => '设置已更新']);
    }
}
```

**RouteGroup 注解参数：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `app` | `string` | ✅ | 路由注册名，用于识别应用模块 |
| `route` | `string` | ✅ | 路由前缀，所有组内路由都会加上此前缀 |
| `name` | `string` | ❌ | 组名称，默认根据类名自动生成 |
| `middleware` | `array` | ❌ | 中间件列表，组内所有路由都会应用 |
| `auth` | `bool` | ❌ | 组内路由是否需要认证，默认 `true` |

::: tip Route auth 参数说明
`Route` 注解的 `auth` 参数**只在 `RouteGroup` 内使用时有效**：

- **继承组设置**：如果 `Route` 未指定 `auth`，则继承 `RouteGroup` 的 `auth` 设置
- **覆盖组设置**：如果 `Route` 明确指定 `auth`，则覆盖 `RouteGroup` 的设置
- **独立路由**：在没有 `RouteGroup` 的独立路由中，`auth` 参数无效

中间件通过 `Attribute::getRequestParams($request, "auth")` 获取最终的认证设置。
:::

### 3. 资源路由注解 (`Resource`)

使用 `#[Resource]` 注解定义 RESTful 资源路由，这是 DuxLite 特有的强大功能：

```php
// 源码位置：src/Resources/Attribute/Resource.php
use Core\Resources\Attribute\Resource;
use Core\Resources\Attribute\Action;
use Core\Resources\Action\Resources;

#[Resource(
    app: 'api',                                // 必需：路由应用名
    route: '/api/users',                       // 必需：资源路由前缀
    name: 'users',                             // 必需：资源名称
    actions: ['list', 'show', 'create', 'edit', 'delete'], // 可选：启用的操作
    middleware: [],                            // 可选：中间件
    softDelete: true                           // 可选：是否支持软删除
)]
class UserResourceController extends Resources
{
    protected string $model = User::class;    // 关联的模型

    // 自动生成的路由：
    // GET    /api/users           -> list     (获取列表)
    // GET    /api/users/{id}      -> show     (获取单个)
    // POST   /api/users           -> create   (创建)
    // PUT    /api/users/{id}      -> edit     (完整更新)
    // PATCH  /api/users/{id}      -> store    (部分更新)
    // DELETE /api/users/{id}      -> delete   (删除单个)
    // DELETE /api/users           -> deleteMany (批量删除)
    // DELETE /api/users/{id}/trash -> trash   (彻底删除，软删除时)
    // PUT    /api/users/{id}/restore -> restore (恢复，软删除时)

    #[Action(['GET'], '/export', name: 'export', auth: true, can: true)]
    public function export(): ResponseInterface
    {
        // 自定义操作：导出用户
        // 路径：/api/users/export
        // 路由名称：users.export
        return send($response, ['file' => 'users.xlsx']);
    }

    #[Action(['POST'], '/import', name: 'import', auth: false, can: false)]
    public function import(): ResponseInterface
    {
        // 自定义操作：导入用户（无需认证和权限）
        // 路径：/api/users/import
        // 路由名称：users.import
        return send($response, ['message' => '导入成功']);
    }
}
```

**Resource 注解参数：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `app` | `string` | ✅ | 路由注册名 |
| `route` | `string` | ✅ | 资源路由前缀 |
| `name` | `string` | ✅ | 资源名称，用于生成路由名 |
| `actions` | `array\|false` | ❌ | 启用的操作，默认全部，`false` 为禁用 |
| `middleware` | `array` | ❌ | 中间件列表 |
| `softDelete` | `bool` | ❌ | 是否支持软删除，启用后添加 trash 和 restore 操作 |

**Action 注解参数：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `methods` | `array\|string` | ✅ | HTTP 方法 |
| `route` | `string` | ✅ | 路由路径 |
| `name` | `string` | ❌ | 操作名称，默认根据方法名生成 |
| `auth` | `bool` | ❌ | 是否需要认证 |
| `can` | `bool` | ❌ | 是否需要权限检查 |



## 路由中间件

### 1. 全局中间件

在 `src/Bootstrap.php` 中配置：

```php
public static function loadApp(Slim\App $app): void
{
    // 全局中间件（所有路由都会应用）
    $app->add(new CorsMiddleware());
    $app->add(new LangMiddleware());
}
```

### 2. 注解路由中间件

```php
use Core\Auth\AuthMiddleware;
use Core\Permission\PermissionMiddleware;

// 路由组中间件
#[RouteGroup(
    app: 'admin',
    route: '/admin',
    middleware: [
        AuthMiddleware::class,
        PermissionMiddleware::class
    ]
)]
class AdminController {
    // 组内所有路由都会应用认证和权限中间件
}

// 资源路由中间件
#[Resource(
    app: 'api',
    route: '/api/users',
    name: 'users',
    middleware: [AuthMiddleware::class]
)]
class UserResourceController extends Resources {
    // 所有资源路由都会应用认证中间件
}
```

### 3. 编程式路由中间件

```php
// 构造函数全局中间件
$route = new Route('/api', 'api',
    new CorsMiddleware(),
    new AuthMiddleware('api')
);

// 分组中间件
$adminGroup = $route->group('/admin', 'admin',
    new PermissionMiddleware('admin', 'Admin')
);

// 单个路由中间件
$route->map(['POST'], '/upload', 'UploadController:handle', 'upload', [
    new FileUploadMiddleware()
]);
```



## 路由注册和管理

### 1. 路由应用注册

DuxLite 使用应用概念管理路由：

```php
// 在应用扩展类中注册路由
class AdminApp extends AppExtend
{
    public function register(Bootstrap $bootstrap): void
    {
        // 注册路由应用
        $route = new Route('/admin', 'admin', new AuthMiddleware('admin'));
        App::route()->set('admin', $route);
    }
}
```

### 2. 自动注解注册

框架自动扫描并注册注解路由：

```php
// 在 Bootstrap::loadApp() 中自动调用
App::route()->registerAttribute();  // 注册路由注解
App::resource()->registerAttribute(); // 注册资源注解
```

### 3. 路由优先级

路由支持优先级设置，数字越小优先级越高：

```php
$route->get('/users', 'UserController:index', 'list', 100);      // 低优先级
$route->get('/users/admin', 'AdminController:users', 'admin', 1); // 高优先级
```

## 路由解析和调试

### 1. 路由列表命令

路由名查询命令是调试权限配置的重要工具，特别是用于验证自动生成的路由名是否与权限配置匹配。

#### 基本用法

```bash
# 查看所有应用的路由
php dux route:list

# 查看特定应用的路由
php dux route:list admin

# 查看 API 应用路由
php dux route:list api
```

#### 输出格式说明

```bash
# 示例输出
php dux route:list admin

routes admin
+------------------+------------------+---------+------------------+
| Pattern          | Name             | Methods | middleware       |
+------------------+------------------+---------+------------------+
| /admin/dashboard | admin.dashboard  | GET     | AuthMiddleware   |
| /admin/users     | admin.users.list | GET     | AuthMiddleware   |
| /admin/users/{id}| admin.users.show | GET     | AuthMiddleware   |
| /admin/users     | admin.users.create| POST   | AuthMiddleware   |
| /admin/users/{id}| admin.users.edit | PUT     | AuthMiddleware   |
| /admin/users/{id}| admin.users.delete| DELETE | AuthMiddleware   |
+------------------+------------------+---------+------------------+
```

**输出字段说明：**

| 字段 | 说明 | 用途 |
|------|------|------|
| **Pattern** | 路由模式 | HTTP 请求匹配的 URL 路径 |
| **Name** | 路由名称 | **权限系统使用的标识符** |
| **Methods** | HTTP 方法 | 支持的请求方法（GET\|POST\|PUT\|DELETE） |
| **middleware** | 中间件列表 | 应用到该路由的中间件 |

#### 调试权限配置

**1. 验证路由名生成：**
```bash
# 检查资源路由的完整路由名
php dux route:list admin | grep users

# 输出示例：
# /admin/users     | admin.users.list   | GET    | AuthMiddleware
# /admin/users/{id}| admin.users.show   | GET    | AuthMiddleware
# /admin/users     | admin.users.create | POST   | AuthMiddleware
# /admin/users/{id}| admin.users.edit   | PUT    | AuthMiddleware
# /admin/users/{id}| admin.users.delete | DELETE | AuthMiddleware
```

**2. 对比权限数组：**
```php
// 根据路由命令输出配置用户权限
$userPermissions = [
    'admin.users.list',    // ✅ 与路由名匹配
    'admin.users.show',    // ✅ 与路由名匹配
    'admin.users.create',  // ✅ 与路由名匹配
    'admin.users.edit',    // ✅ 与路由名匹配
    'admin.users.delete',  // ✅ 与路由名匹配

    // ❌ 错误示例：
    // 'admin.user.list',     // 错误：应该是 users 不是 user
    // 'admin.users.index',   // 错误：应该是 list 不是 index
];
```

**3. 查找缺失的路由：**
```bash
# 如果权限检查失败，使用此命令查找正确的路由名
php dux route:list admin | grep -E "(create|edit|delete)"

# 确认路由是否正确注册
php dux route:list api | grep "POST"
```

#### 实际调试场景

**场景 1：权限检查失败**
```bash
# 用户报告无法访问用户管理页面
# 1. 查看路由名
php dux route:list admin | grep users

# 2. 检查用户权限数组是否包含正确的路由名
# 比如发现路由名是 admin.users.list，但用户权限配置了 admin.user.list
```

**场景 2：注解路由未生效**
```bash
# 检查注解路由是否正确注册
php dux route:list

# 如果未显示预期的路由，检查：
# 1. 注解语法是否正确
# 2. 应用是否已注册
# 3. Bootstrap 是否调用了 registerAttribute()
```

**场景 3：中间件配置验证**
```bash
# 检查路由的中间件配置
php dux route:list admin

# 验证预期的中间件是否已应用：
# - AuthMiddleware 用于认证
# - PermissionMiddleware 用于权限检查
```

#### 高级过滤技巧

```bash
# 查看所有 POST 路由（通常是创建操作）
php dux route:list | grep "POST"

# 查看包含特定模式的路由
php dux route:list admin | grep "/users"

# 查看所有删除操作路由
php dux route:list | grep "DELETE"

# 查看特定权限相关的路由
php dux route:list admin | grep -E "(list|create|edit|delete)"

# 导出路由列表到文件供分析
php dux route:list admin > admin_routes.txt
```

#### 与权限命令结合使用

```bash
# 同时查看路由和权限，便于对比
php dux route:list admin
php dux permission:list admin

# 这样可以验证：
# 1. 权限列表中的路由名是否与实际路由匹配
# 2. 是否有遗漏的权限配置
# 3. 是否有多余的权限配置
```

### 2. 编程式路由数据解析

除了命令行工具，也可以通过代码获取路由信息：

```php
// 获取路由数据
$routes = App::route()->get('admin')->parseData();

// 获取树形路由结构
$tree = App::route()->get('admin')->parseTree();

// 遍历路由信息
foreach ($routes as $route) {
    echo "路径: {$route['pattern']}\n";
    echo "名称: {$route['name']}\n";
    echo "方法: " . implode('|', $route['methods']) . "\n";
    echo "中间件: " . implode(', ', $route['middleware']) . "\n\n";
}

// 检查特定路由是否存在
function routeExists(string $app, string $routeName): bool {
    $routes = App::route()->get($app)->parseData();
    return collect($routes)->contains('name', $routeName);
}

// 使用示例
if (routeExists('admin', 'admin.users.delete')) {
    echo "删除用户路由存在\n";
}

// 获取用户有权限访问的路由
function getUserAccessibleRoutes(string $app, array $userPermissions): array {
    $routes = App::route()->get($app)->parseData();
    return collect($routes)
        ->filter(fn($route) => in_array($route['name'], $userPermissions))
        ->toArray();
}
```

### 3. 调试最佳实践

**开发阶段：**
1. 每次添加新路由后运行 `php dux route:list` 验证
2. 对比路由名与权限配置，确保一致性
3. 使用 `grep` 过滤查看特定模块的路由

**生产部署前：**
1. 运行完整的路由列表检查
2. 验证所有权限相关的路由都已正确配置
3. 确认中间件配置符合安全要求

**问题排查：**
1. 403 权限错误时，首先检查路由名是否正确
2. 404 错误时，检查路由是否已注册
3. 使用路由命令输出与预期进行对比

::: tip 调试建议
1. **建立权限配置检查清单**：将 `php dux route:list` 输出作为权限配置的参考
2. **自动化验证**：在 CI/CD 中加入路由检查脚本
3. **文档同步**：将路由命令输出包含在项目文档中
4. **团队协作**：团队成员都应掌握路由查询命令的使用
:::

## 注解路由 vs 编程式路由

### 特性对比

| 特性 | 注解路由 | 编程式路由 |
|------|----------|------------|
| **类型安全** | ✅ IDE 支持良好 | ❌ 字符串定义易出错 |
| **代码集中** | ✅ 路由定义在控制器旁 | ❌ 路由与业务逻辑分离 |
| **动态路由** | ❌ 编译时确定 | ✅ 运行时动态生成 |
| **条件路由** | ❌ 不支持条件定义 | ✅ 支持条件和循环 |
| **可读性** | ✅ 直观清晰 | ⚠️ 需要良好组织 |
| **调试** | ✅ 易于定位 | ⚠️ 需要路由列表查看 |
| **性能** | ✅ 编译时解析 | ⚠️ 运行时处理 |

### 选择建议

**推荐使用注解路由的场景：**
- 标准的 CRUD 操作
- RESTful API 开发
- 团队开发项目（更好的可维护性）
- 大部分常规 Web 应用

**推荐使用编程式路由的场景：**
- 需要动态生成路由
- 复杂的路由条件判断
- 第三方包集成
- 老项目迁移
- 需要在运行时修改路由

**混合使用策略：**
```php
// 主要功能使用注解路由
#[RouteGroup(app: 'admin', route: '/admin')]
class AdminController {
    #[Route('GET', '/dashboard')]
    public function dashboard() { /* ... */ }
}

// 动态功能使用编程式路由
class PluginManager {
    public function registerPluginRoutes() {
        $route = App::route()->get('admin');

        foreach ($this->getActivePlugins() as $plugin) {
            $pluginGroup = $route->group("/plugins/{$plugin->getSlug()}", $plugin->getSlug());
            $plugin->registerRoutes($pluginGroup);
        }
    }
}
```

## 最佳实践

### 1. 注解路由组织

```php
// ✅ 推荐：使用 RouteGroup 组织相关路由
#[RouteGroup(app: 'admin', route: '/admin')]
class AdminController {
    #[Route('GET', '/dashboard')]
    public function dashboard() {}

    #[Route('GET', '/users')]
    public function users() {}
}

// ✅ 推荐：使用 Resource 处理 RESTful 资源
#[Resource(app: 'api', route: '/api/users', name: 'users')]
class UserResourceController extends Resources {
    #[Action('GET', '/export')]
    public function export() {}
}

// ❌ 不推荐：单独定义每个路由
class UserController {
    #[Route('GET', '/users', app: 'api')]
    public function list() {}

    #[Route('POST', '/users', app: 'api')]
    public function create() {}
}
```

### 2. 中间件应用

```php
// ✅ 推荐：在路由组级别应用中间件
#[RouteGroup(
    app: 'admin',
    route: '/admin',
    middleware: [AuthMiddleware::class, PermissionMiddleware::class]
)]
class AdminController {}

// ✅ 推荐：资源级别的中间件
#[Resource(
    app: 'api',
    route: '/api/users',
    name: 'users',
    middleware: [AuthMiddleware::class]
)]
class UserResourceController extends Resources {}
```

### 3. 路由参数验证

```php
// ✅ 推荐：使用正则约束验证参数
#[Route('GET', '/users/{id:[0-9]+}')]
public function show($id) {
    // $id 保证是数字
}

#[Route('GET', '/posts/{slug:[a-z0-9-]+}')]
public function post($slug) {
    // $slug 只能包含字母、数字和连字符
}
```

### 4. RESTful 设计

```php
// ✅ 推荐：遵循 RESTful 约定
#[Resource(
    app: 'api',
    route: '/api/users',
    name: 'users',
    actions: ['list', 'show', 'create', 'edit', 'delete']
)]
class UserResourceController extends Resources {
    // GET    /api/users        -> 获取用户列表
    // GET    /api/users/{id}   -> 获取单个用户
    // POST   /api/users        -> 创建用户
    // PUT    /api/users/{id}   -> 更新用户
    // DELETE /api/users/{id}   -> 删除用户
}
```

通过 DuxLite 的路由系统，你可以构建清晰、可维护且功能丰富的应用程序！