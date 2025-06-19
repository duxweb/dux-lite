# Queue 队列

DuxLite 队列系统的核心类定义和 API 规格说明。

## Queue 类

**命名空间：** `Core\Queue\Queue`

### 方法

```php
public function __construct(array $config = [])
```
- **参数：** `$config` - 队列配置数组
- **说明：** 构造函数

```php
public function push(string $job, array $data = [], string $queue = 'default'): string
```
- **参数：**
  - `$job` - 任务类名
  - `$data` - 任务数据（可选）
  - `$queue` - 队列名称（可选）
- **返回：** `string` - 任务ID
- **说明：** 推送任务到队列

```php
public function later(int $delay, string $job, array $data = [], string $queue = 'default'): string
```
- **参数：**
  - `$delay` - 延迟秒数
  - `$job` - 任务类名
  - `$data` - 任务数据（可选）
  - `$queue` - 队列名称（可选）
- **返回：** `string` - 任务ID
- **说明：** 延迟推送任务到队列

```php
public function pop(string $queue = 'default'): ?QueueMessage
```
- **参数：** `$queue` - 队列名称（可选）
- **返回：** `QueueMessage|null` - 队列消息对象或null
- **说明：** 从队列中取出任务

```php
public function size(string $queue = 'default'): int
```
- **参数：** `$queue` - 队列名称（可选）
- **返回：** `int` - 队列大小
- **说明：** 获取队列中任务数量

```php
public function clear(string $queue = 'default'): void
```
- **参数：** `$queue` - 队列名称（可选）
- **返回：** `void`
- **说明：** 清空队列

## QueueMessage 类

**命名空间：** `Core\Queue\QueueMessage`

### 属性

| 属性 | 类型 | 说明 |
|------|------|------|
| `$id` | `string` | 消息ID |
| `$job` | `string` | 任务类名 |
| `$data` | `array` | 任务数据 |
| `$queue` | `string` | 队列名称 |
| `$attempts` | `int` | 尝试次数 |
| `$createdAt` | `int` | 创建时间戳 |
| `$availableAt` | `int` | 可用时间戳 |

### 方法

```php
public function __construct(string $id, string $job, array $data, string $queue = 'default')
```
- **参数：**
  - `$id` - 消息ID
  - `$job` - 任务类名
  - `$data` - 任务数据
  - `$queue` - 队列名称（可选）

```php
public function getId(): string
```
- **返回：** `string` - 消息ID

```php
public function getJob(): string
```
- **返回：** `string` - 任务类名

```php
public function getData(): array
```
- **返回：** `array` - 任务数据

```php
public function getQueue(): string
```
- **返回：** `string` - 队列名称

```php
public function getAttempts(): int
```
- **返回：** `int` - 尝试次数

```php
public function incrementAttempts(): void
```
- **返回：** `void`
- **说明：** 增加尝试次数

```php
public function isReady(): bool
```
- **返回：** `bool` - 是否可以执行
- **说明：** 检查任务是否到达执行时间

## QueueProcessor 类

**命名空间：** `Core\Queue\QueueProcessor`

### 方法

```php
public function __construct(Queue $queue)
```
- **参数：** `$queue` - 队列实例

```php
public function process(string $queue = 'default', int $maxJobs = 0, int $memory = 128): void
```
- **参数：**
  - `$queue` - 队列名称（可选）
  - `$maxJobs` - 最大处理任务数（0表示无限制）
  - `$memory` - 内存限制（MB）
- **返回：** `void`
- **说明：** 处理队列任务

```php
public function processJob(QueueMessage $message): bool
```
- **参数：** `$message` - 队列消息
- **返回：** `bool` - 是否处理成功
- **说明：** 处理单个任务

```php
public function failed(QueueMessage $message, \Throwable $exception): void
```
- **参数：**
  - `$message` - 队列消息
  - `$exception` - 异常对象
- **返回：** `void`
- **说明：** 处理失败任务

## 任务接口

### QueueJobInterface

```php
interface QueueJobInterface
{
    public function handle(array $data): void;
}
```

### 可重试任务接口

```php
interface RetryableJobInterface extends QueueJobInterface
{
    public function getMaxRetries(): int;
    public function getRetryDelay(): int;
}
```

### 批量任务接口

```php
interface BatchJobInterface extends QueueJobInterface
{
    public function getBatchSize(): int;
    public function processBatch(array $items): void;
}
```

## 队列配置结构

配置文件：`config/queue.toml`

### 基础配置

```toml
[default]
driver = "database"
table = "queue_jobs"
connection = "default"
```

### 支持的驱动类型

| 驱动类型 | 说明 | 必需配置 |
|----------|------|----------|
| `database` | 数据库队列 | `table`, `connection` |
| `redis` | Redis队列 | `connection`, `prefix` |
| `sync` | 同步队列 | 无额外配置 |

### Redis 驱动配置

```toml
[redis]
driver = "redis"
connection = "default"
prefix = "queue:"
retry_after = 60
```

### 多队列配置

```toml
[high]
driver = "redis"
connection = "default"
prefix = "queue:high:"

[normal]
driver = "database"
table = "queue_jobs"
connection = "default"

[low]
driver = "database"
table = "queue_jobs_low"
connection = "default"
```

## 数据库表结构

队列任务表（`queue_jobs`）：

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | `bigint` | 主键ID |
| `queue` | `varchar(255)` | 队列名称 |
| `payload` | `longtext` | 任务载荷 |
| `attempts` | `tinyint` | 尝试次数 |
| `reserved_at` | `int` | 预留时间戳 |
| `available_at` | `int` | 可用时间戳 |
| `created_at` | `int` | 创建时间戳 |

失败任务表（`failed_jobs`）：

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | `bigint` | 主键ID |
| `connection` | `text` | 连接名称 |
| `queue` | `text` | 队列名称 |
| `payload` | `longtext` | 任务载荷 |
| `exception` | `longtext` | 异常信息 |
| `failed_at` | `timestamp` | 失败时间 |

## 任务状态常量

| 状态 | 值 | 说明 |
|------|----|----- |
| `PENDING` | 0 | 待处理 |
| `PROCESSING` | 1 | 处理中 |
| `COMPLETED` | 2 | 已完成 |
| `FAILED` | 3 | 已失败 |
| `RETRYING` | 4 | 重试中 |

## 队列优先级

| 优先级 | 数值 | 说明 |
|--------|------|------|
| `HIGH` | 100 | 高优先级 |
| `NORMAL` | 50 | 普通优先级 |
| `LOW` | 10 | 低优先级 |

## 命令行工具

### QueueCommand 类

**命名空间：** `Core\Queue\QueueCommand`

#### 可用命令

```bash
# 启动队列处理器
php dux queue:work [queue] [--sleep=3] [--tries=3] [--memory=128] [--timeout=60]

# 处理单个任务
php dux queue:work --once

# 重启队列处理器
php dux queue:restart

# 查看队列状态
php dux queue:status

# 清空队列
php dux queue:clear [queue]

# 重试失败任务
php dux queue:retry [id]

# 查看失败任务
php dux queue:failed
```

#### 命令参数

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `queue` | `string` | `default` | 队列名称 |
| `--sleep` | `int` | `3` | 空队列时休眠秒数 |
| `--tries` | `int` | `3` | 最大重试次数 |
| `--memory` | `int` | `128` | 内存限制（MB） |
| `--timeout` | `int` | `60` | 任务超时时间（秒） |
| `--once` | `bool` | `false` | 只处理一个任务 |

## 事件钩子

### 队列事件

| 事件名 | 触发时机 | 参数 |
|--------|----------|------|
| `queue.before` | 任务执行前 | `QueueMessage` |
| `queue.after` | 任务执行后 | `QueueMessage` |
| `queue.failed` | 任务失败时 | `QueueMessage`, `\Throwable` |
| `queue.retrying` | 任务重试时 | `QueueMessage` |

### 处理器事件

| 事件名 | 触发时机 | 参数 |
|--------|----------|------|
| `worker.starting` | 处理器启动时 | `string $queue` |
| `worker.stopping` | 处理器停止时 | `string $queue` |
| `worker.looping` | 每次循环时 | `string $queue` |