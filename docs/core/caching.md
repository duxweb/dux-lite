# 缓存系统

DuxLite 提供了强大的缓存系统，基于 Symfony Cache 组件，支持文件和 Redis 两种缓存驱动，实现了 PSR-16 简单缓存接口标准。缓存系统能够显著提升应用性能，减少数据库查询和计算开销。

## 系统概述

### 缓存架构

DuxLite 的缓存系统采用统一接口、多驱动架构：

```
应用层 → PSR-16 接口 → 缓存适配器 → 存储后端（文件/Redis）
```

### 核心组件

- **Cache**：缓存管理器，统一缓存接口
- **FilesystemAdapter**：文件系统缓存适配器
- **RedisAdapter**：Redis 缓存适配器
- **PSR-16 接口**：标准化的缓存操作接口

## 缓存配置

### 配置文件设置

缓存配置在 `config/use.toml` 文件中：

```toml
[cache]
# 缓存类型：file（文件缓存）或 redis（Redis缓存）
type = "file"

# 缓存前缀（避免命名冲突）
prefix = "duxlite_"

# 默认缓存生存时间（秒，0表示永不过期）
defaultLifetime = 3600
```

### 文件缓存配置

```toml
[cache]
type = "file"
prefix = "app_cache_"
defaultLifetime = 7200

# 文件缓存存储在 data/cache/ 目录中
# 目录会自动创建，确保有写入权限
```

### Redis 缓存配置

```toml
[cache]
type = "redis"
prefix = "cache:"
defaultLifetime = 3600

# Redis 连接配置在 database.toml 中
```

在 `config/database.toml` 中配置 Redis 连接：

```toml
[redis.drivers.default]
host = "localhost"
port = 6379
password = ""
database = 1                 # 使用专门的缓存数据库
timeout = 2.5
optPrefix = "cache_"

# 专用缓存 Redis
[redis.drivers.cache]
host = "localhost"
port = 6379
password = ""
database = 2
timeout = 2.5
optPrefix = "app_cache_"
```

## 基础用法

### 获取缓存实例

```php
use Core\App;

// 获取默认缓存（从 use.toml 读取类型）
$cache = App::cache();

// 获取指定类型的缓存
$fileCache = App::cache('file');
$redisCache = App::cache('redis');
```

### 基本缓存操作

```php
use Core\App;

class UserService
{
    private $cache;

    public function __construct()
    {
        $this->cache = App::cache();
    }

    public function getUserById(int $id): ?array
    {
        $key = "user:{$id}";

        // 尝试从缓存获取
        $user = $this->cache->get($key);

        if ($user === null) {
            // 缓存未命中，从数据库获取
            $user = User::find($id);

            if ($user) {
                // 存储到缓存，有效期 1 小时
                $this->cache->set($key, $user->toArray(), 3600);
            }
        }

        return $user;
    }

    public function updateUser(int $id, array $data): bool
    {
        // 更新数据库
        $result = User::where('id', $id)->update($data);

        if ($result) {
            // 删除缓存
            $this->cache->delete("user:{$id}");

            // 或者更新缓存
            $user = User::find($id);
            $this->cache->set("user:{$id}", $user->toArray(), 3600);
        }

        return $result;
    }
}
```

### 批量缓存操作

```php
class CacheService
{
    private $cache;

    public function __construct()
    {
        $this->cache = App::cache();
    }

    public function getMultiple(array $keys): array
    {
        // 批量获取
        return $this->cache->getMultiple($keys);
    }

    public function setMultiple(array $values, int $ttl = 3600): bool
    {
        // 批量设置
        return $this->cache->setMultiple($values, $ttl);
    }

    public function deleteMultiple(array $keys): bool
    {
        // 批量删除
        return $this->cache->deleteMultiple($keys);
    }

    public function getUsersData(array $userIds): array
    {
        // 生成缓存键
        $keys = array_map(fn($id) => "user:{$id}", $userIds);

        // 批量获取
        $cached = $this->cache->getMultiple($keys);

        // 找出未命中的键
        $missingIds = [];
        foreach ($userIds as $id) {
            $key = "user:{$id}";
            if (!isset($cached[$key]) || $cached[$key] === null) {
                $missingIds[] = $id;
            }
        }

        // 从数据库获取未命中的数据
        if (!empty($missingIds)) {
            $users = User::whereIn('id', $missingIds)->get();
            $toCache = [];

            foreach ($users as $user) {
                $key = "user:{$user->id}";
                $userData = $user->toArray();
                $cached[$key] = $userData;
                $toCache[$key] = $userData;
            }

            // 批量缓存新数据
            if (!empty($toCache)) {
                $this->cache->setMultiple($toCache, 3600);
            }
        }

        return $cached;
    }
}
```

## 高级用法

### 缓存穿透防护

```php
class AntiCacheBreakthrough
{
    private $cache;

    public function __construct()
    {
        $this->cache = App::cache();
    }

    public function getUserSafely(int $id): ?array
    {
        $key = "user:{$id}";
        $nullKey = "user_null:{$id}";

        // 检查是否有空值标记
        if ($this->cache->has($nullKey)) {
            return null;
        }

        // 尝试获取数据
        $user = $this->cache->get($key);

        if ($user === null) {
            $user = User::find($id);

            if ($user) {
                // 缓存用户数据
                $this->cache->set($key, $user->toArray(), 3600);
            } else {
                // 缓存空值，防止缓存穿透
                $this->cache->set($nullKey, true, 300); // 5分钟
            }
        }

        return $user;
    }
}
```

### 缓存预热

```php
class CacheWarmer
{
    private $cache;

    public function __construct()
    {
        $this->cache = App::cache();
    }

    public function warmUpUserCache(): void
    {
        // 预热活跃用户缓存
        $activeUsers = User::where('last_login_at', '>=', now()->subDays(7))
            ->limit(1000)
            ->get();

        $cacheData = [];
        foreach ($activeUsers as $user) {
            $key = "user:{$user->id}";
            $cacheData[$key] = $user->toArray();
        }

        // 批量设置缓存
        $this->cache->setMultiple($cacheData, 3600);

        echo "已预热 " . count($cacheData) . " 个用户缓存\n";
    }

    public function warmUpConfigCache(): void
    {
        // 预热配置缓存
        $configs = [
            'site_settings' => $this->getSiteSettings(),
            'menu_items' => $this->getMenuItems(),
            'permissions' => $this->getPermissions()
        ];

        foreach ($configs as $key => $data) {
            $this->cache->set($key, $data, 86400); // 24小时
        }

        echo "配置缓存预热完成\n";
    }

    private function getSiteSettings(): array
    {
        // 获取站点设置
        return Setting::pluck('value', 'key')->toArray();
    }

    private function getMenuItems(): array
    {
        // 获取菜单项
        return Menu::with('children')->where('parent_id', 0)->get()->toArray();
    }

    private function getPermissions(): array
    {
        // 获取权限列表
        return Permission::all()->toArray();
    }
}
```

### 缓存标签系统

```php
class TaggedCache
{
    private $cache;

    public function __construct()
    {
        $this->cache = App::cache();
    }

    public function setWithTags(string $key, $value, array $tags = [], int $ttl = 3600): bool
    {
        // 设置缓存
        $result = $this->cache->set($key, $value, $ttl);

        // 关联标签
        foreach ($tags as $tag) {
            $this->addKeyToTag($tag, $key);
        }

        return $result;
    }

    public function clearByTag(string $tag): bool
    {
        $tagKey = "tag:{$tag}";
        $keys = $this->cache->get($tagKey, []);

        if (!empty($keys)) {
            // 删除标签下的所有键
            $this->cache->deleteMultiple($keys);

            // 删除标签本身
            $this->cache->delete($tagKey);
        }

        return true;
    }

    public function clearByTags(array $tags): bool
    {
        foreach ($tags as $tag) {
            $this->clearByTag($tag);
        }

        return true;
    }

    private function addKeyToTag(string $tag, string $key): void
    {
        $tagKey = "tag:{$tag}";
        $keys = $this->cache->get($tagKey, []);

        if (!in_array($key, $keys)) {
            $keys[] = $key;
            $this->cache->set($tagKey, $keys, 86400); // 标签24小时有效
        }
    }

    // 使用示例
    public function cacheProductData(int $productId, array $data): void
    {
        $key = "product:{$productId}";
        $tags = ['products', "category:{$data['category_id']}", "brand:{$data['brand_id']}"];

        $this->setWithTags($key, $data, $tags);
    }

    public function clearProductCacheByCategory(int $categoryId): void
    {
        $this->clearByTag("category:{$categoryId}");
    }
}
```

## 缓存策略模式

### 懒加载缓存

```php
class LazyCache
{
    private $cache;

    public function __construct()
    {
        $this->cache = App::cache();
    }

    public function remember(string $key, int $ttl, callable $callback)
    {
        $value = $this->cache->get($key);

        if ($value === null) {
            $value = $callback();
            $this->cache->set($key, $value, $ttl);
        }

        return $value;
    }

    public function rememberForever(string $key, callable $callback)
    {
        return $this->remember($key, 0, $callback);
    }

    public function forget(string $key): bool
    {
        return $this->cache->delete($key);
    }

    // 使用示例
    public function getExpensiveData(int $id)
    {
        return $this->remember("expensive_data:{$id}", 3600, function() use ($id) {
            // 执行昂贵的计算或查询
            sleep(2); // 模拟耗时操作
            return "计算结果: {$id}";
        });
    }
}
```

### 写入时缓存

```php
class WriteThrough
{
    private $cache;

    public function __construct()
    {
        $this->cache = App::cache();
    }

    public function updateUser(int $id, array $data): bool
    {
        // 1. 更新数据库
        $result = User::where('id', $id)->update($data);

        if ($result) {
            // 2. 立即更新缓存
            $user = User::find($id);
            $this->cache->set("user:{$id}", $user->toArray(), 3600);

            // 3. 更新相关缓存
            $this->updateRelatedCache($id, $user);
        }

        return $result;
    }

    private function updateRelatedCache(int $id, $user): void
    {
        // 更新用户列表缓存
        $this->cache->delete('users:list');

        // 更新用户统计缓存
        $this->cache->delete('users:stats');

        // 更新部门用户缓存
        if (isset($user->department_id)) {
            $this->cache->delete("department:{$user->department_id}:users");
        }
    }
}
```

### 回写缓存

```php
class WriteBack
{
    private $cache;
    private $dirtyKeys = [];

    public function __construct()
    {
        $this->cache = App::cache();
    }

    public function updateUserInCache(int $id, array $data): void
    {
        $key = "user:{$id}";
        $user = $this->cache->get($key, []);

        // 更新缓存中的数据
        $user = array_merge($user, $data);
        $this->cache->set($key, $user, 3600);

        // 标记为脏数据
        $this->dirtyKeys[] = $key;
    }

    public function flush(): void
    {
        foreach ($this->dirtyKeys as $key) {
            $userData = $this->cache->get($key);

            if ($userData) {
                // 从缓存键提取ID
                $id = (int)str_replace('user:', '', $key);

                // 写回数据库
                User::where('id', $id)->update($userData);
            }
        }

        // 清空脏数据标记
        $this->dirtyKeys = [];
    }

    public function __destruct()
    {
        // 对象销毁时自动刷新
        $this->flush();
    }
}
```

## 缓存监控和调试

### 缓存统计

```php
class CacheStats
{
    private $cache;
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];

    public function __construct()
    {
        $this->cache = App::cache();
    }

    public function get(string $key, $default = null)
    {
        $value = $this->cache->get($key, $default);

        if ($value !== $default) {
            $this->stats['hits']++;
        } else {
            $this->stats['misses']++;
        }

        return $value;
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $this->stats['sets']++;
        return $this->cache->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        $this->stats['deletes']++;
        return $this->cache->delete($key);
    }

    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) * 100 : 0;

        return array_merge($this->stats, [
            'total_requests' => $total,
            'hit_rate' => round($hitRate, 2) . '%'
        ]);
    }

    public function resetStats(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0
        ];
    }
}
```

### 缓存调试工具

```php
class CacheDebugger
{
    private $cache;
    private $debugMode = false;

    public function __construct(bool $debugMode = false)
    {
        $this->cache = App::cache();
        $this->debugMode = $debugMode;
    }

    public function get(string $key, $default = null)
    {
        $start = microtime(true);
        $value = $this->cache->get($key, $default);
        $time = microtime(true) - $start;

        if ($this->debugMode) {
            $this->log('GET', $key, $value !== $default ? 'HIT' : 'MISS', $time);
        }

        return $value;
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $start = microtime(true);
        $result = $this->cache->set($key, $value, $ttl);
        $time = microtime(true) - $start;

        if ($this->debugMode) {
            $this->log('SET', $key, $result ? 'SUCCESS' : 'FAILED', $time, $ttl);
        }

        return $result;
    }

    public function has(string $key): bool
    {
        $start = microtime(true);
        $exists = $this->cache->has($key);
        $time = microtime(true) - $start;

        if ($this->debugMode) {
            $this->log('HAS', $key, $exists ? 'TRUE' : 'FALSE', $time);
        }

        return $exists;
    }

    private function log(string $operation, string $key, string $result, float $time, int $ttl = null): void
    {
        $message = sprintf(
            '[CACHE] %s %s -> %s (%.4fms)',
            $operation,
            $key,
            $result,
            $time * 1000
        );

        if ($ttl !== null) {
            $message .= " TTL: {$ttl}s";
        }

        error_log($message);
    }
}
```

## 缓存清理和维护

### 定期清理任务

```php
use Core\Scheduler\Attribute\Scheduler;

class CacheMaintenanceTasks
{
    #[Scheduler('0 2 * * *')]  // 每天凌晨2点
    public function cleanExpiredCache(): void
    {
        $cache = App::cache();

        // 对于文件缓存，清理过期文件
        if ($cache instanceof FilesystemAdapter) {
            $this->cleanFileCache();
        }

        // 记录清理日志
        error_log("缓存清理任务完成: " . date('Y-m-d H:i:s'));
    }

    #[Scheduler('0 3 * * 0')]  // 每周日凌晨3点
    public function optimizeCache(): void
    {
        $cache = App::cache();

        // 清除所有缓存
        $cache->clear();

        // 预热重要缓存
        $warmer = new CacheWarmer();
        $warmer->warmUpUserCache();
        $warmer->warmUpConfigCache();

        error_log("缓存优化任务完成: " . date('Y-m-d H:i:s'));
    }

    private function cleanFileCache(): void
    {
        $cacheDir = base_path('data/cache');

        if (!is_dir($cacheDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheDir)
        );

        $deletedCount = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() !== '.gitkeep') {
                // 检查文件是否过期（这里简化处理）
                if (time() - $file->getMTime() > 86400) { // 24小时
                    unlink($file->getPathname());
                    $deletedCount++;
                }
            }
        }

        error_log("已清理 {$deletedCount} 个过期缓存文件");
    }
}
```

### 缓存管理命令

```php
use Core\Command\Attribute\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command('cache:clear', '清理缓存')]
class CacheClearCommand extends \Core\Command\Command
{
    protected function configure(): void
    {
        $this->addArgument('type', InputArgument::OPTIONAL, '缓存类型 (file|redis|all)', 'all');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type');

        switch ($type) {
            case 'file':
                $this->clearFileCache($output);
                break;
            case 'redis':
                $this->clearRedisCache($output);
                break;
            case 'all':
            default:
                $this->clearAllCache($output);
                break;
        }

        return self::SUCCESS;
    }

    private function clearFileCache(OutputInterface $output): void
    {
        $cache = App::cache('file');
        $cache->clear();
        $output->writeln('<info>文件缓存已清理</info>');
    }

    private function clearRedisCache(OutputInterface $output): void
    {
        $cache = App::cache('redis');
        $cache->clear();
        $output->writeln('<info>Redis缓存已清理</info>');
    }

    private function clearAllCache(OutputInterface $output): void
    {
        // 清理所有类型的缓存
        $this->clearFileCache($output);
        $this->clearRedisCache($output);
        $output->writeln('<comment>所有缓存已清理完成</comment>');
    }
}

#[Command('cache:warm', '预热缓存')]
class CacheWarmCommand extends \Core\Command\Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>开始预热缓存...</info>');

        $warmer = new CacheWarmer();
        $warmer->warmUpUserCache();
        $warmer->warmUpConfigCache();

        $output->writeln('<comment>缓存预热完成</comment>');

        return self::SUCCESS;
    }
}
```

## 与其他系统集成

### 与模型系统集成

```php
trait Cacheable
{
    protected static function bootCacheable(): void
    {
        static::updated(function ($model) {
            $model->clearCache();
        });

        static::deleted(function ($model) {
            $model->clearCache();
        });
    }

    public function getCached(string $key, callable $callback, int $ttl = 3600)
    {
        $cache = App::cache();
        $cacheKey = $this->getCacheKey($key);

        return $cache->get($cacheKey) ?? tap($callback(), function ($value) use ($cache, $cacheKey, $ttl) {
            $cache->set($cacheKey, $value, $ttl);
        });
    }

    public function clearCache(): void
    {
        $cache = App::cache();
        $pattern = $this->getCacheKey('*');

        // 这里需要根据缓存驱动实现具体的清理逻辑
        // 对于简单实现，可以维护一个键列表
    }

    protected function getCacheKey(string $suffix): string
    {
        $table = $this->getTable();
        $id = $this->getKey();

        return "{$table}:{$id}:{$suffix}";
    }
}

// 在模型中使用
class User extends Model
{
    use Cacheable;

    public function getProfileData(): array
    {
        return $this->getCached('profile', function () {
            return [
                'posts_count' => $this->posts()->count(),
                'comments_count' => $this->comments()->count(),
                'last_post' => $this->posts()->latest()->first()
            ];
        }, 3600);
    }
}
```

### 与事件系统集成

```php
use Core\Event\Attribute\Listener;

class CacheEventListener
{
    #[Listener('user.updated')]
    public function clearUserCache($user): void
    {
        $cache = App::cache();

        // 清理用户相关缓存
        $cache->delete("user:{$user->id}");
        $cache->delete("user:{$user->id}:profile");
        $cache->delete("user:{$user->id}:permissions");

        // 清理列表缓存
        $cache->delete('users:list');
        $cache->delete('users:active');
    }

    #[Listener('post.published')]
    public function updatePostCache($post): void
    {
        $cache = App::cache();

        // 清理文章列表缓存
        $cache->delete('posts:latest');
        $cache->delete("posts:category:{$post->category_id}");

        // 缓存热门文章
        $cache->set("post:{$post->id}", $post->toArray(), 7200);
    }
}
```

## 最佳实践

### 缓存键命名约定

```php
class CacheKeyHelper
{
    public const NAMESPACE_USER = 'user';
    public const NAMESPACE_POST = 'post';
    public const NAMESPACE_CONFIG = 'config';

    public static function userKey(int $id, string $suffix = ''): string
    {
        return self::buildKey(self::NAMESPACE_USER, $id, $suffix);
    }

    public static function postKey(int $id, string $suffix = ''): string
    {
        return self::buildKey(self::NAMESPACE_POST, $id, $suffix);
    }

    public static function configKey(string $name): string
    {
        return self::buildKey(self::NAMESPACE_CONFIG, $name);
    }

    private static function buildKey(string $namespace, ...$parts): string
    {
        $parts = array_filter($parts, fn($part) => $part !== '');
        return $namespace . ':' . implode(':', $parts);
    }
}

// 使用示例
$cache = App::cache();

// 用户数据
$cache->set(CacheKeyHelper::userKey(123), $userData);
$cache->set(CacheKeyHelper::userKey(123, 'profile'), $profileData);

// 文章数据
$cache->set(CacheKeyHelper::postKey(456), $postData);
$cache->set(CacheKeyHelper::postKey(456, 'comments'), $commentsData);

// 配置数据
$cache->set(CacheKeyHelper::configKey('site_settings'), $settings);
```

### 缓存时间策略

```php
class CacheTTL
{
    public const MINUTE = 60;
    public const HOUR = 3600;
    public const DAY = 86400;
    public const WEEK = 604800;
    public const MONTH = 2592000;

    // 根据数据特性选择合适的缓存时间
    public const USER_PROFILE = self::HOUR * 2;      // 用户资料：2小时
    public const USER_PERMISSIONS = self::HOUR * 6;   // 用户权限：6小时
    public const CONFIG_SETTINGS = self::DAY;         // 配置信息：1天
    public const POST_CONTENT = self::HOUR * 12;      // 文章内容：12小时
    public const POST_LIST = self::MINUTE * 30;       // 文章列表：30分钟
    public const STATISTICS = self::HOUR;             // 统计数据：1小时
}

// 使用示例
$cache->set('user:123:profile', $data, CacheTTL::USER_PROFILE);
$cache->set('posts:latest', $posts, CacheTTL::POST_LIST);
```

### 缓存一致性保证

```php
class CacheConsistency
{
    private $cache;

    public function __construct()
    {
        $this->cache = App::cache();
    }

    public function updateUserWithCache(int $id, array $data): bool
    {
        // 使用分布式锁保证一致性
        $lockKey = "lock:user:{$id}";

        if ($this->cache->set($lockKey, 1, 10)) { // 10秒锁
            try {
                // 1. 删除旧缓存
                $this->clearUserCache($id);

                // 2. 更新数据库
                $result = User::where('id', $id)->update($data);

                if ($result) {
                    // 3. 设置新缓存
                    $user = User::find($id);
                    $this->cache->set("user:{$id}", $user->toArray(), CacheTTL::USER_PROFILE);
                }

                return $result;

            } finally {
                // 释放锁
                $this->cache->delete($lockKey);
            }
        }

        return false; // 获取锁失败
    }

    private function clearUserCache(int $id): void
    {
        $keys = [
            "user:{$id}",
            "user:{$id}:profile",
            "user:{$id}:permissions"
        ];

        $this->cache->deleteMultiple($keys);
    }
}
```

## 故障排除

### 常见问题诊断

#### 1. 缓存连接问题

```bash
# 检查文件缓存目录权限
ls -la data/cache/

# 检查 Redis 连接
redis-cli ping

# 测试缓存功能
php -r "
$cache = \Core\App::cache();
$cache->set('test', 'hello');
echo $cache->get('test');
"
```

#### 2. 缓存性能问题

```php
// 缓存命中率监控
class CacheMonitor
{
    public function checkHitRate(): array
    {
        $stats = new CacheStats();

        // 模拟一些缓存操作
        for ($i = 0; $i < 100; $i++) {
            $stats->get("test_key_{$i}");
        }

        return $stats->getStats();
    }
}
```

#### 3. 内存使用监控

```php
// 监控缓存内存使用
function getCacheMemoryUsage(): array
{
    $cache = App::cache();

    if ($cache instanceof RedisAdapter) {
        $redis = App::redis();
        $info = $redis->info('memory');

        return [
            'used_memory' => $info['used_memory_human'] ?? 'N/A',
            'used_memory_peak' => $info['used_memory_peak_human'] ?? 'N/A'
        ];
    }

    return ['type' => 'file', 'memory' => 'N/A'];
}
```

DuxLite 的缓存系统为应用程序提供了高效的数据缓存能力，通过合理使用缓存策略，可以显著提升应用性能和用户体验。