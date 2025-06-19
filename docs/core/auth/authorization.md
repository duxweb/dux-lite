# 权限管理

DuxLite 提供了完整的基于角色的权限控制系统（RBAC），支持细粒度的权限管理和灵活的权限检查机制。**推荐优先使用自动化权限注册方式。**

## 权限系统概述

### 核心理念

DuxLite 的权限系统**优先自动化**，通过资源路由自动生成权限，无需手动创建和维护权限定义。这种设计大大简化了权限管理的复杂性。

### 核心组件

- **资源路由自动权限**：通过 `#[Resource]` 注解自动生成权限
- **Action 路由自动权限**：通过 `#[Action]` 注解自动注册权限
- **PermissionMiddleware**：权限验证中间件
- **Can 类**：运行时权限检查工具
- **Permission 类**：高级权限定义（手动使用）

### 权限系统架构

```
应用 (App) - 自动创建
├── 权限组 (users) - 通过资源路由自动创建
│   ├── admin.users.list - 自动生成
│   ├── admin.users.create - 自动生成
│   ├── admin.users.edit - 自动生成
│   ├── admin.users.delete - 自动生成
│   └── admin.users.export - 通过 Action 自动生成
├── 权限组 (articles) - 通过资源路由自动创建
│   ├── admin.articles.list - 自动生成
│   └── ...
└── ...
```

## 自动权限注册（推荐方式）

### 资源路由自动权限

**最简单的方式**：创建资源控制器时，权限会自动注册：

```php
use Core\Resources\Attribute\Resource;
use Core\Resources\Action\Resources;

#[Resource(
    app: 'admin',           // 应用标识
    route: '/admin/users',  // 路由前缀
    name: 'users'           // 权限组名称
)]
class UserController extends Resources
{
    protected string $model = User::class;

    // ✨ 框架自动完成：
    // 1. 自动创建 Permission 实例：new Permission()
    // 2. 自动创建权限组：$permission->group('users')
    // 3. 自动生成标准 CRUD 权限：
    //    - admin.users.list
    //    - admin.users.show
    //    - admin.users.create
    //    - admin.users.edit
    //    - admin.users.store
    //    - admin.users.delete
    // 4. 自动注册到权限系统：App::permission()->set('admin', $permission)
}
```

### Action 路由自动权限

添加自定义操作时，权限也会自动注册：

```php
use Core\Resources\Attribute\Action;

#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    protected string $model = User::class;

    // 自定义操作会自动添加权限
    #[Action(['POST'], '/export', name: 'export')]
    public function export(): ResponseInterface
    {
        // ✨ 自动生成权限：admin.users.export
        return send($response, 'success', $exportData);
    }

    #[Action(['POST'], '/import')]
    public function importUsers(): ResponseInterface
    {
        // ✨ 自动生成权限：admin.users.importUsers（基于方法名）
        return send($response, 'success', ['imported' => $count]);
    }

    #[Action(['POST'], '/{id}/reset-password', name: 'resetPassword')]
    public function resetPassword(): ResponseInterface
    {
        // ✨ 自动生成权限：admin.users.resetPassword
        return send($response, '密码重置成功');
    }

    #[Action(['POST'], '/{id}/activate', name: 'activate')]
    public function activateUser(): ResponseInterface
    {
        // ✨ 自动生成权限：admin.users.activate
        return send($response, '用户已激活');
    }
}
```

### 软删除权限自动生成

启用软删除时，会自动生成相关权限：

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

    // ✨ 自动生成标准权限 + 软删除权限：
    // - admin.posts.list
    // - admin.posts.show
    // - admin.posts.create
    // - admin.posts.edit
    // - admin.posts.store
    // - admin.posts.delete
    // - admin.posts.trash    （软删除权限）
    // - admin.posts.restore  （恢复权限）
}
```

### 自定义权限范围

可以限制自动生成的权限范围：

```php
#[Resource(
    app: 'api',
    route: '/api/posts',
    name: 'posts',
    actions: ['list', 'show', 'create']  // 只生成指定权限
)]
class ApiPostController extends Resources
{
    protected string $model = Post::class;

    // ✨ 只自动生成指定权限：
    // - api.posts.list
    // - api.posts.show
    // - api.posts.create
    // 不生成：edit, store, delete
}
```

## 权限验证自动集成

### 自动权限检查

资源路由会自动启用权限验证：

```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    protected string $model = User::class;

    // ✨ 自动权限检查，无需手动配置
    public function list(): ResponseInterface
    {
        // 框架自动检查：admin.users.list 权限
        $users = User::all();
        return send($response, 'success', $users->toArray());
    }

    public function create(): ResponseInterface
    {
        // 框架自动检查：admin.users.create 权限
        return send($response, 'success', $formData);
    }

    #[Action(['POST'], '/export', name: 'export')]
    public function export(): ResponseInterface
    {
        // 框架自动检查：admin.users.export 权限
        return send($response, 'success', $exportData);
    }
}
```

### 跳过权限检查

当需要跳过权限检查时：

```php
class UserController extends Resources
{
    // 跳过特定路由的权限检查
    #[Action(['GET'], '/public', can: false)]
    public function publicList(): ResponseInterface
    {
        // 此方法跳过权限检查
        $publicUsers = User::where('is_public', true)->get();
        return send($response, 'success', $publicUsers->toArray());
    }
}
```

## 多应用自动权限

### 不同应用自动隔离

```php
// 管理后台应用
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class AdminUserController extends Resources
{
    protected string $model = User::class;
    // 自动生成：admin.users.* 权限
}

// API 应用
#[Resource(app: 'api', route: '/api/users', name: 'users')]
class ApiUserController extends Resources
{
    protected string $model = User::class;
    // 自动生成：api.users.* 权限
}

// 移动应用
#[Resource(app: 'mobile', route: '/mobile/users', name: 'users')]
class MobileUserController extends Resources
{
    protected string $model = User::class;
    // 自动生成：mobile.users.* 权限
}
```

### 应用权限完全隔离

不同应用的权限完全独立，互不干扰：

```php
// 用户在不同应用中可以有不同权限
$adminUser = User::create([
    'username' => 'admin',
    'permission' => [
        'admin.users.list',      // 管理后台权限
        'admin.users.create',
        'admin.users.edit',
        'admin.users.delete',
    ]
]);

$apiUser = User::create([
    'username' => 'api_user',
    'permission' => [
        'api.users.list',        // API 权限
        'api.users.show',
    ]
]);
```

## 高级权限管理（手动方式）

### 何时使用手动方式

在以下少数情况下可能需要手动权限管理：

1. **复杂业务权限**：需要特殊的权限逻辑，无法通过资源路由满足
2. **第三方集成**：需要与外部权限系统集成
3. **特殊权限继承**：需要实现权限继承或权限组合

**注意**：99% 的情况下推荐使用自动化方式，手动方式仅用于特殊需求。

### 手动创建权限

```php
use Core\Permission\Permission;
use Core\App;

// 创建权限实例（通常不需要，资源路由会自动创建）
$permission = new Permission();
$permission->setApp('admin');

// 创建权限组（通常不需要，资源路由会自动创建）
$userGroup = $permission->group('user')->label('用户管理');

// 添加具体权限（通常不需要，资源路由会自动添加）
$userGroup->add('list');
$userGroup->add('create');
$userGroup->add('edit');
$userGroup->add('delete');

// 注册到系统（通常不需要，资源路由会自动注册）
App::permission()->set('admin', $permission);
```

**重要提醒**：上述代码在使用资源路由时是多余的，框架已经自动完成了这些操作。

## 用户权限模型

### 用户模型要求

用户模型必须包含 `permission` 属性来存储用户权限：

```php
use Illuminate\Database\Eloquent\Model;

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
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function getPermissionAttribute()
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->toArray();
    }
}
```

### 权限数组格式

```php
// 用户权限数组示例
$userPermissions = [
    // 用户管理权限
    'admin.users.list',
    'admin.users.show',
    'admin.users.create',
    'admin.users.edit',
    'admin.users.delete',
    'admin.users.export',

    // 文章管理权限
    'admin.articles.list',
    'admin.articles.show',
    'admin.articles.create',
    'admin.articles.edit',
    'admin.articles.publish',

    // 系统设置权限
    'admin.settings.view',
    'admin.settings.edit',
];
```

### 角色权限配置

```php
// 数据库迁移示例
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->string('email');
    $table->string('password');
    $table->json('permission')->nullable(); // 存储权限数组
    $table->string('role')->default('user');
    $table->string('status')->default('active');
    $table->timestamps();
});

// 权限分配示例
class UserSeeder
{
    public function run()
    {
        // 超级管理员 - 所有权限
        User::create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'permission' => [
                'admin.users.list', 'admin.users.create', 'admin.users.edit', 'admin.users.delete',
                'admin.articles.list', 'admin.articles.create', 'admin.articles.edit', 'admin.articles.delete',
                'admin.articles.publish', 'admin.articles.unpublish',
                'admin.settings.view', 'admin.settings.edit',
                'admin.logs.view', 'admin.logs.download', 'admin.logs.clear'
            ]
        ]);

        // 内容管理员 - 文章相关权限
        User::create([
            'username' => 'editor',
            'email' => 'editor@example.com',
            'password' => bcrypt('password'),
            'role' => 'editor',
            'permission' => [
                'admin.articles.list', 'admin.articles.show', 'admin.articles.create',
                'admin.articles.edit', 'admin.articles.publish'
            ]
        ]);

        // 普通用户 - 只读权限
        User::create([
            'username' => 'viewer',
            'email' => 'viewer@example.com',
            'password' => bcrypt('password'),
            'role' => 'viewer',
            'permission' => [
                'admin.articles.list', 'admin.articles.show'
            ]
        ]);
    }
}
```

## 运行时权限检查

### Can 类手动检查

```php
use Core\Permission\Can;

public function deleteUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    // 手动权限检查
    Can::check($request, User::class, 'admin.users.delete');

    // 权限检查通过，执行删除操作
    $userId = $request->getAttribute('id');
    User::destroy($userId);

    return send($response, '删除成功');
}
```

### 用户权限辅助方法

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

    // 检查用户是否有任一权限
    public function hasAnyPermission(array $permissions): bool
    {
        return !empty(array_intersect($permissions, $this->permission ?? []));
    }

    // 检查用户是否有所有权限
    public function hasAllPermissions(array $permissions): bool
    {
        return empty(array_diff($permissions, $this->permission ?? []));
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

$userPermissions = $user->getModulePermissions('admin.users');
// 获取用户在用户管理模块的所有权限
```

## 权限命令工具

### 查看权限列表

```bash
# 查看所有权限应用
php dux permission:list

# 查看指定应用权限
php dux permission:list admin
```

输出示例：
```
permissions admin
+----------------------+
| Name                 |
+----------------------+
| group:users          |
| admin.users.list     |
| admin.users.show     |
| admin.users.create   |
| admin.users.edit     |
| admin.users.store    |
| admin.users.delete   |
| group:articles       |
| admin.articles.list  |
| admin.articles.show  |
| admin.articles.create|
+----------------------+
```

### 权限调试

```bash
# 查看路由和权限对应关系
php dux route:list admin | grep users

# 输出示例：
# /admin/users     | admin.users.list   | GET    | AuthMiddleware,PermissionMiddleware
# /admin/users/{id}| admin.users.show   | GET    | AuthMiddleware,PermissionMiddleware
# /admin/users     | admin.users.create | POST   | AuthMiddleware,PermissionMiddleware
```

## 权限最佳实践

### 1. 权限命名规范

```php
// ✅ 推荐的命名规范
'admin.users.list'           // 应用.模块.操作
'admin.users.create'
'admin.users.edit'
'admin.users.delete'
'admin.articles.publish'     // 业务相关操作
'admin.settings.backup'      // 系统相关操作

// ❌ 避免的命名方式
'userList'                   // 缺少应用和模块前缀
'admin.user.list'           // 模块名不一致（应该是 users）
'admin.users.index'         // 使用 index 而不是 list
```

### 2. 权限分组策略

```php
// ✅ 按业务模块分组
$permission->group('user')->resources();      // 用户管理
$permission->group('article')->resources();   // 文章管理
$permission->group('product')->resources();   // 产品管理

// ✅ 按功能层级分组
$permission->group('admin.user')->resources();    // 后台用户管理
$permission->group('api.user')->resources();      // API 用户接口
$permission->group('mobile.user')->resources();   // 移动端用户功能
```

### 3. 角色权限设计

```php
class RolePermissionManager
{
    public static function getDefaultRoles(): array
    {
        return [
            'super_admin' => [
                'description' => '超级管理员',
                'permissions' => ['*'] // 所有权限
            ],
            'admin' => [
                'description' => '管理员',
                'permissions' => [
                    'admin.users.*',      // 用户管理所有权限
                    'admin.articles.*',   // 文章管理所有权限
                    'admin.settings.view' // 只能查看设置
                ]
            ],
            'editor' => [
                'description' => '编辑',
                'permissions' => [
                    'admin.articles.list',
                    'admin.articles.show',
                    'admin.articles.create',
                    'admin.articles.edit',
                    'admin.articles.publish'
                ]
            ],
            'viewer' => [
                'description' => '查看者',
                'permissions' => [
                    'admin.articles.list',
                    'admin.articles.show',
                    'admin.users.list',
                    'admin.users.show'
                ]
            ]
        ];
    }
}
```

### 4. 动态权限控制

```php
// 在视图中根据权限显示内容
class UserController extends Resources
{
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $auth = $request->getAttribute('auth');
        $currentUser = User::find($auth['id']);

        $users = User::query();

        // 根据权限过滤数据
        if (!$currentUser->hasPermission('admin.users.viewAll')) {
            // 只能看到自己创建的用户
            $users->where('created_by', $currentUser->id);
        }

        $result = $users->paginate(20);

        // 返回权限信息供前端使用
        return send($response, 'success', $result->items(), [
            'pagination' => [...],
            'permissions' => [
                'can_create' => $currentUser->hasPermission('admin.users.create'),
                'can_edit' => $currentUser->hasPermission('admin.users.edit'),
                'can_delete' => $currentUser->hasPermission('admin.users.delete'),
                'can_export' => $currentUser->hasPermission('admin.users.export'),
            ]
        ]);
    }
}
```

## 故障排除

### 常见权限问题

**Q: 权限检查不生效**
```
检查清单：
1. 用户模型是否正确配置 permission 属性
2. 权限名称是否与路由名称匹配
3. 是否正确注册权限中间件
4. 中间件执行顺序是否正确（认证在前，权限在后）
```

**Q: 资源路由权限名称不正确**
```
解决方案：
1. 使用 php dux route:list 查看生成的路由名
2. 使用 php dux permission:list 查看权限列表
3. 确保路由名和权限名一致
4. 检查资源控制器的命名是否符合规范
```

**Q: 用户权限不更新**
```
检查：
1. 数据库中的权限数据是否正确更新
2. 是否有权限缓存需要清理
3. 用户模型的权限访问器是否正确
4. 前端是否正确处理权限状态
```

通过 DuxLite 的权限管理系统，您可以构建安全、灵活、易维护的权限控制机制，实现细粒度的访问控制。