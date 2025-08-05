# 队列处理

DuxLite 基于 Enqueue 提供队列系统，支持 Redis 和 AMQP（RabbitMQ）两种队列后端，用于处理异步任务。

## 核心组件

- **Queue**：队列管理器 (`Core\Queue\Queue`)
- **QueueMessage**：队列消息封装 (`Core\Queue\QueueMessage`)
- **QueueProcessor**：队列处理器 (`Core\Queue\QueueProcessor`)
- **QueueCommand**：队列消费命令，启动队列工作进程

## 配置系统

### 队列服务配置 (`config/queue.toml`)

```toml
# 队列服务类型：redis 或 amqp
type = "redis"

# 驱动器名称（对应 database.toml 中的配置）
driver = "default"
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

// 获取指定类型的队列
$redisQueue = App::queue('redis');
$amqpQueue = App::queue('amqp');
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

// 获取队列实例
$queue = App::queue();

// 添加任务到默认队列
$message = $queue->add(
    class: 'App\Jobs\EmailJob',
    method: 'send',
    params: ['user@example.com', '测试邮件', '这是测试内容'],
    name: 'queue'  // 队列名称（可选，默认为 'queue'）
);

// 立即发送任务
$message->send();
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
// 添加到指定队列
$message = $queue->add(
    'App\Jobs\ImageJob',
    'resize',
    ['/path/to/image.jpg', 800, 600],
    'image_queue'  // 专门处理图片的队列
);
$message->send();
```

## 启动队列消费者

### 基础消费命令

```bash
# 启动默认队列消费者
php dux queue:start

# 启动指定队列消费者
php dux queue:start email_queue

# 启动图片处理队列
php dux queue:start image_queue
```

### 命令输出示例

```
+---------------+
| Queue Service |
+---------------+
| Core Ver: 2.0 |
| Run Time: ... |
+---------------+
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

DuxLite 队列系统基于 Enqueue 实现，支持 Redis 和 AMQP 后端，提供简单高效的异步任务处理能力。