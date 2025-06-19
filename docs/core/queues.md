# 队列系统

DuxLite 提供了基于 Enqueue 的强大队列系统，支持 Redis 和 AMQP（RabbitMQ）两种队列后端。队列系统用于处理异步任务，如邮件发送、图片处理、数据导入等耗时操作，提高应用响应性能。

## 系统概述

### 队列系统架构

DuxLite 的队列系统采用生产者-消费者模式：

```
任务创建 → 队列消息 → 队列存储 → 队列消费者 → 任务执行 → 结果处理
```

### 核心组件

- **Queue**：队列管理器，负责连接和任务分发
- **QueueMessage**：队列消息封装，支持延迟执行
- **QueueProcessor**：队列处理器，执行具体任务
- **QueueCommand**：队列消费命令，启动队列工作进程

## 队列配置

### 配置文件设置

队列配置分为两个文件：`queue.toml` 和 `database.toml`。

#### 1. 队列服务配置 (`config/queue.toml`)

```toml
# 队列服务类型：redis 或 amqp
type = "redis"

# 驱动器名称（对应 database.toml 中的配置）
driver = "default"
```

#### 2. 队列后端配置 (`config/database.toml`)

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

# 专用队列 Redis
[redis.drivers.queue]
host = "localhost"
port = 6379
auth = ""
database = 2
persistent = false
optPrefix = "dux_queue_"
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

### 获取队列实例

```php
use Core\App;

// 获取默认队列（从 queue.toml 读取类型）
$queue = App::queue();

// 获取指定类型的队列
$redisQueue = App::queue('redis');
$amqpQueue = App::queue('amqp');
```

## 创建队列任务

### 任务类定义

创建处理任务的类：

```php
<?php
namespace App\Jobs;

class EmailJob
{
    public function send(string $to, string $subject, string $body): void
    {
        // 邮件发送逻辑
        $this->sendEmail($to, $subject, $body);

        // 记录日志
        error_log("邮件已发送到: {$to}");
    }

    public function sendWelcome(int $userId): void
    {
        // 发送欢迎邮件
        $user = User::find($userId);
        if ($user) {
            $this->send(
                $user->email,
                '欢迎注册',
                "欢迎 {$user->name} 注册我们的网站！"
            );
        }
    }

    private function sendEmail(string $to, string $subject, string $body): void
    {
        // 实际的邮件发送实现
        // 可以使用 PHPMailer、SwiftMailer 等
        mail($to, $subject, $body);
    }
}
```

### 添加任务到队列

#### 1. 基础用法

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

#### 2. 延迟执行

```php
// 延迟 30 秒执行
$message = $queue->add('App\Jobs\EmailJob', 'send', [
    'to' => 'user@example.com',
    'subject' => '延迟邮件',
    'body' => '这是延迟 30 秒的邮件'
]);

$message->delay(30)->send();

// 延迟 1 小时执行
$message = $queue->add('App\Jobs\DataProcessJob', 'process', [$data]);
$message->delay(3600)->send();
```

#### 3. 指定队列

```php
// 添加到指定队列
$message = $queue->add(
    'App\Jobs\ImageJob',
    'resize',
    ['/path/to/image.jpg', 800, 600],
    'image_queue'  // 专门处理图片的队列
);
$message->send();

// 高优先级队列
$message = $queue->add(
    'App\Jobs\NotificationJob',
    'urgent',
    [$alertData],
    'high_priority'
);
$message->send();
```

## 队列任务处理模式

### 1. 简单任务处理

```php
class SimpleJob
{
    public function handle(array $data): void
    {
        // 处理数据
        foreach ($data as $item) {
            $this->processItem($item);
        }
    }

    private function processItem($item): void
    {
        // 具体处理逻辑
        sleep(1); // 模拟耗时操作
    }
}

// 使用
$queue->add('App\Jobs\SimpleJob', 'handle', [$batchData])->send();
```

### 2. 批量数据处理

```php
class BatchProcessJob
{
    public function processUsers(array $userIds): void
    {
        foreach ($userIds as $userId) {
            try {
                $this->processUser($userId);
            } catch (\Exception $e) {
                // 记录错误但继续处理其他用户
                error_log("处理用户 {$userId} 失败: " . $e->getMessage());
            }
        }
    }

    public function importCsv(string $filePath): void
    {
        $handle = fopen($filePath, 'r');
        while (($row = fgetcsv($handle)) !== false) {
            $this->processCsvRow($row);
        }
        fclose($handle);
    }

    private function processUser(int $userId): void
    {
        // 用户数据处理
    }

    private function processCsvRow(array $row): void
    {
        // CSV 行处理
    }
}
```

### 3. 文件处理任务

```php
class FileProcessJob
{
    public function compressImages(array $imagePaths): void
    {
        foreach ($imagePaths as $path) {
            $this->compressImage($path);
        }
    }

    public function generateReport(int $reportId): void
    {
        // 生成报告
        $report = Report::find($reportId);
        $data = $this->generateReportData($report);

        // 保存到文件
        $filePath = "data/reports/report_{$reportId}.pdf";
        $this->savePdf($filePath, $data);

        // 更新报告状态
        $report->update([
            'status' => 'completed',
            'file_path' => $filePath
        ]);
    }

    private function compressImage(string $path): void
    {
        // 图片压缩逻辑
    }

    private function generateReportData($report): array
    {
        // 报告数据生成
        return [];
    }

    private function savePdf(string $path, array $data): void
    {
        // PDF 生成和保存
    }
}
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

### 生产环境部署

#### 1. 使用 Supervisor 管理

创建 Supervisor 配置文件 `/etc/supervisor/conf.d/duxlite-queue.conf`：

```ini
[program:duxlite-default-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/dux queue:start
directory=/path/to/your/app
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/duxlite-queue.log
stdout_logfile_maxbytes=10MB

[program:duxlite-email-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/dux queue:start email_queue
directory=/path/to/your/app
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/duxlite-email-queue.log
```

启动 Supervisor：

```bash
# 重新加载配置
sudo supervisorctl reread
sudo supervisorctl update

# 启动队列进程
sudo supervisorctl start duxlite-default-queue:*
sudo supervisorctl start duxlite-email-queue:*

# 查看状态
sudo supervisorctl status
```

#### 2. 使用 systemd 管理

创建 systemd 服务文件 `/etc/systemd/system/duxlite-queue@.service`：

```ini
[Unit]
Description=DuxLite Queue Worker %i
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=3
ExecStart=/usr/bin/php /path/to/your/app/dux queue:start %i
WorkingDirectory=/path/to/your/app
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

启动服务：

```bash
# 启动默认队列
sudo systemctl start duxlite-queue@queue
sudo systemctl enable duxlite-queue@queue

# 启动邮件队列
sudo systemctl start duxlite-queue@email_queue
sudo systemctl enable duxlite-queue@email_queue

# 查看状态
sudo systemctl status duxlite-queue@queue
```

## 错误处理和重试

### 任务执行状态

队列处理器会根据任务执行结果返回不同状态：

| 状态 | 常量 | 说明 |
|------|------|------|
| **ACK** | `Processor::ACK` | 任务执行成功，从队列中移除 |
| **REJECT** | `Processor::REJECT` | 任务无效，直接丢弃 |
| **REQUEUE** | `Processor::REQUEUE` | 任务失败，重新放入队列等待重试 |

### 自定义错误处理

```php
class RobustJob
{
    public function processWithRetry(array $data): void
    {
        $maxRetries = 3;
        $currentRetry = 0;

        while ($currentRetry < $maxRetries) {
            try {
                $this->process($data);
                return; // 成功则退出
            } catch (\Exception $e) {
                $currentRetry++;

                if ($currentRetry >= $maxRetries) {
                    // 记录最终失败
                    error_log("任务最终失败: " . $e->getMessage());
                    throw $e;
                }

                // 等待后重试
                sleep(pow(2, $currentRetry)); // 指数退避
            }
        }
    }

    public function processWithFallback($data): void
    {
        try {
            $this->primaryProcess($data);
        } catch (\Exception $e) {
            // 记录主要处理失败
            error_log("主要处理失败，使用备选方案: " . $e->getMessage());

            try {
                $this->fallbackProcess($data);
            } catch (\Exception $fallbackError) {
                // 备选方案也失败
                error_log("备选方案也失败: " . $fallbackError->getMessage());
                throw $fallbackError;
            }
        }
    }

    private function process(array $data): void
    {
        // 主要处理逻辑
        if (random_int(1, 10) <= 3) {
            throw new \Exception('模拟随机失败');
        }
    }

    private function primaryProcess($data): void
    {
        // 主要处理方法
    }

    private function fallbackProcess($data): void
    {
        // 备选处理方法
    }
}
```

### 死信队列处理

```php
class DeadLetterJob
{
    public function handleFailedJob(array $originalData, string $error): void
    {
        // 记录失败任务
        FailedJob::create([
            'data' => json_encode($originalData),
            'error' => $error,
            'failed_at' => now(),
            'retry_count' => 0
        ]);

        // 发送告警
        $this->sendFailureAlert($originalData, $error);
    }

    public function retryFailedJobs(): void
    {
        $failedJobs = FailedJob::where('retry_count', '<', 5)
            ->where('created_at', '>', now()->subHours(24))
            ->get();

        foreach ($failedJobs as $job) {
            try {
                // 重新加入队列
                $data = json_decode($job->data, true);
                App::queue()->add(
                    $data['class'],
                    $data['method'],
                    $data['params']
                )->send();

                $job->increment('retry_count');
            } catch (\Exception $e) {
                error_log("重试失败任务出错: " . $e->getMessage());
            }
        }
    }

    private function sendFailureAlert(array $data, string $error): void
    {
        // 发送失败告警邮件或通知
    }
}
```

## 队列监控和管理

### 队列状态监控

```php
class QueueMonitor
{
    public function getQueueStats(string $queueName = 'queue'): array
    {
        $redis = App::redis('queue');

        return [
            'pending' => $redis->llen($queueName),
            'processing' => $redis->get("{$queueName}:processing") ?: 0,
            'failed' => $redis->get("{$queueName}:failed") ?: 0,
            'completed' => $redis->get("{$queueName}:completed") ?: 0
        ];
    }

    public function clearQueue(string $queueName): int
    {
        $redis = App::redis('queue');
        return $redis->del($queueName);
    }

    public function pauseQueue(string $queueName): void
    {
        $redis = App::redis('queue');
        $redis->set("{$queueName}:paused", 1);
    }

    public function resumeQueue(string $queueName): void
    {
        $redis = App::redis('queue');
        $redis->del("{$queueName}:paused");
    }
}
```

### 队列性能统计

```php
class QueueStats
{
    public function recordJobExecution(string $jobClass, float $executionTime): void
    {
        $redis = App::redis('queue');
        $today = date('Y-m-d');

        // 记录任务执行次数
        $redis->incr("queue:stats:{$today}:count");
        $redis->incr("queue:stats:{$today}:jobs:{$jobClass}");

        // 记录执行时间
        $redis->lpush("queue:stats:{$today}:times", $executionTime);
        $redis->ltrim("queue:stats:{$today}:times", 0, 999); // 保留最近1000条
    }

    public function getDailyStats(string $date): array
    {
        $redis = App::redis('queue');

        $totalJobs = $redis->get("queue:stats:{$date}:count") ?: 0;
        $times = $redis->lrange("queue:stats:{$date}:times", 0, -1);

        $avgTime = count($times) > 0 ? array_sum($times) / count($times) : 0;
        $maxTime = count($times) > 0 ? max($times) : 0;

        return [
            'total_jobs' => $totalJobs,
            'avg_execution_time' => $avgTime,
            'max_execution_time' => $maxTime,
            'execution_times' => $times
        ];
    }
}
```

## 与其他系统集成

### 与事件系统集成

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

        // 异步生成用户报告
        App::queue()->add(
            'App\Jobs\ReportJob',
            'generateUserReport',
            [$user->id]
        )->delay(300)->send(); // 延迟 5 分钟
    }

    #[Listener('order.completed')]
    public function handleOrderCompleted($order): void
    {
        // 异步发送订单确认邮件
        App::queue()->add(
            'App\Jobs\EmailJob',
            'sendOrderConfirmation',
            [$order->id]
        )->send();

        // 异步更新库存
        App::queue()->add(
            'App\Jobs\InventoryJob',
            'updateStock',
            [$order->items]
        )->send();
    }
}
```

### 与计划任务集成

```php
use Core\Scheduler\Attribute\Scheduler;

class ScheduledQueueJobs
{
    #[Scheduler('0 2 * * *')] // 每天凌晨 2 点
    public function dailyCleanup(): void
    {
        // 清理过期数据
        App::queue()->add(
            'App\Jobs\CleanupJob',
            'cleanExpiredData',
            []
        )->send();

        // 生成日报
        App::queue()->add(
            'App\Jobs\ReportJob',
            'generateDailyReport',
            [date('Y-m-d')]
        )->send();
    }

    #[Scheduler('*/30 * * * *')] // 每30分钟
    public function healthCheck(): void
    {
        // 检查队列健康状态
        App::queue()->add(
            'App\Jobs\MonitorJob',
            'checkQueueHealth',
            []
        )->send();
    }
}
```

## 最佳实践

### 1. 任务设计原则

```php
// ✅ 推荐：幂等性设计
class IdempotentJob
{
    public function processOrder(int $orderId): void
    {
        $order = Order::find($orderId);

        // 检查是否已处理
        if ($order->status === 'processed') {
            return; // 已处理，直接返回
        }

        // 处理订单
        $this->doProcess($order);

        // 标记为已处理
        $order->update(['status' => 'processed']);
    }
}

// ✅ 推荐：小任务拆分
class BatchEmailJob
{
    public function sendBatch(array $userIds): void
    {
        // 将大批次拆分为小批次
        $chunks = array_chunk($userIds, 10);

        foreach ($chunks as $chunk) {
            App::queue()->add(
                'App\Jobs\EmailJob',
                'sendToUsers',
                [$chunk]
            )->send();
        }
    }
}
```

### 2. 错误处理策略

```php
class ReliableJob
{
    public function handleWithLogging($data): void
    {
        $jobId = uniqid('job_');

        try {
            App::log('queue')->info("任务开始", ['job_id' => $jobId, 'data' => $data]);

            $this->process($data);

            App::log('queue')->info("任务完成", ['job_id' => $jobId]);
        } catch (\Exception $e) {
            App::log('queue')->error("任务失败", [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // 重新抛出异常，让队列系统处理重试
        }
    }
}
```

### 3. 性能优化

```php
class OptimizedJob
{
    public function processWithBatching(array $items): void
    {
        // ✅ 批量数据库操作
        DB::transaction(function () use ($items) {
            $chunks = array_chunk($items, 100);
            foreach ($chunks as $chunk) {
                $this->batchInsert($chunk);
            }
        });
    }

    public function processWithCache(int $userId): void
    {
        // ✅ 使用缓存减少数据库查询
        $user = Cache::remember("user:{$userId}", 3600, function () use ($userId) {
            return User::find($userId);
        });

        $this->processUser($user);
    }

    private function batchInsert(array $data): void
    {
        // 批量插入实现
    }

    private function processUser($user): void
    {
        // 用户处理逻辑
    }
}
```

### 4. 队列组织

```php
// ✅ 推荐：按业务功能组织队列
class QueueManager
{
    public const QUEUES = [
        'email' => 'email_queue',           // 邮件队列
        'sms' => 'sms_queue',               // 短信队列
        'image' => 'image_queue',           // 图片处理队列
        'report' => 'report_queue',         // 报告生成队列
        'cleanup' => 'cleanup_queue',       // 清理任务队列
        'high_priority' => 'priority_queue' // 高优先级队列
    ];

    public static function addEmailJob(string $jobClass, string $method, array $params): void
    {
        App::queue()->add($jobClass, $method, $params, self::QUEUES['email'])->send();
    }

    public static function addImageJob(string $jobClass, string $method, array $params): void
    {
        App::queue()->add($jobClass, $method, $params, self::QUEUES['image'])->send();
    }

    public static function addUrgentJob(string $jobClass, string $method, array $params): void
    {
        App::queue()->add($jobClass, $method, $params, self::QUEUES['high_priority'])->send();
    }
}
```

## 故障排除

### 常见问题诊断

#### 1. 队列连接问题

```bash
# 检查 Redis 连接
redis-cli ping

# 检查 RabbitMQ 连接
rabbitmqctl status

# 测试队列配置
php -r "
$queue = \Core\App::queue();
$queue->add('Test', 'test', [])->send();
echo 'Queue test successful';
"
```

#### 2. 任务执行失败

```php
// 调试任务执行
class DebugJob
{
    public function debug($data): void
    {
        var_dump($data);
        error_log("Debug job executed with data: " . json_encode($data));
    }
}

// 测试任务
App::queue()->add('DebugJob', 'debug', ['test' => 'data'])->send();
```

#### 3. 性能问题

```bash
# 监控队列长度
redis-cli llen queue

# 监控消费者状态
ps aux | grep "queue:start"

# 查看系统资源使用
top -p $(pgrep -f "queue:start")
```

DuxLite 的队列系统为应用程序提供了强大的异步处理能力，通过合理使用队列，可以显著提升应用的响应性能和用户体验。