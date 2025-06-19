# 资源控制器

资源控制器是 DuxLite 框架的核心特性，通过 `#[Resource]` 注解和 `Resources` 基类，快速实现标准化的 RESTful API。资源控制器必须配合资源路由使用，提供完整的 CRUD 操作、自动验证、权限控制和事件钩子。

## 设计理念

DuxLite 资源控制器采用**约定优于配置的 RESTful API 开发**设计：

- **标准化 CRUD**：自动提供完整的增删改查操作
- **路由注解驱动**：使用 `#[Resource]` 注解自动生成路由
- **自动验证**：集成数据验证和格式化机制
- **权限自动集成**：自动配置权限检查中间件
- **事件钩子系统**：支持操作前后的业务扩展
- **数据转换**：灵活的输入输出数据转换机制

### 核心特性

- **Resource 注解**：自动生成 RESTful 路由和权限
- **Action 注解**：扩展自定义操作
- **数据验证**：自动验证和格式化输入数据
- **数据转换**：灵活的数据输入输出转换
- **权限控制**：自动集成权限检查
- **事件钩子**：支持创建、编辑、删除等操作的钩子
- **软删除支持**：内置软删除功能



## Resource 注解详解

### 基础用法

```php
use Core\Resources\Action\Resources;
use Core\Resources\Attribute\Resource;
use App\Models\User;

#[Resource(
    app: 'admin',           // 必需：路由应用名
    route: '/admin/users',  // 必需：资源路由前缀
    name: 'users',          // 必需：资源名称，用于生成路由名和权限
)]
class UserController extends Resources
{
    protected string $model = User::class;
    protected string $key = 'id';
    protected string $label = '用户管理';
}
```

### 自动生成的路由

上述配置会自动生成以下标准 RESTful 路由：

| HTTP方法 | 路由路径 | 控制器方法 | 路由名称 | 功能描述 |
|----------|----------|-----------|----------|----------|
| **GET** | `/admin/users` | `many()` | `admin.users.list` | 获取用户列表 |
| **GET** | `/admin/users/{id}` | `one()` | `admin.users.show` | 获取单个用户 |
| **POST** | `/admin/users` | `create()` | `admin.users.create` | 创建新用户 |
| **PUT** | `/admin/users/{id}` | `edit()` | `admin.users.edit` | 完整更新用户 |
| **PATCH** | `/admin/users/{id}` | `store()` | `admin.users.store` | 部分更新用户 |
| **DELETE** | `/admin/users/{id}` | `del()` | `admin.users.delete` | 删除单个用户 |
| **DELETE** | `/admin/users` | `delMany()` | `admin.users.deleteMany` | 批量删除用户 |

### Resource 注解参数

```php
#[Resource(
    app: 'admin',                              // 路由应用名
    route: '/admin/users',                     // 资源路由前缀
    name: 'users',                            // 资源名称
    actions: ['list', 'show', 'create'],     // 可选：限制启用的操作
    middleware: [CustomMiddleware::class],    // 可选：自定义中间件
    softDelete: true                          // 可选：启用软删除功能
)]
class UserController extends Resources { }
```

**参数详解：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| **app** | `string` | ✅ | 路由应用名，必须已在应用中注册 |
| **route** | `string` | ✅ | 资源路由前缀，所有操作都会基于此路径 |
| **name** | `string` | ✅ | 资源名称，用于生成路由名和权限标识 |
| **actions** | `array\|false` | ❌ | 启用的操作列表，默认全部，`false` 禁用所有 |
| **middleware** | `array` | ❌ | 自定义中间件列表 |
| **softDelete** | `bool` | ❌ | 是否启用软删除，默认 `false` |

### 限制操作范围

```php
// 只读资源：只允许查询操作
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

// 禁用所有默认操作，只使用自定义操作
#[Resource(
    app: 'api',
    route: '/api/tools',
    name: 'tools',
    actions: false  // 禁用所有默认操作
)]
class ToolController extends Resources
{
    // 不生成任何默认路由，只能使用 Action 注解自定义操作
}
```

### 软删除支持

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

启用软删除后会额外生成：

| HTTP方法 | 路由路径 | 控制器方法 | 路由名称 | 功能描述 |
|----------|----------|-----------|----------|----------|
| **DELETE** | `/admin/posts/{id}/trash` | `trash()` | `admin.posts.trash` | 彻底删除 |
| **PUT** | `/admin/posts/{id}/restore` | `restore()` | `admin.posts.restore` | 恢复删除 |

## Action 注解详解

### 基础用法

使用 `#[Action]` 注解为资源控制器添加自定义操作：

```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    protected string $model = User::class;

    #[Action(
        methods: ['GET'],           // 必需：HTTP方法
        route: '/export',          // 必需：操作路径
        name: 'export',           // 可选：操作名称
        auth: true,               // 可选：是否需要认证
        can: true                 // 可选：是否需要权限检查
    )]
    public function export(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 导出用户数据
        // 完整路径：/admin/users/export
        // 路由名称：admin.users.export
        // 权限检查：admin.users.export

        $users = User::all();
        $csvData = $this->generateCsv($users);

        return send($response, '导出成功', [
            'download_url' => '/downloads/users.csv'
        ]);
    }

    #[Action(['POST'], '/import', name: 'import')]
    public function import(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 导入用户数据
        // 完整路径：/admin/users/import
        // 路由名称：admin.users.import

        $uploadedFile = $request->getUploadedFiles()['file'];
        $result = $this->processImport($uploadedFile);

        return send($response, '导入成功', $result);
    }

    #[Action(['POST'], '/reset-password/{id}', name: 'resetPassword')]
    public function resetPassword(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 重置用户密码
        // 完整路径：/admin/users/reset-password/{id}
        // 路由名称：admin.users.resetPassword

        $userId = $args['id'];
        $user = User::find($userId);

        if (!$user) {
            throw new ExceptionBusiness('用户不存在', 404);
        }

        $newPassword = $this->generateRandomPassword();
        $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
        $user->save();

        return send($response, '密码重置成功', [
            'new_password' => $newPassword
        ]);
    }
}
```

### Action 注解参数

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| **methods** | `array\|string` | ✅ | HTTP方法，如 `['GET']` 或 `'POST'` |
| **route** | `string` | ✅ | 操作路径，相对于资源路径 |
| **name** | `string` | ❌ | 操作名称，默认使用方法名 |
| **auth** | `bool` | ❌ | 是否需要认证，默认 `true` |
| **can** | `bool` | ❌ | 是否需要权限检查，默认 `true` |

### 高级 Action 示例

```php
#[Resource(app: 'api', route: '/api/products', name: 'products')]
class ProductController extends Resources
{
    protected string $model = Product::class;

    // 支持多种 HTTP 方法
    #[Action(['GET', 'POST'], '/search', name: 'search')]
    public function search(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $method = $request->getMethod();

        if ($method === 'GET') {
            // 显示搜索表单
            return send($response, '搜索表单', [
                'form_fields' => $this->getSearchFields()
            ]);
        } else {
            // 处理搜索请求
            $criteria = $request->getParsedBody();
            $results = $this->performSearch($criteria);
            return send($response, '搜索结果', $results);
        }
    }

    // 无需认证的公开操作
    #[Action(['GET'], '/categories', name: 'categories', auth: false)]
    public function getCategories(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 获取产品分类（公开接口）
        $categories = Category::where('status', 1)->get();
        return send($response, '分类列表', $categories->toArray());
    }

    // 需要认证但无需权限检查
    #[Action(['POST'], '/favorite/{id}', name: 'favorite', can: false)]
    public function favorite(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 用户收藏产品（只需登录，无需特殊权限）
        $productId = $args['id'];
        $auth = $request->getAttribute('auth');
        $userId = $auth['id'];

        UserFavorite::updateOrCreate([
            'user_id' => $userId,
            'product_id' => $productId
        ]);

        return send($response, '收藏成功');
    }

    // 带参数的复杂路径
    #[Action(['PUT'], '/category/{categoryId}/products/{id}/status', name: 'updateStatus')]
    public function updateProductStatus(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 更新特定分类下产品的状态
        // 路径：/api/products/category/{categoryId}/products/{id}/status

        $categoryId = $args['categoryId'];
        $productId = $args['id'];
        $data = $request->getParsedBody();

        $product = Product::where('id', $productId)
            ->where('category_id', $categoryId)
            ->firstOrFail();

        $product->status = $data['status'];
        $product->save();

        return send($response, '状态更新成功', $product->toArray());
    }
}
```

## 数据验证和格式化

### 验证规则定义

```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    protected string $model = User::class;

    /**
     * 数据验证规则
     */
    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        $action = $request->getAttribute('action', '');

        $rules = [
            'username' => [
                ['required', '用户名不能为空'],
                ['lengthMin', 3, '用户名至少3个字符'],
                ['lengthMax', 20, '用户名不能超过20个字符'],
                ['regex', '/^[a-zA-Z0-9_]+$/', '用户名只能包含字母、数字和下划线']
            ],
            'email' => [
                ['required', '邮箱不能为空'],
                ['email', '邮箱格式无效']
            ]
        ];

        // 创建时需要密码
        if ($action === 'create') {
            $rules['password'] = [
                ['required', '密码不能为空'],
                ['lengthMin', 8, '密码至少8个字符'],
                ['regex', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', '密码必须包含大小写字母和数字']
            ];
        }

        // 更新时密码可选
        if ($action === 'edit' && !empty($data['password'])) {
            $rules['password'] = [
                ['lengthMin', 8, '密码至少8个字符'],
                ['regex', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', '密码必须包含大小写字母和数字']
            ];
        }

        return $rules;
    }

    /**
     * 数据格式化
     */
    public function format(Data $data, ServerRequestInterface $request, array $args): array
    {
        $formatted = [
            'username' => $data->username,
            'email' => strtolower($data->email),
            'status' => (int) ($data->status ?? 1),
        ];

        // 密码加密
        if (isset($data->password)) {
            $formatted['password'] = password_hash($data->password, PASSWORD_DEFAULT);
        }

        // 处理个人资料JSON字段
        if (isset($data->profile)) {
            $formatted['profile'] = json_encode($data->profile);
        }

        return $formatted;
    }
}
```

### 数据转换

```php
class UserController extends Resources
{
    /**
     * 数据转换 - 输出格式化
     */
    public function transform(object $item): array
    {
        return [
            'id' => $item->id,
            'username' => $item->username,
            'email' => $item->email,
            'phone' => $item->phone,
            'status' => $item->status,
            'status_text' => $this->getStatusText($item->status),
            'avatar' => $item->avatar ? url('/storage/' . $item->avatar) : null,
            'profile' => $item->profile ? json_decode($item->profile, true) : null,
            'role' => $item->role->name ?? null,
            'permissions' => $item->permissions->pluck('name')->toArray(),
            'last_login_at' => $item->last_login_at?->format('Y-m-d H:i:s'),
            'created_at' => $item->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    private function getStatusText(int $status): string
    {
        return match($status) {
            0 => '禁用',
            1 => '正常',
            2 => '待审核',
            default => '未知'
        };
    }
}
```

## 查询和过滤

### 查询定制

```php
class UserController extends Resources
{
    /**
     * 通用查询条件（适用于所有操作）
     */
    public function query(Builder $query): void
    {
        $query->where('deleted_at', null);
    }

    /**
     * 列表查询定制
     */
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $params = $request->getQueryParams();

        // 搜索功能
        if (!empty($params['keyword'])) {
            $query->where(function($q) use ($params) {
                $q->where('username', 'like', "%{$params['keyword']}%")
                  ->orWhere('email', 'like', "%{$params['keyword']}%");
            });
        }

        // 状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        // 时间范围筛选
        if (!empty($params['start_date'])) {
            $query->where('created_at', '>=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $query->where('created_at', '<=', $params['end_date']);
        }

        // 角色筛选
        if (!empty($params['role'])) {
            $query->whereHas('roles', function($q) use ($params) {
                $q->where('name', $params['role']);
            });
        }
    }

    /**
     * 单条查询定制
     */
    public function queryOne(Builder $query, ServerRequestInterface $request, array $args): void
    {
        // 预加载关联数据
        $query->with(['profile', 'roles.permissions']);
    }
}
```

### 分页配置

```php
class UserController extends Resources
{
    // 分页设置
    protected array $pagination = [
        'status' => true,        // 是否启用分页
        'pageSize' => 20,       // 默认每页数量
    ];

    // 多条数据字段控制
    public array $includesMany = ['id', 'username', 'email', 'status', 'created_at'];
    public array $excludesMany = ['password', 'remember_token'];

    // 单条数据字段控制
    public array $includesOne = ['id', 'username', 'email', 'phone', 'status', 'profile'];
    public array $excludesOne = ['password', 'remember_token'];
}
```

## 事件钩子系统

### 操作钩子

```php
class UserController extends Resources
{
    /**
     * 创建前钩子
     */
    public function createBefore(Data $data, mixed $model): void
    {
        // 设置创建者
        $auth = App::auth();
        $model->created_by = $auth['id'];

        // 业务逻辑检查
        if ($this->emailExists($data->email)) {
            throw new ExceptionBusiness('邮箱已被使用');
        }
    }

    /**
     * 创建后钩子
     */
    public function createAfter(Data $data, mixed $model): void
    {
        // 创建用户配置文件
        $model->profile()->create([
            'nickname' => $data->username,
            'avatar' => 'default.png'
        ]);

        // 分配默认角色
        $defaultRole = Role::where('name', 'user')->first();
        if ($defaultRole) {
            $model->roles()->attach($defaultRole->id);
        }

        // 发送欢迎邮件
        $this->sendWelcomeEmail($model);
    }

    /**
     * 编辑前钩子
     */
    public function editBefore(Data $data, mixed $model): void
    {
        // 权限检查
        $auth = App::auth();
        if ($model->id !== $auth['id'] && !$this->isAdmin($auth)) {
            throw new ExceptionBusiness('无权限编辑此用户', 403);
        }

        // 邮箱唯一性检查（排除自己）
        if (isset($data->email)) {
            $exists = User::where('email', $data->email)
                ->where('id', '!=', $model->id)
                ->exists();
            if ($exists) {
                throw new ExceptionBusiness('邮箱已被其他用户使用');
            }
        }
    }

    /**
     * 编辑后钩子
     */
    public function editAfter(Data $data, mixed $model): void
    {
        // 记录操作日志
        OperationLog::create([
            'user_id' => App::auth()['id'],
            'action' => 'edit_user',
            'target_id' => $model->id,
            'changes' => $model->getChanges(),
            'created_at' => now()
        ]);

        // 清除相关缓存
        Cache::tags(['users'])->flush();
    }

    /**
     * 删除前钩子
     */
    public function delBefore(mixed $model): void
    {
        // 检查是否可以删除
        if ($model->posts()->exists()) {
            throw new ExceptionBusiness('该用户还有关联文章，无法删除');
        }

        if ($model->hasRole('admin')) {
            throw new ExceptionBusiness('不能删除管理员用户');
        }
    }

    /**
     * 删除后钩子
     */
    public function delAfter(mixed $model): void
    {
        // 清理用户相关数据
        $model->profile()->delete();
        $model->sessions()->delete();

        // 删除用户文件
        if ($model->avatar) {
            Storage::delete($model->avatar);
        }
    }
}
```

### 软删除钩子

```php
class PostController extends Resources
{
    /**
     * 彻底删除前钩子
     */
    public function trashBefore(mixed $model): void
    {
        // 检查是否可以彻底删除
        if ($model->comments()->exists()) {
            throw new ExceptionBusiness('文章还有评论，无法彻底删除');
        }
    }

    /**
     * 彻底删除后钩子
     */
    public function trashAfter(mixed $model): void
    {
        // 删除关联文件
        if ($model->featured_image) {
            Storage::delete($model->featured_image);
        }

        // 删除所有附件
        foreach ($model->attachments as $attachment) {
            Storage::delete($attachment->file_path);
            $attachment->delete();
        }
    }

    /**
     * 恢复前钩子
     */
    public function restoreBefore(mixed $model): void
    {
        // 检查分类是否仍然存在
        if (!Category::find($model->category_id)) {
            throw new ExceptionBusiness('文章分类已被删除，无法恢复');
        }
    }

    /**
     * 恢复后钩子
     */
    public function restoreAfter(mixed $model): void
    {
        // 恢复相关数据
        $model->comments()->restore();

        // 更新统计
        $this->updateCategoryPostCount($model->category_id);
    }
}
```

## 元数据支持

### 列表元数据

```php
class UserController extends Resources
{
    /**
     * 列表页面元数据
     */
    public function metaMany(object|array $query, array $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('status', 1)->count(),
            'inactive_users' => User::where('status', 0)->count(),
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'filters' => [
                'status_options' => [
                    ['value' => '', 'label' => '全部状态'],
                    ['value' => 1, 'label' => '正常'],
                    ['value' => 0, 'label' => '禁用'],
                    ['value' => 2, 'label' => '待审核']
                ],
                'role_options' => Role::select('id as value', 'name as label')->get()->toArray()
            ],
            'permissions' => [
                'can_create' => $this->canCreate(),
                'can_bulk_delete' => $this->canBulkDelete(),
                'can_export' => $this->canExport()
            ]
        ];
    }
}
```

### 详情元数据

```php
class UserController extends Resources
{
    /**
     * 详情页面元数据
     */
    public function metaOne(mixed $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'permissions' => $this->getUserPermissions($data),
            'statistics' => [
                'posts_count' => $data->posts()->count(),
                'comments_count' => $data->comments()->count(),
                'login_count' => $data->login_logs()->count(),
                'last_login' => $data->last_login_at?->format('Y-m-d H:i:s')
            ],
            'related_data' => [
                'roles' => $data->roles->map(function($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name
                    ];
                }),
                'recent_posts' => $data->posts()
                    ->latest()
                    ->limit(5)
                    ->select('id', 'title', 'created_at')
                    ->get()
            ],
            'actions' => [
                'can_edit' => $this->canEdit($data),
                'can_delete' => $this->canDelete($data),
                'can_reset_password' => $this->canResetPassword($data)
            ]
        ];
    }
}
```

## 权限控制集成

### 自动权限检查

资源控制器会自动集成权限检查：

```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    // 自动权限检查 admin.users.*
}
```

### 跳过权限检查

```php
class UserController extends Resources
{
    #[Action(['GET'], '/public-info', name: 'publicInfo', can: false)]
    public function getPublicInfo(): ResponseInterface
    {
        // 跳过权限检查的公开接口
        return send($response, '公开信息', [
            'total_users' => User::count(),
            'online_users' => $this->getOnlineUserCount()
        ]);
    }
}
```

## 完整示例

### 电商产品管理

```php
use Core\Resources\Action\Resources;
use Core\Resources\Attribute\Resource;
use Core\Resources\Attribute\Action;

#[Resource(
    app: 'admin',
    route: '/admin/products',
    name: 'products',
    softDelete: true,
    middleware: [AdminMiddleware::class]
)]
class ProductController extends Resources
{
    protected string $model = Product::class;
    protected string $label = '产品管理';

    // 分页配置
    protected array $pagination = [
        'status' => true,
        'pageSize' => 15,
    ];

    // 字段控制
    public array $includesMany = ['id', 'name', 'price', 'stock', 'status', 'category_id', 'created_at'];
    public array $excludesMany = ['description', 'content'];
    public array $includesOne = ['id', 'name', 'price', 'stock', 'status', 'category_id', 'description', 'content', 'images'];

    /**
     * 验证规则
     */
    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'name' => [
                ['required', '产品名称不能为空'],
                ['lengthMin', 2, '产品名称至少2个字符'],
                ['lengthMax', 100, '产品名称不能超过100个字符']
            ],
            'price' => [
                ['required', '价格不能为空'],
                ['numeric', '价格必须是数字'],
                ['min', 0.01, '价格不能小于0.01']
            ],
            'stock' => [
                ['required', '库存不能为空'],
                ['integer', '库存必须是整数'],
                ['min', 0, '库存不能小于0']
            ],
            'category_id' => [
                ['required', '分类不能为空'],
                ['integer', '分类ID必须是整数']
            ],
            'status' => [
                ['required', '状态不能为空'],
                ['in', [0, 1], '状态值无效']
            ]
        ];
    }

    /**
     * 数据格式化
     */
    public function format(Data $data, ServerRequestInterface $request, array $args): array
    {
        $formatted = [
            'name' => $data->name,
            'price' => bc_format($data->price, 2),
            'stock' => (int) $data->stock,
            'category_id' => (int) $data->category_id,
            'status' => (int) $data->status,
        ];

        if (isset($data->description)) {
            $formatted['description'] = $data->description;
        }

        if (isset($data->content)) {
            $formatted['content'] = $data->content;
        }

        if (isset($data->images)) {
            $formatted['images'] = json_encode($data->images);
        }

        return $formatted;
    }

    /**
     * 数据转换
     */
    public function transform(object $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'price' => $item->price,
            'price_formatted' => '¥' . number_format($item->price, 2),
            'stock' => $item->stock,
            'stock_status' => $this->getStockStatus($item->stock),
            'status' => $item->status,
            'status_text' => $item->status ? '上架' : '下架',
            'category' => [
                'id' => $item->category->id,
                'name' => $item->category->name
            ],
            'images' => $item->images ? json_decode($item->images, true) : [],
            'sales_count' => $item->order_items()->sum('quantity'),
            'created_at' => $item->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 列表查询
     */
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $params = $request->getQueryParams();

        // 预加载关联
        $query->with(['category', 'tags']);

        // 搜索
        if (!empty($params['keyword'])) {
            $query->where('name', 'like', "%{$params['keyword']}%");
        }

        // 分类筛选
        if (!empty($params['category_id'])) {
            $query->where('category_id', $params['category_id']);
        }

        // 状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        // 价格范围
        if (!empty($params['min_price'])) {
            $query->where('price', '>=', $params['min_price']);
        }
        if (!empty($params['max_price'])) {
            $query->where('price', '<=', $params['max_price']);
        }

        // 库存筛选
        if (!empty($params['low_stock'])) {
            $query->where('stock', '<', 10);
        }
    }

    /**
     * 详情查询
     */
    public function queryOne(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $query->with(['category', 'order_items.order']);
    }

    /**
     * 创建前钩子
     */
    public function createBefore(Data $data, mixed $model): void
    {
        // 检查分类是否存在
        if (!Category::find($data->category_id)) {
            throw new ExceptionBusiness('产品分类不存在');
        }

        // 设置创建者
        $auth = App::auth();
        $model->created_by = $auth['id'];
    }

    /**
     * 创建后钩子
     */
    public function createAfter(Data $data, mixed $model): void
    {
        // 更新分类产品数量
        $this->updateCategoryProductCount($model->category_id);

        // 记录操作日志
        $this->logOperation('create', $model);
    }

    /**
     * 删除前钩子
     */
    public function delBefore(mixed $model): void
    {
        // 检查是否有订单
        if ($model->order_items()->exists()) {
            throw new ExceptionBusiness('该产品已有订单，无法删除');
        }
    }

    /**
     * 列表元数据
     */
    public function metaMany(object|array $query, array $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'statistics' => [
                'total_products' => Product::count(),
                'active_products' => Product::where('status', 1)->count(),
                'low_stock_products' => Product::where('stock', '<', 10)->count(),
                'total_value' => Product::sum('price')
            ],
            'filters' => [
                'categories' => Category::select('id as value', 'name as label')->get()->toArray(),
                'status_options' => [
                    ['value' => '', 'label' => '全部状态'],
                    ['value' => 1, 'label' => '上架'],
                    ['value' => 0, 'label' => '下架']
                ]
            ]
        ];
    }

    // 自定义操作：批量上架
    #[Action(['POST'], '/batch-publish', name: 'batchPublish')]
    public function batchPublish(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = $request->getParsedBody();
        $ids = $data['ids'] ?? [];

        if (empty($ids)) {
            throw new ExceptionBusiness('请选择要上架的产品');
        }

        $count = Product::whereIn('id', $ids)->update(['status' => 1]);

        return send($response, '批量上架成功', [
            'updated_count' => $count
        ]);
    }

    // 自定义操作：库存预警
    #[Action(['GET'], '/stock-alert', name: 'stockAlert')]
    public function stockAlert(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $lowStockProducts = Product::where('stock', '<', 10)
            ->with('category')
            ->get()
            ->map(function($product) {
                return $this->transform($product);
            });

        return send($response, '库存预警', [
            'low_stock_count' => $lowStockProducts->count(),
            'products' => $lowStockProducts
        ]);
    }

    // 辅助方法
    private function getStockStatus(int $stock): string
    {
        if ($stock <= 0) return '缺货';
        if ($stock < 10) return '库存不足';
        return '正常';
    }

    private function updateCategoryProductCount(int $categoryId): void
    {
        $count = Product::where('category_id', $categoryId)->count();
        Category::where('id', $categoryId)->update(['product_count' => $count]);
    }

    private function logOperation(string $action, mixed $model): void
    {
        OperationLog::create([
            'user_id' => App::auth()['id'],
            'action' => "product_{$action}",
            'target_type' => Product::class,
            'target_id' => $model->id,
            'data' => $model->toArray(),
            'created_at' => now()
        ]);
    }
}
```

## 最佳实践

### 1. 合理使用钩子

```php
// ✅ 推荐：在钩子中处理业务逻辑
public function createAfter(Data $data, mixed $model): void
{
    // 发送通知
    $this->sendNotification($model);

    // 更新缓存
    Cache::tags(['users'])->flush();

    // 记录日志
    $this->logUserCreated($model);
}

// ❌ 避免：在钩子中进行复杂的HTTP请求
public function createAfter(Data $data, mixed $model): void
{
    // 不要在钩子中进行耗时操作
    Http::post('https://api.example.com/webhook', $model->toArray());
}
```

### 2. 数据验证

```php
// ✅ 推荐：根据操作类型设置不同验证规则
public function validator(array $data, ServerRequestInterface $request, array $args): array
{
    $action = $request->getAttribute('action', '');

    $rules = $this->getBaseRules();

    if ($action === 'create') {
        $rules = array_merge($rules, $this->getCreateRules());
    } elseif ($action === 'edit') {
        $rules = array_merge($rules, $this->getEditRules());
    }

    return $rules;
}
```

### 3. 权限控制

```php
// ✅ 推荐：使用 Resource 注解自动权限检查
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    // 自动权限检查 admin.users.*
}

// ✅ 推荐：自定义操作明确权限设置
#[Action(['POST'], '/export', name: 'export', can: true)]
public function export(): ResponseInterface
{
    // 需要 admin.users.export 权限
}
```

### 4. 数据转换

```php
// ✅ 推荐：在 transform 中处理数据格式
public function transform(object $item): array
{
    return [
        'id' => $item->id,
        'name' => $item->name,
        // 格式化日期
        'created_at' => $item->created_at->format('Y-m-d H:i:s'),
        // 处理关联数据
        'category_name' => $item->category->name ?? '',
        // 计算字段
        'is_active' => $item->status === 1
    ];
}
```

### 5. 查询优化

```php
// ✅ 推荐：预加载关联避免N+1问题
public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
{
    $query->with(['category', 'tags']);
}

// ✅ 推荐：限制查询字段
public array $includesMany = ['id', 'name', 'price', 'status', 'created_at'];
public array $excludesMany = ['description', 'content']; // 排除大字段
```

通过资源控制器，您可以快速构建功能完整、安全可靠的 RESTful API，同时保持代码的简洁和可维护性。资源控制器与路由系统的完美结合，让API开发变得简单而高效。