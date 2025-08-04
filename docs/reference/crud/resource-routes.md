# 资源路由

资源路由通过 `#[Resource]` 注解自动生成标准的 RESTful 路由，并集成认证中间件和权限控制。

## 基本概念

### 自动路由生成

资源路由会自动生成标准的 CRUD 路由：

```php
use Core\Resources\Attribute\Resource;
use Core\Auth\AuthMiddleware;

#[Resource(
    app: 'admin',
    route: '/admin/users',
    name: 'users',
    middleware: [AuthMiddleware::class]  // 必须添加认证中间件
)]
class UserController extends Resources
{
    protected string $model = User::class;
}
```

### 生成的路由表

上述配置会自动生成以下路由：

| HTTP方法 | 路由路径 | 控制器方法 | 路由名称 | 功能描述 |
|----------|----------|-----------|----------|----------|
| **GET** | `/admin/users` | `list()` | `admin.users.list` | 获取用户列表 |
| **GET** | `/admin/users/{id}` | `show()` | `admin.users.show` | 获取单个用户 |
| **POST** | `/admin/users` | `create()` | `admin.users.create` | 创建新用户 |
| **PUT** | `/admin/users/{id}` | `edit()` | `admin.users.edit` | 完整更新用户 |
| **PATCH** | `/admin/users/{id}` | `store()` | `admin.users.store` | 部分更新用户 |
| **DELETE** | `/admin/users/{id}` | `delete()` | `admin.users.delete` | 删除单个用户 |
| **DELETE** | `/admin/users` | `deleteMany()` | `admin.users.deleteMany` | 批量删除用户 |

## 注解参数

### Resource 注解参数详解

```php
#[Resource(
    app: 'admin',                              // 路由应用名
    route: '/admin/users',                     // 资源路由前缀
    name: 'users',                            // 资源名称
    actions: ['list', 'show', 'create'],     // 可选：限制启用的操作
    middleware: [AuthMiddleware::class],      // 必需：认证中间件
    softDelete: true                          // 可选：启用软删除功能
)]
```

**参数说明：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| **app** | `string` | ✅ | 路由应用名，必须已在应用中注册 |
| **route** | `string` | ✅ | 资源路由前缀，所有操作都会基于此路径 |
| **name** | `string` | ✅ | 资源名称，用于生成路由名和权限标识 |
| **actions** | `array\|false` | ❌ | 启用的操作列表，默认全部，`false` 禁用所有 |
| **middleware** | `array` | ❌ | 中间件列表，**建议添加认证中间件** |
| **softDelete** | `bool` | ❌ | 是否启用软删除，默认 `false` |

## 认证中间件集成

### 必须使用认证中间件

资源路由依赖于认证中间件来保护接口安全：

```php
use Core\Auth\AuthMiddleware;
use Core\Permission\PermissionMiddleware;

#[Resource(
    app: 'admin',
    route: '/admin/users',
    name: 'users',
    middleware: [
        AuthMiddleware::class,           // 必需：用户认证
        PermissionMiddleware::class      // 可选：权限检查
    ]
)]
class UserController extends Resources
{
    // 所有 CRUD 操作都需要认证
}
```

### 中间件执行顺序

中间件按照数组顺序执行：

```php
middleware: [
    AuthMiddleware::class,        // 1. 先认证用户身份
    PermissionMiddleware::class,  // 2. 再检查操作权限
    CustomMiddleware::class       // 3. 最后执行自定义逻辑
]
```

## 限制操作范围

### 只读资源

只允许查询操作，不允许修改：

```php
#[Resource(
    app: 'api',
    route: '/api/reports',
    name: 'reports',
    actions: ['list', 'show'],  // 只生成查询相关路由
    middleware: [AuthMiddleware::class]
)]
class ReportController extends Resources
{
    // 自动生成：
    // GET /api/reports     -> api.reports.list
    // GET /api/reports/{id} -> api.reports.show
    // 不生成增删改操作
}
```

### 禁用默认操作

完全禁用默认操作，只使用自定义操作：

```php
#[Resource(
    app: 'api',
    route: '/api/tools',
    name: 'tools',
    actions: false,  // 禁用所有默认操作
    middleware: [AuthMiddleware::class]
)]
class ToolController extends Resources
{
    // 不生成任何默认路由，只能使用 Action 注解自定义操作
    
    #[Action(['GET'], '/status', name: 'status')]
    public function getStatus(...): ResponseInterface
    {
        return send($response, '工具状态正常');
    }
}
```

## 软删除支持

### 启用软删除

启用软删除功能会生成额外的路由：

```php
#[Resource(
    app: 'admin',
    route: '/admin/posts',
    name: 'posts',
    softDelete: true,  // 启用软删除
    middleware: [AuthMiddleware::class]
)]
class PostController extends Resources
{
    protected string $model = Post::class;
}
```

### 软删除路由

启用软删除后会额外生成：

| HTTP方法 | 路由路径 | 控制器方法 | 路由名称 | 功能描述 |
|----------|----------|-----------|----------|----------|
| **DELETE** | `/admin/posts/{id}/trash` | `trash()` | `admin.posts.trash` | 彻底删除 |
| **PUT** | `/admin/posts/{id}/restore` | `restore()` | `admin.posts.restore` | 恢复删除 |

## 权限自动生成

### 权限命名规则

资源路由会自动生成权限标识：

- 格式：`{app}.{name}.{action}`
- 示例：`admin.users.list`、`admin.users.create`

```php
#[Resource(
    app: 'admin',        // 应用名
    route: '/admin/users',
    name: 'users',       // 资源名
    middleware: [AuthMiddleware::class, PermissionMiddleware::class]
)]
class UserController extends Resources
{
    // 自动生成权限：
    // - admin.users.list
    // - admin.users.show  
    // - admin.users.create
    // - admin.users.edit
    // - admin.users.delete
}
```

### 权限检查

配置权限中间件后，会自动检查用户权限：

```php
// 用户访问 GET /admin/users 时
// 会自动检查用户是否有 admin.users.list 权限

// 用户访问 POST /admin/users 时  
// 会自动检查用户是否有 admin.users.create 权限
```

## 路由注册

### 应用注册

确保在应用中注册了路由：

```php
// App.php
class App extends AppExtend
{
    public function register(Bootstrap $app): void
    {
        // 注册路由应用
        \Core\App::route()->set('admin', new \Core\Route\Route());
        \Core\App::route()->set('api', new \Core\Route\Route());
    }
}
```

### 配置文件

在 `config/app.toml` 中注册应用：

```toml
# 应用注册
registers = [
    "App\\App"
]
```

## 自定义操作路由

### Action 注解

使用 `#[Action]` 注解添加自定义路由：

```php
#[Resource(
    app: 'admin',
    route: '/admin/users',
    name: 'users',
    middleware: [AuthMiddleware::class]
)]
class UserController extends Resources
{
    /**
     * 导出用户数据
     */
    #[Action(['GET'], '/export', name: 'export')]
    public function export(...): ResponseInterface
    {
        // 完整路径：/admin/users/export
        // 路由名称：admin.users.export
        // 权限检查：admin.users.export（如果启用权限中间件）
        
        return send($response, '导出成功');
    }

    /**
     * 批量操作
     */
    #[Action(['POST'], '/batch-activate', name: 'batchActivate')]
    public function batchActivate(...): ResponseInterface
    {
        // 完整路径：/admin/users/batch-activate
        // 路由名称：admin.users.batchActivate
        
        return send($response, '批量激活成功');
    }
}
```

### Action 权限控制

```php
// 需要权限检查（默认）
#[Action(['GET'], '/export', name: 'export', can: true)]
public function export(...): ResponseInterface
{
    // 需要 admin.users.export 权限
}

// 跳过权限检查
#[Action(['GET'], '/public-info', name: 'publicInfo', can: false)]
public function getPublicInfo(...): ResponseInterface
{
    // 不需要权限，但仍需要认证
}

// 跳过认证和权限
#[Action(['GET'], '/status', name: 'status', auth: false, can: false)]
public function getStatus(...): ResponseInterface
{
    // 完全公开的接口
}
```

## 完整示例

```php
use Core\Resources\Attribute\Resource;
use Core\Resources\Attribute\Action;
use Core\Auth\AuthMiddleware;
use Core\Permission\PermissionMiddleware;

#[Resource(
    app: 'admin',
    route: '/admin/products',
    name: 'products',
    middleware: [
        AuthMiddleware::class,       // 认证中间件
        PermissionMiddleware::class  // 权限中间件
    ]
)]
class ProductController extends Resources
{
    protected string $model = Product::class;

    // 标准 CRUD 路由自动生成：
    // GET    /admin/products         -> admin.products.list
    // GET    /admin/products/{id}    -> admin.products.show
    // POST   /admin/products         -> admin.products.create
    // PUT    /admin/products/{id}    -> admin.products.edit
    // DELETE /admin/products/{id}    -> admin.products.delete

    /**
     * 自定义操作：批量上架
     */
    #[Action(['POST'], '/batch-publish', name: 'batchPublish')]
    public function batchPublish(...): ResponseInterface
    {
        // 路径：/admin/products/batch-publish
        // 权限：admin.products.batchPublish
        
        return send($response, '批量上架成功');
    }

    /**
     * 自定义操作：库存预警（公开接口）
     */
    #[Action(['GET'], '/stock-alert', name: 'stockAlert', can: false)]
    public function stockAlert(...): ResponseInterface
    {
        // 路径：/admin/products/stock-alert
        // 需要认证但不需要权限
        
        return send($response, '库存预警数据');
    }
}
```

资源路由通过注解自动生成标准 RESTful 接口，配合认证中间件和权限控制，提供安全可靠的 API 访问机制。