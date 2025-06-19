# Scheduler 计划任务

DuxLite 计划任务调度系统的核心类定义和 API 规格说明。

## Register 类

**命名空间：** `Core\Scheduler\Register`

**说明：** 计划任务注册器，用于管理任务调度器。

### 方法

```php
public function set(string $name, Scheduler $scheduler): void
```
- **参数：**
  - `$name` - 调度器名称
  - `$scheduler` - 调度器实例
- **返回：** `void`
- **说明：** 注册任务调度器

```php
public function get(string $name): Scheduler
```
- **参数：** `$name` - 调度器名称
- **返回：** `Scheduler` - 调度器实例
- **说明：** 获取任务调度器

## Scheduler 类

**命名空间：** `Core\Scheduler\Scheduler`

**说明：** 计划任务调度器核心类。

### 属性

```php
public GoScheduler $scheduler
```
- **说明：** GO调度器实例

```php
public array $data = []
```
- **说明：** 任务数据数组

### 方法

```php
public function __construct()
```
- **说明：** 构造调度器实例，初始化GO调度器

```php
public function add(string $cron, callable|array $callback, array $params = []): void
```
- **参数：**
  - `$cron` - Cron表达式
  - `$callback` - 回调函数或类方法数组
  - `$params` - 参数数组（可选）
- **返回：** `void`
- **说明：** 添加计划任务

```php
public function job(callable|array $callback, array $params = []): \GO\Job
```
- **参数：**
  - `$callback` - 回调函数或类方法数组
  - `$params` - 参数数组（可选）
- **返回：** `\GO\Job` - 任务实例
- **异常：** `Exception` - 类或方法不存在时抛出
- **说明：** 创建任务实例

```php
public function run(): void
```
- **返回：** `void`
- **说明：** 运行调度器，启动事件循环

```php
public function registerAttribute(): void
```
- **返回：** `void`
- **说明：** 注册属性注解任务

## Job 类（GO组件）

**命名空间：** `\GO\Job`

**说明：** 任务实例类，用于配置任务执行时间。

### 方法

```php
public function at(string $cron): self
```
- **参数：** `$cron` - Cron表达式
- **返回：** `self` - 当前实例
- **说明：** 设置任务执行时间

```php
public function everyMinute(): self
```
- **返回：** `self` - 当前实例
- **说明：** 每分钟执行

```php
public function hourly(): self
```
- **返回：** `self` - 当前实例
- **说明：** 每小时执行

```php
public function daily(): self
```
- **返回：** `self` - 当前实例
- **说明：** 每天执行

```php
public function weekly(): self
```
- **返回：** `self` - 当前实例
- **说明：** 每周执行

```php
public function monthly(): self
```
- **返回：** `self` - 当前实例
- **说明：** 每月执行

```php
public function yearly(): self
```
- **返回：** `self` - 当前实例
- **说明：** 每年执行

```php
public function then(callable $callback): self
```
- **参数：** `$callback` - 成功回调函数
- **返回：** `self` - 当前实例
- **说明：** 任务成功后执行回调

```php
public function catch(callable $callback): self
```
- **参数：** `$callback` - 异常回调函数
- **返回：** `self` - 当前实例
- **说明：** 任务异常时执行回调

## SchedulerCommand 类

**命名空间：** `Core\Scheduler\SchedulerCommand`

**说明：** 计划任务命令行工具。

### 方法

```php
public function run(): void
```
- **返回：** `void`
- **说明：** 运行调度器

```php
public function list(): void
```
- **返回：** `void`
- **说明：** 列出所有任务

```php
public function status(): void
```
- **返回：** `void`
- **说明：** 查看任务状态

## Scheduler 属性注解

**命名空间：** `Core\Scheduler\Attribute\Scheduler`

**说明：** 计划任务属性注解。

### 方法

```php
public function __construct(string $cron)
```
- **参数：** `$cron` - Cron表达式
- **说明：** 构造任务注解

## 使用示例

```php
use Core\Scheduler\Attribute\Scheduler;

class TaskService
{
    // 每分钟执行
    #[Scheduler('* * * * *')]
    public function minuteTask(): void
    {
        App::log('scheduler')->info('每分钟任务执行');
    }

    // 每天凌晨2点执行
    #[Scheduler('0 2 * * *')]
    public function dailyCleanup(): void
    {
        // 清理日志文件
        $this->cleanupLogs();
    }

    // 每周一凌晨1点执行
    #[Scheduler('0 1 * * 1')]
    public function weeklyReport(): void
    {
        // 生成周报
        $this->generateWeeklyReport();
    }

    private function cleanupLogs(): void
    {
        $logDir = App::$dataPath . '/logs';
        $files = glob($logDir . '/*.log');

        foreach ($files as $file) {
            if (time() - filemtime($file) > 30 * 24 * 60 * 60) { // 30天
                unlink($file);
            }
        }
    }

    private function generateWeeklyReport(): void
    {
        // 报告生成逻辑
        App::log('scheduler')->info('周报生成完成');
    }
}

// 手动添加任务
$scheduler = App::scheduler();

// 闭包任务
$scheduler->add('0 0 * * *', function() {
    echo "每天午夜执行\n";
});

// 类方法任务
$scheduler->add('*/5 * * * *', [TaskService::class, 'minuteTask']);

// 使用Job实例配置
$scheduler->job([TaskService::class, 'dailyCleanup'])
          ->daily()
          ->then(function() {
              App::log('scheduler')->info('清理任务完成');
          })
          ->catch(function($e) {
              App::log('scheduler')->error('清理任务失败: ' . $e->getMessage());
          });
```

## Cron 表达式格式

### 基本格式

```
* * * * *
│ │ │ │ │
│ │ │ │ └─── 星期 (0-7, 0和7都表示周日)
│ │ │ └───── 月份 (1-12)
│ │ └─────── 日期 (1-31)
│ └───────── 小时 (0-23)
└─────────── 分钟 (0-59)
```

### 常用表达式

| 表达式 | 说明 |
|--------|------|
| `* * * * *` | 每分钟执行 |
| `0 * * * *` | 每小时执行 |
| `0 0 * * *` | 每天午夜执行 |
| `0 0 * * 0` | 每周日午夜执行 |
| `0 0 1 * *` | 每月1号午夜执行 |
| `0 0 1 1 *` | 每年1月1号午夜执行 |
| `*/5 * * * *` | 每5分钟执行 |
| `0 */2 * * *` | 每2小时执行 |
| `0 9-17 * * 1-5` | 工作日9-17点每小时执行 |

### 特殊字符

| 字符 | 说明 | 示例 |
|------|------|------|
| `*` | 匹配任何值 | `* * * * *` |
| `?` | 不指定值（日期和星期互斥） | `0 0 ? * 1` |
| `-` | 范围 | `0 9-17 * * *` |
| `,` | 列举 | `0 9,12,15 * * *` |
| `/` | 步长 | `*/5 * * * *` |
| `L` | 最后 | `0 0 L * *` |
| `W` | 工作日 | `0 0 15W * *` |
| `#` | 第几个星期几 | `0 0 * * 1#2` |

## 任务管理

### 任务状态

| 状态 | 说明 |
|------|------|
| `pending` | 等待执行 |
| `running` | 正在执行 |
| `success` | 执行成功 |
| `failed` | 执行失败 |
| `skipped` | 跳过执行 |

### 任务配置

| 配置项 | 类型 | 说明 |
|--------|------|------|
| `cron` | `string` | Cron表达式 |
| `callback` | `callable\|array` | 回调函数 |
| `params` | `array` | 参数数组 |
| `timeout` | `int` | 超时时间（秒） |
| `memory_limit` | `string` | 内存限制 |
| `max_retries` | `int` | 最大重试次数 |

## 任务监控

### 日志记录

```php
// 任务执行日志会自动记录到 data/logs/scheduler.log
App::log('scheduler')->info('任务开始执行', [
    'task' => $taskName,
    'cron' => $cronExpression
]);

App::log('scheduler')->error('任务执行失败', [
    'task' => $taskName,
    'error' => $exception->getMessage()
]);
```

### 性能监控

```php
class PerformanceTask
{
    #[Scheduler('*/10 * * * *')]
    public function monitorSystem(): void
    {
        $metrics = [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'cpu_load' => sys_getloadavg()[0],
            'disk_free' => disk_free_space('.'),
            'timestamp' => time()
        ];

        App::log('performance')->info('系统指标', $metrics);

        // 告警检查
        if ($metrics['memory_usage'] > 100 * 1024 * 1024) { // 100MB
            App::log('alert')->warning('内存使用过高', $metrics);
        }
    }
}
```