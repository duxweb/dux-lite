# 缓存系统

DuxLite 基于 PSR-16 标准提供统一的缓存接口，支持文件、Redis、Memcached 等多种驱动。

## 缓存配置

配置文件：`config/cache.toml`

```toml
[default]
driver = "file"
path = "data/cache"

[redis]
driver = "redis"
connection = "default"
prefix = "cache:"

[session]
driver = "redis"
connection = "session"
prefix = "sess:"
```

## 基础操作

### 获取缓存实例

```php
use Core\App;

// 获取默认缓存驱动
$cache = App::cache();

// 获取指定缓存驱动
$redisCache = App::cache('redis');
$sessionCache = App::cache('session');
```

### 基本操作

```php
$cache = App::cache();

// 设置缓存
$cache->set('user.123', $userData, 3600); // 缓存1小时
$cache->set('config.app', $appConfig);     // 永久缓存

// 获取缓存
$userData = $cache->get('user.123');
$appConfig = $cache->get('config.app', []); // 带默认值

// 检查和删除
if ($cache->has('user.123')) {
    $cache->delete('user.123');
}

// 清空所有缓存
$cache->clear();
```

### 批量操作

```php
// 批量设置
$values = [
    'user.1' => $user1Data,
    'user.2' => $user2Data,
];
$cache->setMultiple($values, 3600);

// 批量获取和删除
$results = $cache->getMultiple(['user.1', 'user.2'], []);
$cache->deleteMultiple(['user.1', 'user.2']);
```


## 驱动特性

### 文件缓存
- 适用：小型应用、开发环境
- 特点：无需额外服务，读写较慢，单服务器部署

### Redis 缓存
- 适用：高性能应用、分布式系统
- 特点：高性能读写，支持数据结构，内存存储

### Memcached 缓存
- 适用：大型分布式应用
- 特点：专为缓存设计，分布式友好，LRU 自动清理

## 最佳实践

### 键名规范
```php
// ✅ 推荐格式
$cache->set('user.profile.123', $userData);
$cache->set('post.list.category_5.page_1', $posts);
$cache->set('config.app.settings', $settings);

// ❌ 避免格式
$cache->set('user info 123', $userData);    // 包含空格
$cache->set('post/list/1', $posts);         // 包含斜杠
```

### TTL 设置策略
```php
$cache->set('user.session.123', $sessionData, 1800);   // 会话：30分钟
$cache->set('user.profile.123', $userData, 3600);      // 用户资料：1小时
$cache->set('post.list.page_1', $posts, 600);          // 文章列表：10分钟
$cache->set('config.app.settings', $settings);         // 配置：永久
$cache->set('api.stats.realtime', $stats, 60);         // 实时统计：1分钟
```

### 错误处理
```php
try {
    $data = $cache->get($key);
    if ($data === null) {
        $data = $this->loadFromDatabase();
        $cache->set($key, $data, 3600);
    }
} catch (\Psr\SimpleCache\CacheException $e) {
    // 缓存异常时直接从数据库加载
    App::log()->warning('Cache error', ['error' => $e->getMessage()]);
    $data = $this->loadFromDatabase();
}
```

## PSR-16 接口方法

```php
// 基础操作
public function get(string $key, mixed $default = null): mixed
public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
public function delete(string $key): bool
public function clear(): bool
public function has(string $key): bool

// 批量操作
public function getMultiple(iterable $keys, mixed $default = null): iterable
public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
public function deleteMultiple(iterable $keys): bool
```

DuxLite 的缓存系统简单易用，通过合理的缓存策略可以显著提升应用程序性能。