# CRUD 开发

基于资源控制器的快速 CRUD 开发，适用于标准的增删改查操作，大幅减少重复代码。

## 基础资源控制器

### 最简实现

```php
// app/Api/UserController.php
use Core\Attribute\Resource;
use Core\Controller\Resources;

#[Resource(app: 'api', route: '/users')]
class UserController extends Resources
{
    protected string $model = User::class;
    
    // 自动提供以下接口：
    // GET    /users        list()    列表
    // POST   /users        create()  创建  
    // GET    /users/{id}   show()    详情
    // PUT    /users/{id}   store()   更新
    // DELETE /users/{id}   delete()  删除
}
```

### 带中间件的资源控制器

```php
use Core\Auth\AuthMiddleware;
use Core\Permission\PermissionMiddleware;

#[Resource(
    app: 'api', 
    route: '/users', 
    middleware: [
        AuthMiddleware::class,
        PermissionMiddleware::class
    ]
)]
class UserController extends Resources
{
    protected string $model = User::class;
    
    // 中间件会自动验证用户登录和权限
    // 无需在方法中手动检查
}
```

## 数据模型

### 模型数据转换

所有模型必须实现 `transform()` 方法：

```php
// app/Models/User.php
class User extends Model
{
    protected $table = 'users';
    
    public function transform(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'status_text' => $this->status ? '启用' : '禁用',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### 关联数据处理

```php
// 文章模型
class Article extends Model
{
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
    
    public function transform(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'status' => $this->status,
            'category' => $this->category?->transform(),
            'tags' => $this->tags->map(fn($tag) => $tag->transform())->toArray(),
            'created_at' => $this->created_at,
        ];
    }
}
```

## 自定义资源方法

### 重写标准方法

```php
#[Resource(app: 'api', route: '/users')]
class UserController extends Resources
{
    protected string $model = User::class;
    
    // 重写列表方法
    #[Action(methods: 'GET', route: '')]
    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $params = $request->getQueryParams();
        
        $query = User::query();
        
        // 状态筛选
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }
        
        // 关键词搜索
        if (isset($params['keyword'])) {
            $query->where(function($q) use ($params) {
                $q->where('name', 'like', "%{$params['keyword']}%")
                  ->orWhere('email', 'like', "%{$params['keyword']}%");
            });
        }
        
        $list = $query->paginate();
        ["data" => $data, "meta" => $meta] = format_data($list, function ($item) {
            return $item->transform();
        });
        
        return send($response, 'ok', $data, $meta);
    }
}
```

### 添加自定义方法

```php
#[Resource(app: 'api', route: '/users')]
class UserController extends Resources
{
    protected string $model = User::class;
    
    // 批量操作
    #[Action(methods: 'POST', route: '/batch')]
    public function batch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();
        $action = $data['action'] ?? '';
        $ids = $data['ids'] ?? [];
        
        if (empty($ids)) {
            throw new ExceptionBusiness('请选择要操作的数据');
        }
        
        switch ($action) {
            case 'delete':
                User::whereIn('id', $ids)->delete();
                break;
            case 'enable':
                User::whereIn('id', $ids)->update(['status' => 1]);
                break;
            case 'disable':
                User::whereIn('id', $ids)->update(['status' => 0]);
                break;
            default:
                throw new ExceptionBusiness('不支持的操作');
        }
        
        return send($response, 'ok', '操作成功');
    }
    
    // 用户统计
    #[Action(methods: 'GET', route: '/stats')]
    public function stats(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = [
            'total' => User::count(),
            'active' => User::where('status', 1)->count(),
            'inactive' => User::where('status', 0)->count(),
            'today' => User::whereDate('created_at', today())->count(),
        ];
        
        return send($response, 'ok', $data);
    }
}
```

## 数据验证

### 重写验证方法

```php
#[Resource(app: 'api', route: '/users')]
class UserController extends Resources
{
    protected string $model = User::class;
    
    // 重写验证规则
    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'name' => [['required', '用户名不能为空'], ['lengthMax', 50, '用户名过长']],
            'email' => [['required', '邮箱不能为空'], ['email', '邮箱格式不正确']],
            'phone' => [['required', '手机号不能为空'], ['regex', '/^1[3-9]\d{9}$/', '手机号格式不正确']],
        ];
    }
}
```

### 条件验证

```php
public function validator(array $data, ServerRequestInterface $request, array $args): array
{
    $rules = [
        'name' => [['required', '用户名不能为空'], ['lengthMax', 50, '用户名过长']],
        'email' => [['required', '邮箱不能为空'], ['email', '邮箱格式不正确']],
    ];
    
    // 创建时需要密码
    if ($request->getMethod() === 'POST') {
        $rules['password'] = [['required', '密码不能为空'], ['lengthMin', 6, '密码至少6位']];
    }
    
    // 更新时密码可选
    if ($request->getMethod() === 'PUT' && !empty($data['password'])) {
        $rules['password'] = [['lengthMin', 6, '密码至少6位']];
    }
    
    return $rules;
}
```

## 数据格式化

### 数据入库格式化

```php
public function format(\Core\Validator\Data $data, ServerRequestInterface $request, array $args): array
{
    $result = $data->toArray();
    
    // 密码加密
    if (!empty($result['password'])) {
        $result['password'] = password_hash($result['password'], PASSWORD_DEFAULT);
    }
    
    // 处理状态
    $result['status'] = $result['status'] ?? 1;
    
    return $result;
}
```

### 查询条件定制

```php
public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
{
    $params = $request->getQueryParams();
    
    // 状态筛选
    if (isset($params['status'])) {
        $query->where('status', $params['status']);
    }
    
    // 关键词搜索
    if (isset($params['keyword'])) {
        $query->where(function($q) use ($params) {
            $q->where('name', 'like', "%{$params['keyword']}%")
              ->orWhere('email', 'like', "%{$params['keyword']}%");
        });
    }
    
    // 日期范围
    if (isset($params['date_start']) && isset($params['date_end'])) {
        $query->whereBetween('created_at', [$params['date_start'], $params['date_end']]);
    }
}

public function queryOne(Builder $query, ServerRequestInterface $request, array $args): void
{
    // 预加载关联数据
    $query->with(['profile', 'roles']);
}
```

## 钩子方法

### 数据保存钩子

```php
public function createBefore(\Core\Validator\Data $data, mixed $model): void
{
    // 创建前处理
    $model->uuid = \Str::uuid();
}

public function createAfter(\Core\Validator\Data $data, mixed $model): void
{
    // 创建后处理
    \Core\App::log()->info('用户创建', ['user_id' => $model->id]);
}

public function storeBefore(\Core\Validator\Data $data, mixed $model): void
{
    // 更新前处理
    $model->updated_by = auth()->id();
}

public function storeAfter(\Core\Validator\Data $data, mixed $model): void
{
    // 更新后处理
    \Core\App::cache()->forget("user.{$model->id}");
}

public function delBefore(mixed $model): void
{
    // 删除前处理
    if ($model->status === 1) {
        throw new ExceptionBusiness('启用状态的用户不能删除');
    }
}

public function delAfter(mixed $model): void
{
    // 删除后处理
    \Core\App::cache()->forget("user.{$model->id}");
}
```

## 数据筛选

### 字段筛选

```php
#[Resource(app: 'api', route: '/users')]
class UserController extends Resources
{
    protected string $model = User::class;
    
    // 列表数据包含字段
    public array $includesMany = ['id', 'name', 'email', 'status', 'created_at'];
    
    // 列表数据排除字段
    public array $excludesMany = ['password', 'remember_token'];
    
    // 详情数据包含字段
    public array $includesOne = ['id', 'name', 'email', 'phone', 'status', 'profile'];
    
    // 详情数据排除字段
    public array $excludesOne = ['password', 'remember_token'];
}
```

## 复杂关联处理

### 关联数据的 CRUD

```php
#[Resource(app: 'api', route: '/articles')]
class ArticleController extends Resources
{
    protected string $model = Article::class;
    
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        // 预加载关联数据
        $query->with(['category', 'tags']);
    }
    
    public function createAfter(\Core\Validator\Data $data, mixed $model): void
    {
        // 关联标签
        if (!empty($data->tag_ids)) {
            $model->tags()->sync($data->tag_ids);
        }
    }
    
    public function storeAfter(\Core\Validator\Data $data, mixed $model): void
    {
        // 更新标签关联
        if (isset($data->tag_ids)) {
            $model->tags()->sync($data->tag_ids);
        }
    }
}
```

## 软删除支持

### 启用软删除

```php
#[Resource(app: 'api', route: '/users', softDelete: true)]
class UserController extends Resources
{
    protected string $model = User::class;
    
    // 自动支持软删除相关接口：
    // GET    /users/trash     - 回收站列表  
    // PUT    /users/{id}/restore - 恢复数据
    // DELETE /users/{id}/force   - 永久删除
}
```

### 模型软删除

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;
    
    protected $dates = ['deleted_at'];
}
```

资源控制器提供了快速开发 CRUD 功能的完整解决方案，通过合理使用钩子方法和数据处理方法，可以满足大部分标准业务需求。