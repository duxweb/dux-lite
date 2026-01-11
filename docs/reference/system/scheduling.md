# 任务调度

DuxLite 基于 GO\Scheduler 和 React EventLoop 提供任务调度功能，支持 Cron 表达式和注解定义。

## 核心组件

- **Scheduler**：调度器类 (`Core\Scheduler\Scheduler`)
- **#[Scheduler]**：任务注解 (`Core\Scheduler\Attribute\Scheduler`)
- **GO\Job**：任务作业对象
- **React EventLoop**：事件循环处理器

## 基本使用

### 使用注解定义任务

```php
use Core\Scheduler\Attribute\Scheduler;

class TaskService
{
    // 每天凌晨2点执行
    #[Scheduler(cron: '0 2 * * *', name: 'daily-backup', desc: '每日备份')]
    public function dailyBackup(): void
    {
        App::log('scheduler')->info('开始每日备份任务');
        // 执行备份逻辑
    }
    
    // 每5分钟执行
    #[Scheduler(cron: '*/5 * * * *', name: 'clean-cache', desc: '清理缓存')]
    public function cleanCache(): void
    {
        App::log('scheduler')->info('清理缓存');
        // 执行缓存清理
    }
    
    // 每小时执行
    #[Scheduler(cron: '0 * * * *', name: 'generate-report', desc: '生成报表')]
    public function generateReport(): void
    {
        App::log('scheduler')->info('生成报告');
        // 执行报告生成
    }
}
```

### 动态添加任务

```php
// 动态添加类:方法任务（仅收集，不会立即注册）
App::scheduler()->add(
    TaskService::class . ':maintenance',
    [],
    '0 3 * * *',
    'maintenance',
    '定时维护任务'
);
```

## 启动调度器

### 命令行启动

```bash
# 启动调度器服务
php dux scheduler

# 禁用监控（默认开启监控 jobs 文件，变动后自动重启）
php dux scheduler --no-watch
```

如果使用外部守护进程（systemd/supervisor 等），当 `data/scheduler/jobs.php` 发生变动时，命令会以退出码 `100` 退出，由守护进程负责拉起新进程。

说明：调度器自身不会“自我拉起新进程”，Windows/Linux/macOS 都建议用外部守护进程保证常驻与自动拉起。

## 生成计划任务文件

调度器启动时会优先从 `data/scheduler/jobs.php` 读取计划任务并注册运行。

你可以在应用启动阶段（注解 + 手动 add 收集完数据后）调用：

```php
App::scheduler()->gen();
```

也可以通过命令行生成：

```bash
php dux scheduler:gen
```

### Scheduler 类方法

```php
// 收集任务（callback 仅支持 类 或 类:方法）
App::scheduler()->add(string $callback, array $params = [], string $cron = '* * * * *', string $name = '', string $desc = ''): void

// 生成 data 目录任务文件
App::scheduler()->gen(): array

// 运行调度器（从 data 文件读取任务并注册）
App::scheduler()->run(bool $watch = false, int $watchInterval = 3): int

// 注册注解定义的任务
App::scheduler()->registerAttribute(): void

// 获取当前收集到的临时任务数据
App::scheduler()->getData(): array
```

## Cron 表达式

### 基本格式

```
分钟 小时 日期 月份 星期
*    *   *   *    *
```

| 字段 | 取值范围 | 特殊字符 |
|------|----------|----------|
| 分钟 | 0-59 | `*` `,` `-` `/` |
| 小时 | 0-23 | `*` `,` `-` `/` |
| 日期 | 1-31 | `*` `,` `-` `/` |
| 月份 | 1-12 | `*` `,` `-` `/` |
| 星期 | 0-7 (0和7都代表周日) | `*` `,` `-` `/` |

### 常用示例

```php
class CronExamples
{
    // 每分钟执行
    #[Scheduler('* * * * *')]
    public function everyMinute(): void {}
    
    // 每5分钟执行
    #[Scheduler('*/5 * * * *')]
    public function everyFiveMinutes(): void {}
    
    // 每天凌晨2点执行
    #[Scheduler('0 2 * * *')]
    public function dailyAt2AM(): void {}
    
    // 每周一到周五的上午9点执行
    #[Scheduler('0 9 * * 1-5')]
    public function weekdaysAt9AM(): void {}
    
    // 每月1号凌晨3点执行
    #[Scheduler('0 3 1 * *')]
    public function monthlyAt3AM(): void {}
}
```

DuxLite 任务调度系统基于 GO\Scheduler，提供简单的 Cron 任务调度功能。
