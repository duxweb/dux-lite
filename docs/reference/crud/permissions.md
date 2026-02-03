# 权限控制

DuxLite 基于路由注解自动生成权限标识，结合认证中间件提供完整的 CRUD 权限控制。

## 自动权限生成

### Resource 注解权限

权限标识格式：`{app}.{resource}.{action}`

```php
#[Resource(
    app: 'admin',           // 应用名称
    route: '/admin/users',  // 路由前缀
    name: 'users'          // 资源名称
)]
class UserController extends Resources
{
    // 框架自动生成以下权限：
    // admin.users.list     - GET /admin/users
    // admin.users.show     - GET /admin/users/{id}
    // admin.users.create   - POST /admin/users
    // admin.users.edit     - PUT /admin/users/{id}
    // admin.users.delete   - DELETE /admin/users/{id}
    // admin.users.deleteMany - DELETE /admin/users
}
```

### Action 注解权限

```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    // 自动生成权限：admin.users.export
    #[Action(methods: 'POST', route: '/export', name: 'export')]
    public function export(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // 权限由中间件自动验证，无需手动检查
        $users = User::query()->get();
        return send($response, '导出成功', $users->toArray());
    }
}
```

## 权限控制配置

### 限制生成权限

```php
// 只读资源 - 仅生成指定权限
#[Resource(
    app: 'admin',
    route: '/admin/reports',
    name: 'reports', 
    actions: ['list', 'show']  // 只生成 list 和 show 权限
)]
class ReportController extends Resources {}

// 禁用默认权限
#[Resource(
    app: 'admin',
    route: '/admin/dashboard',
    name: 'dashboard',
    actions: false  // 不生成任何默认权限
)]
class DashboardController extends Resources {}
```

### 跳过权限验证

```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources  
{
    // 跳过权限检查（但仍需认证）
    #[Action(methods: 'GET', route: '/stats', name: 'stats', can: false)]
    public function getStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::query()->where('status', 1)->count()
        ];
        return send($response, '获取成功', $stats);
    }

    // 跳过认证和权限（公开接口）
    #[Action(methods: 'GET', route: '/public', name: 'public', auth: false, can: false)]  
    public function getPublic(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return send($response, '公开数据', ['message' => 'Hello World']);
    }
}
```

## 中间件集成

### 应用注册配置

```php
// App.php 中注册
public function register(Bootstrap $app): void
{
    $adminRoute = new Route('/admin', 'admin',
        new AuthMiddleware('admin'),        // 认证中间件
        new PermissionMiddleware('admin', \App\Models\Admin::class)   // 权限验证中间件
    );
    \Core\App::route()->set('admin', $adminRoute);
}
```

## 获取认证信息

```php
class UserController extends Resources
{
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // 获取当前认证用户信息
        $auth = $request->getAttribute('auth');
        
        $userId = $auth['id'];
        $userRole = $auth['role'] ?? 'user';
        
        // 根据用户角色限制数据
        $query = User::query();
        if ($userRole !== 'admin') {
            $query->where('created_by', $userId);
        }
        
        $users = $query->paginate();
        
        ["data" => $data, "meta" => $meta] = format_data($users, function ($item) {
            return $item->transform();
        });
        
        return send($response, 'ok', $data, $meta);
    }
}
```

## 自定义权限逻辑

### 资源所有者权限

```php
class PostController extends Resources
{
    public function edit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $auth = $request->getAttribute('auth');
        $postId = (int)$args['id'];
        
        $post = Post::find($postId);
        if (!$post) {
            throw new ExceptionNotFound('文章不存在');
        }
        
        // 只有作者或管理员才能编辑
        if ($post->author_id !== $auth['id'] && $auth['role'] !== 'admin') {
            throw new ExceptionBusiness('只能编辑自己的文章', 403);
        }
        
        $rules = $this->validator((array)$request->getParsedBody(), $request, $args);
        $data = \Core\Validator\Validator::parser($request->getParsedBody(), $rules);
        $post->update($data->toArray());
        
        return send($response, '更新成功', $post->transform());
    }
}
```

### 条件权限验证

```php
class OrderController extends Resources
{
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $auth = $request->getAttribute('auth');
        $orderId = (int)$args['id'];
        
        $order = Order::find($orderId);
        if (!$order) {
            throw new ExceptionNotFound('订单不存在');
        }
        
        // 权限检查：用户只能查看自己的订单，管理员可以查看所有订单
        if ($auth['role'] !== 'admin' && $order->user_id !== $auth['id']) {
            throw new ExceptionBusiness('无权查看此订单', 403);
        }
        
        return send($response, '获取成功', $order->transform());
    }
}
```

## 权限系统特性

### 1. 完全自动化
- 权限根据路由注解自动生成
- 权限验证在中间件层自动完成
- 无需手动编写权限检查代码

### 2. 灵活配置
- 支持跳过权限验证 (`can: false`)
- 支持跳过认证 (`auth: false`)
- 支持限制生成的权限类型

### 3. 多层权限控制
```php
// 应用级：AuthMiddleware('admin') - 验证应用权限
// 资源级：PermissionMiddleware - 验证具体操作权限  
// 业务级：控制器中的自定义权限逻辑
```

### 4. 统一权限标识
权限名称与路由名称保持一致，便于管理和调试：
- 路由名：`admin.users.create`
- 权限名：`admin.users.create`
