# 任务调度

DuxLite 提供了基于 GO\Scheduler 和 React EventLoop 的强大任务调度系统，支持 Cron 表达式、注解定义和实时执行监控。通过灵活的调度配置和高效的执行机制，轻松实现定时任务和后台作业管理。

## 基本概念

### 设计理念

DuxLite 任务调度系统采用**注解驱动、异步执行、监控完备**的设计：

- **注解驱动**：通过 `#[Scheduler]` 注解定义任务，自动发现和注册
- **Cron 表达式**：支持标准 Cron 语法，精确控制执行时间
- **异步执行**：基于 React EventLoop 的非阻塞任务执行
- **错误处理**：完整的异常捕获和日志记录机制
- **监控支持**：实时任务状态监控和执行统计

### 核心特性

- **灵活调度**：支持秒级精度的任务调度
- **错误恢复**：任务失败自动重试和错误隔离
- **资源管理**：内存和CPU使用监控
- **日志记录**：详细的执行日志和性能统计
- **热重载**：支持任务的动态添加和移除

## 调度系统架构

### 核心组件

```php
// src/Scheduler/Scheduler.php
class Scheduler
{
    public GoScheduler $scheduler;  // GO\Scheduler 实例
    public array $data = [];        // 注册的任务数据
    
    public function __construct()
    {
        $this->scheduler = new GoScheduler();
    }
    
    /**
     * 添加定时任务
     */
    public function add(string $cron, callable|array $callback, $params = []): void
    {
        $this->job($callback, $params)->at($cron);
    }
    
    /**
     * 创建任务作业
     */
    public function job($callback, $params = []): \GO\Job
    {
        if ($callback instanceof \Closure) {
            $this->data[] = ['func', '-'];
            return $this->scheduler->call($callback, $params);
        } else {
            [$class, $method] = $callback;
            
            // 验证类和方法存在
            if (!class_exists($class)) {
                throw new Exception($class . ' does not exist');
            }
            if (!method_exists($class, $method)) {
                throw new Exception($class . ':' . $method . ' does not exist');
            }
            
            $this->data[] = [$class, $method];
            
            return $this->scheduler->call(function () use ($class, $method, $params) {
                try {
                    $object = new $class;
                    call_user_func([$object, $method], $params);
                } catch (Exception $e) {
                    // 记录任务执行错误
                    App::log('scheduler')->error($e->getMessage(), [
                        'file' => $e->getFile() . ':' . $e->getLine(),
                        'class' => $class,
                        'method' => $method,
                        'params' => $params
                    ]);
                }
            });
        }
    }
    
    /**
     * 运行调度器
     */
    public function run(): void
    {
        $this->scheduler->work();
        $loop = Loop::get();
        
        // 每秒检查一次任务
        $loop->addPeriodicTimer(1, function () {
            $seconds = [0]; // 在每分钟的第0秒执行
            if (in_array((int)date('s'), $seconds)) {
                $this->scheduler->run();
            }
        });
        
        $loop->run();
    }
    
    /**
     * 注册注解定义的任务
     */
    public function registerAttribute(): void
    {
        $attributes = App::attributes();
        
        foreach ($attributes as $item) {
            foreach ($item["annotations"] as $annotation) {
                if ($annotation["name"] != AttributeScheduler::class) {
                    continue;
                }
                
                $params = $annotation["params"];
                $callback = explode(':', $annotation["class"]);
                
                $this->add($params["cron"], $callback);
            }
        }
    }
}
```

### 注解定义

```php
// src/Scheduler/Attribute/Scheduler.php
#[Attribute(Attribute::TARGET_METHOD)]
class Scheduler 
{
    public function __construct(
        public string $cron  // Cron 表达式
    ) {}
}
```

## 任务定义和注册

### 使用注解定义任务

```php
use Core\Scheduler\Attribute\Scheduler;

class TaskService
{
    /**
     * 每天凌晨2点执行数据备份
     */
    #[Scheduler('0 2 * * *')]
    public function backupDatabase(): void
    {
        App::log('scheduler')->info('开始数据库备份任务');
        
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupFile = App::$dataPath . "/backups/database_{$timestamp}.sql";
            
            // 创建备份目录
            $backupDir = dirname($backupFile);
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            // 执行数据库备份
            $config = App::config('database')->get('db.drivers.mysql');
            $command = sprintf(
                'mysqldump -h%s -u%s -p%s %s > %s',
                $config['host'],
                $config['username'],
                $config['password'],
                $config['database'],
                $backupFile
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                App::log('scheduler')->info('数据库备份完成', [
                    'backup_file' => $backupFile,
                    'file_size' => filesize($backupFile)
                ]);
                
                // 清理旧备份文件
                $this->cleanOldBackups();
            } else {
                throw new Exception('备份命令执行失败，返回码: ' . $returnCode);
            }
            
        } catch (Exception $e) {
            App::log('scheduler')->error('数据库备份失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }
    
    /**
     * 每5分钟执行一次缓存清理
     */
    #[Scheduler('*/5 * * * *')]
    public function cleanExpiredCache(): void
    {
        App::log('scheduler')->info('开始清理过期缓存');
        
        try {
            $cache = App::cache();
            $cleaned = 0;
            
            // 清理应用缓存
            $keys = $cache->get('cache_keys', []);
            foreach ($keys as $key) {
                if ($cache->has($key)) {
                    $cache->delete($key);
                    $cleaned++;
                }
            }
            
            App::log('scheduler')->info('缓存清理完成', [
                'cleaned_count' => $cleaned
            ]);
            
        } catch (Exception $e) {
            App::log('scheduler')->error('缓存清理失败', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 每小时生成统计报告
     */
    #[Scheduler('0 * * * *')]
    public function generateHourlyReport(): void
    {
        App::log('scheduler')->info('开始生成小时统计报告');
        
        try {
            $hour = date('Y-m-d H:00:00');
            $stats = $this->collectHourlyStats($hour);
            
            // 保存统计数据
            $reportFile = App::$dataPath . "/reports/hourly_" . date('Y-m-d_H') . ".json";
            $reportDir = dirname($reportFile);
            
            if (!is_dir($reportDir)) {
                mkdir($reportDir, 0755, true);
            }
            
            file_put_contents($reportFile, json_encode($stats, JSON_PRETTY_PRINT));
            
            App::log('scheduler')->info('小时统计报告生成完成', [
                'hour' => $hour,
                'report_file' => $reportFile,
                'stats' => $stats
            ]);
            
        } catch (Exception $e) {
            App::log('scheduler')->error('统计报告生成失败', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 每周日凌晨执行系统维护
     */
    #[Scheduler('0 3 * * 0')]
    public function weeklyMaintenance(): void
    {
        App::log('scheduler')->info('开始系统维护任务');
        
        try {
            // 清理日志文件
            $this->cleanOldLogs();
            
            // 优化数据库
            $this->optimizeDatabase();
            
            // 清理临时文件
            $this->cleanTempFiles();
            
            // 更新系统统计
            $this->updateSystemStats();
            
            App::log('scheduler')->info('系统维护任务完成');
            
        } catch (Exception $e) {
            App::log('scheduler')->error('系统维护任务失败', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function cleanOldBackups(): void
    {
        $backupDir = App::$dataPath . '/backups';
        $files = glob($backupDir . '/database_*.sql');
        
        // 保留最近7天的备份
        $cutoffTime = time() - (7 * 24 * 3600);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                App::log('scheduler')->info('删除旧备份文件', ['file' => $file]);
            }
        }
    }
    
    private function collectHourlyStats(string $hour): array
    {
        // 实现统计数据收集逻辑
        return [
            'hour' => $hour,
            'requests' => rand(100, 1000),
            'users' => rand(10, 100),
            'errors' => rand(0, 10),
            'memory_usage' => memory_get_peak_usage(true),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    private function cleanOldLogs(): void
    {
        $logDir = App::$dataPath . '/logs';
        $files = glob($logDir . '/*.log');
        $cutoffTime = time() - (30 * 24 * 3600); // 30天
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }
    
    private function optimizeDatabase(): void
    {
        // 实现数据库优化逻辑
        App::db()->statement('OPTIMIZE TABLE users, posts, logs');
    }
    
    private function cleanTempFiles(): void
    {
        $tempDir = App::$dataPath . '/temp';
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && time() - filemtime($file) > 3600) { // 1小时
                    unlink($file);
                }
            }
        }
    }
    
    private function updateSystemStats(): void
    {
        $stats = [
            'total_users' => User::count(),
            'total_posts' => Post::count(),
            'disk_usage' => disk_total_space('.') - disk_free_space('.'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents(
            App::$dataPath . '/system_stats.json',
            json_encode($stats, JSON_PRETTY_PRINT)
        );
    }
}
```

### 动态注册任务

```php
class DynamicTaskManager
{
    /**
     * 动态添加任务
     */
    public function addTask(string $name, string $cron, string $class, string $method, array $params = []): void
    {
        $scheduler = App::scheduler();
        
        // 验证 Cron 表达式
        if (!$this->validateCronExpression($cron)) {
            throw new InvalidArgumentException('无效的 Cron 表达式: ' . $cron);
        }
        
        // 验证类和方法
        if (!class_exists($class)) {
            throw new InvalidArgumentException('类不存在: ' . $class);
        }
        
        if (!method_exists($class, $method)) {
            throw new InvalidArgumentException('方法不存在: ' . $class . '::' . $method);
        }
        
        // 添加任务
        $scheduler->add($cron, [$class, $method], $params);
        
        // 记录任务添加
        App::log('scheduler')->info('动态添加任务', [
            'name' => $name,
            'cron' => $cron,
            'class' => $class,
            'method' => $method,
            'params' => $params
        ]);
        
        // 保存任务配置
        $this->saveTaskConfig($name, $cron, $class, $method, $params);
    }
    
    /**
     * 使用闭包添加任务
     */
    public function addClosureTask(string $name, string $cron, \Closure $callback): void
    {
        $scheduler = App::scheduler();
        
        if (!$this->validateCronExpression($cron)) {
            throw new InvalidArgumentException('无效的 Cron 表达式: ' . $cron);
        }
        
        $scheduler->add($cron, $callback);
        
        App::log('scheduler')->info('动态添加闭包任务', [
            'name' => $name,
            'cron' => $cron
        ]);
    }
    
    /**
     * 验证 Cron 表达式
     */
    private function validateCronExpression(string $cron): bool
    {
        $parts = explode(' ', $cron);
        if (count($parts) !== 5) {
            return false;
        }
        
        // 简单的 Cron 表达式验证
        $patterns = [
            '/^(\*|[0-5]?\d)$/',           // 分钟 (0-59)
            '/^(\*|[01]?\d|2[0-3])$/',     // 小时 (0-23)
            '/^(\*|[12]?\d|3[01])$/',      // 日 (1-31)
            '/^(\*|[1-9]|1[0-2])$/',       // 月 (1-12)
            '/^(\*|[0-6])$/'               // 星期 (0-6)
        ];
        
        foreach ($parts as $index => $part) {
            // 处理步长值 (*/5)
            if (strpos($part, '/') !== false) {
                [$range, $step] = explode('/', $part, 2);
                if (!is_numeric($step) || $step <= 0) {
                    return false;
                }
                $part = $range;
            }
            
            // 处理范围值 (1-5)
            if (strpos($part, '-') !== false) {
                [$start, $end] = explode('-', $part, 2);
                if (!is_numeric($start) || !is_numeric($end) || $start >= $end) {
                    return false;
                }
                continue;
            }
            
            // 处理列表值 (1,3,5)
            if (strpos($part, ',') !== false) {
                $values = explode(',', $part);
                foreach ($values as $value) {
                    if (!preg_match($patterns[$index], $value)) {
                        return false;
                    }
                }
                continue;
            }
            
            if (!preg_match($patterns[$index], $part)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function saveTaskConfig(string $name, string $cron, string $class, string $method, array $params): void
    {
        $configFile = App::$dataPath . '/scheduler/tasks.json';
        $configDir = dirname($configFile);
        
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        $tasks = [];
        if (file_exists($configFile)) {
            $tasks = json_decode(file_get_contents($configFile), true) ?: [];
        }
        
        $tasks[$name] = [
            'cron' => $cron,
            'class' => $class,
            'method' => $method,
            'params' => $params,
            'created_at' => date('Y-m-d H:i:s'),
            'enabled' => true
        ];
        
        file_put_contents($configFile, json_encode($tasks, JSON_PRETTY_PRINT));
    }
}
```

## 任务监控和管理

### 任务执行监控

```php
class TaskMonitor
{
    private array $taskStats = [];
    
    /**
     * 监控任务执行
     */
    public function monitorTask(string $taskName, callable $task): mixed
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        // 记录任务开始
        App::log('scheduler')->info("任务开始: {$taskName}", [
            'task' => $taskName,
            'start_time' => date('Y-m-d H:i:s'),
            'memory_usage' => $this->formatBytes($startMemory)
        ]);
        
        try {
            $result = $task();
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            $executionTime = ($endTime - $startTime) * 1000; // ms
            $memoryUsed = $endMemory - $startMemory;
            
            // 更新统计信息
            $this->updateTaskStats($taskName, true, $executionTime, $memoryUsed);
            
            // 记录任务成功
            App::log('scheduler')->info("任务完成: {$taskName}", [
                'task' => $taskName,
                'execution_time' => $executionTime . 'ms',
                'memory_used' => $this->formatBytes($memoryUsed),
                'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
                'result' => is_scalar($result) ? $result : gettype($result)
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000;
            
            // 更新失败统计
            $this->updateTaskStats($taskName, false, $executionTime, 0);
            
            // 记录任务失败
            App::log('scheduler')->error("任务失败: {$taskName}", [
                'task' => $taskName,
                'execution_time' => $executionTime . 'ms',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 更新任务统计
     */
    private function updateTaskStats(string $taskName, bool $success, float $executionTime, int $memoryUsed): void
    {
        if (!isset($this->taskStats[$taskName])) {
            $this->taskStats[$taskName] = [
                'total_runs' => 0,
                'successful_runs' => 0,
                'failed_runs' => 0,
                'total_time' => 0,
                'average_time' => 0,
                'max_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'total_memory' => 0,
                'average_memory' => 0,
                'last_run' => null,
                'last_success' => null,
                'last_failure' => null
            ];
        }
        
        $stats = &$this->taskStats[$taskName];
        $stats['total_runs']++;
        $stats['total_time'] += $executionTime;
        $stats['total_memory'] += $memoryUsed;
        
        if ($success) {
            $stats['successful_runs']++;
            $stats['last_success'] = date('Y-m-d H:i:s');
        } else {
            $stats['failed_runs']++;
            $stats['last_failure'] = date('Y-m-d H:i:s');
        }
        
        $stats['average_time'] = $stats['total_time'] / $stats['total_runs'];
        $stats['average_memory'] = $stats['total_memory'] / $stats['total_runs'];
        $stats['max_time'] = max($stats['max_time'], $executionTime);
        $stats['min_time'] = min($stats['min_time'], $executionTime);
        $stats['last_run'] = date('Y-m-d H:i:s');
        
        // 保存统计数据
        $this->saveTaskStats();
    }
    
    /**
     * 获取任务统计
     */
    public function getTaskStats(string $taskName = null): array
    {
        if ($taskName) {
            return $this->taskStats[$taskName] ?? [];
        }
        
        return $this->taskStats;
    }
    
    /**
     * 生成监控报告
     */
    public function generateReport(): array
    {
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'total_tasks' => count($this->taskStats),
            'summary' => [
                'total_runs' => 0,
                'successful_runs' => 0,
                'failed_runs' => 0,
                'success_rate' => 0,
                'total_time' => 0,
                'average_time' => 0
            ],
            'tasks' => []
        ];
        
        foreach ($this->taskStats as $taskName => $stats) {
            $report['summary']['total_runs'] += $stats['total_runs'];
            $report['summary']['successful_runs'] += $stats['successful_runs'];
            $report['summary']['failed_runs'] += $stats['failed_runs'];
            $report['summary']['total_time'] += $stats['total_time'];
            
            $successRate = $stats['total_runs'] > 0 
                ? ($stats['successful_runs'] / $stats['total_runs']) * 100 
                : 0;
                
            $report['tasks'][$taskName] = array_merge($stats, [
                'success_rate' => round($successRate, 2) . '%',
                'average_time_formatted' => round($stats['average_time'], 2) . 'ms',
                'average_memory_formatted' => $this->formatBytes($stats['average_memory'])
            ]);
        }
        
        if ($report['summary']['total_runs'] > 0) {
            $report['summary']['success_rate'] = round(
                ($report['summary']['successful_runs'] / $report['summary']['total_runs']) * 100, 
                2
            ) . '%';
            $report['summary']['average_time'] = round(
                $report['summary']['total_time'] / $report['summary']['total_runs'], 
                2
            ) . 'ms';
        }
        
        return $report;
    }
    
    private function saveTaskStats(): void
    {
        $statsFile = App::$dataPath . '/scheduler/stats.json';
        $statsDir = dirname($statsFile);
        
        if (!is_dir($statsDir)) {
            mkdir($statsDir, 0755, true);
        }
        
        file_put_contents($statsFile, json_encode($this->taskStats, JSON_PRETTY_PRINT));
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
```

### 任务管理命令

```php
// src/Scheduler/SchedulerCommand.php 的扩展版本
class EnhancedSchedulerCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('scheduler')
             ->setDescription('任务调度器管理')
             ->addOption('action', 'a', InputOption::VALUE_OPTIONAL, '操作类型: start|stop|status|list', 'start')
             ->addOption('task', 't', InputOption::VALUE_OPTIONAL, '指定任务名称')
             ->addOption('report', 'r', InputOption::VALUE_NONE, '生成执行报告');
    }
    
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getOption('action');
        $taskName = $input->getOption('task');
        $showReport = $input->getOption('report');
        
        switch ($action) {
            case 'start':
                return $this->startScheduler($output);
                
            case 'stop':
                return $this->stopScheduler($output);
                
            case 'status':
                return $this->showStatus($output);
                
            case 'list':
                return $this->listTasks($output, $taskName);
                
            default:
                $output->writeln("<error>未知操作: {$action}</error>");
                return Command::FAILURE;
        }
    }
    
    private function startScheduler(OutputInterface $output): int
    {
        $scheduler = App::scheduler();
        $scheduler->registerAttribute();
        
        // 显示任务列表
        $this->displayTaskTable($output, $scheduler->data);
        
        $output->writeln("<info>任务调度器正在启动...</info>");
        
        // 启动调度器
        $scheduler->run();
        
        return Command::SUCCESS;
    }
    
    private function stopScheduler(OutputInterface $output): int
    {
        // 实现停止逻辑
        $pidFile = App::$dataPath . '/scheduler/scheduler.pid';
        
        if (file_exists($pidFile)) {
            $pid = (int) file_get_contents($pidFile);
            if (posix_kill($pid, SIGTERM)) {
                unlink($pidFile);
                $output->writeln("<info>调度器已停止 (PID: {$pid})</info>");
            } else {
                $output->writeln("<error>无法停止调度器</error>");
                return Command::FAILURE;
            }
        } else {
            $output->writeln("<comment>调度器未运行</comment>");
        }
        
        return Command::SUCCESS;
    }
    
    private function showStatus(OutputInterface $output): int
    {
        $pidFile = App::$dataPath . '/scheduler/scheduler.pid';
        $statsFile = App::$dataPath . '/scheduler/stats.json';
        
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            $output->writeln("<info>调度器状态: 运行中 (PID: {$pid})</info>");
        } else {
            $output->writeln("<comment>调度器状态: 未运行</comment>");
        }
        
        // 显示统计信息
        if (file_exists($statsFile)) {
            $stats = json_decode(file_get_contents($statsFile), true);
            $output->writeln("");
            $output->writeln("任务统计:");
            
            foreach ($stats as $taskName => $taskStats) {
                $successRate = $taskStats['total_runs'] > 0 
                    ? round(($taskStats['successful_runs'] / $taskStats['total_runs']) * 100, 2)
                    : 0;
                    
                $output->writeln("  {$taskName}:");
                $output->writeln("    总运行次数: {$taskStats['total_runs']}");
                $output->writeln("    成功率: {$successRate}%");
                $output->writeln("    平均执行时间: " . round($taskStats['average_time'], 2) . "ms");
                $output->writeln("    最后运行: " . ($taskStats['last_run'] ?? '未知'));
                $output->writeln("");
            }
        }
        
        return Command::SUCCESS;
    }
    
    private function listTasks(OutputInterface $output, ?string $taskName): int
    {
        $scheduler = App::scheduler();
        $scheduler->registerAttribute();
        
        if ($taskName) {
            // 显示特定任务信息
            $output->writeln("任务详情: {$taskName}");
            // 实现特定任务信息显示
        } else {
            // 显示所有任务
            $this->displayTaskTable($output, $scheduler->data);
        }
        
        return Command::SUCCESS;
    }
    
    private function displayTaskTable(OutputInterface $output, array $tasks): void
    {
        if (empty($tasks)) {
            $output->writeln("<comment>没有注册的任务</comment>");
            return;
        }
        
        $table = new Table($output);
        $table->setHeaders(['任务', '类/方法', '状态']);
        
        foreach ($tasks as $task) {
            [$class, $method] = $task;
            $status = class_exists($class) && method_exists($class, $method) ? '✓ 就绪' : '✗ 错误';
            $table->addRow([$class . '::' . $method, $class, $status]);
        }
        
        $table->render();
    }
}
```

## Cron 表达式指南

### 基本格式

```
分钟 小时 日期 月份 星期
*    *   *   *    *
```

### 字段说明

| 字段 | 取值范围 | 特殊字符 |
|------|----------|----------|
| 分钟 | 0-59 | `*` `,` `-` `/` |
| 小时 | 0-23 | `*` `,` `-` `/` |
| 日期 | 1-31 | `*` `,` `-` `/` |
| 月份 | 1-12 | `*` `,` `-` `/` |
| 星期 | 0-7 (0和7都代表周日) | `*` `,` `-` `/` |

### 特殊字符含义

- `*`：匹配所有值
- `,`：分隔多个值 (`1,3,5`)
- `-`：指定范围 (`1-5`)
- `/`：指定步长 (`*/5` 表示每5个单位)

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
    
    // 每小时的第30分钟执行
    #[Scheduler('30 * * * *')]
    public function everyHourAt30(): void {}
    
    // 每天凌晨2点执行
    #[Scheduler('0 2 * * *')]
    public function dailyAt2AM(): void {}
    
    // 每周一到周五的上午9点执行
    #[Scheduler('0 9 * * 1-5')]
    public function weekdaysAt9AM(): void {}
    
    // 每月1号凌晨3点执行
    #[Scheduler('0 3 1 * *')]
    public function monthlyAt3AM(): void {}
    
    // 每年1月1号凌晨执行
    #[Scheduler('0 0 1 1 *')]
    public function yearlyJan1(): void {}
    
    // 工作日每2小时执行
    #[Scheduler('0 */2 * * 1-5')]
    public function workdaysEveryTwoHours(): void {}
    
    // 周末每30分钟执行
    #[Scheduler('*/30 * * * 0,6')]
    public function weekendsEvery30Min(): void {}
}
```

## 错误处理和恢复

### 重试机制

```php
class RetryableTask
{
    private int $maxRetries = 3;
    private int $retryDelay = 5; // 秒
    
    #[Scheduler('0 */6 * * *')] // 每6小时执行
    public function processDataWithRetry(): void
    {
        $attempts = 0;
        
        while ($attempts < $this->maxRetries) {
            try {
                $this->processData();
                
                App::log('scheduler')->info('数据处理成功', [
                    'attempts' => $attempts + 1
                ]);
                
                return; // 成功则退出
                
            } catch (Exception $e) {
                $attempts++;
                
                App::log('scheduler')->warning('数据处理失败，准备重试', [
                    'attempt' => $attempts,
                    'max_attempts' => $this->maxRetries,
                    'error' => $e->getMessage(),
                    'next_retry_in' => $this->retryDelay . ' seconds'
                ]);
                
                if ($attempts >= $this->maxRetries) {
                    App::log('scheduler')->error('数据处理最终失败', [
                        'total_attempts' => $attempts,
                        'error' => $e->getMessage()
                    ]);
                    
                    // 发送告警通知
                    $this->sendFailureAlert($e);
                    throw $e;
                }
                
                // 等待重试
                sleep($this->retryDelay);
            }
        }
    }
    
    private function processData(): void
    {
        // 模拟可能失败的数据处理
        if (rand(1, 3) === 1) {
            throw new Exception('模拟处理失败');
        }
        
        // 实际的数据处理逻辑
        echo "数据处理成功\n";
    }
    
    private function sendFailureAlert(Exception $e): void
    {
        // 实现告警发送逻辑
        $message = "任务执行失败: " . get_class($this) . "\n";
        $message .= "错误信息: " . $e->getMessage() . "\n";
        $message .= "时间: " . date('Y-m-d H:i:s');
        
        // 发送邮件、短信或其他通知方式
        file_put_contents(
            App::$dataPath . '/alerts/task_failures.log',
            $message . "\n\n",
            FILE_APPEND
        );
    }
}
```

### 任务健康检查

```php
class HealthChecker
{
    #[Scheduler('*/10 * * * *')] // 每10分钟检查一次
    public function healthCheck(): void
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'memory' => $this->checkMemory(),
            'disk' => $this->checkDisk()
        ];
        
        $failures = array_filter($checks, fn($result) => !$result['healthy']);
        
        if (!empty($failures)) {
            App::log('scheduler')->warning('健康检查发现问题', [
                'failures' => $failures,
                'all_checks' => $checks
            ]);
            
            $this->handleHealthFailures($failures);
        } else {
            App::log('scheduler')->info('健康检查通过', [
                'checks' => array_keys($checks)
            ]);
        }
    }
    
    private function checkDatabase(): array
    {
        try {
            App::db()->getPdo()->query('SELECT 1');
            return ['healthy' => true, 'message' => 'Database connection OK'];
        } catch (Exception $e) {
            return ['healthy' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }
    
    private function checkCache(): array
    {
        try {
            $cache = App::cache();
            $testKey = 'health_check_' . time();
            $cache->set($testKey, 'test', 10);
            $result = $cache->get($testKey);
            $cache->delete($testKey);
            
            if ($result === 'test') {
                return ['healthy' => true, 'message' => 'Cache working properly'];
            } else {
                return ['healthy' => false, 'message' => 'Cache read/write failed'];
            }
        } catch (Exception $e) {
            return ['healthy' => false, 'message' => 'Cache error: ' . $e->getMessage()];
        }
    }
    
    private function checkStorage(): array
    {
        try {
            $testFile = App::$dataPath . '/health_check.tmp';
            file_put_contents($testFile, 'health check');
            $content = file_get_contents($testFile);
            unlink($testFile);
            
            if ($content === 'health check') {
                return ['healthy' => true, 'message' => 'Storage read/write OK'];
            } else {
                return ['healthy' => false, 'message' => 'Storage read/write failed'];
            }
        } catch (Exception $e) {
            return ['healthy' => false, 'message' => 'Storage error: ' . $e->getMessage()];
        }
    }
    
    private function checkMemory(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return ['healthy' => true, 'message' => 'Memory limit disabled'];
        }
        
        $limitBytes = $this->parseMemoryLimit($memoryLimit);
        $usagePercent = ($memoryUsage / $limitBytes) * 100;
        
        if ($usagePercent > 80) {
            return ['healthy' => false, 'message' => sprintf('High memory usage: %.2f%%', $usagePercent)];
        }
        
        return ['healthy' => true, 'message' => sprintf('Memory usage OK: %.2f%%', $usagePercent)];
    }
    
    private function checkDisk(): array
    {
        $freeBytes = disk_free_space(App::$dataPath);
        $totalBytes = disk_total_space(App::$dataPath);
        $usagePercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
        
        if ($usagePercent > 90) {
            return ['healthy' => false, 'message' => sprintf('High disk usage: %.2f%%', $usagePercent)];
        }
        
        return ['healthy' => true, 'message' => sprintf('Disk usage OK: %.2f%%', $usagePercent)];
    }
    
    private function parseMemoryLimit(string $limit): int
    {
        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);
        
        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }
    
    private function handleHealthFailures(array $failures): void
    {
        foreach ($failures as $check => $result) {
            App::log('scheduler')->error("健康检查失败: {$check}", [
                'check' => $check,
                'message' => $result['message']
            ]);
        }
        
        // 如果是关键服务失败，可以采取自动恢复措施
        if (isset($failures['database'])) {
            $this->attemptDatabaseReconnection();
        }
        
        if (isset($failures['cache'])) {
            $this->clearCacheFiles();
        }
    }
    
    private function attemptDatabaseReconnection(): void
    {
        try {
            // 重新初始化数据库连接
            App::$di->set('db', null);
            App::db();
            
            App::log('scheduler')->info('数据库重连成功');
        } catch (Exception $e) {
            App::log('scheduler')->error('数据库重连失败', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function clearCacheFiles(): void
    {
        try {
            $cacheDir = App::$dataPath . '/cache';
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                
                App::log('scheduler')->info('缓存文件清理完成');
            }
        } catch (Exception $e) {
            App::log('scheduler')->error('缓存清理失败', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

## 最佳实践

### 1. 任务设计原则

```php
// ✅ 推荐：任务应该是幂等的
#[Scheduler('0 2 * * *')]
public function dailyCleanup(): void
{
    $today = date('Y-m-d');
    $lockKey = "cleanup_lock_{$today}";
    
    // 使用分布式锁防止重复执行
    $lock = App::lock()->createLock($lockKey, 3600);
    
    if (!$lock->acquire()) {
        App::log('scheduler')->info('今日清理任务已执行，跳过');
        return;
    }
    
    try {
        $this->performCleanup();
        App::log('scheduler')->info('清理任务完成');
    } finally {
        $lock->release();
    }
}

// ✅ 推荐：任务应该有明确的超时处理
#[Scheduler('*/30 * * * *')]
public function processQueue(): void
{
    $timeout = 25 * 60; // 25分钟超时
    $startTime = time();
    
    while (time() - $startTime < $timeout) {
        $job = Queue::pop();
        if (!$job) {
            break; // 队列为空
        }
        
        $this->processJob($job);
        
        // 检查是否接近超时
        if (time() - $startTime > $timeout - 60) {
            App::log('scheduler')->warning('任务接近超时，提前退出');
            break;
        }
    }
}
```

### 2. 错误处理

```php
// ✅ 推荐：完善的错误处理和恢复
#[Scheduler('0 */4 * * *')]
public function syncExternalData(): void
{
    try {
        $this->performSync();
    } catch (TemporaryException $e) {
        // 临时错误，稍后重试
        App::log('scheduler')->warning('同步遇到临时错误，将在下次执行时重试', [
            'error' => $e->getMessage()
        ]);
    } catch (PermanentException $e) {
        // 永久错误，需要人工干预
        App::log('scheduler')->error('同步遇到永久错误，需要人工处理', [
            'error' => $e->getMessage()
        ]);
        $this->sendCriticalAlert($e);
    } catch (Exception $e) {
        // 未知错误
        App::log('scheduler')->error('同步遇到未知错误', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}
```

### 3. 性能优化

```php
// ✅ 推荐：批量处理和内存管理
#[Scheduler('0 1 * * *')]
public function processPendingOrders(): void
{
    $batchSize = 100;
    $processed = 0;
    
    do {
        // 使用分页查询避免内存溢出
        $orders = Order::where('status', 'pending')
                      ->orderBy('id')
                      ->limit($batchSize)
                      ->get();
        
        foreach ($orders as $order) {
            $this->processOrder($order);
            $processed++;
        }
        
        // 清理内存
        unset($orders);
        
        // 定期检查内存使用
        if ($processed % 1000 === 0) {
            $memoryUsage = memory_get_usage(true);
            App::log('scheduler')->info('处理进度', [
                'processed' => $processed,
                'memory_usage' => $this->formatBytes($memoryUsage)
            ]);
            
            // 如果内存使用过高，提前退出
            if ($memoryUsage > 128 * 1024 * 1024) { // 128MB
                App::log('scheduler')->warning('内存使用过高，提前结束处理');
                break;
            }
        }
        
    } while (count($orders) === $batchSize);
    
    App::log('scheduler')->info('订单处理完成', [
        'total_processed' => $processed
    ]);
}
```

### 4. 监控和告警

```php
// ✅ 推荐：关键任务应该有监控
#[Scheduler('0 0 * * *')]
public function criticalDailyTask(): void
{
    $startTime = microtime(true);
    
    try {
        $result = $this->performCriticalTask();
        
        $executionTime = (microtime(true) - $startTime) * 1000;
        
        // 记录成功指标
        App::log('scheduler')->info('关键任务执行成功', [
            'execution_time' => $executionTime . 'ms',
            'result' => $result
        ]);
        
        // 如果执行时间异常，发送告警
        if ($executionTime > 300000) { // 5分钟
            $this->sendSlowTaskAlert($executionTime);
        }
        
    } catch (Exception $e) {
        App::log('scheduler')->critical('关键任务执行失败', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // 立即发送告警
        $this->sendCriticalTaskFailureAlert($e);
        
        throw $e;
    }
}
```

### 5. 配置和部署

```bash
# ✅ 推荐：使用系统服务管理调度器
# /etc/systemd/system/duxlite-scheduler.service
[Unit]
Description=DuxLite Task Scheduler
After=mysql.service redis.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/duxlite
ExecStart=/usr/bin/php dux scheduler
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target

# 启用服务
sudo systemctl enable duxlite-scheduler
sudo systemctl start duxlite-scheduler
```

通过遵循这些最佳实践，您可以构建出稳定、高效、可监控的 DuxLite 任务调度系统。合理的任务设计和完善的错误处理机制能够确保系统的可靠性和可维护性。