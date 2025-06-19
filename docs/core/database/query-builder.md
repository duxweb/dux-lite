# 数据库查询构建器

DuxLite 基于 Laravel Eloquent 查询构建器，提供了丰富而强大的数据库查询接口。查询构建器使用流畅的接口来构建和运行数据库查询，为各种数据库操作提供了方便的、语义化的接口。

## 核心概念

### 获取查询构建器

::: tip IDE 代码提示
**重要提示**：在 DuxLite 中，使用模型查询时建议使用 `query()` 方法而不是静态调用，这样可以获得完整的 IDE 代码提示和类型检查。
:::

```php
use Core\Database\Model;

class User extends Model
{
    protected $table = 'users';
    protected $tableComment = '用户表';
}

// ✅ 推荐方式：使用 query() 方法获得完整代码提示
$users = User::query()->where('status', 1)->get();
$user = User::query()->find(1);

// ❌ 不推荐：静态调用可能缺少 IDE 提示
$users = User::where('status', 1)->get();
```

### 在资源控制器中使用

DuxLite 资源控制器通过 `queryModel()` 方法自动使用 `query()` 方法：

```php
use Core\Resources\Action\Resources;
use Illuminate\Database\Eloquent\Builder;

class UserController extends Resources
{
    protected string $model = User::class;

    /**
     * queryModel 方法自动调用 query()
     */
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        // $query 已经是 Builder 实例，具有完整的代码提示
        $query->where('status', 1)
              ->orderBy('created_at', 'desc')
              ->with('profile');
    }
}
```

## 基础查询

### 检索数据

```php
// 获取所有记录
$users = User::query()->get();

// 获取单条记录
$user = User::query()->first();
$user = User::query()->find(1);
$user = User::query()->where('email', $email)->first();

// 获取指定列
$users = User::query()->select(['id', 'name', 'email'])->get();
$users = User::query()->select('id', 'name', 'email')->get();

// 获取不重复的记录
$users = User::query()->distinct()->get();

// 获取单个值
$name = User::query()->where('id', 1)->value('name');
$names = User::query()->pluck('name');
$namesByEmail = User::query()->pluck('name', 'email');
```

### 聚合查询

```php
// 统计函数
$count = User::query()->count();
$max = User::query()->max('created_at');
$min = User::query()->min('created_at');
$avg = User::query()->avg('score');
$sum = User::query()->sum('points');

// 条件聚合
$activeCount = User::query()->where('status', 1)->count();
$avgScore = User::query()->where('status', 1)->avg('score');

// 检查记录是否存在
$exists = User::query()->where('email', $email)->exists();
$notExists = User::query()->where('email', $email)->doesntExist();
```

## 条件查询

### Where 子句

```php
// 基础 where 条件
$users = User::query()->where('status', 1)->get();
$users = User::query()->where('votes', '>', 100)->get();
$users = User::query()->where('name', 'like', '%john%')->get();

// 多个 where 条件
$users = User::query()
    ->where('status', 1)
    ->where('votes', '>', 100)
    ->get();

// where 数组条件
$users = User::query()->where([
    ['status', '=', 1],
    ['votes', '>', 100],
])->get();

// orWhere 条件
$users = User::query()
    ->where('votes', '>', 100)
    ->orWhere('name', 'John')
    ->get();

// whereBetween / orWhereBetween
$users = User::query()->whereBetween('votes', [1, 100])->get();
$users = User::query()->whereNotBetween('votes', [1, 100])->get();

// whereIn / whereNotIn
$users = User::query()->whereIn('id', [1, 2, 3])->get();
$users = User::query()->whereNotIn('id', [1, 2, 3])->get();

// whereNull / whereNotNull
$users = User::query()->whereNull('updated_at')->get();
$users = User::query()->whereNotNull('updated_at')->get();

// whereDate / whereTime
$users = User::query()->whereDate('created_at', '2023-12-25')->get();
$users = User::query()->whereYear('created_at', 2023)->get();
$users = User::query()->whereMonth('created_at', 12)->get();
$users = User::query()->whereDay('created_at', 25)->get();
$users = User::query()->whereTime('created_at', '=', '11:20:45')->get();
```

### 高级 Where 子句

```php
// 参数分组
$users = User::query()
    ->where('name', '=', 'John')
    ->where(function (Builder $query) {
        $query->where('votes', '>', 100)
              ->orWhere('title', '=', 'Admin');
    })
    ->get();

// JSON Where 子句 (MySQL 5.7+)
$users = User::query()->where('preferences->dining->meal', 'salad')->get();
$users = User::query()->whereJsonContains('options->languages', 'en')->get();
$users = User::query()->whereJsonLength('options->languages', 3)->get();

// 列比较
$users = User::query()->whereColumn('first_name', 'last_name')->get();
$users = User::query()->whereColumn('updated_at', '>', 'created_at')->get();
$users = User::query()->whereColumn([
    ['first_name', '=', 'last_name'],
    ['updated_at', '>', 'created_at'],
])->get();
```

### 条件查询

```php
// when 条件查询
$sortBy = request()->input('sort_by');

$users = User::query()
    ->when($sortBy, function (Builder $query, string $sortBy) {
        return $query->orderBy($sortBy);
    })
    ->get();

// unless 条件查询
$users = User::query()
    ->unless(empty($search), function (Builder $query) use ($search) {
        return $query->where('name', 'like', "%{$search}%");
    })
    ->get();
```

## 排序与分组

### 排序

```php
// 基础排序
$users = User::query()->orderBy('name')->get();
$users = User::query()->orderBy('name', 'desc')->get();

// 多字段排序
$users = User::query()
    ->orderBy('name', 'asc')
    ->orderBy('email', 'desc')
    ->get();

// 最新和最旧
$users = User::query()->latest()->get(); // 按 created_at desc
$users = User::query()->latest('updated_at')->get(); // 按 updated_at desc
$users = User::query()->oldest()->get(); // 按 created_at asc

// 随机排序
$users = User::query()->inRandomOrder()->get();

// 重新排序
$query = User::query()->orderBy('name');
$users = $query->reorder()->get(); // 移除所有排序
$users = $query->reorder('email', 'desc')->get(); // 重新设置排序

// 原生排序
$users = User::query()->orderByRaw('updated_at - created_at DESC')->get();
```

### 分组

```php
// 基础分组
$users = User::query()
    ->select('status', User::query()->raw('count(*) as total'))
    ->groupBy('status')
    ->get();

// 多字段分组
$users = User::query()
    ->select('status', 'type', User::query()->raw('count(*) as total'))
    ->groupBy('status', 'type')
    ->get();

// Having 子句
$users = User::query()
    ->select('status', User::query()->raw('count(*) as total'))
    ->groupBy('status')
    ->having('total', '>', 1)
    ->get();

// Having 原生条件
$users = User::query()
    ->select('status', User::query()->raw('count(*) as total'))
    ->groupBy('status')
    ->havingRaw('count(*) > 1')
    ->get();
```

## 限制和偏移

### 基础限制

```php
// 限制结果数量
$users = User::query()->take(5)->get();
$users = User::query()->limit(5)->get();

// 跳过记录
$users = User::query()->skip(10)->take(5)->get();
$users = User::query()->offset(10)->limit(5)->get();

// 分页
$users = User::query()->forPage(3, 15)->get(); // 第3页，每页15条
```

### 游标和分块

```php
// 分块处理大量数据
User::query()->chunk(200, function (Collection $users) {
    foreach ($users as $user) {
        // 处理每个用户
    }
});

// 按 ID 分块
User::query()->chunkById(200, function (Collection $users) {
    foreach ($users as $user) {
        // 处理每个用户
    }
});

// 懒加载集合
foreach (User::query()->lazy() as $user) {
    // 处理用户，内存友好
}

// 懒加载分块
foreach (User::query()->lazyById(200) as $user) {
    // 分块懒加载
}

// 游标分页
foreach (User::query()->cursor() as $user) {
    // 使用游标遍历
}
```

## 连接查询

### 基础连接

```php
// 内连接
$users = User::query()
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.*', 'posts.title')
    ->get();

// 左连接
$users = User::query()
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.*', 'posts.title')
    ->get();

// 右连接
$users = User::query()
    ->rightJoin('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.*', 'posts.title')
    ->get();

// 交叉连接
$sizes = User::query()
    ->crossJoin('colors')
    ->get();
```

### 高级连接

```php
// 复杂连接条件
$users = User::query()
    ->join('posts', function (JoinClause $join) {
        $join->on('users.id', '=', 'posts.user_id')
             ->where('posts.status', '=', 'published');
    })
    ->get();

// 子查询连接
$latestPosts = Post::query()
    ->select('user_id', User::query()->raw('MAX(created_at) as last_post_created_at'))
    ->where('status', 'published')
    ->groupBy('user_id');

$users = User::query()
    ->joinSub($latestPosts, 'latest_posts', function (JoinClause $join) {
        $join->on('users.id', '=', 'latest_posts.user_id');
    })
    ->get();
```

## 联合查询

```php
// 基础联合
$first = User::query()->where('votes', '>', 100);
$second = User::query()->where('status', 'premium');

$users = $first->union($second)->get();

// 联合所有
$users = $first->unionAll($second)->get();
```

## 原生表达式

### Raw 方法

```php
// 原生选择
$users = User::query()
    ->select(User::query()->raw('count(*) as user_count, status'))
    ->where('status', '<>', 1)
    ->groupBy('status')
    ->get();

// 原生 Where 子句
$users = User::query()
    ->whereRaw('price > IF(state = "TX", ?, 100)', [200])
    ->get();

// 原生 Having 子句
$users = User::query()
    ->select('department', User::query()->raw('SUM(price) as total_sales'))
    ->groupBy('department')
    ->havingRaw('SUM(price) > ?', [2500])
    ->get();

// 原生排序
$users = User::query()
    ->orderByRaw('updated_at - created_at DESC')
    ->get();

// 原生分组
$users = User::query()
    ->select('city', 'state')
    ->groupByRaw('city, state')
    ->get();
```

### DB Raw

```php
use Core\App;

// 使用 DB Raw
$users = User::query()
    ->select('*')
    ->where('votes', '>', App::db()->raw('(SELECT AVG(votes) FROM users)'))
    ->get();
```

## 子查询

### Where 子查询

```php
// Where Exists
$users = User::query()
    ->whereExists(function (Builder $query) {
        $query->select(User::query()->raw(1))
              ->from('posts')
              ->whereColumn('posts.user_id', 'users.id');
    })
    ->get();

// Where Not Exists
$users = User::query()
    ->whereNotExists(function (Builder $query) {
        $query->select(User::query()->raw(1))
              ->from('posts')
              ->whereColumn('posts.user_id', 'users.id');
    })
    ->get();

// 子查询 Where 子句
$users = User::query()
    ->where('votes', '>', function (Builder $query) {
        $query->select(User::query()->raw('AVG(votes)'))
              ->from('users')
              ->where('status', 'active');
    })
    ->get();
```

### Select 子查询

```php
// 添加子查询列
$users = User::query()
    ->addSelect([
        'last_post_created_at' => Post::query()
            ->select('created_at')
            ->whereColumn('user_id', 'users.id')
            ->orderBy('created_at', 'desc')
            ->limit(1)
    ])
    ->get();

// 使用子查询排序
$users = User::query()
    ->orderBy(
        Post::query()
            ->select('created_at')
            ->whereColumn('user_id', 'users.id')
            ->orderBy('created_at', 'desc')
            ->limit(1),
        'desc'
    )
    ->get();
```

## 在资源控制器中的应用

### 基础查询定制

```php
use Core\Resources\Action\Resources;
use Core\Resources\Attribute\Resource;
use Illuminate\Database\Eloquent\Builder;

#[Resource(
    app: "admin",
    route: "/users",
    name: "user"
)]
class UserController extends Resources
{
    protected string $model = User::class;

    /**
     * 多条数据查询定制
     */
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $params = $request->getQueryParams();

        // 状态筛选
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        // 关键词搜索
        if (!empty($params['search'])) {
            $query->where(function (Builder $q) use ($params) {
                $q->where('name', 'like', "%{$params['search']}%")
                  ->orWhere('email', 'like', "%{$params['search']}%");
            });
        }

        // 日期范围筛选
        if (!empty($params['start_date'])) {
            $query->where('created_at', '>=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $query->where('created_at', '<=', $params['end_date']);
        }

        // 预加载关联
        $query->with(['profile', 'roles']);
    }

    /**
     * 单条数据查询定制
     */
    public function queryOne(Builder $query, ServerRequestInterface $request, array $args): void
    {
        // 预加载详细关联
        $query->with([
            'profile',
            'posts' => function (Builder $q) {
                $q->where('status', 'published')->latest();
            },
            'roles.permissions'
        ]);
    }

    /**
     * 通用查询条件
     */
    public function query(Builder $query): void
    {
        // 只查询活跃用户
        $query->where('status', 'active');
    }
}
```

### 高级查询示例

```php
class PostController extends Resources
{
    protected string $model = Post::class;

    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $params = $request->getQueryParams();

        // 复杂的搜索逻辑
        if (!empty($params['keyword'])) {
            $query->where(function (Builder $q) use ($params) {
                $q->where('title', 'like', "%{$params['keyword']}%")
                  ->orWhere('content', 'like', "%{$params['keyword']}%")
                  ->orWhere('excerpt', 'like', "%{$params['keyword']}%");
            });
        }

        // 根据作者筛选
        if (!empty($params['author_id'])) {
            $query->where('user_id', $params['author_id']);
        }

        // 根据分类筛选
        if (!empty($params['category_id'])) {
            $query->where('category_id', $params['category_id']);
        }

        // 根据标签筛选
        if (!empty($params['tag'])) {
            $query->whereHas('tags', function (Builder $q) use ($params) {
                $q->where('name', $params['tag']);
            });
        }

        // 根据评论数筛选
        if (!empty($params['min_comments'])) {
            $query->has('comments', '>=', (int)$params['min_comments']);
        }

        // 统计信息
        $query->withCount(['comments', 'likes']);

        // 添加评分子查询
        $query->addSelect([
            'avg_rating' => Rating::query()
                ->select(User::query()->raw('AVG(rating)'))
                ->whereColumn('post_id', 'posts.id')
        ]);

        // 预加载关联
        $query->with([
            'author:id,name,email',
            'category:id,name,slug',
            'tags:id,name'
        ]);

        // 排序逻辑
        switch ($params['sort'] ?? 'latest') {
            case 'popular':
                $query->orderBy('views', 'desc');
                break;
            case 'commented':
                $query->orderBy('comments_count', 'desc');
                break;
            case 'rating':
                $query->orderBy('avg_rating', 'desc');
                break;
            default:
                $query->latest();
        }
    }
}
```

### 自定义查询作用域

```php
class User extends Model
{
    /**
     * 活跃用户作用域
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
                     ->where('email_verified_at', '!=', null);
    }

    /**
     * 最近注册用户作用域
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>', now()->subDays($days));
    }

    /**
     * 高质量用户作用域
     */
    public function scopeHighQuality(Builder $query): Builder
    {
        return $query->where('reputation', '>', 100)
                     ->has('posts', '>=', 5);
    }

    /**
     * 搜索作用域
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword) {
            $q->where('name', 'like', "%{$keyword}%")
              ->orWhere('email', 'like', "%{$keyword}%")
              ->orWhere('bio', 'like', "%{$keyword}%");
        });
    }
}

// 在控制器中使用作用域
class UserController extends Resources
{
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $params = $request->getQueryParams();

        // 使用作用域
        $query->active();

        if (!empty($params['recent'])) {
            $query->recent($params['recent']);
        }

        if (!empty($params['high_quality'])) {
            $query->highQuality();
        }

        if (!empty($params['search'])) {
            $query->search($params['search']);
        }
    }
}
```

## 查询调试

### 调试 SQL

```php
use Core\App;

// 开启查询日志
App::db()->enableQueryLog();

// 执行查询
$users = User::query()->where('votes', '>', 100)->get();

// 获取查询日志
$queries = App::db()->getQueryLog();
foreach ($queries as $query) {
    dump($query['query'], $query['bindings'], $query['time']);
}

// 获取 SQL 语句（不执行）
$sql = User::query()->where('votes', '>', 100)->toSql();
dump($sql); // select * from `users` where `votes` > ?

// 获取带绑定参数的 SQL
$query = User::query()->where('votes', '>', 100);
$sqlWithBindings = str_replace_array(
    '?',
    $query->getBindings(),
    $query->toSql()
);
dump($sqlWithBindings);
```

### 性能分析

```php
// 使用 explain 分析查询
$result = App::db()->select('explain ' .
    User::query()->where('email', $email)->toSql(),
    [$email]
);

// 查询执行计划
$users = User::query()
    ->where('status', 'active')
    ->where('created_at', '>', now()->subDays(30))
    ->explain();
```

## 性能优化技巧

### 查询优化

```php
// ✅ 好的做法
class UserController extends Resources
{
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        // 1. 只选择需要的字段
        $query->select(['id', 'name', 'email', 'created_at']);

        // 2. 合理使用索引
        $query->where('status', 'active') // 确保 status 有索引
              ->where('created_at', '>', now()->subDays(30)); // 确保 created_at 有索引

        // 3. 预加载关联以避免 N+1 问题
        $query->with([
            'profile:user_id,avatar,bio',
            'roles:id,name'
        ]);

        // 4. 使用计数而不是加载数据
        $query->withCount(['posts', 'comments']);

        // 5. 合理的分页
        // 由框架自动处理
    }
}

// ❌ 避免的做法
class BadUserController extends Resources
{
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        // 获取所有字段
        $query->select('*');

        // 没有使用索引的条件
        $query->where(User::query()->raw('UPPER(name)'), 'JOHN');

        // 过度预加载
        $query->with('posts.comments.user.profile.avatar');

        // 在应用层处理数据
        $query->get()->filter(function ($user) {
            return $user->posts->count() > 5;
        });
    }
}
```

### 批量操作优化

```php
// 批量插入
$userData = [
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com'],
    // ... 更多数据
];

User::query()->insert($userData);

// 批量更新
User::query()->where('status', 'pending')->update(['status' => 'active']);

// 使用 upsert（MySQL 5.7+）
User::query()->upsert(
    [
        ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
    ],
    ['id'], // 唯一标识符
    ['name', 'email'] // 要更新的字段
);
```

## 最佳实践

### 查询构建规范

```php
// 1. 使用 query() 方法获得代码提示
$users = User::query()->where('status', 1)->get();

// 2. 合理的方法链顺序
$users = User::query()
    ->select(['id', 'name', 'email'])           // 选择字段
    ->where('status', 'active')                // 条件筛选
    ->whereHas('posts', function($q) {          // 关联条件
        $q->where('published', true);
    })
    ->with(['profile', 'roles'])               // 预加载
    ->withCount('posts')                       // 计数
    ->orderBy('created_at', 'desc')            // 排序
    ->limit(20)                                // 限制
    ->get();                                   // 执行

// 3. 复杂条件使用闭包分组
$users = User::query()
    ->where('status', 'active')
    ->where(function (Builder $query) use ($search) {
        $query->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
    })
    ->get();
```

### 安全注意事项

```php
// ✅ 使用参数绑定
$users = User::query()->whereRaw('age > ? AND city = ?', [25, $city])->get();

// ❌ 避免 SQL 注入
$users = User::query()->whereRaw("age > {$age} AND city = '{$city}'")->get();

// ✅ 验证用户输入
$allowedSorts = ['name', 'email', 'created_at'];
$sortField = in_array($request->input('sort'), $allowedSorts)
    ? $request->input('sort')
    : 'created_at';

$users = User::query()->orderBy($sortField)->get();
```

DuxLite 的查询构建器继承了 Laravel Eloquent 的所有强大功能，通过使用 `query()` 方法，你可以获得完整的 IDE 支持，构建出高效、安全、可维护的数据库查询。