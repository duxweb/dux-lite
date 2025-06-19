# 原子锁

DuxLite 提供了基于 Symfony Lock 组件的原子锁系统，支持多种存储后端，用于防止并发操作引起的数据竞争和重复执行问题。原子锁是高并发应用中确保数据一致性的重要工具。

## 系统概述

### 原子锁架构

DuxLite 的原子锁系统采用统一接口、多存储架构：

```
应用层 → Lock 接口 → 锁工厂 → 存储后端（Semaphore/Flock/Redis）
```

### 核心组件

- **Lock**：锁管理器，提供统一的锁创建接口
- **LockFactory**：锁工厂，负责创建具体的锁实例
- **SemaphoreStore**：信号量存储，适用于单机多进程
- **FlockStore**：文件锁存储，基于文件系统
- **RedisStore**：Redis 存储，支持分布式锁

## 锁配置

### 配置文件设置

锁配置在 `config/use.toml` 文件中：

```toml
[lock]
# 锁类型：semaphore（信号量）、flock（文件锁）、redis（Redis锁）
type = "redis"

# 锁的默认超时时间（秒）
default_timeout = 60
```

### 存储类型对比

| 锁类型 | 适用场景 | 优点 | 缺点 |
|--------|----------|------|------|
| **Semaphore** | 单机多进程 | 性能最佳，系统原生支持 | 仅限单机，需要 sysvsem 扩展 |
| **Flock** | 单机文件锁 | 无需扩展，简单可靠 | 仅限单机，依赖文件系统 |
| **Redis** | 多进程/多机环境 | 支持跨进程跨机器，功能丰富 | 依赖 Redis 服务 |

### Redis 锁配置

如果使用 Redis 锁，需要在 `config/database.toml` 中配置 Redis 连接：

```toml
[redis.drivers.default]
host = "localhost"
port = 6379
password = ""
database = 3                 # 使用专门的锁数据库
timeout = 2.5

# 专用锁 Redis
[redis.drivers.lock]
host = "localhost"
port = 6379
password = ""
database = 4
timeout = 2.5
optPrefix = "lock_"
```

## 基础用法

### 获取锁实例

```php
use Core\App;

// 获取默认锁工厂（从 use.toml 读取类型）
$lockFactory = App::lock();

// 获取指定类型的锁工厂
$redisLock = App::lock('redis');
$flockLock = App::lock('flock');
$semaphoreLock = App::lock('semaphore');
```

### 基本锁操作

```php
use Core\App;

class CriticalSectionService
{
    private $lockFactory;

    public function __construct()
    {
        $this->lockFactory = App::lock();
    }

    public function executeCriticalSection(string $resource): void
    {
        // 创建锁
        $lock = $this->lockFactory->createLock($resource);

        // 尝试获取锁
        if ($lock->acquire()) {
            try {
                // 执行需要锁保护的代码
                $this->performCriticalOperation();

            } finally {
                // 释放锁
                $lock->release();
            }
        } else {
            throw new \Exception("无法获取锁：{$resource}");
        }
    }

    private function performCriticalOperation(): void
    {
        // 关键业务逻辑
        sleep(2); // 模拟业务处理
    }
}

### 带超时的锁获取

```php
// 创建带超时的锁（30秒自动过期）
$lock = $lockFactory->createLock('user_process', 30.0);

// 尝试获取锁，最多等待5秒
if ($lock->acquire(true, 5.0)) {
    try {
        // 处理用户相关操作
        $this->processUserData();

    } finally {
        $lock->release();
    }
} else {
    throw new \Exception('获取锁超时，请稍后重试');
}
```

## 锁操作模式

### 阻塞模式

```php
// 阻塞模式：等待获取锁，直到成功或超时
$lock = $lockFactory->createLock('resource', 30.0); // 30秒超时

if ($lock->acquire(true, 5.0)) { // 最多等待5秒
    try {
        // 执行业务逻辑
    } finally {
        $lock->release();
    }
} else {
    throw new \Exception('获取锁超时');
}
```

### 非阻塞模式

```php
// 非阻塞模式：立即返回结果
$lock = $lockFactory->createLock('resource');

if ($lock->acquire(false)) { // 不等待，立即返回
    try {
        // 执行业务逻辑
    } finally {
        $lock->release();
    }
} else {
    // 锁被占用，可以选择排队或者跳过
    $this->scheduleForLater();
}
```

### 自动超时释放

```php
// 创建会自动过期的锁
$lock = $lockFactory->createLock('resource', 300.0); // 5分钟后自动释放

if ($lock->acquire()) {
    try {
        // 长时间运行的任务
        $this->longRunningTask();

        // 即使忘记释放，锁也会在5分钟后自动过期
    } finally {
        $lock->release();
    }
}
```

## 应用场景

### 1. 防止重复执行

```php
class OrderService
{
    public function processOrder(int $orderId): void
    {
        $lockFactory = App::lock('redis');
        $lock = $lockFactory->createLock("order_process:{$orderId}", 300); // 5分钟超时

        if (!$lock->acquire()) {
            throw new \Exception("订单 {$orderId} 正在处理中，请勿重复操作");
        }

        try {
            // 检查订单状态
            $order = Order::find($orderId);
            if ($order->status !== 'pending') {
                return; // 订单已处理
            }

            // 处理订单业务逻辑
            $this->doProcessOrder($order);

            // 更新订单状态
            $order->update(['status' => 'processing']);

        } finally {
            $lock->release();
        }
    }

    private function doProcessOrder(Order $order): void
    {
        // 具体的订单处理逻辑
        // 库存扣减、支付处理、物流安排等
    }
}
```

### 2. 文件上传去重

```php
class FileUploadService
{
    public function uploadFile(UploadedFileInterface $file): array
    {
        $hash = $this->calculateFileHash($file);
        $lockFactory = App::lock('redis');
        $lock = $lockFactory->createLock("file_upload:{$hash}", 60);

        if (!$lock->acquire()) {
            // 等待其他进程完成上传
            sleep(1);
            return $this->getExistingFile($hash);
        }

        try {
            // 检查文件是否已存在
            $existingFile = $this->findFileByHash($hash);
            if ($existingFile) {
                return $existingFile;
            }

            // 执行文件上传
            $uploadedFile = $this->performUpload($file, $hash);

            // 保存文件记录
            $this->saveFileRecord($uploadedFile, $hash);

            return $uploadedFile;

        } finally {
            $lock->release();
        }
    }

    private function calculateFileHash(UploadedFileInterface $file): string
    {
        $file->getStream()->rewind();
        return hash('sha256', $file->getStream()->getContents());
    }

    private function performUpload($file, string $hash): array
    {
        $storage = App::storage();
        $path = "uploads/{$hash}/" . $file->getClientFilename();

        $storage->put($path, $file->getStream());

        return [
            'path' => $path,
            'url' => $storage->publicUrl($path),
            'size' => $file->getSize(),
            'hash' => $hash
        ];
    }
}
```

### 3. 缓存预热

```php
class CacheWarmupService
{
    public function warmupUserCache(int $userId): void
    {
        $lockFactory = App::lock('redis');
        $lock = $lockFactory->createLock("cache_warmup:user:{$userId}", 120);

        if (!$lock->acquire()) {
            return; // 其他进程正在预热，跳过
        }

        try {
            $cache = App::cache();
            $cacheKey = "user_profile:{$userId}";

            // 检查缓存是否已存在
            if ($cache->has($cacheKey)) {
                return;
            }

            // 从数据库加载用户数据
            $user = User::with(['profile', 'permissions', 'roles'])->find($userId);

            if ($user) {
                // 构建缓存数据
                $cacheData = [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'profile' => $user->profile->toArray(),
                    'permissions' => $user->permissions->pluck('name')->toArray(),
                    'roles' => $user->roles->pluck('name')->toArray(),
                ];

                // 缓存1小时
                $cache->set($cacheKey, $cacheData, 3600);
            }

        } finally {
            $lock->release();
        }
    }
}
```

### 4. 数据同步任务

```php
use Core\Scheduler\Attribute\Scheduler;

class DataSyncService
{
    #[Scheduler('*/5 * * * *')] // 每5分钟执行
    public function syncExternalData(): void
    {
        $lockFactory = App::lock('redis');
        $lock = $lockFactory->createLock('data_sync_task', 300); // 5分钟超时

        if (!$lock->acquire()) {
            App::log('sync')->info('数据同步任务已在运行中，跳过本次执行');
            return;
        }

        try {
            App::log('sync')->info('开始数据同步任务');

            // 获取上次同步时间
            $lastSync = $this->getLastSyncTime();

            // 从外部API获取增量数据
            $data = $this->fetchIncrementalData($lastSync);

            // 批量处理数据
            $this->processSyncData($data);

            // 更新同步时间戳
            $this->updateLastSyncTime();

            App::log('sync')->info('数据同步任务完成', [
                'processed_records' => count($data)
            ]);

        } catch (\Exception $e) {
            App::log('sync')->error('数据同步任务失败', [
                'error' => $e->getMessage()
            ]);
            throw $e;

        } finally {
            $lock->release();
        }
    }

    private function getLastSyncTime(): string
    {
        $cache = App::cache();
        return $cache->get('data_sync_last_time', '2024-01-01 00:00:00');
    }

    private function fetchIncrementalData(string $since): array
    {
        // 调用外部 API 获取数据
        // 这里简化处理
        return [];
    }

    private function processSyncData(array $data): void
    {
        foreach ($data as $item) {
            // 处理单条数据
            $this->processDataItem($item);
        }
    }

    private function updateLastSyncTime(): void
    {
        $cache = App::cache();
        $cache->set('data_sync_last_time', date('Y-m-d H:i:s'));
    }
}
```

## 高级特性

### 1. 锁的续期

```php
class LongRunningTaskService
{
    public function executeLongTask(): void
    {
        $lockFactory = App::lock('redis');
        $lock = $lockFactory->createLock('long_running_task', 60); // 初始60秒

        if (!$lock->acquire()) {
            throw new \Exception('任务正在运行中');
        }

        try {
            $steps = $this->getTaskSteps();

            foreach ($steps as $index => $step) {
                // 每处理几个步骤就续期锁
                if ($index % 10 === 0) {
                    $this->renewLock($lock);
                }

                $this->executeStep($step);
            }

        } finally {
            $lock->release();
        }
    }

    private function renewLock($lock): void
    {
        // 先释放再重新获取，实现续期
        $lock->release();

        if (!$lock->acquire()) {
            throw new \Exception('无法续期锁，任务可能被其他进程接管');
        }
    }
}
```

### 2. 分层锁机制

```php
class HierarchicalLockService
{
    public function processUserOrder(int $userId, int $orderId): void
    {
        $lockFactory = App::lock('redis');

        // 先获取用户级锁
        $userLock = $lockFactory->createLock("user:{$userId}", 300);
        if (!$userLock->acquire()) {
            throw new \Exception("用户 {$userId} 正在处理其他订单");
        }

        try {
            // 再获取订单级锁
            $orderLock = $lockFactory->createLock("order:{$orderId}", 180);
            if (!$orderLock->acquire()) {
                throw new \Exception("订单 {$orderId} 正在处理中");
            }

            try {
                // 执行业务逻辑
                $this->doProcessOrder($userId, $orderId);

            } finally {
                $orderLock->release();
            }

        } finally {
            $userLock->release();
        }
    }
}
```

### 3. 锁状态检查

```php
class LockStatusService
{
    public function checkLockStatus(string $resource): array
    {
        $lockFactory = App::lock('redis');
        $lock = $lockFactory->createLock($resource);

        // 尝试获取锁来检查状态
        $isLocked = !$lock->acquire(false); // 非阻塞获取

        if (!$isLocked) {
            $lock->release(); // 如果获取成功，立即释放
        }

        return [
            'resource' => $resource,
            'is_locked' => $isLocked,
            'check_time' => date('Y-m-d H:i:s')
        ];
    }

    public function getLockInfo(): array
    {
        $resources = [
            'order_process:123',
            'user_process:456',
            'data_sync_task',
            'cache_warmup:user:789'
        ];

        $lockInfo = [];
        foreach ($resources as $resource) {
            $lockInfo[] = $this->checkLockStatus($resource);
        }

        return $lockInfo;
    }
}
```

## 锁的监控和调试

### 锁状态检查

```php
class LockMonitorService
{
    private $lockFactory;

    public function __construct()
    {
        $this->lockFactory = App::lock();
    }

    public function checkLockStatus(string $resource): array
    {
        $lock = $this->lockFactory->createLock($resource);

        // 尝试非阻塞获取来检查状态
        $isLocked = !$lock->acquire(false);

        if (!$isLocked) {
            $lock->release(); // 如果获取成功，立即释放
        }

        return [
            'resource' => $resource,
            'is_locked' => $isLocked,
            'check_time' => date('Y-m-d H:i:s')
        ];
    }

    public function monitorActiveLocks(): array
    {
        $resources = [
            'order_process:*',
            'user_operation:*',
            'data_sync_task',
            'file_upload:*'
        ];

        $status = [];
        foreach ($resources as $resource) {
            $status[] = $this->checkLockStatus($resource);
        }

        return $status;
    }
}

## 性能优化

### 1. 锁粒度控制

```php
// ❌ 锁粒度过大
$lock = $lockFactory->createLock('all_users'); // 影响所有用户操作

// ✅ 合适的锁粒度
$lock = $lockFactory->createLock("user:{$userId}"); // 只影响特定用户
```

### 2. 超时时间优化

```php
// ❌ 超时时间过长
$lock = $lockFactory->createLock('quick_task', 3600); // 1小时，过长

// ✅ 合理的超时时间
$lock = $lockFactory->createLock('quick_task', 30); // 30秒，合理
```

### 3. 非阻塞获取锁

```php
// 非阻塞方式获取锁，快速失败
if ($lock->acquire(false)) {
    try {
        // 执行任务
    } finally {
        $lock->release();
    }
} else {
    // 快速返回或排队处理
    $this->queueTask($taskData);
}
```

## 故障排除

### 常见问题诊断

#### 1. 锁无法获取

```php
try {
    $lock = $lockFactory->createLock('test_lock');
    if (!$lock->acquire(false)) {
        echo "锁被占用\n";

        // 检查锁的详细信息
        if (App::config('lock.type') === 'redis') {
            $redis = App::redis();
            $lockKey = 'sf:lock:test_lock'; // Symfony Lock 的 Redis key 格式
            $value = $redis->get($lockKey);
            echo "锁值: " . ($value ?: '不存在') . "\n";
        }
    }
} catch (\Exception $e) {
    echo "锁操作异常: " . $e->getMessage() . "\n";
}
```

#### 2. 死锁检测

```php
class DeadlockDetector
{
    public function detectPotentialDeadlock(): array
    {
        $redis = App::redis();
        $lockPattern = 'sf:lock:*';
        $locks = [];

        // 获取所有锁
        $keys = $redis->keys($lockPattern);

        foreach ($keys as $key) {
            $ttl = $redis->ttl($key);
            $value = $redis->get($key);

            $locks[] = [
                'key' => $key,
                'ttl' => $ttl,
                'value' => $value,
                'created_ago' => $ttl < 0 ? '永不过期' : 'N/A'
            ];
        }

        return $locks;
    }
}
```

#### 3. 锁监控

```php
class LockMonitor
{
    public function monitorLocks(): void
    {
        while (true) {
            $detector = new DeadlockDetector();
            $locks = $detector->detectPotentialDeadlock();

            foreach ($locks as $lock) {
                if ($lock['ttl'] < 0) {
                    App::log('lock')->warning('发现永不过期的锁', $lock);
                }

                if ($lock['ttl'] > 3600) {
                    App::log('lock')->warning('发现长时间持有的锁', $lock);
                }
            }

            sleep(60); // 每分钟检查一次
        }
    }
}
```

## 最佳实践

### 1. 锁命名规范

```php
// ✅ 推荐的命名格式
"user_operation:{$userId}"           // 用户操作
"order_process:{$orderId}"           // 订单处理
"cache_warmup:user:{$userId}"        // 缓存预热
"sync_task:{$taskName}"              // 同步任务
"file_upload:{$fileHash}"            // 文件上传

// ❌ 不推荐的命名
"lock1"                              // 无意义名称
"processing"                         // 过于通用
"user123"                            // 硬编码ID
```

### 2. 错误处理

```php
public function safeExecuteWithLock(string $resource, callable $callback): mixed
{
    $lockFactory = App::lock();
    $lock = $lockFactory->createLock($resource, 60);

    try {
        if (!$lock->acquire(true, 5.0)) {
            throw new \Exception("获取锁失败: {$resource}");
        }

        return $callback();

    } catch (\Exception $e) {
        App::log('lock')->error('锁保护的操作失败', [
            'resource' => $resource,
            'error' => $e->getMessage()
        ]);
        throw $e;

    } finally {
        try {
            $lock->release();
        } catch (\Exception $e) {
            App::log('lock')->warning('释放锁失败', [
                'resource' => $resource,
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

### 3. 锁的自动管理

```php
class LockManager
{
    private array $activeLocks = [];

    public function acquireLock(string $resource, float $timeout = 60.0): bool
    {
        $lockFactory = App::lock();
        $lock = $lockFactory->createLock($resource, $timeout);

        if ($lock->acquire()) {
            $this->activeLocks[$resource] = $lock;
            return true;
        }

        return false;
    }

    public function releaseLock(string $resource): void
    {
        if (isset($this->activeLocks[$resource])) {
            $this->activeLocks[$resource]->release();
            unset($this->activeLocks[$resource]);
        }
    }

    public function __destruct()
    {
        // 自动释放所有锁
        foreach ($this->activeLocks as $resource => $lock) {
            try {
                $lock->release();
            } catch (\Exception $e) {
                error_log("Failed to release lock: {$resource}");
            }
        }
    }
}
```

通过 DuxLite 的原子锁系统，您可以轻松解决并发访问问题，确保数据一致性和操作原子性，构建更加安全可靠的应用程序。