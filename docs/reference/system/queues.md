# 队列处理

DuxLite 基于 Symfony Messenger 提供队列系统，支持 Redis 和 AMQP（RabbitMQ）两种队列后端，用于处理异步任务。

## 核心组件

- **Queue**：队列管理器 (`Core\Queue\Queue`)
- **QueueMessage**：队列消息封装 (`Core\Queue\QueueMessage`)
- **QueueJobMessage**：内部消息对象（框架自动使用）
- **QueueCommand**：队列管理命令，启动并发 worker 并输出状态
- **QueueConsumeCommand**：单 worker 消费命令（`queue:consume`）

## 依赖要求

- Redis 后端：需要安装 `ext-redis`
- AMQP 后端：需要安装 `ext-amqp`

## 配置系统

### 队列服务配置 (`config/queue.toml`)

```toml
# 默认 work 名称（add() 不传 name 时使用）
default = "queueA"

# work 配置：num 是该 work 的总并发；high/medium/low 是优先级权重（会按权重分配并发，总和不要求等于 num）
[workers.queueA]
type = "redis"
driver = "default"
num = 10
high = 3
medium = 4
low = 3

[workers.queueB]
type = "amqp"
driver = "default"
num = 5
high = 5
```

### 队列后端配置 (`config/database.toml`)

**Redis 队列配置：**

```toml
# Redis 队列后端
[redis.drivers.default]
host = "localhost"
port = 6379
auth = ""                    # Redis 密码
database = 0
persistent = false
optPrefix = "queue_"         # 队列前缀
```

**AMQP 队列配置：**

```toml
# RabbitMQ / AMQP 配置
[amqp.drivers.default]
host = "localhost"
port = 5672
vhost = "/"
username = "guest"
password = "guest"
persisted = false
prefix = "dux_"
```

## 使用方法

### 获取队列实例

```php
use Core\App;

// 获取默认队列
$queue = App::queue();
```

### 创建任务类

```php
<?php
namespace App\Jobs;

class EmailJob
{
    public function send(string $to, string $subject, string $body): void
    {
        // 邮件发送逻辑
        mail($to, $subject, $body);
        
        // 记录日志
        error_log("邮件已发送到: {$to}");
    }

    public function sendWelcome(int $userId): void
    {
        $user = User::find($userId);
        if ($user) {
            $this->send(
                $user->email,
                '欢迎注册',
                "欢迎 {$user->name} 注册我们的网站！"
            );
        }
    }
}
```

### 添加任务到队列

**基础用法：**

```php
use Core\App;

// 发送到默认 work（config/queue.toml 的 default）+ 默认队列（优先 medium）
App::queue()->add(
    class: 'App\Jobs\EmailJob',
    method: 'send',
    params: ['user@example.com', '测试邮件', '这是测试内容'],
)->send();
```

**延迟执行：**

```php
// 延迟 30 秒执行
$message = $queue->add('App\Jobs\EmailJob', 'send', [
    'user@example.com', '延迟邮件', '这是延迟邮件'
]);
$message->delay(30)->send();

// 延迟 1 小时执行
$message = $queue->add('App\Jobs\DataProcessJob', 'process', [$data]);
$message->delay(3600)->send();
```

**指定队列：**

```php
// name 指向 work（workers.<name>）
// priority 指向该 work 下的优先级队列名（high/medium/low/queue...）
App::queue()->add(
    'App\Jobs\ImageJob',
    'resize',
    ['/path/to/image.jpg', 800, 600],
    name: 'queueA',
    priority: 'high',
)->send();

// 或者用 priority() 链式设置优先级
App::queue()
    ->add('App\Jobs\ImageJob', 'resize', ['/path/to/image.jpg', 800, 600], name: 'queueA')
    ->priority('high')
    ->send();
```

## 启动队列消费者

### 基础消费命令

```bash
# 启动队列管理进程（读取 config/queue.toml 的 workers.*，并周期显示队列状态）
php dux queue:start

# 只启动指定 work
php dux queue:start queueA queueB

# 禁用状态输出
php dux queue:start --no-status

# 单 worker 模式（只消费一个队列，适合自行用守护进程拉起多份）
php dux queue:consume queueA high
```

说明：`queueA` 的 `num=10` 表示总 worker 数；`high/medium/low` 是权重，管理进程会按权重把 10 个 worker 分配到不同优先级队列。

### 命令输出示例

```
+---------------+
| Queue Service |
+---------------+
| Core Ver: 2.0 |
| Run Time: ... |
+---------------+
```

队列管理进程会输出各队列的 `pending/delayed/reserved`（仅 Redis 支持统计）。

## 队列状态查询

你也可以在业务代码中查询队列状态（不同后端支持程度不同，Redis 最完整）：

```php
$stats = App::queue()->stats(['high', 'medium', 'low'], 'queueA');
```

## 队列处理状态

队列处理器根据任务执行结果返回不同状态：

| 状态 | 常量 | 说明 |
|------|------|------|
| **ACK** | `Processor::ACK` | 任务执行成功，从队列中移除 |
| **REJECT** | `Processor::REJECT` | 任务无效，直接丢弃 |
| **REQUEUE** | `Processor::REQUEUE` | 任务失败，重新放入队列等待重试 |

## 与事件系统集成

```php
use Core\Event\Attribute\Listener;

class QueueEventListener
{
    #[Listener('user.registered')]
    public function handleUserRegistered($user): void
    {
        // 异步发送欢迎邮件
        App::queue()->add(
            'App\Jobs\EmailJob',
            'sendWelcome',
            [$user->id]
        )->send();

        // 延迟生成用户报告
        App::queue()->add(
            'App\Jobs\ReportJob',
            'generateUserReport',
            [$user->id]
        )->delay(300)->send(); // 延迟 5 分钟
    }
}
```

DuxLite 队列系统基于 Symfony Messenger 实现，支持 Redis 和 AMQP 后端，提供简单高效的异步任务处理能力。
