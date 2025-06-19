# 性能优化

DuxLite 应用性能优化实用指南。

## 数据库优化

### 查询优化

```php
// ❌ 低效查询
$posts = Post::all(); // 加载所有数据
foreach ($posts as $post) {
    echo $post->user->name; // N+1 查询问题
}

// ✅ 优化查询
$posts = Post::select(['id', 'title', 'user_id'])
    ->with('user:id,name') // 预加载关联
    ->where('status', 'published')
    ->paginate(20); // 使用分页
```

### 索引使用

```php
// 在迁移中添加索引
$table->index('user_id');
$table->index('status');
$table->index(['status', 'created_at']); // 复合索引

// 查询时利用索引
$posts = Post::where('status', 'published') // 使用索引
    ->where('created_at', '>=', now()->subDays(7)) // 使用索引
    ->get();
```

## 缓存优化

### 基础缓存

```php
// 缓存查询结果
public function getPopularPosts(): array
{
    return App::cache()->remember('popular_posts', 3600, function () {
        return Post::where('status', 'published')
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    });
}

// 更新时清除缓存
public function updatePost(int $id, array $data): void
{
    Post::find($id)->update($data);
    App::cache()->delete('popular_posts');
}
```

### 配置缓存

```toml
# config/use.toml
[cache]
default = "redis"

[cache.redis]
host = "127.0.0.1"
port = 6379
database = 0
```

## 队列优化

### 异步处理

```php
// 耗时操作使用队列
class EmailService
{
    public function sendWelcomeEmail(User $user): void
    {
        // 推入队列异步处理
        App::queue()->push(new SendEmailJob($user->email, '欢迎注册'));
    }
}

class SendEmailJob extends QueueMessage
{
    public function __construct(
        private string $email,
        private string $subject
    ) {}

    public function handle(): void
    {
        // 发送邮件逻辑
        mail($this->email, $this->subject, '欢迎内容');
    }
}
```

### 队列配置

```toml
# config/use.toml
[queue]
default = "database"

[queue.database]
table = "queue_jobs"
```

## 应用优化

### 响应优化

```php
// 只返回需要的字段
public function getUsers(): array
{
    return User::select(['id', 'name', 'email'])
        ->where('status', 'active')
        ->get()
        ->toArray();
}

// 使用资源控制器
#[Resource(route: '/api/users')]
class UserController extends Resources
{
    protected string $model = User::class;

    // 自动处理分页、排序、筛选
}
```

### 静态文件

```php
// 设置缓存头
public function serveAsset(string $file): ResponseInterface
{
    $response = new Response();
    $response->getBody()->write(file_get_contents($file));

    return $response
        ->withHeader('Cache-Control', 'public, max-age=31536000')
        ->withHeader('Expires', gmdate('D, d M Y H:i:s T', time() + 31536000));
}
```

## 监控和调试

### 慢查询日志

```php
// 启用查询日志
if (App::config('app.debug')) {
    App::db()->listen(function ($query) {
        if ($query->time > 100) { // 超过100ms
            App::log()->warning('慢查询', [
                'sql' => $query->sql,
                'time' => $query->time . 'ms'
            ]);
        }
    });
}
```

### 性能监控

```php
class PerformanceMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = microtime(true);

        $response = $handler->handle($request);

        $time = round((microtime(true) - $start) * 1000, 2);

        if ($time > 1000) { // 超过1秒
            App::log()->warning('慢请求', [
                'uri' => (string) $request->getUri(),
                'time' => $time . 'ms'
            ]);
        }

        return $response->withHeader('X-Response-Time', $time . 'ms');
    }
}
```

## 生产环境优化

### PHP 配置

```ini
; php.ini 生产环境配置
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
```

### 应用配置

```toml
# config/use.toml 生产环境
[app]
debug = false
env = "production"

[log]
level = "warning"
```

### 清理缓存

```bash
# 清理应用缓存
php dux cache:clear

# 重启队列进程
php dux queue:restart
```

## 常见性能问题

### 1. N+1 查询问题

```php
// ❌ 问题代码
$users = User::all();
foreach ($users as $user) {
    echo $user->profile->bio; // 每次都查询
}

// ✅ 解决方案
$users = User::with('profile')->get();
foreach ($users as $user) {
    echo $user->profile->bio; // 使用预加载的数据
}
```

### 2. 内存泄漏

```php
// ❌ 内存问题
$users = User::all(); // 加载所有数据到内存

// ✅ 分批处理
User::chunk(100, function ($users) {
    foreach ($users as $user) {
        // 处理用户
    }
});
```

### 3. 缓存穿透

```php
// ✅ 防止缓存穿透
public function getUser(int $id): ?User
{
    $cacheKey = "user_{$id}";

    return App::cache()->remember($cacheKey, 3600, function () use ($id) {
        $user = User::find($id);
        return $user ?: 'null'; // 缓存空结果
    });
}
```

通过这些简单实用的优化策略，可以显著提升 DuxLite 应用的性能。