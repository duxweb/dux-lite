# Event 事件

DuxLite 事件系统的核心类定义和 API 规格说明。

## Event 类

**命名空间：** `Core\Event\Event`

### 方法

```php
public function __construct()
```
- **说明：** 构造函数

```php
public function listen(string $event, callable|string $listener, int $priority = 0): void
```
- **参数：**
  - `$event` - 事件名称
  - `$listener` - 监听器（回调函数或类名）
  - `$priority` - 优先级（数字越大优先级越高）
- **返回：** `void`
- **说明：** 注册事件监听器

```php
public function dispatch(string $event, array $data = []): array
```
- **参数：**
  - `$event` - 事件名称
  - `$data` - 事件数据（可选）
- **返回：** `array` - 所有监听器的返回值数组
- **说明：** 分发事件

```php
public function dispatchUntil(string $event, array $data = []): mixed
```
- **参数：**
  - `$event` - 事件名称
  - `$data` - 事件数据（可选）
- **返回：** `mixed` - 第一个非null返回值或null
- **说明：** 分发事件直到某个监听器返回非null值

```php
public function forget(string $event): void
```
- **参数：** `$event` - 事件名称
- **返回：** `void`
- **说明：** 移除事件的所有监听器

```php
public function forgetListener(string $event, callable|string $listener): void
```
- **参数：**
  - `$event` - 事件名称
  - `$listener` - 要移除的监听器
- **返回：** `void`
- **说明：** 移除特定的事件监听器

```php
public function hasListeners(string $event): bool
```
- **参数：** `$event` - 事件名称
- **返回：** `bool` - 是否有监听器
- **说明：** 检查事件是否有监听器

```php
public function getListeners(string $event): array
```
- **参数：** `$event` - 事件名称
- **返回：** `array` - 监听器数组
- **说明：** 获取事件的所有监听器

```php
public function registerAttributes(): void
```
- **返回：** `void`
- **说明：** 注册注解监听器

## 监听器注解

### Listener 注解

**命名空间：** `Core\Event\Attribute\Listener`

```php
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Listener
{
    public function __construct(
        public string $event,
        public int $priority = 0
    ) {}
}
```

**属性：**
- `$event` - 事件名称
- `$priority` - 优先级（数字越大优先级越高）

## 事件接口

### EventInterface

```php
interface EventInterface
{
    public function getName(): string;
    public function getData(): array;
    public function setData(array $data): void;
    public function isPropagationStopped(): bool;
    public function stopPropagation(): void;
}
```

### ListenerInterface

```php
interface ListenerInterface
{
    public function handle(EventInterface $event): mixed;
}
```

## 事件对象基类

### BaseEvent

**命名空间：** `Core\Event\BaseEvent`
**实现：** `EventInterface`

```php
public function __construct(array $data = [])
```
- **参数：** `$data` - 事件数据（可选）

```php
public function getName(): string
```
- **返回：** `string` - 事件名称

```php
public function getData(): array
```
- **返回：** `array` - 事件数据

```php
public function setData(array $data): void
```
- **参数：** `$data` - 事件数据
- **返回：** `void`

```php
public function get(string $key, mixed $default = null): mixed
```
- **参数：**
  - `$key` - 数据键名
  - `$default` - 默认值（可选）
- **返回：** `mixed` - 数据值

```php
public function set(string $key, mixed $value): void
```
- **参数：**
  - `$key` - 数据键名
  - `$value` - 数据值
- **返回：** `void`

```php
public function has(string $key): bool
```
- **参数：** `$key` - 数据键名
- **返回：** `bool` - 是否存在该键

```php
public function isPropagationStopped(): bool
```
- **返回：** `bool` - 是否停止传播

```php
public function stopPropagation(): void
```
- **返回：** `void`
- **说明：** 停止事件传播

## 系统事件

### 应用生命周期事件

| 事件名 | 触发时机 | 数据参数 |
|--------|----------|----------|
| `app.created` | 应用创建后 | `['app' => App]` |
| `app.initialized` | 应用初始化后 | `['app' => App]` |
| `app.booted` | 应用启动后 | `['app' => App]` |
| `app.terminated` | 应用终止前 | `['app' => App]` |

### 数据库事件

| 事件名 | 触发时机 | 数据参数 |
|--------|----------|----------|
| `database.connected` | 数据库连接后 | `['connection' => string]` |
| `database.query` | 执行查询后 | `['sql' => string, 'bindings' => array, 'time' => float]` |
| `database.transaction.begin` | 事务开始 | `['connection' => string]` |
| `database.transaction.commit` | 事务提交 | `['connection' => string]` |
| `database.transaction.rollback` | 事务回滚 | `['connection' => string]` |

### 模型事件

| 事件名 | 触发时机 | 数据参数 |
|--------|----------|----------|
| `model.creating` | 模型创建前 | `['model' => Model]` |
| `model.created` | 模型创建后 | `['model' => Model]` |
| `model.updating` | 模型更新前 | `['model' => Model]` |
| `model.updated` | 模型更新后 | `['model' => Model]` |
| `model.deleting` | 模型删除前 | `['model' => Model]` |
| `model.deleted` | 模型删除后 | `['model' => Model]` |

### 缓存事件

| 事件名 | 触发时机 | 数据参数 |
|--------|----------|----------|
| `cache.hit` | 缓存命中 | `['key' => string, 'value' => mixed]` |
| `cache.miss` | 缓存未命中 | `['key' => string]` |
| `cache.write` | 缓存写入 | `['key' => string, 'value' => mixed, 'ttl' => int]` |
| `cache.delete` | 缓存删除 | `['key' => string]` |

### 队列事件

| 事件名 | 触发时机 | 数据参数 |
|--------|----------|----------|
| `queue.before` | 任务执行前 | `['job' => QueueMessage]` |
| `queue.after` | 任务执行后 | `['job' => QueueMessage, 'result' => mixed]` |
| `queue.failed` | 任务失败 | `['job' => QueueMessage, 'exception' => \Throwable]` |
| `queue.retrying` | 任务重试 | `['job' => QueueMessage, 'attempt' => int]` |

### 认证事件

| 事件名 | 触发时机 | 数据参数 |
|--------|----------|----------|
| `auth.login` | 用户登录 | `['user' => mixed, 'token' => string]` |
| `auth.logout` | 用户登出 | `['user' => mixed]` |
| `auth.failed` | 认证失败 | `['credentials' => array, 'error' => string]` |
| `auth.token.refresh` | 令牌刷新 | `['old_token' => string, 'new_token' => string]` |

### HTTP 事件

| 事件名 | 触发时机 | 数据参数 |
|--------|----------|----------|
| `http.request.received` | 收到请求 | `['request' => ServerRequestInterface]` |
| `http.response.sending` | 发送响应前 | `['request' => ServerRequestInterface, 'response' => ResponseInterface]` |
| `http.exception` | HTTP 异常 | `['exception' => \Throwable, 'request' => ServerRequestInterface]` |

## 事件优先级

### 优先级常量

| 常量 | 值 | 说明 |
|------|----|----- |
| `PRIORITY_HIGHEST` | 1000 | 最高优先级 |
| `PRIORITY_HIGH` | 100 | 高优先级 |
| `PRIORITY_NORMAL` | 0 | 普通优先级 |
| `PRIORITY_LOW` | -100 | 低优先级 |
| `PRIORITY_LOWEST` | -1000 | 最低优先级 |

### 执行顺序

1. 按优先级从高到低执行
2. 相同优先级按注册顺序执行
3. 可通过 `stopPropagation()` 停止后续监听器执行

## 监听器类型

### 闭包监听器

```php
$event->listen('user.created', function($event) {
    // 处理逻辑
});
```

### 类方法监听器

```php
$event->listen('user.created', 'UserEventHandler@handleCreated');
```

### 可调用对象监听器

```php
$event->listen('user.created', [$handler, 'handleCreated']);
```

### 注解监听器

```php
class UserEventHandler
{
    #[Listener('user.created', priority: 100)]
    public function handleCreated(EventInterface $event): void
    {
        // 处理逻辑
    }
}
```

## 异步事件处理

### 队列监听器

通过队列系统异步处理事件：

```php
$event->listen('email.send', function($event) {
    App::queue()->push(SendEmailJob::class, $event->getData());
});
```

### 批量事件处理

```php
$event->listen('user.activity', function($event) {
    // 批量收集用户活动数据
    static $activities = [];
    $activities[] = $event->getData();

    if (count($activities) >= 100) {
        App::queue()->push(ProcessActivitiesJob::class, ['activities' => $activities]);
        $activities = [];
    }
});
```

## 事件通配符

### 通配符模式

| 模式 | 说明 |
|------|------|
| `user.*` | 匹配所有以 `user.` 开头的事件 |
| `*.created` | 匹配所有以 `.created` 结尾的事件 |
| `*` | 匹配所有事件 |

### 通配符监听器

```php
$event->listen('user.*', function($eventName, $data) {
    // 处理所有用户相关事件
});
```

## 事件数据结构

### 标准事件数据

| 字段 | 类型 | 说明 |
|------|------|------|
| `timestamp` | `int` | 事件发生时间戳 |
| `user_id` | `int|null` | 当前用户ID |
| `request_id` | `string` | 请求ID |
| `ip` | `string` | 客户端IP |
| `user_agent` | `string` | 用户代理 |

### 自定义事件数据

事件数据可以包含任意键值对，建议使用有意义的键名：

```php
[
    'id' => 123,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'action' => 'register',
    'metadata' => [
        'source' => 'web',
        'referrer' => 'google.com'
    ]
]
```

## 性能考虑

### 监听器数量限制

- 建议单个事件的监听器数量不超过50个
- 高频事件的监听器应尽量简化处理逻辑
- 耗时操作应使用异步处理

### 内存使用

- 事件数据应避免包含大对象
- 长时间运行的应用应定期清理事件监听器
- 使用弱引用避免内存泄漏