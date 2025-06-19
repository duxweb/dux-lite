# 数据库关联关系

DuxLite 基于 Laravel Eloquent ORM，提供了完整的关联关系支持。通过定义模型之间的关联关系，你可以轻松处理复杂的数据查询和操作。

## 关联关系概述

### 核心特性

- **完整的关联支持**：支持一对一、一对多、多对多等所有 Eloquent 关联类型
- **智能预加载**：解决 N+1 查询问题，提升性能
- **关联查询**：基于关联条件进行数据筛选和查询
- **关联操作**：便捷的关联数据创建、更新和删除
- **资源控制器集成**：在 DuxLite 资源控制器中无缝使用关联关系

### 支持的关联类型

| 关联类型 | 描述 | 应用场景 |
|---------|------|----------|
| `hasOne` | 一对一 | 用户-个人资料 |
| `belongsTo` | 反向一对一/一对多 | 文章-作者 |
| `hasMany` | 一对多 | 用户-文章 |
| `belongsToMany` | 多对多 | 用户-角色 |
| `hasManyThrough` | 远程一对多 | 国家-文章(通过用户) |
| `hasOneThrough` | 远程一对一 | 国家-最新文章(通过用户) |
| `morphTo` | 多态关联 | 评论-可评论对象 |
| `morphMany` | 反向多态一对多 | 可评论对象-评论 |
| `morphToMany` | 多态多对多 | 标签-可标记对象 |

## 一对一关联

### 定义关联

```php
use Core\Database\Model;

class User extends Model
{
    protected $table = 'users';
    protected $tableComment = '用户表';

    public function migration(Blueprint $table)
    {
        $table->id();
        $table->string('name')->comment('用户名');
        $table->string('email')->unique()->comment('邮箱');
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    }

    /**
     * 用户资料（正向关联）
     */
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * 用户头像（指定外键）
     */
    public function avatar()
    {
        return $this->hasOne(Avatar::class, 'user_id', 'id');
    }
}

class Profile extends Model
{
    protected $table = 'profiles';
    protected $tableComment = '用户资料表';

    public function migration(Blueprint $table)
    {
        $table->id();
        $table->foreignId('user_id')->comment('用户ID');
        $table->string('nickname')->nullable()->comment('昵称');
        $table->text('bio')->nullable()->comment('个人简介');
        $table->string('avatar')->nullable()->comment('头像');
        $table->timestamps();

        $table->foreign('user_id')->references('id')->on('users');
    }

    /**
     * 归属用户（反向关联）
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### 使用一对一关联

```php
// 查询用户和资料
$user = User::with('profile')->find(1);
echo $user->profile->bio ?? '暂无简介';

// 创建关联数据
$user = User::find(1);
$user->profile()->create([
    'nickname' => '小明',
    'bio' => '这是我的个人简介'
]);

// 更新关联数据
$user->profile()->update([
    'bio' => '更新后的简介'
]);

// 删除关联数据
$user->profile()->delete();
```

## 一对多关联

### 定义关联

```php
class User extends Model
{
    /**
     * 用户文章（一对多）
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * 用户评论（带排序）
     */
    public function comments()
    {
        return $this->hasMany(Comment::class)->latest();
    }

    /**
     * 已发布的文章
     */
    public function publishedPosts()
    {
        return $this->hasMany(Post::class)->where('status', 'published');
    }
}

class Post extends Model
{
    protected $table = 'posts';
    protected $tableComment = '文章表';

    public function migration(Blueprint $table)
    {
        $table->id();
        $table->foreignId('user_id')->comment('作者ID');
        $table->string('title')->comment('标题');
        $table->text('content')->comment('内容');
        $table->enum('status', ['draft', 'published'])->default('draft')->comment('状态');
        $table->timestamps();

        $table->foreign('user_id')->references('id')->on('users');
    }

    /**
     * 文章作者（反向关联）
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 文章评论
     */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
```

### 使用一对多关联

```php
// 预加载查询
$user = User::with('posts')->find(1);
foreach ($user->posts as $post) {
    echo $post->title;
}

// 创建关联数据
$user = User::find(1);
$post = $user->posts()->create([
    'title' => '新文章标题',
    'content' => '文章内容',
    'status' => 'published'
]);

// 查询条件
$user = User::find(1);
$publishedPosts = $user->posts()->where('status', 'published')->get();

// 统计关联数据
$user = User::find(1);
$postCount = $user->posts()->count();
$publishedCount = $user->posts()->where('status', 'published')->count();

// 删除关联数据
$user->posts()->delete(); // 删除所有文章
$user->posts()->where('status', 'draft')->delete(); // 删除草稿
```

## 多对多关联

### 定义关联

```php
class User extends Model
{
    /**
     * 用户角色（多对多）
     */
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,           // 关联模型
            'user_roles',          // 中间表名
            'user_id',            // 当前模型外键
            'role_id'             // 关联模型外键
        )->withTimestamps()       // 包含时间戳
          ->withPivot('status');  // 包含中间表字段
    }

    /**
     * 活跃角色
     */
    public function activeRoles()
    {
        return $this->roles()->wherePivot('status', 1);
    }
}

class Role extends Model
{
    protected $table = 'roles';
    protected $tableComment = '角色表';

    public function migration(Blueprint $table)
    {
        $table->id();
        $table->string('name')->comment('角色名');
        $table->string('label')->comment('角色标签');
        $table->timestamps();
    }

    /**
     * 角色用户
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
}

// 中间表迁移
class UserRole extends Model
{
    protected $table = 'user_roles';
    protected $tableComment = '用户角色关联表';

    public function migration(Blueprint $table)
    {
        $table->id();
        $table->foreignId('user_id')->comment('用户ID');
        $table->foreignId('role_id')->comment('角色ID');
        $table->tinyInteger('status')->default(1)->comment('状态');
        $table->timestamps();

        $table->foreign('user_id')->references('id')->on('users');
        $table->foreign('role_id')->references('id')->on('roles');
        $table->unique(['user_id', 'role_id']);
    }
}
```

### 使用多对多关联

```php
// 查询关联数据
$user = User::with('roles')->find(1);
foreach ($user->roles as $role) {
    echo $role->name;
    echo $role->pivot->status; // 中间表字段
    echo $role->pivot->created_at; // 中间表时间戳
}

// 附加关联（attach）
$user = User::find(1);
$user->roles()->attach($roleId); // 简单附加
$user->roles()->attach($roleId, ['status' => 1]); // 带中间表数据
$user->roles()->attach([1, 2, 3]); // 批量附加

// 分离关联（detach）
$user->roles()->detach($roleId); // 分离单个
$user->roles()->detach([1, 2]); // 分离多个
$user->roles()->detach(); // 分离所有

// 同步关联（sync）
$user->roles()->sync([1, 2, 3]); // 只保留这些角色
$user->roles()->sync([
    1 => ['status' => 1],
    2 => ['status' => 0]
]); // 带中间表数据同步

// 切换关联（toggle）
$user->roles()->toggle([1, 2]); // 有则删除，无则添加

// 更新中间表
$user->roles()->updateExistingPivot($roleId, ['status' => 0]);
```

## 预加载关联

### 基础预加载

::: tip 性能优化
预加载可以有效解决 N+1 查询问题，提升应用性能。
:::

```php
// 预加载单个关联
$users = User::with('posts')->get();

// 预加载多个关联
$users = User::with(['posts', 'profile', 'roles'])->get();

// 嵌套预加载
$users = User::with('posts.comments')->get();
$users = User::with('posts.comments.user')->get();

// 多层嵌套
$users = User::with([
    'posts.comments.user.profile',
    'roles'
])->get();
```

### 条件预加载

```php
// 预加载时添加条件
$users = User::with(['posts' => function ($query) {
    $query->where('status', 'published')
          ->orderBy('created_at', 'desc')
          ->limit(5);
}])->get();

// 多个条件预加载
$users = User::with([
    'posts' => function ($query) {
        $query->where('status', 'published');
    },
    'comments' => function ($query) {
        $query->latest()->limit(10);
    }
])->get();

// 嵌套条件预加载
$users = User::with([
    'posts' => function ($query) {
        $query->with(['comments' => function ($commentQuery) {
            $commentQuery->where('approved', true);
        }]);
    }
])->get();
```

### 懒加载

```php
// 运行时加载关联
$user = User::find(1);
$user->load('posts');
$user->load(['posts', 'comments']);

// 条件懒加载
$user->load(['posts' => function ($query) {
    $query->where('status', 'published');
}]);

// 懒加载计数
$user->loadCount('posts');
$user->loadCount(['posts', 'comments']);
```

## 关联查询

### 基于关联的查询

```php
// 查询有文章的用户
$users = User::has('posts')->get();

// 查询至少有3篇文章的用户
$users = User::has('posts', '>=', 3)->get();

// 查询有已发布文章的用户
$users = User::whereHas('posts', function ($query) {
    $query->where('status', 'published');
})->get();

// 查询没有文章的用户
$users = User::doesntHave('posts')->get();

// 查询没有已发布文章的用户
$users = User::whereDoesntHave('posts', function ($query) {
    $query->where('status', 'published');
})->get();
```

### 关联计数

```php
// 添加关联计数
$users = User::withCount('posts')->get();
foreach ($users as $user) {
    echo $user->posts_count;
}

// 多个关联计数
$users = User::withCount(['posts', 'comments'])->get();

// 条件计数
$users = User::withCount([
    'posts',
    'publishedPosts' => function ($query) {
        $query->where('status', 'published');
    }
])->get();

// 别名计数
$users = User::withCount([
    'posts as total_posts',
    'posts as published_posts' => function ($query) {
        $query->where('status', 'published');
    }
])->get();
```

### 关联聚合

```php
// 关联求和
$users = User::withSum('posts', 'views')->get();

// 关联平均值
$users = User::withAvg('posts', 'rating')->get();

// 关联最大值
$users = User::withMax('posts', 'created_at')->get();

// 关联最小值
$users = User::withMin('posts', 'created_at')->get();

// 多个聚合
$users = User::withCount('posts')
    ->withSum('posts', 'views')
    ->withAvg('posts', 'rating')
    ->get();
```

## 多态关联

### 一对多多态关联

```php
// 评论模型（多态）
class Comment extends Model
{
    protected $table = 'comments';
    protected $tableComment = '评论表';

    public function migration(Blueprint $table)
    {
        $table->id();
        $table->text('content')->comment('评论内容');
        $table->morphs('commentable'); // 创建 commentable_id 和 commentable_type
        $table->timestamps();
    }

    /**
     * 可评论的模型（多态关联）
     */
    public function commentable()
    {
        return $this->morphTo();
    }
}

// 文章模型
class Post extends Model
{
    /**
     * 文章评论（多态）
     */
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

// 视频模型
class Video extends Model
{
    /**
     * 视频评论（多态）
     */
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
```

### 多对多多态关联

```php
// 标签模型
class Tag extends Model
{
    protected $table = 'tags';
    protected $tableComment = '标签表';

    public function migration(Blueprint $table)
    {
        $table->id();
        $table->string('name')->comment('标签名');
        $table->timestamps();
    }

    /**
     * 文章标签
     */
    public function posts()
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    /**
     * 视频标签
     */
    public function videos()
    {
        return $this->morphedByMany(Video::class, 'taggable');
    }
}

// 文章模型
class Post extends Model
{
    /**
     * 文章标签（多态多对多）
     */
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}

// 标签关联表
class Taggable extends Model
{
    protected $table = 'taggables';

    public function migration(Blueprint $table)
    {
        $table->id();
        $table->foreignId('tag_id')->comment('标签ID');
        $table->morphs('taggable'); // taggable_id, taggable_type
        $table->timestamps();
    }
}
```

## 在资源控制器中使用关联

### 基础使用

```php
use Core\Resources\Action\Resources;
use Core\Resources\Attribute\Resource;

#[Resource(
    app: "admin",
    route: "/users",
    name: "user"
)]
class UserController extends Resources
{
    protected string $model = User::class;

    /**
     * 预加载关联数据
     */
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $query->with(['profile', 'roles']);
    }

    /**
     * 单条数据预加载
     */
    public function queryOne(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $query->with(['profile', 'posts.comments', 'roles']);
    }

    /**
     * 转换关联数据
     */
    public function transform(object $item, ServerRequestInterface $request): array
    {
        return [
            'profile_nickname' => $item->profile?->nickname,
            'posts_count' => $item->posts_count ?? $item->posts()->count(),
            'roles' => $item->roles->pluck('name'),
        ];
    }
}
```

### 关联查询和筛选

```php
class PostController extends Resources
{
    protected string $model = Post::class;

    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $params = $request->getQueryParams();

        // 根据作者筛选
        if (!empty($params['author_id'])) {
            $query->where('user_id', $params['author_id']);
        }

        // 根据作者名筛选
        if (!empty($params['author_name'])) {
            $query->whereHas('author', function ($q) use ($params) {
                $q->where('name', 'like', "%{$params['author_name']}%");
            });
        }

        // 根据评论数筛选
        if (!empty($params['min_comments'])) {
            $query->has('comments', '>=', (int)$params['min_comments']);
        }

        // 包含关联计数
        $query->withCount(['comments', 'likes']);

        // 预加载关联
        $query->with(['author.profile', 'comments.user']);
    }

    public function transform(object $item, ServerRequestInterface $request): array
    {
        return [
            'author_name' => $item->author->name,
            'author_avatar' => $item->author->profile?->avatar,
            'comments_count' => $item->comments_count,
            'latest_comment' => $item->comments->first()?->content,
        ];
    }
}
```

### 关联数据操作

```php
class UserController extends Resources
{
    /**
     * 创建用户时同时创建资料
     */
    public function createAfter(Data $data, mixed $model): void
    {
        if ($data->get('nickname')) {
            $model->profile()->create([
                'nickname' => $data->get('nickname'),
                'bio' => $data->get('bio', '')
            ]);
        }

        // 分配角色
        if ($data->get('role_ids')) {
            $roleIds = explode(',', $data->get('role_ids'));
            $model->roles()->attach($roleIds);
        }
    }

    /**
     * 更新用户时更新关联数据
     */
    public function storeAfter(Data $data, mixed $model): void
    {
        // 更新资料
        if ($data->has('nickname') || $data->has('bio')) {
            $model->profile()->updateOrCreate(
                ['user_id' => $model->id],
                [
                    'nickname' => $data->get('nickname'),
                    'bio' => $data->get('bio')
                ]
            );
        }

        // 同步角色
        if ($data->has('role_ids')) {
            $roleIds = $data->get('role_ids') ? explode(',', $data->get('role_ids')) : [];
            $model->roles()->sync($roleIds);
        }
    }

    /**
     * 删除用户时处理关联数据
     */
    public function delBefore(mixed $model): void
    {
        // 删除关联数据
        $model->profile()->delete();
        $model->roles()->detach();

        // 软删除文章而不是直接删除
        $model->posts()->update(['deleted_at' => now()]);
    }
}
```

## 高级关联技巧

### 远程关联

```php
class Country extends Model
{
    /**
     * 通过用户获取国家的所有文章
     */
    public function posts()
    {
        return $this->hasManyThrough(
            Post::class,        // 最终模型
            User::class,        // 中间模型
            'country_id',       // 中间模型外键
            'user_id',          // 最终模型外键
            'id',              // 当前模型主键
            'id'               // 中间模型主键
        );
    }

    /**
     * 通过用户获取国家的最新文章
     */
    public function latestPost()
    {
        return $this->hasOneThrough(Post::class, User::class)
            ->latest();
    }
}
```

### 动态关联

```php
class User extends Model
{
    /**
     * 动态关联方法
     */
    public function getRelation($relation)
    {
        switch ($relation) {
            case 'recentPosts':
                return $this->hasMany(Post::class)->where('created_at', '>', now()->subDays(7));
            case 'popularPosts':
                return $this->hasMany(Post::class)->where('views', '>', 1000);
            default:
                return parent::getRelation($relation);
        }
    }
}

// 使用
$user = User::with('recentPosts')->find(1);
```

### 关联查询优化

```php
class PostController extends Resources
{
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        // 使用 select 减少查询字段
        $query->select(['id', 'title', 'user_id', 'created_at']);

        // 预加载时也指定字段
        $query->with([
            'author:id,name,email',
            'comments:id,post_id,content,created_at'
        ]);

        // 使用关联计数而不是加载所有数据
        $query->withCount(['comments', 'likes']);
    }
}
```

## 数据格式化集成

### 使用 format_data 函数

```php
use function format_data;

class UserController extends Resources
{
    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $users = User::with(['profile', 'roles'])
            ->withCount('posts')
            ->paginate(15);

        $result = format_data($users, function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'nickname' => $user->profile?->nickname,
                'avatar' => $user->profile?->avatar,
                'posts_count' => $user->posts_count,
                'roles' => $user->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'label' => $role->label
                    ];
                })
            ];
        });

        return send($response, 'ok', $result['data'], $result['meta']);
    }
}
```

## 性能优化建议

### 预加载策略

::: warning 注意事项
合理使用预加载，避免加载不必要的关联数据。
:::

```php
// ✅ 好的做法
$users = User::select(['id', 'name', 'email'])
    ->with([
        'profile:user_id,nickname,avatar', // 只加载需要的字段
        'roles:id,name' // 多对多关联也可以指定字段
    ])
    ->withCount('posts') // 使用计数而不是加载所有数据
    ->paginate(15);

// ❌ 避免的做法
$users = User::with('posts.comments.user.profile')->get(); // 过度预加载
```

### 查询优化

```php
// 使用关联查询代替子查询
$users = User::whereHas('posts', function ($query) {
    $query->where('status', 'published');
})->get();

// 使用存在查询提升性能
$users = User::whereExists(function ($query) {
    $query->select(DB::raw(1))
          ->from('posts')
          ->whereColumn('posts.user_id', 'users.id')
          ->where('status', 'published');
})->get();
```

### 缓存策略

```php
use Core\App;

class User extends Model
{
    public function getCachedPosts()
    {
        $key = "user:{$this->id}:posts";

        return App::cache()->remember($key, 3600, function () {
            return $this->posts()->with('comments')->get();
        });
    }

    protected static function boot()
    {
        parent::boot();

        // 模型更新时清除相关缓存
        static::updated(function ($user) {
            App::cache()->delete("user:{$user->id}:posts");
        });
    }
}
```

## 最佳实践

### 关联定义规范

```php
class User extends Model
{
    // 1. 明确指定关联参数
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }

    // 2. 使用语义化的关联名称
    public function publishedPosts()
    {
        return $this->hasMany(Post::class)->where('status', 'published');
    }

    // 3. 添加关联注释
    /**
     * 用户的活跃角色
     * @return BelongsToMany
     */
    public function activeRoles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->wherePivot('status', 1);
    }
}
```

### 关联查询技巧

```php
// 使用作用域简化关联查询
class Post extends Model
{
    public function scopeWithAuthor($query)
    {
        return $query->with('author:id,name,email');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}

// 使用
$posts = Post::withAuthor()->published()->get();
```

### 避免 N+1 问题

```php
// ❌ 会产生 N+1 查询
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // 每次循环都会查询
}

// ✅ 正确的做法
$users = User::withCount('posts')->get();
foreach ($users as $user) {
    echo $user->posts_count; // 只查询一次
}
```

DuxLite 的关联关系系统基于成熟的 Eloquent ORM，提供了完整的关联支持和优秀的性能。通过合理使用关联关系，你可以构建出高效、易维护的数据模型。