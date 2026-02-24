# 资源控制器

资源控制器是 DuxLite 的核心特性，通过 `#[Resource]` 注解和 `Resources` 基类，快速实现标准化的 CRUD 操作。

## 应用注册

在使用资源控制器之前，必须先在应用中注册资源：

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
        // 初始化资源
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
    }
}
```

配置文件注册：
```toml
# config/app.toml
registers = [
    "App\\App"
]
```

## 基本概念

### 设计理念

- **标准化 CRUD**：自动提供完整的增删改查操作
- **注解驱动**：使用 `#[Resource]` 注解自动生成功能
- **事件钩子**：支持操作前后的业务扩展
- **数据验证**：集成验证和格式化机制

### 基础用法

```php
use Core\Resources\Attribute\Resource;

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

## 查询功能

### 列表查询

使用 `queryMany()` 方法定制列表查询：

```php
class UserController extends Resources
{
    /**
     * 列表查询定制
     */
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $params = \Core\Utils\RequestParam::query($request);

        // 搜索功能
        if (!empty($params['keyword'])) {
            $query->where(function($q) use ($params) {
                $q->where('name', 'like', "%{$params['keyword']}%")
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
    }
}
```

### 详情查询

使用 `queryOne()` 方法定制详情查询：

```php
class UserController extends Resources
{
    /**
     * 详情查询定制
     */
    public function queryOne(Builder $query, ServerRequestInterface $request, array $args): void
    {
        // 预加载关联数据
        $query->with(['profile', 'roles.permissions']);
    }
}
```

### 通用查询条件

使用 `query()` 方法添加通用条件：

```php
class UserController extends Resources
{
    /**
     * 通用查询条件（适用于所有操作）
     */
    public function query(Builder $query): void
    {
        // 只查询未删除的记录
        $query->where('deleted_at', null);
        
        // 按创建时间倒序
        $query->orderBy('created_at', 'desc');
    }
}
```

### 数据转换

使用 `transform()` 方法格式化输出数据：

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
            'name' => $item->name,
            'email' => $item->email,
            'status' => $item->status,
            'status_text' => $item->status ? '正常' : '禁用',
            'avatar' => $item->avatar ? \Core\App::storage()->publicUrl($item->avatar) : null,
            'created_at' => $item->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
```

## 创建功能

### 数据验证

使用 `validator()` 方法定义验证规则。更多验证规则和使用方法请参考：[数据验证](/reference/data/validation)

```php
class UserController extends Resources
{
    /**
     * 数据验证规则
     */
    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        $action = $this->method;

        $rules = [
            'name' => [
                ['required', '姓名不能为空'],
                ['lengthMin', 2, '姓名至少2个字符'],
                ['lengthMax', 50, '姓名不能超过50个字符']
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
                ['lengthMin', 8, '密码至少8个字符']
            ];
        }

        return $rules;
    }
}
```

### 数据格式化

使用 `format()` 方法格式化入库数据：

```php
class UserController extends Resources
{
    /**
     * 数据格式化
     */
    public function format(Data $data, ServerRequestInterface $request, array $args): array
    {
        $formatted = [
            'name' => $data->name,
            'email' => strtolower($data->email),
            'status' => (int) ($data->status ?? 1),
        ];

        // 密码加密
        if (isset($data->password)) {
            $formatted['password'] = password_hash($data->password, PASSWORD_DEFAULT);
        }

        return $formatted;
    }
}
```

### 创建钩子

使用创建前后钩子处理业务逻辑：

```php
class UserController extends Resources
{
    private array $auth = [];

    public function init(ServerRequestInterface $request, ResponseInterface $response, array $args): void
    {
        // AuthMiddleware 会把 token 解析结果写入 request attribute：auth
        $this->auth = (array) \Core\Utils\Attribute::getRequestParams($request, 'auth') ?: [];
    }

    /**
     * 创建前钩子
     */
    public function createBefore(Data $data, mixed $model): void
    {
        // 检查邮箱唯一性
        if (User::where('email', $data->email)->exists()) {
            throw new \Core\Handlers\ExceptionBusiness('邮箱已存在');
        }

        // 设置创建者
        $model->created_by = $this->auth['id'] ?? 0;
    }

    /**
     * 创建后钩子
     */
    public function createAfter(Data $data, mixed $model): void
    {
        // 创建用户配置文件
        $model->profile()->create([
            'nickname' => $data->name,
            'avatar' => 'default.png'
        ]);

        // 发送欢迎邮件
        $this->sendWelcomeEmail($model);
    }

    private function sendWelcomeEmail($user): void
    {
        // 发送邮件逻辑
    }
}
```

## 修改功能

### 编辑钩子

使用编辑前后钩子处理更新逻辑：

```php
class UserController extends Resources
{
    /**
     * 编辑前钩子
     */
    public function editBefore(Data $data, mixed $model): void
    {
        // 权限检查
        if ($model->id !== ($this->auth['id'] ?? 0) && !$this->isAdmin($this->auth)) {
            throw new \Core\Handlers\ExceptionBusiness('无权限编辑此用户', 403);
        }

        // 邮箱唯一性检查（排除自己）
        if (isset($data->email)) {
            $exists = User::where('email', $data->email)
                ->where('id', '!=', $model->id)
                ->exists();
            if ($exists) {
                throw new \Core\Handlers\ExceptionBusiness('邮箱已被其他用户使用');
            }
        }
    }

    /**
     * 编辑后钩子
     */
    public function editAfter(Data $data, mixed $model): void
    {
        // 记录操作日志
        \Core\App::log()->info('用户信息已更新', [
            'user_id' => $model->id,
            'changes' => $model->getChanges()
        ]);

        // 清除相关缓存
        \Core\App::cache()->delete("user_{$model->id}");
    }

    private function isAdmin(array $auth): bool
    {
        return ($auth['role'] ?? '') === 'admin';
    }
}
```

## 删除功能

### 删除钩子

使用删除前后钩子处理删除逻辑：

```php
class UserController extends Resources
{
    /**
     * 删除前钩子
     */
    public function delBefore(mixed $model): void
    {
        // 检查是否可以删除
        if ($model->posts()->exists()) {
            throw new \Core\Handlers\ExceptionBusiness('该用户还有关联文章，无法删除');
        }

        // 不能删除管理员
        if ($model->hasRole('admin')) {
            throw new \Core\Handlers\ExceptionBusiness('不能删除管理员用户');
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
            \Core\App::storage()->delete($model->avatar);
        }
    }
}
```

## 软删除功能

### 软删除钩子

使用软删除前后钩子处理软删除逻辑：

```php
class UserController extends Resources
{
    /**
     * 软删除前钩子
     */
    public function trashBefore(mixed $model): void
    {
        // 检查是否可以彻底删除
        if ($model->orders()->exists()) {
            throw new \Core\Handlers\ExceptionBusiness('该用户还有关联订单，无法彻底删除');
        }
    }

    /**
     * 软删除后钩子
     */
    public function trashAfter(mixed $model): void
    {
        // 记录彻底删除日志
        \Core\App::log()->info('用户已被彻底删除', [
            'user_id' => $model->id
        ]);
    }

    /**
     * 恢复前钩子
     */
    public function restoreBefore(mixed $model): void
    {
        // 检查是否可以恢复
        if (User::where('email', $model->email)->where('id', '!=', $model->id)->exists()) {
            throw new \Core\Handlers\ExceptionBusiness('邮箱已被其他用户使用，无法恢复');
        }
    }

    /**
     * 恢复后钩子
     */
    public function restoreAfter(mixed $model): void
    {
        // 发送恢复通知
        \Core\App::log()->info('用户已被恢复', [
            'user_id' => $model->id
        ]);
    }
}
```

## 批量删除功能

### 批量删除钩子

使用批量删除前后钩子处理批量删除逻辑：

```php
class UserController extends Resources
{
    /**
     * 批量删除前钩子
     */
    public function delManyBefore(array $models): void
    {
        // 检查是否可以批量删除
        foreach ($models as $model) {
            if ($model->posts()->exists()) {
                throw new \Core\Handlers\ExceptionBusiness("用户 {$model->name} 还有关联文章，无法删除");
            }
        }
    }

    /**
     * 批量删除后钩子
     */
    public function delManyAfter(array $models): void
    {
        // 记录批量删除日志
        $userIds = array_map(fn($model) => $model->id, $models);
        \Core\App::log()->info('用户批量删除成功', [
            'user_ids' => $userIds
        ]);
    }
}
```

## 扩展方法

### 自定义操作

使用 `#[Action]` 注解添加自定义操作：

```php
use Core\Resources\Attribute\Action;

class UserController extends Resources
{
    /**
     * 导出用户数据
     */
    #[Action(methods: 'GET', route: '/export', name: 'export')]
    public function export(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $users = User::all();
        $csvData = $this->generateCsv($users);

        return send($response, '导出成功', [
            'download_url' => '/downloads/users.csv'
        ]);
    }

    /**
     * 批量激活用户
     */
    #[Action(methods: 'PUT', route: '/batch-activate', name: 'batchActivate')]
    public function batchActivate(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = \Core\Utils\RequestParam::body($request);
        $userIds = $data['user_ids'] ?? [];

        if (empty($userIds)) {
            throw new \Core\Handlers\ExceptionBusiness('请选择要激活的用户');
        }

        $count = User::whereIn('id', $userIds)->update(['status' => 1]);

        return send($response, '批量激活成功', [
            'affected_count' => $count
        ]);
    }

    /**
     * 修改用户密码
     */
    #[Action(methods: 'PUT', route: '/{id}/password', name: 'changePassword')]
    public function changePassword(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $userId = (int) $args['id'];
        $data = \Core\Utils\RequestParam::body($request);

        $user = User::find($userId);
        if (!$user) {
            throw new \Core\Handlers\ExceptionNotFound('用户不存在');
        }

        // 验证当前密码
        if (!password_verify($data['current_password'], $user->password)) {
            throw new \Core\Handlers\ExceptionBusiness('当前密码错误');
        }

        // 更新密码
        $user->update([
            'password' => password_hash($data['new_password'], PASSWORD_DEFAULT)
        ]);

        return send($response, '密码修改成功');
    }

    private function generateCsv($users): string
    {
        // CSV 生成逻辑
        return 'csv_content';
    }
}
```

### 权限控制

在自定义操作中控制权限：

```php
class UserController extends Resources
{
    // 需要权限检查
    #[Action(methods: 'GET', route: '/statistics', name: 'statistics', can: true)]
    public function getStatistics(...): ResponseInterface
    {
        // 需要 admin.users.statistics 权限
        return send($response, '统计数据', $stats);
    }

    // 跳过权限检查
    #[Action(methods: 'GET', route: '/public-info', name: 'publicInfo', can: false)]
    public function getPublicInfo(...): ResponseInterface
    {
        // 不需要权限，但仍需要认证
        return send($response, '公开信息', $info);
    }

    // 跳过认证和权限
    #[Action(methods: 'GET', route: '/status', name: 'status', auth: false, can: false)]
    public function getStatus(...): ResponseInterface
    {
        // 完全公开的接口
        return send($response, '服务状态正常');
    }
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

    /**
     * 验证规则
     */
    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'name' => [
                ['required', '产品名称不能为空'],
                ['lengthMin', 2, '产品名称至少2个字符']
            ],
            'price' => [
                ['required', '价格不能为空'],
                ['numeric', '价格必须是数字'],
                ['min', 0.01, '价格不能小于0.01']
            ],
            'category_id' => [
                ['required', '分类不能为空'],
                ['integer', '分类ID必须是整数']
            ]
        ];
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
            'status' => $item->status,
            'status_text' => $item->status ? '上架' : '下架',
            'category' => [
                'id' => $item->category->id,
                'name' => $item->category->name
            ],
            'created_at' => $item->created_at->format('Y-m-d H:i:s')
        ];
    }

    /**
     * 列表查询
     */
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $params = \Core\Utils\RequestParam::query($request);

        // 分类筛选
        if (!empty($params['category_id'])) {
            $query->where('category_id', $params['category_id']);
        }

        // 状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        // 预加载分类
        $query->with('category');
    }

    /**
     * 创建后处理
     */
    public function createAfter(Data $data, mixed $model): void
    {
        // 清除分类缓存
        \Core\App::cache()->delete("category_{$model->category_id}_products");
    }

    /**
     * 批量上架
     */
    #[Action(methods: 'POST', route: '/batch-publish', name: 'batchPublish')]
    public function batchPublish(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = \Core\Utils\RequestParam::body($request);
        $ids = $data['ids'] ?? [];

        if (empty($ids)) {
            throw new \Core\Handlers\ExceptionBusiness('请选择要上架的产品');
        }

        $count = Product::whereIn('id', $ids)->update(['status' => 1]);

        return send($response, '批量上架成功', [
            'updated_count' => $count
        ]);
    }
}
```

通过资源控制器，可以快速构建功能完整的 CRUD API，同时通过钩子方法和自定义操作实现复杂的业务逻辑。
