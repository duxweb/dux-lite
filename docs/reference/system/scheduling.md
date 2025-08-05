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
    #[Scheduler('0 2 * * *')]
    public function dailyBackup(): void
    {
        App::log('scheduler')->info('开始每日备份任务');
        // 执行备份逻辑
    }
    
    // 每5分钟执行
    #[Scheduler('*/5 * * * *')]
    public function cleanCache(): void
    {
        App::log('scheduler')->info('清理缓存');
        // 执行缓存清理
    }
    
    // 每小时执行
    #[Scheduler('0 * * * *')]
    public function generateReport(): void
    {
        App::log('scheduler')->info('生成报告');
        // 执行报告生成
    }
}
```

### 动态添加任务

```php
// 动态添加类方法任务
App::scheduler()->add('0 3 * * *', [TaskService::class, 'maintenance']);

// 动态添加闭包任务
App::scheduler()->add('*/10 * * * *', function() {
    App::log('scheduler')->info('定时检查任务');
    // 执行检查逻辑
});
```

## 启动调度器

### 命令行启动

```bash
# 启动调度器服务
php dux scheduler
```

### Scheduler 类方法

```php
// 添加任务
// $cron: Cron表达式, $callback: 回调函数或类方法数组, $params: 参数
App::scheduler()->add(string $cron, callable|array $callback, array $params = []): void

// 创建任务作业
App::scheduler()->job($callback, array $params = []): \GO\Job

// 运行调度器
App::scheduler()->run(): void

// 注册注解定义的任务
App::scheduler()->registerAttribute(): void
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