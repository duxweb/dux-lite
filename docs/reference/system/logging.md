# 日志系统

DuxLite 提供了基于 Monolog 的完整日志系统，支持多通道日志记录、日志轮转和结构化日志管理。通过灵活的日志配置和强大的处理机制，帮助开发者有效地调试应用程序和监控系统运行状态。

## 基本概念

### 设计理念

DuxLite 日志系统采用**分通道记录、自动轮转、结构化存储**的设计：

- **多通道管理**：不同模块使用不同的日志通道，便于管理和分析
- **自动轮转**：基于时间的日志文件轮转，防止单个文件过大
- **结构化记录**：支持上下文数据和结构化日志格式
- **性能优化**：异步写入和批量处理，减少对应用性能的影响
- **安全保护**：敏感信息过滤和访问权限控制

### 核心特性

- **分级记录**：支持 Debug、Info、Warning、Error、Critical 等日志级别
- **通道隔离**：不同业务模块使用独立的日志通道
- **文件轮转**：每日轮转，保留指定天数的历史日志
- **上下文记录**：支持记录请求上下文和自定义数据
- **性能监控**：内置应用性能和错误统计功能

## 日志配置

### 基础配置

```toml
# config/log.toml
[log]
# 默认日志级别
level = "debug"

# 日志存储路径
path = "data/logs"

# 日志轮转设置
rotate_days = 15

# 缓冲区设置
buffer_size = 100

# 异步写入
async = true

# 通道配置
[log.channels]
app = { level = "debug", file = "app.log" }
error = { level = "error", file = "error.log" }
access = { level = "info", file = "access.log" }
sql = { level = "debug", file = "sql.log" }
scheduler = { level = "info", file = "scheduler.log" }

# 生产环境配置
[log.production]
level = "warning"
buffer_size = 500
rotate_days = 30
```

### 环境变量配置

```bash
# .env
LOG_LEVEL=debug
LOG_PATH=data/logs
LOG_ROTATE_DAYS=15
LOG_ASYNC=true
```

## 日志处理器

### LogHandler 核心类

```php
// src/Logs/LogHandler.php
class LogHandler
{
    /**
     * 初始化日志记录器
     */
    public static function init(string $name, Level $level): Logger
    {
        // 创建轮转文件处理器
        $fileHandle = new RotatingFileHandler(
            App::$dataPath . '/logs/' . $name . '.log',
            15,  // 保留15天
            $level,
            true,    // 支持冒泡
            0777     // 文件权限
        );
        
        // 创建日志记录器
        $logger = new Logger($name);
        $logger->useLoggingLoopDetection(false);
        $logger->pushHandler($fileHandle);
        
        return $logger;
    }
}
```

### 扩展日志处理器

```php
class EnhancedLogHandler extends LogHandler
{
    /**
     * 创建增强的日志记录器
     */
    public static function createEnhanced(string $name, array $config = []): Logger
    {
        $level = Level::tryFrom($config['level'] ?? 'debug') ?? Level::Debug;
        $rotateDays = $config['rotate_days'] ?? 15;
        $bufferSize = $config['buffer_size'] ?? 100;
        
        // 创建基础文件处理器
        $fileHandler = new RotatingFileHandler(
            App::$dataPath . '/logs/' . $name . '.log',
            $rotateDays,
            $level,
            true,
            0644
        );
        
        // 添加格式化器
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s.u',
            true,
            true
        );
        $fileHandler->setFormatter($formatter);
        
        // 创建缓冲处理器（性能优化）
        $bufferHandler = new BufferHandler($fileHandler, $bufferSize, Level::Debug, true, true);
        
        // 创建错误邮件处理器（仅生产环境）
        $handlers = [$bufferHandler];
        if (!App::$debug && $config['mail_on_error'] ?? false) {
            $mailer = new SwiftMailerHandler(
                self::createMailer($config['mail']),
                self::createMessage($config['mail']),
                Level::Error
            );
            $handlers[] = $mailer;
        }
        
        // 创建日志记录器
        $logger = new Logger($name);
        foreach ($handlers as $handler) {
            $logger->pushHandler($handler);
        }
        
        // 添加处理器
        $logger->pushProcessor(new UidProcessor());
        $logger->pushProcessor(new WebProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
        $logger->pushProcessor(new MemoryPeakUsageProcessor());
        
        return $logger;
    }
    
    /**
     * 创建邮件发送器
     */
    private static function createMailer(array $config): Swift_Mailer
    {
        $transport = (new Swift_SmtpTransport($config['host'], $config['port']))
            ->setUsername($config['username'])
            ->setPassword($config['password']);
            
        return new Swift_Mailer($transport);
    }
    
    /**
     * 创建邮件消息
     */
    private static function createMessage(array $config): Swift_Message
    {
        return (new Swift_Message('Application Error'))
            ->setFrom([$config['from'] => $config['from_name']])
            ->setTo($config['to']);
    }
}
```

## 日志使用

### 基础日志记录

```php
class UserController
{
    /**
     * 用户登录
     */
    public function login(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $data = $request->getParsedBody();
        
        // 记录登录尝试
        App::log('access')->info('用户尝试登录', [
            'username' => $data['username'],
            'ip' => $this->getClientIp($request),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'timestamp' => time()
        ]);
        
        try {
            $user = $this->authenticateUser($data);
            
            // 记录登录成功
            App::log('access')->info('用户登录成功', [
                'user_id' => $user->id,
                'username' => $user->username,
                'ip' => $this->getClientIp($request)
            ]);
            
            return send($response, '登录成功', ['user' => $user->transform()]);
            
        } catch (ExceptionBusiness $e) {
            // 记录登录失败
            App::log('access')->warning('用户登录失败', [
                'username' => $data['username'],
                'error' => $e->getMessage(),
                'ip' => $this->getClientIp($request)
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 删除用户
     */
    public function deleteUser(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $userId = (int) $args['id'];
        $currentUser = $request->getAttribute('user');
        
        try {
            $user = User::find($userId);
            if (!$user) {
                App::log('error')->error('尝试删除不存在的用户', [
                    'target_user_id' => $userId,
                    'operator_id' => $currentUser['user_id'],
                    'ip' => $this->getClientIp($request)
                ]);
                
                throw new ExceptionNotFound('用户不存在');
            }
            
            // 记录敏感操作
            App::log('app')->critical('用户删除操作', [
                'target_user_id' => $user->id,
                'target_username' => $user->username,
                'operator_id' => $currentUser['user_id'],
                'operator_name' => $currentUser['username'],
                'ip' => $this->getClientIp($request),
                'timestamp' => now()
            ]);
            
            $user->delete();
            
            return send($response, '用户删除成功');
            
        } catch (Exception $e) {
            App::log('error')->error('用户删除失败', [
                'target_user_id' => $userId,
                'operator_id' => $currentUser['user_id'],
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            throw $e;
        }
    }
}
```

### SQL 查询日志

```php
class DatabaseLogger
{
    /**
     * 记录 SQL 查询
     */
    public static function logQuery(string $sql, array $bindings = [], float $time = 0): void
    {
        // 只在调试模式下记录 SQL
        if (!App::$debug) {
            return;
        }
        
        App::log('sql')->debug('SQL Query', [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => $time . 'ms',
            'memory' => memory_get_usage(true)
        ]);
        
        // 记录慢查询
        if ($time > 1000) { // 超过1秒
            App::log('sql')->warning('Slow Query Detected', [
                'sql' => $sql,
                'bindings' => $bindings,
                'time' => $time . 'ms'
            ]);
        }
    }
    
    /**
     * 记录数据库错误
     */
    public static function logError(string $sql, array $bindings, Exception $error): void
    {
        App::log('error')->error('Database Error', [
            'sql' => $sql,
            'bindings' => $bindings,
            'error' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine()
        ]);
    }
}

// 在模型中使用
class User extends Model
{
    protected static function booted()
    {
        // 监听查询事件
        static::addGlobalScope('log', function (Builder $builder) {
            $builder->macro('logQuery', function () {
                $sql = $this->toSql();
                $bindings = $this->getBindings();
                
                DatabaseLogger::logQuery($sql, $bindings);
                
                return $this;
            });
        });
    }
    
    public static function findWithLog(int $id): ?self
    {
        $start = microtime(true);
        
        try {
            $result = static::find($id);
            $time = (microtime(true) - $start) * 1000;
            
            DatabaseLogger::logQuery(
                "SELECT * FROM users WHERE id = ?",
                [$id],
                $time
            );
            
            return $result;
            
        } catch (Exception $e) {
            DatabaseLogger::logError(
                "SELECT * FROM users WHERE id = ?",
                [$id],
                $e
            );
            throw $e;
        }
    }
}
```

### 计划任务日志

```php
class TaskScheduler
{
    /**
     * 执行计划任务
     */
    public function runTask(string $taskName, callable $task): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        App::log('scheduler')->info("任务开始执行: {$taskName}", [
            'task' => $taskName,
            'start_time' => date('Y-m-d H:i:s'),
            'memory_usage' => $this->formatBytes($startMemory)
        ]);
        
        try {
            $result = $task();
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            $executionTime = ($endTime - $startTime) * 1000;
            
            App::log('scheduler')->info("任务执行成功: {$taskName}", [
                'task' => $taskName,
                'execution_time' => $executionTime . 'ms',
                'memory_used' => $this->formatBytes($endMemory - $startMemory),
                'result' => is_scalar($result) ? $result : gettype($result)
            ]);
            
        } catch (Exception $e) {
            App::log('scheduler')->error("任务执行失败: {$taskName}", [
                'task' => $taskName,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
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

## 高级日志功能

### 日志聚合和分析

```php
class LogAnalyzer
{
    /**
     * 分析错误日志
     */
    public function analyzeErrors(string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $logFile = App::$dataPath . "/logs/error-{$date}.log";
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $errors = [];
        $handle = fopen($logFile, 'r');
        
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/\[(.*?)\] error\.ERROR: (.*?) \{(.*?)\}/', $line, $matches)) {
                $timestamp = $matches[1];
                $message = $matches[2];
                $context = json_decode('{' . $matches[3] . '}', true);
                
                $key = md5($message);
                if (!isset($errors[$key])) {
                    $errors[$key] = [
                        'message' => $message,
                        'count' => 0,
                        'first_seen' => $timestamp,
                        'last_seen' => $timestamp,
                        'contexts' => []
                    ];
                }
                
                $errors[$key]['count']++;
                $errors[$key]['last_seen'] = $timestamp;
                $errors[$key]['contexts'][] = $context;
            }
        }
        
        fclose($handle);
        
        // 按发生次数排序
        uasort($errors, function ($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return $errors;
    }
    
    /**
     * 生成日志统计报告
     */
    public function generateReport(string $channel = 'app', int $days = 7): array
    {
        $report = [
            'channel' => $channel,
            'period' => $days . ' days',
            'summary' => [
                'total_entries' => 0,
                'by_level' => [],
                'by_day' => [],
                'top_errors' => [],
                'performance' => []
            ]
        ];
        
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $logFile = App::$dataPath . "/logs/{$channel}-{$date}.log";
            
            if (file_exists($logFile)) {
                $dayStats = $this->analyzeLogFile($logFile);
                $report['summary']['by_day'][$date] = $dayStats;
                $report['summary']['total_entries'] += $dayStats['total'];
                
                foreach ($dayStats['by_level'] as $level => $count) {
                    $report['summary']['by_level'][$level] = 
                        ($report['summary']['by_level'][$level] ?? 0) + $count;
                }
            }
        }
        
        return $report;
    }
    
    private function analyzeLogFile(string $file): array
    {
        $stats = [
            'total' => 0,
            'by_level' => [],
            'errors' => []
        ];
        
        $handle = fopen($file, 'r');
        while (($line = fgets($handle)) !== false) {
            $stats['total']++;
            
            if (preg_match('/\[(.*?)\] \w+\.(\w+):/', $line, $matches)) {
                $level = strtolower($matches[2]);
                $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;
            }
        }
        fclose($handle);
        
        return $stats;
    }
}
```

### 实时日志监控

```php
class LogMonitor
{
    private array $watchers = [];
    
    /**
     * 监控错误日志
     */
    public function watchErrors(): void
    {
        $errorLog = App::$dataPath . '/logs/error-' . date('Y-m-d') . '.log';
        
        $this->watchFile($errorLog, function (string $line) {
            if (preg_match('/ERROR|CRITICAL/', $line)) {
                $this->handleCriticalError($line);
            }
        });
    }
    
    /**
     * 监控性能日志
     */
    public function watchPerformance(): void
    {
        $appLog = App::$dataPath . '/logs/app-' . date('Y-m-d') . '.log';
        
        $this->watchFile($appLog, function (string $line) {
            if (preg_match('/execution_time.*?(\d+)ms/', $line, $matches)) {
                $time = (int) $matches[1];
                if ($time > 5000) { // 超过5秒
                    $this->handleSlowRequest($line);
                }
            }
        });
    }
    
    private function watchFile(string $file, callable $handler): void
    {
        if (!file_exists($file)) {
            return;
        }
        
        $handle = fopen($file, 'r');
        fseek($handle, 0, SEEK_END);
        
        while (true) {
            $line = fgets($handle);
            if ($line !== false) {
                $handler(trim($line));
            } else {
                usleep(100000); // 等待100ms
            }
        }
    }
    
    private function handleCriticalError(string $line): void
    {
        // 发送告警通知
        $this->sendAlert('Critical Error Detected', $line);
        
        // 记录到专门的告警日志
        App::log('alert')->critical('Auto detected critical error', [
            'original_log' => $line,
            'detected_at' => now()
        ]);
    }
    
    private function handleSlowRequest(string $line): void
    {
        // 记录性能问题
        App::log('performance')->warning('Slow request detected', [
            'original_log' => $line,
            'detected_at' => now()
        ]);
    }
    
    private function sendAlert(string $subject, string $message): void
    {
        // 实现告警发送逻辑（邮件、短信、钉钉等）
        // 这里只是示例
        file_put_contents(
            App::$dataPath . '/alerts.log',
            "[" . date('Y-m-d H:i:s') . "] {$subject}: {$message}\n",
            FILE_APPEND
        );
    }
}
```

## 日志管理命令

### 日志清理命令

```php
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LogCleanCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('log:clean')
             ->setDescription('清理过期的日志文件')
             ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, '保留天数', 30)
             ->addOption('channel', 'c', InputOption::VALUE_OPTIONAL, '指定通道')
             ->addOption('dry-run', null, InputOption::VALUE_NONE, '预览模式，不实际删除');
    }
    
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        $channel = $input->getOption('channel');
        $dryRun = $input->getOption('dry-run');
        
        $logDir = App::$dataPath . '/logs';
        $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $pattern = $channel ? "{$channel}-*.log" : "*.log";
        $files = glob($logDir . '/' . $pattern);
        
        $deletedCount = 0;
        $deletedSize = 0;
        
        foreach ($files as $file) {
            $basename = basename($file);
            
            // 提取日期
            if (preg_match('/\d{4}-\d{2}-\d{2}/', $basename, $matches)) {
                $fileDate = $matches[0];
                
                if ($fileDate < $cutoffDate) {
                    $size = filesize($file);
                    
                    if ($dryRun) {
                        $output->writeln("将删除: {$basename} (" . $this->formatBytes($size) . ")");
                    } else {
                        if (unlink($file)) {
                            $output->writeln("已删除: {$basename} (" . $this->formatBytes($size) . ")");
                            $deletedCount++;
                            $deletedSize += $size;
                        } else {
                            $output->writeln("<error>删除失败: {$basename}</error>");
                        }
                    }
                }
            }
        }
        
        if ($dryRun) {
            $output->writeln("<info>预览模式: 找到 {$deletedCount} 个过期日志文件</info>");
        } else {
            $output->writeln("<info>清理完成: 删除了 {$deletedCount} 个文件，释放空间 " . $this->formatBytes($deletedSize) . "</info>");
        }
        
        return Command::SUCCESS;
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

### 日志分析命令

```php
class LogAnalyzeCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('log:analyze')
             ->setDescription('分析日志文件')
             ->addOption('channel', 'c', InputOption::VALUE_REQUIRED, '日志通道', 'app')
             ->addOption('date', 'd', InputOption::VALUE_OPTIONAL, '分析日期 (Y-m-d)')
             ->addOption('level', 'l', InputOption::VALUE_OPTIONAL, '日志级别过滤')
             ->addOption('export', 'e', InputOption::VALUE_OPTIONAL, '导出报告文件');
    }
    
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $channel = $input->getOption('channel');
        $date = $input->getOption('date') ?: date('Y-m-d');
        $level = $input->getOption('level');
        $exportFile = $input->getOption('export');
        
        $analyzer = new LogAnalyzer();
        $report = $analyzer->generateReport($channel, 1);
        
        // 显示统计信息
        $output->writeln("<info>日志分析报告 - {$channel} ({$date})</info>");
        $output->writeln(str_repeat('=', 50));
        
        $dayStats = $report['summary']['by_day'][$date] ?? ['total' => 0, 'by_level' => []];
        
        $output->writeln("总日志条数: {$dayStats['total']}");
        $output->writeln("");
        
        // 按级别统计
        $output->writeln("按级别分布:");
        foreach ($dayStats['by_level'] as $logLevel => $count) {
            if (!$level || $level === $logLevel) {
                $percentage = $dayStats['total'] > 0 ? round(($count / $dayStats['total']) * 100, 2) : 0;
                $output->writeln("  {$logLevel}: {$count} ({$percentage}%)");
            }
        }
        
        // 错误分析
        if (!$level || $level === 'error') {
            $output->writeln("");
            $output->writeln("错误分析:");
            $errors = $analyzer->analyzeErrors($date);
            
            $count = 0;
            foreach ($errors as $error) {
                if ($count >= 10) break; // 只显示前10个
                
                $output->writeln("  错误: " . substr($error['message'], 0, 60) . "...");
                $output->writeln("    发生次数: {$error['count']}");
                $output->writeln("    首次发生: {$error['first_seen']}");
                $output->writeln("    最后发生: {$error['last_seen']}");
                $output->writeln("");
                
                $count++;
            }
        }
        
        // 导出报告
        if ($exportFile) {
            file_put_contents($exportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $output->writeln("<info>报告已导出到: {$exportFile}</info>");
        }
        
        return Command::SUCCESS;
    }
}
```

## 最佳实践

### 1. 合理的日志级别

```php
// ✅ 推荐：选择合适的日志级别
App::log()->debug('调试信息，仅开发环境使用');
App::log()->info('一般信息，记录业务流程');
App::log()->warning('警告信息，需要关注但不影响运行');
App::log()->error('错误信息，需要及时处理');
App::log()->critical('严重错误，系统可能无法继续运行');

// ❌ 避免：滥用日志级别
App::log()->error('用户输入验证失败'); // 应该用 warning
App::log()->debug('支付接口调用失败'); // 应该用 error
```

### 2. 结构化日志记录

```php
// ✅ 推荐：提供完整的上下文信息
App::log()->info('用户订单创建', [
    'user_id' => $user->id,
    'order_id' => $order->id,
    'amount' => $order->total_amount,
    'payment_method' => $order->payment_method,
    'ip' => $request->getClientIp(),
    'user_agent' => $request->getHeaderLine('User-Agent')
]);

// ❌ 避免：缺少上下文的日志
App::log()->info('订单创建成功');
```

### 3. 性能考虑

```php
// ✅ 推荐：避免在循环中频繁记录日志
$batchData = [];
foreach ($users as $user) {
    $result = $this->processUser($user);
    $batchData[] = [
        'user_id' => $user->id,
        'result' => $result
    ];
}

App::log()->info('批量处理用户完成', [
    'total_count' => count($users),
    'success_count' => count(array_filter($batchData, fn($item) => $item['result'])),
    'batch_data' => $batchData
]);

// ❌ 避免：在循环中记录日志
foreach ($users as $user) {
    $result = $this->processUser($user);
    App::log()->info('处理用户', ['user_id' => $user->id, 'result' => $result]);
}
```

### 4. 敏感信息保护

```php
// ✅ 推荐：过滤敏感信息
App::log()->info('用户登录', [
    'user_id' => $user->id,
    'username' => $user->username,
    'ip' => $request->getClientIp(),
    'password' => '***' // 密码脱敏
]);

// ✅ 推荐：使用专门的脱敏函数
function sanitizeLogData(array $data): array
{
    $sensitiveFields = ['password', 'token', 'secret', 'key', 'card_number'];
    
    foreach ($sensitiveFields as $field) {
        if (isset($data[$field])) {
            $data[$field] = '***';
        }
    }
    
    return $data;
}
```

### 5. 日志轮转和清理

```bash
# 定期清理日志
# 在 crontab 中添加
0 2 * * * php /path/to/project/dux log:clean --days=30

# 按大小轮转（通过系统 logrotate）
# /etc/logrotate.d/duxlite
/var/www/duxlite/data/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    copytruncate
    notifempty
    missingok
}
```

### 6. 监控和告警

```php
// ✅ 推荐：设置错误阈值告警
class ErrorThresholdMonitor
{
    public function checkErrorRate(): void
    {
        $analyzer = new LogAnalyzer();
        $report = $analyzer->generateReport('error', 1);
        
        $errorCount = $report['summary']['by_level']['error'] ?? 0;
        $totalCount = $report['summary']['total_entries'];
        
        if ($totalCount > 0) {
            $errorRate = ($errorCount / $totalCount) * 100;
            
            if ($errorRate > 5) { // 错误率超过5%
                $this->sendAlert("错误率过高: {$errorRate}%");
            }
        }
    }
}
```

通过遵循这些最佳实践，您可以构建出高效、可靠的 DuxLite 日志系统。合理的日志记录不仅有助于调试和监控，还能为业务分析和系统优化提供宝贵的数据支持。