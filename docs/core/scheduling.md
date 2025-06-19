# 任务调度

DuxLite 提供了基于 Cron 表达式的强大任务调度系统，支持注解式配置和编程式配置两种方式。调度系统基于 ReactPHP 事件循环，能够精确执行定时任务，适用于数据清理、报告生成、系统维护等场景。

## 系统概述

### 调度系统架构

DuxLite 的任务调度系统采用事件循环模式：

```
任务注册 → Cron 解析 → 事件循环 → 时间检查 → 任务执行 → 日志记录
```

### 核心组件

- **Scheduler**：调度管理器，基于 GO\Scheduler 库
- **SchedulerCommand**：调度命令，启动调度服务
- **Scheduler 注解**：用于声明定时任务的注解
- **ReactPHP 事件循环**：提供精确的时间控制

## 基础用法

### 注解式调度（推荐）

使用 `#[Scheduler]` 注解声明定时任务：

```php
<?php
namespace App\Tasks;

use Core\Scheduler\Attribute\Scheduler;

class SystemTasks
{
    #[Scheduler('0 2 * * *')]  // 每天凌晨 2 点执行
    public function dailyCleanup(): void
    {
        // 清理临时文件
        $this->cleanTempFiles();

        // 清理过期日志
        $this->cleanExpiredLogs();

        // 清理过期缓存
        $this->cleanExpiredCache();
    }

    #[Scheduler('*/15 * * * *')]  // 每 15 分钟执行
    public function healthCheck(): void
    {
        // 检查系统健康状态
        $this->checkSystemHealth();

        // 检查数据库连接
        $this->checkDatabaseConnection();

        // 检查 Redis 连接
        $this->checkRedisConnection();
    }

    #[Scheduler('0 0 * * 0')]  // 每周日午夜执行
    public function weeklyReport(): void
    {
        // 生成周报
        $this->generateWeeklyReport();

        // 发送统计邮件
        $this->sendWeeklyStats();
    }

    #[Scheduler('0 */6 * * *')]  // 每 6 小时执行
    public function dataSync(): void
    {
        // 同步外部数据
        $this->syncExternalData();

        // 更新缓存
        $this->refreshCache();
    }
}
```

### 编程式调度

在应用模块中手动注册任务：

```php
<?php
namespace App\Admin;

use Core\App\AppExtend;
use Core\Bootstrap;
use Core\App;

class App extends AppExtend
{
    public function register(Bootstrap $bootstrap): void
    {
        $scheduler = App::scheduler();

        // 使用闭包任务
        $scheduler->add('0 1 * * *', function () {
            // 每天凌晨 1 点备份数据库
            $this->backupDatabase();
        });

        // 使用类方法任务
        $scheduler->add('*/30 * * * *', [
            'App\Tasks\MonitorTask',
            'checkServices'
        ]);

        // 带参数的任务
        $scheduler->add('0 3 * * *', [
            'App\Tasks\ReportTask',
            'generateReport'
        ], ['daily']);
    }

    private function backupDatabase(): void
    {
        // 数据库备份逻辑
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_{$timestamp}.sql";

        // 执行备份命令
        exec("mysqldump -u user -p password database > data/backups/{$filename}");

        // 记录日志
        error_log("数据库备份完成: {$filename}");
    }
}
```

## Cron 表达式详解

### 基本格式

Cron 表达式由 5 个字段组成：

```
分钟 小时 日期 月份 星期
*    *   *   *    *
```

| 字段 | 范围 | 特殊字符 | 说明 |
|------|------|----------|------|
| **分钟** | 0-59 | `* , - /` | 分钟数 |
| **小时** | 0-23 | `* , - /` | 小时数（24小时制） |
| **日期** | 1-31 | `* , - /` | 月份中的第几天 |
| **月份** | 1-12 | `* , - /` | 月份 |
| **星期** | 0-7 | `* , - /` | 星期几（0和7都表示周日） |

### 特殊字符含义

- **`*`** - 匹配任意值
- **`,`** - 分隔多个值
- **`-`** - 指定范围
- **`/`** - 指定间隔

### 常用 Cron 表达式示例

```php
class CronExamples
{
    #[Scheduler('0 0 * * *')]     // 每天午夜执行
    public function daily(): void {}

    #[Scheduler('0 */2 * * *')]   // 每 2 小时执行
    public function everyTwoHours(): void {}

    #[Scheduler('30 8 * * 1-5')]  // 工作日早上 8:30 执行
    public function weekdayMorning(): void {}

    #[Scheduler('0 9,17 * * 1-5')] // 工作日 9:00 和 17:00 执行
    public function businessHours(): void {}

    #[Scheduler('*/10 * * * *')]   // 每 10 分钟执行
    public function everyTenMinutes(): void {}

    #[Scheduler('0 2 1 * *')]      // 每月 1 号凌晨 2 点执行
    public function monthly(): void {}

    #[Scheduler('0 3 * * 0')]      // 每周日凌晨 3 点执行
    public function weekly(): void {}

    #[Scheduler('0 4 1 1 *')]      // 每年 1 月 1 日凌晨 4 点执行
    public function yearly(): void {}

    #[Scheduler('15,45 * * * *')]  // 每小时的第 15 和 45 分钟执行
    public function twicePerHour(): void {}

    #[Scheduler('0 0-8/2 * * *')]  // 0 点到 8 点，每 2 小时执行
    public function earlyHours(): void {}
}
```

## 任务类型和模式

### 1. 数据处理任务

```php
class DataProcessingTasks
{
    #[Scheduler('0 1 * * *')]  // 每天凌晨 1 点
    public function processOrderData(): void
    {
        // 处理昨天的订单数据
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $orders = Order::whereDate('created_at', $yesterday)->get();

        foreach ($orders as $order) {
            $this->calculateOrderMetrics($order);
            $this->updateCustomerStats($order);
        }

        // 生成数据报告
        $this->generateDailyReport($yesterday);
    }

    #[Scheduler('*/20 * * * *')]  // 每 20 分钟
    public function syncInventory(): void
    {
        // 同步库存数据
        $products = Product::where('sync_required', true)->get();

        foreach ($products as $product) {
            $this->syncProductInventory($product);
        }
    }

    #[Scheduler('0 3 * * 0')]  // 每周日凌晨 3 点
    public function weeklyDataCleanup(): void
    {
        // 清理过期数据
        $this->cleanExpiredSessions();
        $this->cleanOldLogFiles();
        $this->optimizeDatabaseTables();
    }

    private function calculateOrderMetrics($order): void
    {
        // 计算订单指标
    }

    private function updateCustomerStats($order): void
    {
        // 更新客户统计
    }

    private function generateDailyReport(string $date): void
    {
        // 生成日报
    }

    private function syncProductInventory($product): void
    {
        // 同步产品库存
    }

    private function cleanExpiredSessions(): void
    {
        // 清理过期会话
    }

    private function cleanOldLogFiles(): void
    {
        // 清理旧日志文件
    }

    private function optimizeDatabaseTables(): void
    {
        // 优化数据库表
    }
}
```

### 2. 系统维护任务

```php
class SystemMaintenanceTasks
{
    #[Scheduler('0 2 * * *')]  // 每天凌晨 2 点
    public function systemCleanup(): void
    {
        // 清理临时文件
        $this->cleanTempDirectory();

        // 清理缓存
        $this->clearExpiredCache();

        // 清理日志
        $this->rotateLogFiles();

        // 优化数据库
        $this->optimizeDatabase();
    }

    #[Scheduler('*/5 * * * *')]   // 每 5 分钟
    public function systemMonitor(): void
    {
        // 监控系统资源
        $cpuUsage = $this->getCpuUsage();
        $memoryUsage = $this->getMemoryUsage();
        $diskUsage = $this->getDiskUsage();

        // 记录监控数据
        $this->recordSystemMetrics($cpuUsage, $memoryUsage, $diskUsage);

        // 检查警告阈值
        $this->checkAlertThresholds($cpuUsage, $memoryUsage, $diskUsage);
    }

    #[Scheduler('0 4 * * 0')]   // 每周日凌晨 4 点
    public function weeklyMaintenance(): void
    {
        // 数据库备份
        $this->backupDatabase();

        // 系统更新检查
        $this->checkSystemUpdates();

        // 生成系统报告
        $this->generateSystemReport();
    }

    private function cleanTempDirectory(): void
    {
        // 清理临时目录
        $tempDir = 'data/temp';
        $files = glob($tempDir . '/*');
        $cutoff = time() - (24 * 60 * 60); // 24小时前

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }

    private function clearExpiredCache(): void
    {
        // 清理过期缓存
        $cache = App::cache();
        $cache->clear(); // 根据具体缓存实现调整
    }

    private function rotateLogFiles(): void
    {
        // 日志轮转
        $logDir = 'data/logs';
        $files = glob($logDir . '/*.log');

        foreach ($files as $file) {
            if (filesize($file) > 50 * 1024 * 1024) { // 50MB
                $newName = $file . '.' . date('Y-m-d');
                rename($file, $newName);
                gzopen($newName . '.gz', 'wb');
            }
        }
    }

    private function optimizeDatabase(): void
    {
        // 数据库优化
        $tables = ['users', 'orders', 'products']; // 需要优化的表

        foreach ($tables as $table) {
            DB::statement("OPTIMIZE TABLE {$table}");
        }
    }

    private function getCpuUsage(): float
    {
        // 获取 CPU 使用率
        $load = sys_getloadavg();
        return $load[0];
    }

    private function getMemoryUsage(): array
    {
        // 获取内存使用情况
        return [
            'used' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true)
        ];
    }

    private function getDiskUsage(): array
    {
        // 获取磁盘使用情况
        return [
            'free' => disk_free_space('.'),
            'total' => disk_total_space('.')
        ];
    }

    private function recordSystemMetrics($cpu, $memory, $disk): void
    {
        // 记录系统指标
        $metrics = [
            'timestamp' => time(),
            'cpu_usage' => $cpu,
            'memory_usage' => $memory,
            'disk_usage' => $disk
        ];

        // 保存到数据库或日志
        file_put_contents(
            'data/logs/system_metrics.log',
            json_encode($metrics) . "\n",
            FILE_APPEND
        );
    }

    private function checkAlertThresholds($cpu, $memory, $disk): void
    {
        // 检查警告阈值
        if ($cpu > 80) {
            $this->sendAlert('CPU usage high', "CPU: {$cpu}%");
        }

        $memoryPercent = ($memory['used'] / $memory['peak']) * 100;
        if ($memoryPercent > 90) {
            $this->sendAlert('Memory usage high', "Memory: {$memoryPercent}%");
        }

        $diskPercent = (($disk['total'] - $disk['free']) / $disk['total']) * 100;
        if ($diskPercent > 85) {
            $this->sendAlert('Disk usage high', "Disk: {$diskPercent}%");
        }
    }

    private function sendAlert(string $title, string $message): void
    {
        // 发送警告通知
        error_log("ALERT: {$title} - {$message}");
        // 可以集成邮件、短信、Slack 等通知方式
    }

    private function backupDatabase(): void
    {
        // 数据库备份
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_weekly_{$timestamp}.sql";

        // 执行备份
        exec("mysqldump -u root -p database > data/backups/{$filename}");
        gzopen("data/backups/{$filename}.gz", 'wb');
        unlink("data/backups/{$filename}");
    }

    private function checkSystemUpdates(): void
    {
        // 检查系统更新
        // 实现具体的更新检查逻辑
    }

    private function generateSystemReport(): void
    {
        // 生成系统报告
        // 实现报告生成逻辑
    }
}
```

### 3. 业务任务

```php
class BusinessTasks
{
    #[Scheduler('0 9 * * 1-5')]   // 工作日早上 9 点
    public function sendDailyReports(): void
    {
        // 发送日报给管理员
        $managers = User::where('role', 'manager')->get();

        foreach ($managers as $manager) {
            $report = $this->generateDailyReport($manager);
            $this->sendReportEmail($manager, $report);
        }
    }

    #[Scheduler('0 10 1 * *')]    // 每月 1 日上午 10 点
    public function monthlyBilling(): void
    {
        // 月度计费处理
        $subscriptions = Subscription::where('status', 'active')->get();

        foreach ($subscriptions as $subscription) {
            $this->processMonthlyBilling($subscription);
        }

        // 发送计费通知
        $this->sendBillingNotifications();
    }

    #[Scheduler('0 0 * * *')]     // 每天午夜
    public function processExpiredItems(): void
    {
        // 处理过期项目
        $expiredCoupons = Coupon::where('expires_at', '<', now())->get();
        foreach ($expiredCoupons as $coupon) {
            $coupon->update(['status' => 'expired']);
        }

        // 处理过期订单
        $expiredOrders = Order::where('status', 'pending')
            ->where('created_at', '<', now()->subDays(7))
            ->get();
        foreach ($expiredOrders as $order) {
            $order->update(['status' => 'cancelled']);
            $this->refundPayment($order);
        }
    }

    #[Scheduler('*/30 * * * *')]  // 每 30 分钟
    public function processNotifications(): void
    {
        // 处理待发送的通知
        $pendingNotifications = Notification::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->limit(100)
            ->get();

        foreach ($pendingNotifications as $notification) {
            $this->sendNotification($notification);
            $notification->update(['status' => 'sent', 'sent_at' => now()]);
        }
    }

    private function generateDailyReport($manager): array
    {
        // 生成日报数据
        return [
            'sales' => $this->getDailySales(),
            'users' => $this->getDailyUserStats(),
            'orders' => $this->getDailyOrderStats()
        ];
    }

    private function sendReportEmail($manager, $report): void
    {
        // 发送报告邮件
        // 使用邮件服务发送
    }

    private function processMonthlyBilling($subscription): void
    {
        // 处理月度计费
    }

    private function sendBillingNotifications(): void
    {
        // 发送计费通知
    }

    private function refundPayment($order): void
    {
        // 退款处理
    }

    private function sendNotification($notification): void
    {
        // 发送通知
    }

    private function getDailySales(): array
    {
        // 获取日销售数据
        return [];
    }

    private function getDailyUserStats(): array
    {
        // 获取日用户统计
        return [];
    }

    private function getDailyOrderStats(): array
    {
        // 获取日订单统计
        return [];
    }
}
```

## 启动调度服务

### 基础命令

```bash
# 启动调度服务
php dux scheduler
```

### 命令输出示例

```
+------------------------+-------------------+
| Core Scheduler Service | 2024-01-01 00:00 |
+------------------------+-------------------+
| App\Tasks\SystemTasks  | dailyCleanup      |
| App\Tasks\SystemTasks  | healthCheck       |
| App\Tasks\BusinessTasks| sendDailyReports  |
+------------------------+-------------------+
```

### 生产环境部署

#### 1. 使用 Supervisor 管理

创建 Supervisor 配置文件 `/etc/supervisor/conf.d/duxlite-scheduler.conf`：

```ini
[program:duxlite-scheduler]
process_name=%(program_name)s
command=php /path/to/your/app/dux scheduler
directory=/path/to/your/app
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/duxlite-scheduler.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
```

启动服务：

```bash
# 重新加载配置
sudo supervisorctl reread
sudo supervisorctl update

# 启动调度服务
sudo supervisorctl start duxlite-scheduler

# 查看状态
sudo supervisorctl status duxlite-scheduler
```

#### 2. 使用 systemd 管理

创建 systemd 服务文件 `/etc/systemd/system/duxlite-scheduler.service`：

```ini
[Unit]
Description=DuxLite Task Scheduler
After=network.target
Wants=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5
ExecStart=/usr/bin/php /path/to/your/app/dux scheduler
WorkingDirectory=/path/to/your/app
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

启动服务：

```bash
# 启动服务
sudo systemctl start duxlite-scheduler
sudo systemctl enable duxlite-scheduler

# 查看状态
sudo systemctl status duxlite-scheduler

# 查看日志
sudo journalctl -u duxlite-scheduler -f
```

#### 3. 使用 Docker 部署

创建 `docker-compose.yml`：

```yaml
version: '3.8'

services:
  scheduler:
    build: .
    command: php dux scheduler
    volumes:
      - .:/app
      - ./data:/app/data
    environment:
      - APP_ENV=production
    restart: unless-stopped
    depends_on:
      - mysql
      - redis

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: duxlite
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:alpine
    volumes:
      - redis_data:/data

volumes:
  mysql_data:
  redis_data:
```

## 任务监控和管理

### 任务状态监控

```php
class SchedulerMonitor
{
    public function getTaskStatus(): array
    {
        $scheduler = App::scheduler();

        return [
            'total_tasks' => count($scheduler->data),
            'running' => $this->isSchedulerRunning(),
            'last_execution' => $this->getLastExecutionTime(),
            'next_execution' => $this->getNextExecutionTime(),
            'task_list' => $scheduler->data
        ];
    }

    public function getTaskHistory(): array
    {
        // 从日志中获取任务执行历史
        $logFile = 'data/logs/scheduler.log';

        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $history = [];

        foreach (array_reverse($lines) as $line) {
            if (preg_match('/\[(.*?)\] (.*?) - (.*)/', $line, $matches)) {
                $history[] = [
                    'timestamp' => $matches[1],
                    'level' => $matches[2],
                    'message' => $matches[3]
                ];
            }
        }

        return array_slice($history, 0, 100); // 最近100条记录
    }

    public function getFailedTasks(): array
    {
        // 获取失败的任务
        $history = $this->getTaskHistory();

        return array_filter($history, function($item) {
            return $item['level'] === 'ERROR';
        });
    }

    private function isSchedulerRunning(): bool
    {
        // 检查调度器是否运行
        $pidFile = 'data/scheduler.pid';

        if (!file_exists($pidFile)) {
            return false;
        }

        $pid = file_get_contents($pidFile);
        return file_exists("/proc/{$pid}");
    }

    private function getLastExecutionTime(): ?string
    {
        $history = $this->getTaskHistory();
        return $history[0]['timestamp'] ?? null;
    }

    private function getNextExecutionTime(): array
    {
        // 计算下次执行时间（简化版本）
        $scheduler = App::scheduler();
        $nextTimes = [];

        foreach ($scheduler->data as $task) {
            // 这里需要根据 Cron 表达式计算下次执行时间
            // 实际实现需要使用 Cron 解析库
            $nextTimes[] = [
                'task' => $task,
                'next_run' => 'calculated_time'
            ];
        }

        return $nextTimes;
    }
}
```

### 任务性能统计

```php
class SchedulerPerformance
{
    public function recordTaskExecution(string $taskClass, string $method, float $executionTime): void
    {
        $data = [
            'timestamp' => time(),
            'task' => "{$taskClass}::{$method}",
            'execution_time' => $executionTime,
            'memory_usage' => memory_get_peak_usage(true),
            'date' => date('Y-m-d')
        ];

        // 记录到日志
        $logFile = 'data/logs/scheduler_performance.log';
        file_put_contents($logFile, json_encode($data) . "\n", FILE_APPEND);

        // 记录到 Redis（可选）
        $redis = App::redis();
        $key = "scheduler:performance:" . date('Y-m-d');
        $redis->lpush($key, json_encode($data));
        $redis->expire($key, 86400 * 30); // 保留30天
    }

    public function getPerformanceStats(string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $redis = App::redis();
        $key = "scheduler:performance:{$date}";

        $data = $redis->lrange($key, 0, -1);
        $stats = [];

        foreach ($data as $item) {
            $record = json_decode($item, true);
            $task = $record['task'];

            if (!isset($stats[$task])) {
                $stats[$task] = [
                    'count' => 0,
                    'total_time' => 0,
                    'avg_time' => 0,
                    'max_time' => 0,
                    'min_time' => PHP_FLOAT_MAX,
                    'total_memory' => 0,
                    'avg_memory' => 0
                ];
            }

            $stats[$task]['count']++;
            $stats[$task]['total_time'] += $record['execution_time'];
            $stats[$task]['max_time'] = max($stats[$task]['max_time'], $record['execution_time']);
            $stats[$task]['min_time'] = min($stats[$task]['min_time'], $record['execution_time']);
            $stats[$task]['total_memory'] += $record['memory_usage'];
        }

        // 计算平均值
        foreach ($stats as &$stat) {
            $stat['avg_time'] = $stat['total_time'] / $stat['count'];
            $stat['avg_memory'] = $stat['total_memory'] / $stat['count'];
        }

        return $stats;
    }
}
```

## 与其他系统集成

### 与队列系统集成

```php
class SchedulerQueueIntegration
{
    #[Scheduler('0 */2 * * *')]   // 每2小时
    public function processDelayedJobs(): void
    {
        // 将定时任务转为队列任务异步处理
        App::queue()->add(
            'App\Jobs\DataProcessJob',
            'processLargeDataset',
            []
        )->send();

        App::queue()->add(
            'App\Jobs\ReportJob',
            'generateHourlyReport',
            [date('Y-m-d H:00:00')]
        )->send();
    }

    #[Scheduler('*/15 * * * *')]  // 每15分钟
    public function queueHealthCheck(): void
    {
        // 检查队列健康状态
        $queueStats = $this->getQueueStats();

        if ($queueStats['pending'] > 1000) {
            // 队列积压过多，启动更多消费者
            App::queue()->add(
                'App\Jobs\SystemJob',
                'scaleQueueWorkers',
                ['scale_up']
            )->send();
        }
    }

    private function getQueueStats(): array
    {
        // 获取队列统计信息
        return [
            'pending' => 100,
            'processing' => 5,
            'failed' => 2
        ];
    }
}
```

### 与事件系统集成

```php
use Core\Event\Attribute\Listener;

class SchedulerEventIntegration
{
    #[Scheduler('0 5 * * *')]   // 每天凌晨5点
    public function triggerDailyEvents(): void
    {
        // 触发日常事件
        App::event()->dispatch('scheduler.daily.start');

        // 执行日常任务
        $this->performDailyTasks();

        // 触发完成事件
        App::event()->dispatch('scheduler.daily.complete');
    }

    #[Listener('user.created')]
    public function scheduleWelcomeEmail($user): void
    {
        // 延迟发送欢迎邮件（1小时后）
        $executeAt = date('H i * * *', strtotime('+1 hour'));

        // 动态添加一次性任务（伪代码，实际需要更复杂的实现）
        $this->scheduleOneTimeTask($executeAt, function() use ($user) {
            App::queue()->add(
                'App\Jobs\EmailJob',
                'sendWelcomeEmail',
                [$user->id]
            )->send();
        });
    }

    private function performDailyTasks(): void
    {
        // 执行日常任务
    }

    private function scheduleOneTimeTask(string $cron, callable $callback): void
    {
        // 添加一次性任务（需要特殊处理）
    }
}
```

## 最佳实践

### 1. 任务设计原则

```php
class BestPracticesTasks
{
    #[Scheduler('0 2 * * *')]
    public function wellDesignedTask(): void
    {
        $taskId = uniqid('task_');
        $startTime = microtime(true);

        try {
            // 记录任务开始
            App::log('scheduler')->info("任务开始", [
                'task_id' => $taskId,
                'task' => 'wellDesignedTask'
            ]);

            // 检查是否可以执行（避免重复执行）
            if ($this->isTaskRunning('wellDesignedTask')) {
                App::log('scheduler')->warning("任务已在运行中", ['task_id' => $taskId]);
                return;
            }

            // 设置运行标记
            $this->setTaskRunning('wellDesignedTask', true);

            // 执行任务逻辑（分步骤）
            $this->step1();
            $this->step2();
            $this->step3();

            // 记录成功
            $executionTime = microtime(true) - $startTime;
            App::log('scheduler')->info("任务完成", [
                'task_id' => $taskId,
                'execution_time' => $executionTime
            ]);

        } catch (\Exception $e) {
            // 记录错误
            App::log('scheduler')->error("任务失败", [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            // 清除运行标记
            $this->setTaskRunning('wellDesignedTask', false);
        }
    }

    #[Scheduler('*/5 * * * *')]
    public function idempotentTask(): void
    {
        // 幂等性任务：可以安全地重复执行
        $processedIds = $this->getProcessedIds();
        $pendingItems = $this->getPendingItems($processedIds);

        foreach ($pendingItems as $item) {
            try {
                $this->processItem($item);
                $this->markAsProcessed($item->id);
            } catch (\Exception $e) {
                App::log('scheduler')->error("处理项目失败", [
                    'item_id' => $item->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function isTaskRunning(string $taskName): bool
    {
        $lockFile = "data/locks/scheduler_{$taskName}.lock";
        return file_exists($lockFile);
    }

    private function setTaskRunning(string $taskName, bool $running): void
    {
        $lockFile = "data/locks/scheduler_{$taskName}.lock";

        if ($running) {
            file_put_contents($lockFile, getmypid());
        } else {
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }

    private function step1(): void
    {
        // 任务步骤1
        sleep(1); // 模拟处理时间
    }

    private function step2(): void
    {
        // 任务步骤2
        sleep(1);
    }

    private function step3(): void
    {
        // 任务步骤3
        sleep(1);
    }

    private function getProcessedIds(): array
    {
        // 获取已处理的ID列表
        return [];
    }

    private function getPendingItems(array $excludeIds): array
    {
        // 获取待处理项目
        return [];
    }

    private function processItem($item): void
    {
        // 处理单个项目
    }

    private function markAsProcessed(int $id): void
    {
        // 标记为已处理
    }
}
```

### 2. 错误处理和重试

```php
class RobustSchedulerTasks
{
    #[Scheduler('0 3 * * *')]
    public function taskWithRetry(): void
    {
        $maxRetries = 3;
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            try {
                $this->riskyOperation();
                break; // 成功则退出循环

            } catch (\Exception $e) {
                $retryCount++;

                App::log('scheduler')->warning("任务执行失败，准备重试", [
                    'retry_count' => $retryCount,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage()
                ]);

                if ($retryCount >= $maxRetries) {
                    // 最终失败，记录错误并可能发送告警
                    App::log('scheduler')->error("任务最终失败", [
                        'retry_count' => $retryCount,
                        'error' => $e->getMessage()
                    ]);

                    $this->sendFailureAlert('taskWithRetry', $e->getMessage());
                    throw $e;
                }

                // 指数退避
                sleep(pow(2, $retryCount));
            }
        }
    }

    #[Scheduler('*/10 * * * *')]
    public function taskWithFallback(): void
    {
        try {
            $this->primaryOperation();
        } catch (\Exception $e) {
            App::log('scheduler')->warning("主要操作失败，使用备选方案", [
                'error' => $e->getMessage()
            ]);

            try {
                $this->fallbackOperation();
            } catch (\Exception $fallbackError) {
                App::log('scheduler')->error("备选方案也失败", [
                    'primary_error' => $e->getMessage(),
                    'fallback_error' => $fallbackError->getMessage()
                ]);

                $this->sendFailureAlert('taskWithFallback',
                    "Primary and fallback operations failed");
            }
        }
    }

    private function riskyOperation(): void
    {
        // 可能失败的操作
        if (random_int(1, 10) <= 3) {
            throw new \Exception('模拟随机失败');
        }
    }

    private function primaryOperation(): void
    {
        // 主要操作
    }

    private function fallbackOperation(): void
    {
        // 备选操作
    }

    private function sendFailureAlert(string $task, string $error): void
    {
        // 发送失败告警
        // 可以通过邮件、短信、Slack等方式通知
        App::queue()->add(
            'App\Jobs\AlertJob',
            'sendTaskFailureAlert',
            [$task, $error]
        )->send();
    }
}
```

### 3. 性能优化

```php
class OptimizedSchedulerTasks
{
    #[Scheduler('0 1 * * *')]
    public function batchProcessingTask(): void
    {
        // 批量处理，减少数据库查询
        $batchSize = 1000;
        $offset = 0;

        do {
            $items = $this->getItemsBatch($offset, $batchSize);

            if (empty($items)) {
                break;
            }

            $this->processBatch($items);
            $offset += $batchSize;

            // 防止内存泄漏
            gc_collect_cycles();

        } while (count($items) === $batchSize);
    }

    #[Scheduler('*/30 * * * *')]
    public function cacheOptimizedTask(): void
    {
        $cache = App::cache();
        $cacheKey = 'scheduler:expensive_data';

        // 使用缓存减少重复计算
        $data = $cache->get($cacheKey);

        if (!$data) {
            $data = $this->expensiveCalculation();
            $cache->set($cacheKey, $data, 1800); // 缓存30分钟
        }

        $this->processData($data);
    }

    #[Scheduler('0 4 * * *')]
    public function memoryEfficientTask(): void
    {
        // 处理大量数据时使用生成器节省内存
        foreach ($this->getLargeDatasetGenerator() as $item) {
            $this->processItem($item);

            // 定期检查内存使用
            if (memory_get_usage() > 100 * 1024 * 1024) { // 100MB
                App::log('scheduler')->warning("内存使用过高", [
                    'memory_usage' => memory_get_usage(true)
                ]);
                gc_collect_cycles();
            }
        }
    }

    private function getItemsBatch(int $offset, int $limit): array
    {
        // 获取批量数据
        return [];
    }

    private function processBatch(array $items): void
    {
        // 批量处理
        foreach ($items as $item) {
            $this->processItem($item);
        }
    }

    private function expensiveCalculation(): array
    {
        // 昂贵的计算操作
        return [];
    }

    private function processData($data): void
    {
        // 处理数据
    }

    private function getLargeDatasetGenerator(): \Generator
    {
        // 使用生成器处理大数据集
        for ($i = 0; $i < 1000000; $i++) {
            yield $this->generateItem($i);
        }
    }

    private function generateItem(int $index): array
    {
        // 生成单个项目
        return ['id' => $index];
    }

    private function processItem($item): void
    {
        // 处理单个项目
    }
}
```

## 故障排除

### 常见问题诊断

#### 1. 调度器未启动

```bash
# 检查调度器进程
ps aux | grep "dux scheduler"

# 检查日志
tail -f data/logs/scheduler.log

# 手动启动测试
php dux scheduler
```

#### 2. 任务未执行

```php
// 检查任务注册
class DebugScheduler
{
    public function checkRegisteredTasks(): array
    {
        $scheduler = App::scheduler();
        return $scheduler->data;
    }

    public function testCronExpression(string $cron): bool
    {
        // 测试 Cron 表达式是否正确
        // 可以使用第三方库验证
        return true;
    }
}
```

#### 3. 性能问题

```bash
# 监控系统资源
top -p $(pgrep -f "dux scheduler")

# 检查内存使用
ps -o pid,ppid,cmd,%mem,%cpu -p $(pgrep -f "dux scheduler")

# 查看任务执行日志
grep "execution_time" data/logs/scheduler.log | tail -10
```

DuxLite 的任务调度系统为应用程序提供了强大的定时任务处理能力，通过合理设计和配置调度任务，可以实现系统的自动化运维和业务流程管理。