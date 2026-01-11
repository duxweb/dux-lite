# 性能优化

DuxLite 应用性能优化的最佳实践，基于框架内置功能实现高性能应用。

## 缓存优化

### 缓存配置

DuxLite 支持文件缓存和 Redis 缓存：

```toml
# config/cache.toml
[cache.drivers.default]
driver = "file"
prefix = "dux_"
defaultLifetime = 3600

[cache.drivers.redis]
driver = "redis"
prefix = "dux_"
defaultLifetime = 3600
```

### 缓存使用

```php
// 基本缓存操作
$cache = \Core\App::cache();

// 获取缓存
$data = $cache->get('user:1');

// 设置缓存
$cache->set('user:1', $userData, 3600);

// 删除缓存
$cache->delete('user:1');

// 使用不同缓存驱动
$redisCache = \Core\App::cache('redis');
```

### 查询结果缓存

```php
class UserRepository
{
    public function getActiveUsers(): array
    {
        $cacheKey = 'active_users';
        $cached = \Core\App::cache()->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $users = User::where('is_active', true)
                    ->select('id', 'name', 'email')
                    ->get()
                    ->map(fn($user) => $user->transform())
                    ->toArray();
                    
        \Core\App::cache()->set($cacheKey, $users, 1800);
        return $users;
    }
}
```

## 数据库优化

### 查询优化

```php
// ✅ 预加载关联数据避免 N+1 查询
$users = User::with(['profile', 'orders'])->get();

// ✅ 只选择需要的字段
User::select('id', 'name', 'email')->get();

// ✅ 使用框架分页
$list = User::paginate();
["data" => $data, "meta" => $meta] = format_data($list, function ($item) {
    return $item->transform();
});
```

### 慢查询监控

```php
// 在模块 App.php 中监控慢查询
public function init(Bootstrap $bootstrap): void
{
    \Core\App::db()->listen(function ($query) {
        if ($query->time > 1000) { // 超过1秒
            \Core\App::log()->warning('Slow query detected', [
                'sql' => $query->sql,
                'time' => $query->time . 'ms',
            ]);
        }
    });
}
```

## 队列优化

### 队列配置

```toml
# config/queue.toml
default = "queueA"

[workers.queueA]
type = "redis"
driver = "default"
num = 4
high = 1
medium = 2
low = 1
```

### 异步任务处理

```php
// 创建队列任务
class EmailJob
{
    public function handle(array $users): void
    {
        foreach ($users as $user) {
            // 发送邮件逻辑
            $this->sendEmail($user);
        }
    }
}

// 推送到队列
\Core\App::queue()->add(EmailJob::class, 'handle', [$users])->send();

// 处理队列
\Core\App::queue()->process();
```

### 批量处理

```php
// 批量处理避免内存溢出
class DataExportService
{
    public function exportUsers(): void
    {
        User::chunk(1000, function ($users) {
            foreach ($users as $user) {
                $this->processUser($user);
            }
        });
    }
}
```

## 日志优化

### 合理使用日志级别

```php
// 根据环境使用不同日志级别
\Core\App::log()->debug('Debug information', $data);
\Core\App::log()->info('General information', $data);
\Core\App::log()->warning('Warning message', $data);
\Core\App::log()->error('Error occurred', $data);
```

## 配置优化

### 环境配置

```toml
# config/use.toml - 生产环境
[app]
debug = false
secret = "%env(APP_SECRET)%"

# config/use.dev.toml - 开发环境
[app]
debug = true
secret = "dev-secret-key"
```

### 缓存配置文件

```bash
# 缓存配置文件提升启动速度
php dux config:cache
```

## Worker 模式

### 启用 Worker 模式

```bash
# 启动 Worker 提升性能
./dux worker:start --port=8080 --workers=4
```

Worker 模式将应用常驻内存，提供 3-10x 性能提升。

### Worker 注意事项

```php
// ✅ 使用外部缓存存储状态
class GoodService
{
    public function getData($key)
    {
        return \Core\App::cache()->get($key);
    }
}

// ❌ 避免静态变量污染
class BadService
{
    private static $cache = []; // 在 Worker 模式下会污染
}
```

## 生产环境优化

### Composer 优化

```bash
# 生产环境优化
composer install --no-dev --optimize-autoloader
```

### 服务器配置

```nginx
# Nginx 配置示例
server {
    # 启用 Gzip
    gzip on;
    gzip_types text/plain application/json application/javascript text/css;
    
    # 静态文件缓存
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

通过合理使用 DuxLite 的内置功能，可以显著提升应用性能和响应速度。
