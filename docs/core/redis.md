# Redis 集成

DuxLite 提供了完整的 Redis 集成支持，自动兼容 phpredis 和 predis 两种客户端，广泛应用于缓存、队列、会话存储、计数器等场景。

## 系统概述

### Redis 架构

DuxLite 的 Redis 集成采用适配器模式：

```
应用层 → Redis 管理器 → 适配器 → Redis 客户端（phpredis/predis）
```

### 核心组件

- **Redis**：Redis 连接管理器
- **PhpRedisAdapter**：原生 phpredis 扩展适配器
- **PredisAdapter**：纯 PHP predis 客户端适配器
- **自动检测**：优先使用 phpredis，不可用时回退到 predis

## Redis 配置

### 配置文件设置

Redis 配置在 `config/database.toml` 文件中：

```toml
# 默认 Redis 连接
[redis.drivers.default]
host = "localhost"
port = 6379
password = ""                # Redis 密码
database = 0                 # 数据库索引
timeout = 2.5               # 连接超时（秒）
optPrefix = ""              # 键前缀

# 缓存专用 Redis
[redis.drivers.cache]
host = "localhost"
port = 6379
password = ""
database = 1
timeout = 2.5
optPrefix = "cache_"

# 队列专用 Redis
[redis.drivers.queue]
host = "localhost"
port = 6379
password = ""
database = 2
timeout = 2.5
optPrefix = "queue_"

# 会话存储 Redis
[redis.drivers.session]
host = "localhost"
port = 6379
password = ""
database = 3
timeout = 2.5
optPrefix = "sess_"

# 集群配置示例
[redis.drivers.cluster]
host = "redis-cluster.example.com"
port = 6379
password = "cluster_password"
database = 0
timeout = 5.0
optPrefix = "app_"
```

### Redis 连接获取

```php
use Core\App;

// 获取默认 Redis 连接
$redis = App::redis();

// 获取指定连接
$cacheRedis = App::redis('cache');
$queueRedis = App::redis('queue');
$sessionRedis = App::redis('session');

// 指定数据库索引
$redis = App::redis('default', 5);
```

## 基础 Redis 操作

### 字符串操作

```php
class RedisStringService
{
    private $redis;

    public function __construct()
    {
        $this->redis = App::redis();
    }

    public function setValue(string $key, $value, int $ttl = 0): void
    {
        if ($ttl > 0) {
            $this->redis->setex($key, $ttl, $value);
        } else {
            $this->redis->set($key, $value);
        }
    }

    public function getValue(string $key): ?string
    {
        $value = $this->redis->get($key);
        return $value === false ? null : $value;
    }

    public function increment(string $key, int $by = 1): int
    {
        return $this->redis->incrby($key, $by);
    }

    public function decrement(string $key, int $by = 1): int
    {
        return $this->redis->decrby($key, $by);
    }

    public function exists(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    public function setExpire(string $key, int $seconds): bool
    {
        return $this->redis->expire($key, $seconds);
    }

    public function getTtl(string $key): int
    {
        return $this->redis->ttl($key);
    }
}
```

### 哈希操作

```php
class RedisHashService
{
    private $redis;

    public function __construct()
    {
        $this->redis = App::redis();
    }

    public function setHash(string $key, string $field, $value): void
    {
        $this->redis->hset($key, $field, $value);
    }

    public function getHash(string $key, string $field): ?string
    {
        $value = $this->redis->hget($key, $field);
        return $value === false ? null : $value;
    }

    public function setMultipleHash(string $key, array $data): void
    {
        $this->redis->hmset($key, $data);
    }

    public function getMultipleHash(string $key, array $fields): array
    {
        return $this->redis->hmget($key, $fields);
    }

    public function getAllHash(string $key): array
    {
        return $this->redis->hgetall($key);
    }

    public function deleteHashField(string $key, string $field): bool
    {
        return $this->redis->hdel($key, $field) > 0;
    }

    public function incrementHash(string $key, string $field, int $by = 1): int
    {
        return $this->redis->hincrby($key, $field, $by);
    }

    public function hashExists(string $key, string $field): bool
    {
        return $this->redis->hexists($key, $field);
    }

    public function getHashLength(string $key): int
    {
        return $this->redis->hlen($key);
    }
}
```

### 列表操作

```php
class RedisListService
{
    private $redis;

    public function __construct()
    {
        $this->redis = App::redis();
    }

    public function pushLeft(string $key, $value): int
    {
        return $this->redis->lpush($key, $value);
    }

    public function pushRight(string $key, $value): int
    {
        return $this->redis->rpush($key, $value);
    }

    public function popLeft(string $key): ?string
    {
        $value = $this->redis->lpop($key);
        return $value === false ? null : $value;
    }

    public function popRight(string $key): ?string
    {
        $value = $this->redis->rpop($key);
        return $value === false ? null : $value;
    }

    public function getRange(string $key, int $start = 0, int $end = -1): array
    {
        return $this->redis->lrange($key, $start, $end);
    }

    public function getLength(string $key): int
    {
        return $this->redis->llen($key);
    }

    public function trimList(string $key, int $start, int $end): void
    {
        $this->redis->ltrim($key, $start, $end);
    }

    public function blockingPopLeft(string $key, int $timeout = 0): ?array
    {
        $result = $this->redis->blpop($key, $timeout);
        return $result === false ? null : $result;
    }
}
```

### 集合操作

```php
class RedisSetService
{
    private $redis;

    public function __construct()
    {
        $this->redis = App::redis();
    }

    public function addToSet(string $key, $member): bool
    {
        return $this->redis->sadd($key, $member) > 0;
    }

    public function removeFromSet(string $key, $member): bool
    {
        return $this->redis->srem($key, $member) > 0;
    }

    public function isMember(string $key, $member): bool
    {
        return $this->redis->sismember($key, $member);
    }

    public function getAllMembers(string $key): array
    {
        return $this->redis->smembers($key);
    }

    public function getSetSize(string $key): int
    {
        return $this->redis->scard($key);
    }

    public function randomMember(string $key, int $count = 1): array
    {
        return $this->redis->srandmember($key, $count);
    }

    public function popMember(string $key): ?string
    {
        $value = $this->redis->spop($key);
        return $value === false ? null : $value;
    }

    public function intersect(array $keys): array
    {
        return $this->redis->sinter($keys);
    }

    public function union(array $keys): array
    {
        return $this->redis->sunion($keys);
    }

    public function difference(array $keys): array
    {
        return $this->redis->sdiff($keys);
    }
}
```

## 实际应用场景

### 分布式锁

```php
class RedisLock
{
    private $redis;
    private $lockKey;
    private $lockValue;
    private $acquired = false;

    public function __construct(string $resource, int $timeout = 10)
    {
        $this->redis = App::redis();
        $this->lockKey = "lock:{$resource}";
        $this->lockValue = uniqid();
        $this->timeout = $timeout;
    }

    public function acquire(): bool
    {
        $result = $this->redis->set(
            $this->lockKey,
            $this->lockValue,
            ['nx', 'ex' => $this->timeout]
        );

        $this->acquired = $result === true;
        return $this->acquired;
    }

    public function release(): bool
    {
        if (!$this->acquired) {
            return false;
        }

        // 使用 Lua 脚本确保原子性
        $script = "
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            else
                return 0
            end
        ";

        $result = $this->redis->eval($script, 1, $this->lockKey, $this->lockValue);
        $this->acquired = false;

        return $result > 0;
    }

    public function extend(int $additionalTime): bool
    {
        if (!$this->acquired) {
            return false;
        }

        $script = "
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('expire', KEYS[1], ARGV[2])
            else
                return 0
            end
        ";

        return $this->redis->eval($script, 1, $this->lockKey, $this->lockValue, $additionalTime) > 0;
    }

    public function __destruct()
    {
        $this->release();
    }
}

// 使用示例
function processWithLock(string $userId): void
{
    $lock = new RedisLock("user_process:{$userId}", 30);

    if ($lock->acquire()) {
        try {
            // 执行需要锁保护的操作
            processUserData($userId);
        } finally {
            $lock->release();
        }
    } else {
        throw new Exception('无法获取锁，请稍后重试');
    }
}
```

### 计数器和限流

```php
class RedisCounter
{
    private $redis;

    public function __construct()
    {
        $this->redis = App::redis();
    }

    public function increment(string $key, int $by = 1, int $ttl = 0): int
    {
        $count = $this->redis->incrby($key, $by);

        if ($ttl > 0 && $count === $by) {
            // 首次设置时添加过期时间
            $this->redis->expire($key, $ttl);
        }

        return $count;
    }

    public function rateLimit(string $identifier, int $maxRequests, int $windowSeconds): bool
    {
        $key = "rate_limit:{$identifier}";
        $current = $this->increment($key, 1, $windowSeconds);

        return $current <= $maxRequests;
    }

    public function slidingWindowRateLimit(string $identifier, int $maxRequests, int $windowSeconds): bool
    {
        $key = "sliding_limit:{$identifier}";
        $now = time();
        $windowStart = $now - $windowSeconds;

        // 清理过期记录
        $this->redis->zremrangebyscore($key, 0, $windowStart);

        // 添加当前请求
        $this->redis->zadd($key, $now, uniqid());

        // 设置过期时间
        $this->redis->expire($key, $windowSeconds + 1);

        // 检查当前窗口内的请求数
        $currentCount = $this->redis->zcard($key);

        return $currentCount <= $maxRequests;
    }

    public function getCount(string $key): int
    {
        $value = $this->redis->get($key);
        return $value === false ? 0 : (int)$value;
    }

    public function resetCount(string $key): void
    {
        $this->redis->del($key);
    }
}

// API 限流中间件
class RateLimitMiddleware
{
    private $counter;

    public function __construct()
    {
        $this->counter = new RedisCounter();
    }

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';

        // 每IP每分钟最多60次请求
        if (!$this->counter->rateLimit($clientIp, 60, 60)) {
            throw new ExceptionBusiness('请求过于频繁，请稍后重试', 429);
        }

        return $handler->handle($request);
    }
}
```

### 会话存储

```php
class RedisSessionHandler implements \SessionHandlerInterface
{
    private $redis;
    private $ttl;
    private $prefix;

    public function __construct(int $ttl = 1440)
    {
        $this->redis = App::redis('session');
        $this->ttl = $ttl;
        $this->prefix = 'session:';
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $data = $this->redis->get($this->prefix . $id);
        return $data === false ? '' : $data;
    }

    public function write(string $id, string $data): bool
    {
        return $this->redis->setex($this->prefix . $id, $this->ttl, $data);
    }

    public function destroy(string $id): bool
    {
        return $this->redis->del($this->prefix . $id) > 0;
    }

    public function gc(int $max_lifetime): int|false
    {
        // Redis 自动过期，无需手动清理
        return 0;
    }
}

// 启用 Redis 会话存储
session_set_save_handler(new RedisSessionHandler(), true);

// 会话管理中间件
class SessionMiddleware
{
    private $sessionHandler;

    public function __construct()
    {
        $this->sessionHandler = new RedisSessionHandler();
        session_set_save_handler($this->sessionHandler, true);
    }

    public function __invoke(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // 启动会话
        $this->startSession($request);

        // 将会话数据添加到请求属性
        $request = $request->withAttribute('session', $_SESSION);
        $request = $request->withAttribute('session_id', session_id());

        try {
            $response = $handler->handle($request);

            // 会话数据可能在处理过程中被修改，需要保存
            $this->saveSession();

            return $response;

        } catch (\Exception $e) {
            // 确保会话在异常情况下也能正确保存
            $this->saveSession();
            throw $e;
        }
    }

    private function startSession(ServerRequestInterface $request): void
    {
        // 从 Cookie 或 Header 获取会话 ID
        $sessionId = $this->getSessionId($request);

        if ($sessionId) {
            session_id($sessionId);
        }

        // 配置会话参数
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $request->getUri()->getScheme() === 'https',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }

    private function getSessionId(ServerRequestInterface $request): ?string
    {
        // 优先从 Cookie 获取
        $cookies = $request->getCookieParams();
        if (isset($cookies[session_name()])) {
            return $cookies[session_name()];
        }

        // 从 Authorization Header 获取（API 场景）
        $authHeader = $request->getHeaderLine('Authorization');
        if (strpos($authHeader, 'Session ') === 0) {
            return substr($authHeader, 8);
        }

        return null;
    }

    private function saveSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}

// 会话助手类
class SessionHelper
{
    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, $default = null)
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function regenerate(bool $deleteOldSession = true): string
    {
        session_regenerate_id($deleteOldSession);
        return session_id();
    }

    public static function destroy(): void
    {
        session_destroy();
        $_SESSION = [];
    }

    public static function getId(): string
    {
        return session_id();
    }

    public static function all(): array
    {
        return $_SESSION;
    }
}

// 在应用中使用会话中间件
class App extends AppExtend
{
    public function init(Bootstrap $bootstrap): void
    {
        $route = new Route();

        // 添加会话中间件到所有路由
        $route->group(['middleware' => [SessionMiddleware::class]], function($route) {
            // 用户认证路由
            $route->post('/login', [AuthController::class, 'login']);
            $route->post('/logout', [AuthController::class, 'logout']);
            $route->get('/profile', [UserController::class, 'profile']);
        });

        App::route()->set("web", $route);
    }
}

// 控制器中使用会话
class AuthController
{
    public function login(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = $request->getParsedBody();

        // 验证用户凭据
        $user = $this->validateCredentials($data['username'], $data['password']);

        if ($user) {
            // 登录成功，存储用户信息到会话
            SessionHelper::set('user_id', $user->id);
            SessionHelper::set('username', $user->username);
            SessionHelper::set('login_time', time());

            // 为安全起见，重新生成会话 ID
            SessionHelper::regenerate();

            // 设置成功消息
            SessionHelper::flash('success', '登录成功');

            return send($response, '登录成功', [
                'user' => $user->toArray(),
                'session_id' => SessionHelper::getId()
            ]);
        } else {
            throw new ExceptionBusiness('用户名或密码错误', 401);
        }
    }

    public function logout(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 清理会话数据
        SessionHelper::destroy();

        return send($response, '退出成功');
    }

    private function validateCredentials(string $username, string $password): ?User
    {
        // 实际的用户验证逻辑
        return User::where('username', $username)
            ->where('password', hash('sha256', $password))
            ->first();
    }
}

// 认证中间件
class AuthMiddleware
{
    public function __invoke(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $userId = SessionHelper::get('user_id');

        if (!$userId) {
            throw new ExceptionBusiness('请先登录', 401);
        }

        // 验证会话是否过期
        $loginTime = SessionHelper::get('login_time');
        if ($loginTime && (time() - $loginTime) > 7200) { // 2小时过期
            SessionHelper::destroy();
            throw new ExceptionBusiness('会话已过期，请重新登录', 401);
        }

        // 获取用户信息并添加到请求属性
        $user = User::find($userId);
        if (!$user) {
            SessionHelper::destroy();
            throw new ExceptionBusiness('用户不存在', 401);
        }

        $request = $request->withAttribute('user', $user);
        $request = $request->withAttribute('user_id', $userId);

        return $handler->handle($request);
    }
}
```

### 发布/订阅

```php
class RedisPubSub
{
    private $redis;

    public function __construct()
    {
        $this->redis = App::redis();
    }

    public function publish(string $channel, array $message): int
    {
        return $this->redis->publish($channel, json_encode($message));
    }

    public function subscribe(array $channels, callable $callback): void
    {
        $this->redis->subscribe($channels, function ($redis, $channel, $message) use ($callback) {
            $data = json_decode($message, true);
            $callback($channel, $data);
        });
    }

    public function psubscribe(array $patterns, callable $callback): void
    {
        $this->redis->psubscribe($patterns, function ($redis, $pattern, $channel, $message) use ($callback) {
            $data = json_decode($message, true);
            $callback($pattern, $channel, $data);
        });
    }
}

// 实时通知系统
class NotificationService
{
    private $pubsub;

    public function __construct()
    {
        $this->pubsub = new RedisPubSub();
    }

    public function sendUserNotification(int $userId, array $notification): void
    {
        $channel = "user_notifications:{$userId}";
        $this->pubsub->publish($channel, $notification);
    }

    public function sendGlobalNotification(array $notification): void
    {
        $this->pubsub->publish('global_notifications', $notification);
    }

    public function listenUserNotifications(int $userId, callable $handler): void
    {
        $channel = "user_notifications:{$userId}";
        $this->pubsub->subscribe([$channel], $handler);
    }

    public function listenAllNotifications(callable $handler): void
    {
        // 监听所有用户通知
        $this->pubsub->psubscribe(['user_notifications:*'], $handler);
    }
}
```

### 缓存模式

```php
class RedisCacheService
{
    private $redis;

    public function __construct()
    {
        $this->redis = App::redis('cache');
    }

    public function remember(string $key, int $ttl, callable $callback)
    {
        $value = $this->redis->get($key);

        if ($value !== false) {
            return json_decode($value, true);
        }

        $data = $callback();
        $this->redis->setex($key, $ttl, json_encode($data));

        return $data;
    }

    public function rememberForever(string $key, callable $callback)
    {
        return $this->remember($key, 0, $callback);
    }

    public function tags(array $tags): TaggedCache
    {
        return new TaggedCache($this->redis, $tags);
    }

    public function invalidatePattern(string $pattern): int
    {
        $keys = $this->redis->keys($pattern);
        return empty($keys) ? 0 : $this->redis->del($keys);
    }
}

class TaggedCache
{
    private $redis;
    private $tags;

    public function __construct($redis, array $tags)
    {
        $this->redis = $redis;
        $this->tags = $tags;
    }

    public function put(string $key, $value, int $ttl = 3600): void
    {
        // 存储数据
        $this->redis->setex($key, $ttl, json_encode($value));

        // 关联标签
        foreach ($this->tags as $tag) {
            $this->redis->sadd("tag:{$tag}", $key);
            $this->redis->expire("tag:{$tag}", $ttl + 300); // 标签稍后过期
        }
    }

    public function flush(): void
    {
        foreach ($this->tags as $tag) {
            $keys = $this->redis->smembers("tag:{$tag}");

            if (!empty($keys)) {
                $this->redis->del($keys);
            }

            $this->redis->del("tag:{$tag}");
        }
    }
}

// 使用示例
$cache = new RedisCacheService();

// 基础缓存
$users = $cache->remember('active_users', 3600, function() {
    return User::where('active', true)->get();
});

// 标签缓存
$cache->tags(['users', 'posts'])->put('user_posts:123', $userPosts);

// 清除标签缓存
$cache->tags(['users'])->flush();
```

## 监控和调试

### 连接监控

```php
class RedisMonitor
{
    private $redis;

    public function __construct()
    {
        $this->redis = App::redis();
    }

    public function getInfo(): array
    {
        return $this->redis->info();
    }

    public function getMemoryUsage(): array
    {
        $info = $this->redis->info('memory');

        return [
            'used_memory' => $info['used_memory'] ?? 0,
            'used_memory_human' => $info['used_memory_human'] ?? '0B',
            'used_memory_peak' => $info['used_memory_peak'] ?? 0,
            'used_memory_peak_human' => $info['used_memory_peak_human'] ?? '0B'
        ];
    }

    public function getConnectionInfo(): array
    {
        $info = $this->redis->info('clients');

        return [
            'connected_clients' => $info['connected_clients'] ?? 0,
            'blocked_clients' => $info['blocked_clients'] ?? 0,
            'client_longest_output_list' => $info['client_longest_output_list'] ?? 0
        ];
    }

    public function getKeyspaceInfo(): array
    {
        $info = $this->redis->info('keyspace');
        $keyspaces = [];

        foreach ($info as $key => $value) {
            if (strpos($key, 'db') === 0) {
                $keyspaces[$key] = $value;
            }
        }

        return $keyspaces;
    }

    public function slowLog(int $count = 10): array
    {
        return $this->redis->slowlog('get', $count);
    }
}
```

### 性能调试

```php
class RedisProfiler
{
    private $redis;
    private $commands = [];
    private $enabled = false;

    public function __construct()
    {
        $this->redis = App::redis();
    }

    public function enable(): void
    {
        $this->enabled = true;
        $this->commands = [];
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function profile(string $command, array $args, callable $executor)
    {
        if (!$this->enabled) {
            return $executor();
        }

        $start = microtime(true);
        $result = $executor();
        $time = microtime(true) - $start;

        $this->commands[] = [
            'command' => $command,
            'args' => $args,
            'time' => $time,
            'timestamp' => time()
        ];

        return $result;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getTotalTime(): float
    {
        return array_sum(array_column($this->commands, 'time'));
    }

    public function getCommandCount(): int
    {
        return count($this->commands);
    }

    public function getSlowCommands(float $threshold = 0.1): array
    {
        return array_filter($this->commands, fn($cmd) => $cmd['time'] > $threshold);
    }
}
```

## 最佳实践

### 键命名约定

```php
class RedisKeyHelper
{
    public const SEPARATOR = ':';

    public static function userKey(int $userId, string $suffix = ''): string
    {
        return self::buildKey('user', $userId, $suffix);
    }

    public static function sessionKey(string $sessionId): string
    {
        return self::buildKey('session', $sessionId);
    }

    public static function cacheKey(string $category, string $identifier): string
    {
        return self::buildKey('cache', $category, $identifier);
    }

    public static function lockKey(string $resource): string
    {
        return self::buildKey('lock', $resource);
    }

    public static function queueKey(string $queueName): string
    {
        return self::buildKey('queue', $queueName);
    }

    private static function buildKey(...$parts): string
    {
        $parts = array_filter($parts, fn($part) => $part !== '');
        return implode(self::SEPARATOR, $parts);
    }
}

// 使用示例
$userCacheKey = RedisKeyHelper::userKey(123, 'profile');  // user:123:profile
$lockKey = RedisKeyHelper::lockKey('payment:456');        // lock:payment:456
```

### 错误处理

```php
class RedisService
{
    private $redis;
    private $retryCount = 3;
    private $retryDelay = 100; // 毫秒

    public function __construct()
    {
        $this->redis = App::redis();
    }

    public function executeWithRetry(callable $operation)
    {
        $lastException = null;

        for ($i = 0; $i < $this->retryCount; $i++) {
            try {
                return $operation($this->redis);
            } catch (\RedisException $e) {
                $lastException = $e;

                if ($i < $this->retryCount - 1) {
                    usleep($this->retryDelay * 1000 * pow(2, $i)); // 指数退避
                    $this->reconnect();
                }
            }
        }

        throw $lastException;
    }

    private function reconnect(): void
    {
        try {
            $this->redis = App::redis();
        } catch (\Exception $e) {
            error_log("Redis 重连失败: " . $e->getMessage());
        }
    }

    public function safeGet(string $key, $default = null)
    {
        try {
            return $this->executeWithRetry(fn($redis) => $redis->get($key)) ?: $default;
        } catch (\Exception $e) {
            error_log("Redis GET 失败: " . $e->getMessage());
            return $default;
        }
    }

    public function safeSet(string $key, $value, int $ttl = 0): bool
    {
        try {
            return $this->executeWithRetry(function($redis) use ($key, $value, $ttl) {
                return $ttl > 0 ? $redis->setex($key, $ttl, $value) : $redis->set($key, $value);
            });
        } catch (\Exception $e) {
            error_log("Redis SET 失败: " . $e->getMessage());
            return false;
        }
    }
}
```

## 故障排除

### 常见问题诊断

#### 1. 连接问题

```bash
# 检查 Redis 服务状态
redis-cli ping

# 检查网络连接
telnet localhost 6379

# 测试 PHP Redis 扩展
php -m | grep redis

# 测试连接配置
php -r "
$redis = \Core\App::redis();
var_dump($redis->ping());
"
```

#### 2. 性能问题

```bash
# 监控 Redis 状态
redis-cli info stats

# 查看慢查询
redis-cli slowlog get 10

# 监控内存使用
redis-cli info memory
```

#### 3. 配置错误

```php
// 检查配置加载
$config = App::config('database')->get('redis.drivers.default');
var_dump($config);

// 测试不同驱动
try {
    $redis = App::redis('cache');
    echo "缓存 Redis 连接成功\n";
} catch (Exception $e) {
    echo "连接失败: " . $e->getMessage() . "\n";
}
```

DuxLite 的 Redis 集成为应用程序提供了高性能的数据存储和处理能力，支持缓存、队列、锁、计数器等多种使用模式，是构建高性能应用的重要基础设施。