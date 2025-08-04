# 权限控制

权限控制是 CRUD 开发中的关键安全机制。DuxLite 通过资源控制器的自动权限集成和中间件验证，确保 API 接口的安全访问。

## 基本概念

### 权限层次结构

```
应用 (app) → 资源 (resource) → 操作 (action)
  ↓              ↓                ↓
admin        →  users          →  list
admin        →  users          →  create
admin        →  users          →  edit
admin        →  users          →  delete
```

权限标识格式：`{app}.{resource}.{action}`

例如：`admin.users.list`、`admin.users.create`、`admin.users.edit`

## 自动权限生成

### 基础权限配置

```php
#[Resource(
    app: 'admin',           // 应用名
    route: '/admin/users',  // 路由前缀
    name: 'users'          // 资源名
)]
class UserController extends Resources
{
    protected string $model = User::class;
    
    // 自动生成以下权限：
    // admin.users.list    - 查看用户列表
    // admin.users.show    - 查看用户详情
    // admin.users.create  - 创建用户
    // admin.users.edit    - 编辑用户
    // admin.users.delete  - 删除用户
    // admin.users.deleteMany - 批量删除用户
}
```

### 限制权限范围

```php
// 只读资源：只生成查询权限
#[Resource(
    app: 'admin',
    route: '/admin/reports',
    name: 'reports',
    actions: ['list', 'show']  // 只生成 admin.reports.list 和 admin.reports.show
)]
class ReportController extends Resources
{
    // 只有查询权限，无增删改权限
}

// 禁用所有默认权限
#[Resource(
    app: 'admin',
    route: '/admin/dashboard',
    name: 'dashboard',
    actions: false  // 不生成任何默认权限
)]
class DashboardController extends Resources
{
    // 只能通过 Action 注解自定义权限
}
```

### 自定义操作权限

```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    // 默认需要权限检查
    #[Action(['POST'], '/export', name: 'export')]
    public function export(): ResponseInterface
    {
        // 需要 admin.users.export 权限
        return send($response, '导出成功', $data);
    }

    // 跳过权限检查
    #[Action(['GET'], '/statistics', name: 'statistics', can: false)]
    public function getStatistics(): ResponseInterface
    {
        // 无需权限检查，任何认证用户都可以访问
        return send($response, '统计数据', $data);
    }

    // 无需认证的公开接口
    #[Action(['GET'], '/public-list', name: 'publicList', auth: false, can: false)]
    public function getPublicList(): ResponseInterface
    {
        // 完全公开的接口
        return send($response, '公开数据', $data);
    }
}
```

## 权限中间件

### 认证中间件

```php
use Core\Auth\AuthMiddleware;

#[Resource(
    app: 'admin',
    route: '/admin/users',
    name: 'users',
    middleware: [AuthMiddleware::class]  // 需要认证
)]
class UserController extends Resources
{
    // 所有操作都需要认证
}
```

### 权限检查中间件

```php
use Core\Auth\AuthMiddleware;
use Core\Permission\PermissionMiddleware;

#[Resource(
    app: 'admin',
    route: '/admin/users',
    name: 'users',
    middleware: [AuthMiddleware::class, PermissionMiddleware::class]
)]
class UserController extends Resources
{
    // 自动检查对应的权限：
    // GET  /admin/users     -> 检查 admin.users.list
    // POST /admin/users     -> 检查 admin.users.create
    // PUT  /admin/users/123 -> 检查 admin.users.edit
    // DELETE /admin/users/123 -> 检查 admin.users.delete
}
```

## 动态权限控制

### 在控制器中进行权限检查

```php
class UserController extends Resources
{
    /**
     * 编辑前权限检查
     */
    public function editBefore(Data $data, mixed $model): void
    {
        $auth = \Core\App::auth();
        
        // 只能编辑自己的信息，或者管理员可以编辑所有用户
        if ($model->id !== $auth['id'] && !$this->isAdmin($auth)) {
            throw new \Core\Handlers\ExceptionBusiness('权限不足：只能编辑自己的信息', 403);
        }
        
        // 普通用户不能修改角色
        if (isset($data->role) && !$this->isAdmin($auth)) {
            throw new \Core\Handlers\ExceptionBusiness('权限不足：无法修改用户角色', 403);
        }
    }

    /**
     * 删除前权限检查
     */
    public function delBefore(mixed $model): void
    {
        $auth = \Core\App::auth();
        
        // 不能删除自己
        if ($model->id === $auth['id']) {
            throw new \Core\Handlers\ExceptionBusiness('不能删除自己的账户', 400);
        }
        
        // 不能删除超级管理员
        if ($model->hasRole('super_admin')) {
            throw new \Core\Handlers\ExceptionBusiness('不能删除超级管理员', 403);
        }
    }

    /**
     * 查询权限控制
     */
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $auth = $request->getAttribute('auth');
        
        // 普通用户只能看到自己的数据
        if (!$this->isAdmin($auth)) {
            $query->where('id', $auth['id']);
        }
    }

    private function isAdmin(array $auth): bool
    {
        return in_array($auth['role'] ?? '', ['admin', 'super_admin']);
    }
}
```

### 数据筛选权限

```php
class PostController extends Resources
{
    /**
     * 基于权限的数据筛选
     */
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $auth = $request->getAttribute('auth');
        
        // 根据用户角色筛选数据
        switch ($auth['role'] ?? 'user') {
            case 'super_admin':
                // 超级管理员可以看到所有文章
                break;
                
            case 'admin':
                // 管理员可以看到所有已发布的文章和自己的草稿
                $query->where(function($q) use ($auth) {
                    $q->where('status', 'published')
                      ->orWhere('author_id', $auth['id']);
                });
                break;
                
            case 'author':
                // 作者只能看到自己的文章
                $query->where('author_id', $auth['id']);
                break;
                
            default:
                // 普通用户只能看到已发布的公开文章
                $query->where('status', 'published')
                      ->where('visibility', 'public');
        }
    }

    /**
     * 基于权限的字段控制
     */
    public function transform(object $item): array
    {
        $auth = \Core\App::auth();
        
        $data = [
            'id' => $item->id,
            'title' => $item->title,
            'content' => $item->content,
            'status' => $item->status,
            'created_at' => $item->created_at->format('Y-m-d H:i:s'),
        ];

        // 只有管理员和作者可以看到统计数据
        if ($this->canViewStats($auth, $item)) {
            $data['views'] = $item->views;
            $data['comments_count'] = $item->comments()->count();
        }

        // 只有管理员可以看到审核信息
        if ($this->isAdmin($auth)) {
            $data['reviewer_id'] = $item->reviewer_id;
            $data['reviewed_at'] = $item->reviewed_at;
        }

        return $data;
    }

    private function canViewStats(array $auth, $item): bool
    {
        return $this->isAdmin($auth) || $item->author_id === $auth['id'];
    }

    private function isAdmin(array $auth): bool
    {
        return in_array($auth['role'] ?? '', ['admin', 'super_admin']);
    }
}
```

## 权限检查辅助方法

### 在控制器中使用

```php
class UserController extends Resources
{
    /**
     * 检查是否有指定权限
     */
    protected function hasPermission(string $permission): bool
    {
        $auth = \Core\App::auth();
        
        // 超级管理员拥有所有权限
        if (($auth['role'] ?? '') === 'super_admin') {
            return true;
        }
        
        // 检查用户权限列表
        $userPermissions = $auth['permissions'] ?? [];
        return in_array($permission, $userPermissions);
    }

    /**
     * 检查是否有任一权限
     */
    protected function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查用户角色
     */
    protected function hasRole(string $role): bool
    {
        $auth = \Core\App::auth();
        return ($auth['role'] ?? '') === $role;
    }

    /**
     * 检查是否有任一角色
     */
    protected function hasAnyRole(array $roles): bool
    {
        $auth = \Core\App::auth();
        $userRole = $auth['role'] ?? '';
        return in_array($userRole, $roles);
    }
}
```

### 使用示例

```php
class OrderController extends Resources
{
    #[Action(['PUT'], '/{id}/approve', name: 'approve')]
    public function approveOrder(...): ResponseInterface
    {
        // 检查审核权限
        if (!$this->hasPermission('admin.orders.approve')) {
            throw new \Core\Handlers\ExceptionBusiness('权限不足：无法审核订单', 403);
        }

        // 处理审核逻辑
        return send($response, '审核成功');
    }

    #[Action(['GET'], '/export', name: 'export')]
    public function exportOrders(...): ResponseInterface
    {
        // 检查角色权限
        if (!$this->hasAnyRole(['admin', 'manager'])) {
            throw new \Core\Handlers\ExceptionBusiness('权限不足：只有管理员和经理可以导出', 403);
        }

        // 处理导出逻辑
        return send($response, '导出成功');
    }
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
        AuthMiddleware::class,
        PermissionMiddleware::class
    ]
)]
class ProductController extends Resources
{
    protected string $model = Product::class;

    /**
     * 查询权限控制
     */
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $auth = $request->getAttribute('auth');
        
        // 根据角色限制可见数据
        if (($auth['role'] ?? '') !== 'admin') {
            // 非管理员只能看到已上架的产品
            $query->where('status', 1);
        }
    }

    /**
     * 创建前权限检查
     */
    public function createBefore(Data $data, mixed $model): void
    {
        $auth = \Core\App::auth();
        
        // 设置创建者
        $model->created_by = $auth['id'];
        
        // 只有管理员可以设置为推荐产品
        if (isset($data->is_featured) && $data->is_featured && !$this->hasRole('admin')) {
            throw new \Core\Handlers\ExceptionBusiness('权限不足：无法设置推荐产品', 403);
        }
    }

    /**
     * 删除前权限检查
     */
    public function delBefore(mixed $model): void
    {
        // 检查是否有关联订单
        if ($model->orderItems()->exists()) {
            throw new \Core\Handlers\ExceptionBusiness('该产品有关联订单，无法删除', 400);
        }
    }

    /**
     * 批量上架 - 需要特殊权限
     */
    #[Action(['POST'], '/batch-publish', name: 'batchPublish')]
    public function batchPublish(...): ResponseInterface
    {
        // 检查批量操作权限
        if (!$this->hasPermission('admin.products.batchPublish')) {
            throw new \Core\Handlers\ExceptionBusiness('权限不足：无法批量上架', 403);
        }

        $data = $request->getParsedBody();
        $ids = $data['ids'] ?? [];

        $count = Product::whereIn('id', $ids)->update(['status' => 1]);

        return send($response, '批量上架成功', [
            'updated_count' => $count
        ]);
    }

    /**
     * 产品统计 - 跳过权限检查
     */
    #[Action(['GET'], '/statistics', name: 'statistics', can: false)]
    public function getStatistics(...): ResponseInterface
    {
        // 所有认证用户都可以查看统计
        $stats = [
            'total' => Product::count(),
            'published' => Product::where('status', 1)->count(),
            'draft' => Product::where('status', 0)->count()
        ];

        return send($response, '统计数据', $stats);
    }

    private function hasRole(string $role): bool
    {
        $auth = \Core\App::auth();
        return ($auth['role'] ?? '') === $role;
    }

    private function hasPermission(string $permission): bool
    {
        $auth = \Core\App::auth();
        
        if (($auth['role'] ?? '') === 'super_admin') {
            return true;
        }
        
        $userPermissions = $auth['permissions'] ?? [];
        return in_array($permission, $userPermissions);
    }
}
```

通过合理使用 DuxLite 的权限控制系统，可以构建安全可靠的 CRUD 应用，确保用户只能访问其有权限的资源和操作。