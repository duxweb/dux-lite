# Eloquent ORM

DuxLite 集成了 **Laravel 的 Eloquent ORM**，提供强大而直观的数据库操作接口。Eloquent 是一个优雅的 ActiveRecord 实现，让您可以愉快地与数据库进行交互。

## 核心概念

### 模型基础

DuxLite 的模型继承自 `Core\Database\Model`，自动处理数据库连接和事件系统：

```php
<?php
namespace App\Models;

use Core\Database\Model;

class User extends Model
{
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password'];
    protected $tableComment = '用户表';
}
```

### 数据库连接

模型自动使用默认数据库连接，构造函数中会自动初始化：

```php
public function __construct(array $attributes = [])
{
    App::db()->getConnection();
    $this->setConnection('default');
    parent::__construct($attributes);
}
```

## 自动迁移系统

### AutoMigrate 注解

使用 `#[AutoMigrate]` 注解实现数据表的自动创建和更新：

```php
<?php
namespace App\Models;

use Core\Database\Model;
use Core\Database\Attribute\AutoMigrate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Connection;

#[AutoMigrate]
class User extends Model
{
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'status'];
    protected $hidden = ['password'];
    protected $tableComment = '用户数据表';

    /**
     * 定义数据表结构
     */
    public function migration(Blueprint $table): void
    {
        $table->id();
        $table->string('name')->comment('用户名');
        $table->string('email')->unique()->comment('邮箱地址');
        $table->timestamp('email_verified_at')->nullable()->comment('邮箱验证时间');
        $table->string('password')->comment('密码');
        $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
        $table->timestamps();
    }

    /**
     * 迁移后执行（可选）
     */
    public function migrationAfter(Connection $db): void
    {
        // 创建索引、触发器等
    }

    /**
     * 数据填充（可选）
     */
    public function seed(Connection $db): void
    {
        // 插入初始数据
        $this->create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => password_hash('123456', PASSWORD_DEFAULT),
            'status' => 1
        ]);
    }
}
```

### 迁移命令

```bash
# 同步所有模型的数据表
php dux db:sync

# 同步指定应用的模型
php dux db:sync admin

# 查看已注册的自动迁移模型
php dux db:list
```

::: tip 自动迁移特性
- **字段对比**：自动对比现有表结构与模型定义的差异
- **增量更新**：只更新变化的字段，不影响现有数据
- **注释支持**：完整支持表和字段注释
- **数据填充**：支持初始数据的自动填充
:::

## 模型属性配置

### 基础属性

```php
class User extends Model
{
    // 表名（如果不设置则根据类名自动推断）
    protected $table = 'users';

    // 主键字段名
    protected $primaryKey = 'id';

    // 主键是否自增
    public $incrementing = true;

    // 主键数据类型
    protected $keyType = 'int';

    // 是否维护时间戳字段
    public $timestamps = true;

    // 时间戳字段名
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    // 表注释
    protected $tableComment = '用户数据表';
}
```

### 批量赋值

```php
class User extends Model
{
    // 可批量赋值的字段（白名单）
    protected $fillable = [
        'name', 'email', 'status'
    ];

    // 不可批量赋值的字段（黑名单）
    protected $guarded = [
        'id', 'password', 'remember_token'
    ];

    // 默认 DuxLite 开放所有字段批量赋值
    protected $fillable = [];
    protected $guarded = [];
}
```

### 隐藏字段

```php
class User extends Model
{
    // 序列化时隐藏的字段
    protected $hidden = [
        'password', 'remember_token'
    ];

    // 序列化时显示的字段
    protected $visible = [
        'id', 'name', 'email'
    ];
}
```

### 类型转换

```php
class User extends Model
{
    protected $casts = [
        'email_verified_at' => 'datetime',
        'status' => 'boolean',
        'metadata' => 'array',
        'settings' => 'object',
        'score' => 'decimal:2',
        'tags' => 'collection'
    ];

    // 日期字段
    protected $dates = [
        'created_at', 'updated_at', 'deleted_at'
    ];
}
```

## 数据查询

### 基础查询

```php
// 查询所有记录
$users = User::all();

// 条件查询
$users = User::where('status', 1)->get();
$user = User::where('email', 'admin@example.com')->first();

// 主键查询
$user = User::find(1);
$users = User::find([1, 2, 3]);

// 查询或抛出异常
$user = User::findOrFail(1);
$user = User::where('email', $email)->firstOrFail();

// 软查询
$user = User::firstOrNew(['email' => $email]);
$user = User::firstOrCreate(['email' => $email], ['name' => 'New User']);
$user = User::updateOrCreate(['email' => $email], ['name' => 'Updated User']);
```

### 高级查询

```php
// 条件查询
$users = User::where('status', 1)
    ->where('created_at', '>', now()->subDays(30))
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// 范围查询
$users = User::whereBetween('created_at', [$startDate, $endDate])->get();
$users = User::whereIn('status', [1, 2])->get();
$users = User::whereNotNull('email_verified_at')->get();

// 原生查询
$users = User::whereRaw('age > ? and votes = 100', [25])->get();
$users = User::selectRaw('count(*) as user_count, status')
    ->groupBy('status')
    ->get();

// JSON 查询（MySQL 5.7+）
$users = User::where('metadata->theme', 'dark')->get();
$users = User::whereJsonContains('tags', 'php')->get();
```

### 分页查询

```php
// 简单分页
$users = User::paginate(15);
$users = User::simplePaginate(15);

// 自定义分页
$users = User::where('status', 1)->paginate(
    $perPage = 15,
    $columns = ['*'],
    $pageName = 'page',
    $page = null
);

// 在资源控制器中自动处理分页
class UserController extends Resources
{
    protected array $pagination = [
        'status' => true,
        'pageSize' => 20
    ];
}
```

### 聚合查询

```php
// 统计函数
$count = User::count();
$max = User::max('created_at');
$min = User::min('created_at');
$avg = User::avg('score');
$sum = User::sum('points');

// 条件统计
$activeCount = User::where('status', 1)->count();
$avgScore = User::where('status', 1)->avg('score');

// 分组统计
$stats = User::selectRaw('status, count(*) as count')
    ->groupBy('status')
    ->pluck('count', 'status');
```

## 数据操作

### 创建记录

```php
// 方式一：批量赋值
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('password', PASSWORD_DEFAULT)
]);

// 方式二：实例化创建
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();

// 方式三：填充后保存
$user = new User();
$user->fill([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
$user->save();

// 批量插入
User::insert([
    ['name' => 'User 1', 'email' => 'user1@example.com'],
    ['name' => 'User 2', 'email' => 'user2@example.com']
]);
```

### 更新记录

```php
// 查找并更新
$user = User::find(1);
$user->name = 'Jane Doe';
$user->save();

// 批量更新
User::where('status', 0)->update(['status' => 1]);

// 原子操作
User::where('id', 1)->increment('points');
User::where('id', 1)->increment('points', 5);
User::where('id', 1)->decrement('points', 3);

// 更新或创建
User::updateOrCreate(
    ['email' => 'john@example.com'],
    ['name' => 'John Updated', 'status' => 1]
);
```

### 删除记录

```php
// 模型删除
$user = User::find(1);
$user->delete();

// 主键删除
User::destroy(1);
User::destroy([1, 2, 3]);
User::destroy(collect([1, 2, 3]));

// 条件删除
User::where('status', 0)->delete();

// 软删除（需要 SoftDeletes trait）
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
}

// 软删除操作
$user->delete(); // 软删除
$user->restore(); // 恢复
$user->forceDelete(); // 永久删除

// 查询软删除数据
$users = User::withTrashed()->get(); // 包含软删除
$users = User::onlyTrashed()->get(); // 仅软删除
```

## 模型关联

### 一对一关联

```php
class User extends Model
{
    /**
     * 用户资料
     */
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * 用户头像
     */
    public function avatar()
    {
        return $this->hasOne(Avatar::class, 'user_id', 'id');
    }
}

class Profile extends Model
{
    /**
     * 归属用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// 使用关联
$user = User::with('profile')->find(1);
echo $user->profile->bio;
```

### 一对多关联

```php
class User extends Model
{
    /**
     * 用户文章
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * 用户评论
     */
    public function comments()
    {
        return $this->hasMany(Comment::class)->latest();
    }
}

class Post extends Model
{
    /**
     * 文章作者
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

// 使用关联
$user = User::with('posts')->find(1);
foreach ($user->posts as $post) {
    echo $post->title;
}
```

### 多对多关联

```php
class User extends Model
{
    /**
     * 用户角色
     */
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'user_roles', // 中间表名
            'user_id',    // 当前模型外键
            'role_id'     // 关联模型外键
        )->withTimestamps()->withPivot('status');
    }
}

class Role extends Model
{
    /**
     * 角色用户
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
}

// 使用关联
$user = User::with('roles')->find(1);
foreach ($user->roles as $role) {
    echo $role->name;
    echo $role->pivot->status; // 中间表字段
}

// 关联操作
$user->roles()->attach($roleId);
$user->roles()->detach($roleId);
$user->roles()->sync([1, 2, 3]);
```

### 预加载关联

```php
// 预加载单个关联
$users = User::with('posts')->get();

// 预加载多个关联
$users = User::with(['posts', 'profile'])->get();

// 嵌套预加载
$users = User::with('posts.comments')->get();

// 条件预加载
$users = User::with(['posts' => function ($query) {
    $query->where('status', 1)->orderBy('created_at', 'desc');
}])->get();

// 懒加载
$user = User::find(1);
$user->load('posts');
```

## 查询作用域

### 本地作用域

```php
class User extends Model
{
    /**
     * 活跃用户作用域
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * 最近用户作用域
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>', now()->subDays($days));
    }

    /**
     * 搜索作用域
     */
    public function scopeSearch($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('name', 'like', "%{$keyword}%")
              ->orWhere('email', 'like', "%{$keyword}%");
        });
    }
}

// 使用作用域
$users = User::active()->recent(7)->get();
$users = User::search('john')->paginate(15);
```

### 全局作用域

```php
// 定义全局作用域
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;

class ActiveScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $builder->where('status', 1);
    }
}

// 应用全局作用域
class User extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new ActiveScope);

        // 或使用闭包
        static::addGlobalScope('active', function (Builder $builder) {
            $builder->where('status', 1);
        });
    }
}

// 移除全局作用域
$users = User::withoutGlobalScope(ActiveScope::class)->get();
$users = User::withoutGlobalScopes()->get();
```

## 访问器和修改器

### 访问器（Accessors）

```php
class User extends Model
{
    /**
     * 获取格式化的姓名
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * 获取头像URL
     */
    public function getAvatarUrlAttribute()
    {
        return $this->avatar ? "/storage/{$this->avatar}" : '/images/default-avatar.png';
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute()
    {
        return $this->status ? '启用' : '禁用';
    }
}

// 使用访问器
$user = User::find(1);
echo $user->full_name;     // 自动调用 getFullNameAttribute
echo $user->avatar_url;    // 自动调用 getAvatarUrlAttribute
echo $user->status_text;   // 自动调用 getStatusTextAttribute
```

### 修改器（Mutators）

```php
class User extends Model
{
    /**
     * 设置密码（自动加密）
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }

    /**
     * 设置邮箱（自动转小写）
     */
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }

    /**
     * 设置姓名（自动去除空格）
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = trim($value);
    }
}

// 使用修改器
$user = new User();
$user->password = 'plain-password'; // 自动加密
$user->email = 'USER@EXAMPLE.COM';  // 自动转小写
$user->name = '  John Doe  ';       // 自动去除空格
```

## 模型事件

DuxLite 扩展了 Eloquent 的事件系统，提供更灵活的事件处理：

### 内置事件

```php
class User extends Model
{
    protected static function boot()
    {
        parent::boot();

        // DuxLite 会自动分发这些事件
        static::retrieved(function ($user) {
            // 记录查询日志
        });

        static::creating(function ($user) {
            // 创建前处理
            $user->uuid = Str::uuid();
        });

        static::created(function ($user) {
            // 创建后处理
            event('user.created', $user);
        });

        static::updating(function ($user) {
            // 更新前处理
        });

        static::updated(function ($user) {
            // 更新后处理
        });

        static::deleting(function ($user) {
            // 删除前处理
        });

        static::deleted(function ($user) {
            // 删除后处理
        });
    }
}
```

### 事件监听器

```php
// 通过事件系统监听模型事件
use Core\Event\Attribute\Listener;

class UserEventListener
{
    #[Listener('model.App\Models\User')]
    public function handle(DatabaseEvent $event): void
    {
        $event->created(function ($user) {
            // 用户创建后发送欢迎邮件
            $this->sendWelcomeEmail($user);
        });

        $event->updated(function ($user) {
            // 用户更新后清除缓存
            $this->clearUserCache($user);
        });
    }
}
```

## 序列化

### 数组和 JSON

```php
class User extends Model
{
    protected $hidden = ['password'];
    protected $visible = ['id', 'name', 'email'];
    protected $appends = ['full_name', 'avatar_url'];

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}

// 序列化为数组
$user = User::find(1);
$array = $user->toArray();

// 序列化为 JSON
$json = $user->toJson();
echo $user; // 自动调用 toJson()

// 集合序列化
$users = User::all();
$array = $users->toArray();
$json = $users->toJson();
```

### API 资源

```php
// 与 DuxLite 资源控制器结合
class UserController extends Resources
{
    public function transform(object $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'email' => $item->email,
            'avatar' => $item->avatar_url,
            'status' => $item->status_text,
            'created_at' => $item->created_at->diffForHumans()
        ];
    }
}
```

## 高级特性

### 多语言支持

DuxLite 提供了专门的多语言模型特质：

```php
use Core\Model\TransTrait;

class Post extends Model
{
    use TransTrait;

    protected $fillable = ['title', 'content', 'translations'];
    protected $casts = [
        'translations' => 'array'
    ];
}

// 使用多语言
$post = new Post();
$post->translations = [
    'zh-CN' => ['title' => '标题', 'content' => '内容'],
    'en-US' => ['title' => 'Title', 'content' => 'Content']
];
$post->save();

// 获取翻译
$trans = $post->translate('zh-CN');
echo $trans->title; // 输出：标题

$trans = $post->translate('fr-FR', 'en-US'); // 法语不存在时使用英语
echo $trans->title;
```

### 树形结构

DuxLite 支持嵌套集合模型，适用于分类、菜单等树形数据：

```php
use Kalnoy\Nestedset\NodeTrait;

class Category extends Model
{
    use NodeTrait;

    protected $fillable = ['name', 'parent_id'];
}

// 树形操作
$root = Category::create(['name' => 'Root']);
$child = Category::create(['name' => 'Child']);
$child->appendToNode($root)->save();

// 查询树形数据
$tree = Category::get()->toTree();

// 在资源控制器中启用树形模式
class CategoryController extends Resources
{
    protected bool $tree = true; // 启用树形数据返回
}
```

### 数据库事务

```php
use Core\App;

// 手动事务
App::db()->getConnection()->beginTransaction();
try {
    $user = User::create($userData);
    $profile = $user->profile()->create($profileData);
    App::db()->getConnection()->commit();
} catch (Exception $e) {
    App::db()->getConnection()->rollback();
    throw $e;
}

// 事务闭包
App::db()->getConnection()->transaction(function () use ($userData, $profileData) {
    $user = User::create($userData);
    $user->profile()->create($profileData);
});

// 在资源控制器中自动处理事务
class UserController extends Resources
{
    // CRUD 操作自动包装在事务中
    public function createBefore(Data $data, mixed $model): void
    {
        // 事务已自动开启
    }
}
```

## 性能优化

### 查询优化

```php
// 使用 select 减少字段
$users = User::select(['id', 'name', 'email'])->get();

// 使用索引
$users = User::where('email', $email)->first(); // 确保 email 有索引

// 避免 N+1 问题
$users = User::with('posts')->get(); // 预加载
$users = User::with(['posts' => function ($query) {
    $query->select(['id', 'user_id', 'title']);
}])->get();

// 使用 chunk 处理大量数据
User::chunk(100, function ($users) {
    foreach ($users as $user) {
        // 处理用户
    }
});

// 使用游标分页
foreach (User::cursor() as $user) {
    // 内存友好的遍历
}
```

### 缓存策略

```php
use Core\App;

class User extends Model
{
    public static function getCachedUser($id)
    {
        $key = "user:{$id}";

        return App::cache()->remember($key, 3600, function () use ($id) {
            return static::find($id);
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($user) {
            // 更新时清除缓存
            App::cache()->delete("user:{$user->id}");
        });
    }
}
```

## 最佳实践

### 模型设计原则

```php
class User extends Model
{
    // 1. 明确定义可填充字段
    protected $fillable = [
        'name', 'email', 'status'
    ];

    // 2. 隐藏敏感字段
    protected $hidden = [
        'password', 'remember_token'
    ];

    // 3. 定义类型转换
    protected $casts = [
        'email_verified_at' => 'datetime',
        'status' => 'boolean',
        'metadata' => 'array'
    ];

    // 4. 添加访问器提升用户体验
    public function getAvatarUrlAttribute()
    {
        return $this->avatar ? asset("storage/{$this->avatar}") : asset('images/default-avatar.png');
    }

    // 5. 使用作用域封装常用查询
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    // 6. 合理定义关联关系
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
```

### 查询性能优化

```php
// ✅ 好的做法
$users = User::select(['id', 'name', 'email'])
    ->with(['posts' => function ($query) {
        $query->select(['id', 'user_id', 'title'])
              ->where('status', 1);
    }])
    ->where('status', 1)
    ->paginate(15);

// ❌ 避免的做法
$users = User::all(); // 获取所有字段和记录
foreach ($users as $user) {
    echo $user->posts()->count(); // N+1 查询问题
}
```

### 安全注意事项

```php
class User extends Model
{
    // 1. 严格控制可批量赋值字段
    protected $fillable = [
        'name', 'email' // 不包含敏感字段如 is_admin
    ];

    // 2. 使用修改器处理敏感数据
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }

    // 3. 验证数据（结合 DuxLite 验证器）
    public static function validateCreate(array $data): array
    {
        return Validator::parser($data, [
            'name' => ['required', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:6']
        ]);
    }
}
```

## 与 DuxLite 集成

### 资源控制器集成

```php
use Core\Resources\Action\Resources;

class UserController extends Resources
{
    protected string $model = User::class;

    /**
     * 数据转换
     */
    public function transform(object $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'email' => $item->email,
            'avatar_url' => $item->avatar_url,
            'status_text' => $item->status_text,
            'created_at' => $item->created_at->diffForHumans()
        ];
    }

    /**
     * 查询条件
     */
    public function query(Builder $query): void
    {
        // 所有操作的通用查询条件
        $query->where('deleted_at', null);
    }

    /**
     * 列表查询
     */
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $params = $request->getQueryParams();

        // 状态筛选
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        // 搜索
        if (isset($params['search'])) {
            $query->search($params['search']);
        }
    }
}
```

### format_data 函数集成

```php
// 在控制器中使用 format_data
public function getUsers(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
{
    $users = User::active()->paginate(15);

    $result = format_data($users, function ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'status_text' => $user->status_text,
        ];
    });

    return send($response, '获取成功', $result['data'], $result['meta']);
}
```

## 数据库备份和恢复

DuxLite 提供了数据库备份和恢复命令：

```bash
# 备份数据库
php dux db:backup

# 恢复数据库
php dux db:restore
```

::: tip 性能建议
1. **合理使用预加载**：避免 N+1 查询问题
2. **精确查询字段**：只选择需要的字段
3. **适当使用缓存**：对热点数据进行缓存
4. **索引优化**：确保查询字段有适当的索引
5. **分页处理**：避免一次性加载大量数据
:::

::: warning 安全提醒
1. **批量赋值保护**：严格定义 `$fillable` 字段
2. **SQL 注入防护**：使用参数绑定而非字符串拼接
3. **敏感数据处理**：密码等敏感字段要加密存储
4. **访问控制**：结合 DuxLite 权限系统控制数据访问
:::

通过 DuxLite 的 Eloquent ORM，您可以高效、安全地进行数据库操作。结合框架的资源控制器、验证器、事件系统等特性，能够快速构建功能完整的数据驱动应用。