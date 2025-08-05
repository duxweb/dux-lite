# 资源路由

资源路由通过 `#[Resource]` 注解自动生成标准的 RESTful 路由，并集成认证中间件和权限控制。

## 路由注册

### 资源应用注册

在使用资源路由之前，必须先在应用中注册资源：

```php
// App.php
use Core\App\AppExtend;
use Core\Bootstrap;
use Core\Resources\Resource;
use Core\Auth\AuthMiddleware;
use Core\Permission\PermissionMiddleware;

class App extends AppExtend
{
    public function init(Bootstrap $app): void
    {
        // 初始化资源，配置认证和权限中间件
        \Core\App::resource()->set(
            "admin",
            (new Resource(
                'admin',
                '/admin'
            ))->addAuthMiddleware(
                new AuthMiddleware("admin"),
                new PermissionMiddleware("admin", \App\Models\User::class)
            )
        );

        // 注册其他路由
        \Core\App::route()->set("web", new \Core\Route\Route(""));
        \Core\App::route()->set("api", new \Core\Route\Route("/api"));
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

## 基本概念

### 自动路由生成

资源路由会自动生成标准的 CRUD 路由：

```php
use Core\Resources\Attribute\Resource;
use Core\Auth\AuthMiddleware;

#[Resource(
    app: 'admin',
    route: '/admin/users',
    name: 'users'
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
| **softDelete** | `bool` | ❌ | 是否启用软删除，默认 `false` |

## 认证中间件集成

### AuthMiddleware 认证中间件

`AuthMiddleware` 负责验证用户身份和认证状态：

```php
use Core\Auth\AuthMiddleware;

// 基本用法
new AuthMiddleware($guard)

// 参数说明：
// $guard - 认证守卫名称，用于区分不同的认证系统
//   如：'admin'（管理后台）、'api'（API接口）、'member'（会员系统）
```

**认证流程**：
1. 检查请求中的认证信息（Token、Session等）
2. 验证认证信息的有效性
3. 将认证用户信息注入到请求上下文
4. 认证失败时返回401错误

### PermissionMiddleware 权限中间件

`PermissionMiddleware` 负责检查用户是否有执行当前操作的权限：

```php
use Core\Permission\PermissionMiddleware;

// 基本用法  
new PermissionMiddleware($guard, $userModel)

// 参数说明：
// $guard - 权限守卫名称，与AuthMiddleware保持一致
// $userModel - 用户模型类，用于权限查询
//   如：\App\Models\User::class、\App\Models\Admin::class
```

**权限检查流程**：
1. 从认证信息中获取用户ID
2. 根据路由名生成权限标识（如：admin.users.create）
3. 查询用户是否拥有该权限
4. 权限不足时返回403错误

### 中间件配置示例

```php
// 管理后台配置
\Core\App::resource()->set(
    "admin",
    (new Resource('admin', '/admin'))->addAuthMiddleware(
        new AuthMiddleware("admin"),                              // 管理员认证
        new PermissionMiddleware("admin", \App\Models\Admin::class) // 管理员权限
    )
);

// API接口配置
\Core\App::resource()->set(
    "api", 
    (new Resource('api', '/api'))->addAuthMiddleware(
        new AuthMiddleware("api"),                               // API认证
        new PermissionMiddleware("api", \App\Models\User::class)  // 用户权限
    )
);

// 会员系统配置
\Core\App::resource()->set(
    "member",
    (new Resource('member', '/member'))->addAuthMiddleware(
        new AuthMiddleware("member"),                              // 会员认证
        new PermissionMiddleware("member", \App\Models\Member::class) // 会员权限
    )
);
```

## 限制操作范围

### 只读资源

只允许查询操作，不允许修改：

```php
#[Resource(
    app: 'api',
    route: '/api/reports',
    name: 'reports',
    actions: ['list', 'show']  // 只生成查询相关路由
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
    actions: false  // 禁用所有默认操作
)]
class ToolController extends Resources
{
    // 不生成任何默认路由，只能使用 Action 注解自定义操作
    
    #[Action(methods: 'GET', route: '/status', name: 'status')]
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
    softDelete: true  // 启用软删除
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
    name: 'users'       // 资源名
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

## 自定义操作路由

### Action 注解

使用 `#[Action]` 注解添加自定义路由：

```php
#[Resource(
    app: 'admin',
    route: '/admin/users',
    name: 'users'
)]
class UserController extends Resources
{
    /**
     * 导出用户数据
     */
    #[Action(methods: 'GET', route: '/export', name: 'export')]
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
    #[Action(methods: 'POST', route: '/batch-activate', name: 'batchActivate')]
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
#[Action(methods: 'GET', route: '/export', name: 'export', can: true)]
public function export(...): ResponseInterface
{
    // 需要 admin.users.export 权限
}

// 跳过权限检查
#[Action(methods: 'GET', route: '/public-info', name: 'publicInfo', can: false)]
public function getPublicInfo(...): ResponseInterface
{
    // 不需要权限，但仍需要认证
}

// 跳过认证和权限
#[Action(methods: 'GET', route: '/status', name: 'status', auth: false, can: false)]
public function getStatus(...): ResponseInterface
{
    // 完全公开的接口
}
```

## 完整示例

```php
use Core\Resources\Attribute\Resource;
use Core\Resources\Attribute\Action;

#[Resource(
    app: 'admin',
    route: '/admin/products',
    name: 'products'
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
    #[Action(methods: 'POST', route: '/batch-publish', name: 'batchPublish')]
    public function batchPublish(...): ResponseInterface
    {
        // 路径：/admin/products/batch-publish
        // 权限：admin.products.batchPublish
        
        return send($response, '批量上架成功');
    }

    /**
     * 自定义操作：库存预警（公开接口）
     */
    #[Action(methods: 'GET', route: '/stock-alert', name: 'stockAlert', can: false)]
    public function stockAlert(...): ResponseInterface
    {
        // 路径：/admin/products/stock-alert
        // 需要认证但不需要权限
        
        return send($response, '库存预警数据');
    }
}
```

资源路由通过注解自动生成标准 RESTful 接口，配合认证中间件和权限控制，提供安全可靠的 API 访问机制。