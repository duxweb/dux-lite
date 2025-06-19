# 日志系统

DuxLite 提供了基于 Monolog 的强大日志系统，支持多通道、多级别、自动轮转等功能，帮助开发者进行应用监控、错误追踪和性能分析。

## 设计理念

DuxLite 日志系统采用**结构化、分级、高效的日志管理**设计：

- **多通道支持**：不同模块使用独立的日志通道
- **自动轮转**：日志文件按时间自动分割，防止文件过大
- **分级记录**：支持 Debug、Info、Warning、Error、Fatal 多个级别
- **结构化数据**：支持数组和对象的结构化日志记录
- **高性能**：异步写入，不阻塞业务逻辑

### 日志级别

| 级别 | 数值 | 用途 | 示例场景 |
|------|------|------|----------|
| **DEBUG** | 100 | 调试信息 | 变量值、执行流程 |
| **INFO** | 200 | 一般信息 | 用户登录、业务操作 |
| **NOTICE** | 250 | 注意事项 | 配置变更、状态切换 |
| **WARNING** | 300 | 警告信息 | 弃用功能、资源不足 |
| **ERROR** | 400 | 错误信息 | 异常捕获、操作失败 |
| **CRITICAL** | 500 | 关键错误 | 系统故障、数据损坏 |
| **ALERT** | 550 | 需要立即处理 | 服务不可用 |
| **EMERGENCY** | 600 | 系统不可用 | 整个系统崩溃 |

## 基础用法

### 获取日志记录器

```php
use Core\App;
use Monolog\Level;

// 获取默认应用日志记录器
$logger = App::log();

// 获取指定名称的日志记录器
$apiLogger = App::log('api');
$dbLogger = App::log('database');
$queueLogger = App::log('queue');

// 指定日志级别
$debugLogger = App::log('debug', Level::Debug);
$errorLogger = App::log('error', Level::Error);
```

### 基本日志记录

```php
// 记录不同级别的日志
App::log()->debug('调试信息', ['variable' => $value]);
App::log()->info('用户登录', ['user_id' => 123]);
App::log()->notice('配置更新', ['config' => 'database']);
App::log()->warning('内存使用过高', ['usage' => '85%']);
App::log()->error('数据库连接失败', ['error' => $exception->getMessage()]);
App::log()->critical('支付系统故障', ['payment_gateway' => 'alipay']);
```

### 结构化日志记录

```php
// 记录用户行为
App::log('user')->info('用户操作', [
    'user_id' => $user->id,
    'action' => 'update_profile',
    'ip' => $request->getClientIp(),
    'user_agent' => $request->getHeaderLine('User-Agent'),
    'timestamp' => date('Y-m-d H:i:s'),
    'changes' => [
        'email' => ['old' => $oldEmail, 'new' => $newEmail],
        'phone' => ['old' => $oldPhone, 'new' => $newPhone]
    ]
]);

// 记录API请求
App::log('api')->info('API请求', [
    'method' => $request->getMethod(),
    'uri' => (string)$request->getUri(),
    'params' => $request->getQueryParams(),
    'body' => $request->getParsedBody(),
    'response_time' => $responseTime,
    'status_code' => $response->getStatusCode()
]);
```

## 实际应用场景

### 1. 控制器中的日志记录

```php
use Core\Resources\Resource;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class UserController extends Resource
{
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $startTime = microtime(true);
        $data = $request->getParsedBody();

        App::log('user')->info('创建用户请求', [
            'request_data' => $data,
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'
        ]);

        try {
            // 验证数据
            $validated = $this->validate($data);

            // 创建用户
            $user = User::create($validated);

            $executionTime = microtime(true) - $startTime;

            App::log('user')->info('用户创建成功', [
                'user_id' => $user->id,
                'username' => $user->username,
                'execution_time' => round($executionTime * 1000, 2) . 'ms'
            ]);

            return send($response, '创建成功', $user->toArray());

        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            App::log('user')->error('用户创建失败', [
                'request_data' => $data,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'execution_time' => round($executionTime * 1000, 2) . 'ms'
            ]);

            throw $e;
        }
    }
}
```

### 2. 服务层日志记录

```php
class OrderService
{
    public function processOrder(array $orderData): Order
    {
        $orderId = $orderData['id'] ?? 'unknown';
        $logger = App::log('order');

        $logger->info('订单处理开始', [
            'order_id' => $orderId,
            'customer_id' => $orderData['customer_id'] ?? null,
            'amount' => $orderData['amount'] ?? null
        ]);

        try {
            // 验证库存
            $this->validateInventory($orderData['items']);
            $logger->info('库存验证通过', ['order_id' => $orderId]);

            // 处理支付
            $payment = $this->processPayment($orderData['payment']);
            $logger->info('支付处理完成', [
                'order_id' => $orderId,
                'payment_id' => $payment->id,
                'amount' => $payment->amount
            ]);

            // 创建订单
            $order = Order::create($orderData);
            $logger->info('订单创建成功', [
                'order_id' => $order->id,
                'status' => $order->status
            ]);

            return $order;

        } catch (InsufficientInventoryException $e) {
            $logger->warning('库存不足', [
                'order_id' => $orderId,
                'insufficient_items' => $e->getInsufficientItems()
            ]);
            throw $e;

        } catch (PaymentException $e) {
            $logger->error('支付失败', [
                'order_id' => $orderId,
                'payment_error' => $e->getMessage(),
                'payment_code' => $e->getCode()
            ]);
            throw $e;

        } catch (\Exception $e) {
            $logger->critical('订单处理出现严重错误', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
```

### 3. 中间件日志记录

```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LoggingMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);
        $requestId = uniqid('req_');

        // 记录请求开始
        App::log('request')->info('请求开始', [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'uri' => (string)$request->getUri(),
            'headers' => $this->filterHeaders($request->getHeaders()),
            'query' => $request->getQueryParams(),
            'body_size' => $request->getBody()->getSize()
        ]);

        // 将请求ID添加到请求属性中
        $request = $request->withAttribute('request_id', $requestId);

        try {
            $response = $handler->handle($request);
            $executionTime = microtime(true) - $startTime;

            // 记录请求完成
            App::log('request')->info('请求完成', [
                'request_id' => $requestId,
                'status_code' => $response->getStatusCode(),
                'execution_time' => round($executionTime * 1000, 2) . 'ms',
                'memory_usage' => $this->formatBytes(memory_get_peak_usage()),
                'response_size' => $response->getBody()->getSize()
            ]);

            return $response;

        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            App::log('request')->error('请求处理失败', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'execution_time' => round($executionTime * 1000, 2) . 'ms',
                'memory_usage' => $this->formatBytes(memory_get_peak_usage())
            ]);

            throw $e;
        }
    }

    private function filterHeaders(array $headers): array
    {
        $filtered = [];
        foreach ($headers as $name => $values) {
            // 过滤敏感头信息
            if (in_array(strtolower($name), ['authorization', 'cookie', 'x-api-key'])) {
                $filtered[$name] = ['[FILTERED]'];
            } else {
                $filtered[$name] = $values;
            }
        }
        return $filtered;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . 'MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . 'KB';
        }
        return $bytes . 'B';
    }
}
```

### 4. 队列任务日志

```php
class EmailJob
{
    public function handle(array $data): void
    {
        $jobId = $data['job_id'] ?? uniqid('job_');
        $logger = App::log('queue');

        $logger->info('邮件任务开始', [
            'job_id' => $jobId,
            'to' => $data['to'] ?? null,
            'subject' => $data['subject'] ?? null,
            'template' => $data['template'] ?? null
        ]);

        try {
            // 渲染邮件内容
            $content = $this->renderEmailContent($data['template'], $data['data']);
            $logger->debug('邮件内容渲染完成', [
                'job_id' => $jobId,
                'content_length' => strlen($content)
            ]);

            // 发送邮件
            $result = $this->sendEmail($data['to'], $data['subject'], $content);

            $logger->info('邮件发送成功', [
                'job_id' => $jobId,
                'message_id' => $result['message_id'] ?? null,
                'provider' => $result['provider'] ?? null
            ]);

        } catch (\Exception $e) {
            $logger->error('邮件发送失败', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);
            throw $e;
        }
    }
}
```

## 日志配置

### 文件轮转配置

```php
// 源码位置：src/Logs/LogHandler.php
use Monolog\Handler\RotatingFileHandler;

// 默认配置
new RotatingFileHandler(
    App::$dataPath . '/logs/' . $name . '.log',  // 日志文件路径
    15,                                           // 保留15个文件
    $level,                                       // 日志级别
    true,                                         // 自动创建目录
    0777                                          // 文件权限
);
```

### 自定义日志处理器

```php
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\JsonFormatter;

class CustomLogHandler
{
    public static function createLogger(string $name): Logger
    {
        $logger = new Logger($name);

        // 添加文件处理器
        $fileHandler = new RotatingFileHandler(
            App::$dataPath . "/logs/{$name}.log",
            30,  // 保留30天
            Level::Debug
        );

        // 自定义格式
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,  // 允许内联换行
            true   // 忽略空上下文
        );
        $fileHandler->setFormatter($formatter);
        $logger->pushHandler($fileHandler);

        // 错误级别同时写入系统日志
        if (Level::Error->value <= Level::Debug->value) {
            $syslogHandler = new SyslogHandler($name, LOG_USER, Level::Error);
            $logger->pushHandler($syslogHandler);
        }

        return $logger;
    }
}
```

## 日志查看和分析

### 1. 日志文件位置

```
data/logs/
├── app.log              # 主应用日志
├── api.log              # API请求日志
├── database.log         # 数据库操作日志
├── queue.log            # 队列任务日志
├── user.log             # 用户操作日志
├── order.log            # 订单处理日志
├── error.log            # 错误日志
└── debug.log            # 调试日志
```

### 2. 日志查看命令

```bash
# 查看实时日志
tail -f data/logs/app.log

# 查看最新100行
tail -n 100 data/logs/api.log

# 搜索特定内容
grep "ERROR" data/logs/app.log
grep "user_id.*123" data/logs/user.log

# 按时间范围查看
awk '/2024-01-01 10:00:00/,/2024-01-01 11:00:00/' data/logs/app.log
```

### 3. 日志分析工具

```php
class LogAnalyzer
{
    public function analyzeApiLogs(string $date): array
    {
        $logFile = "data/logs/api.log";
        $stats = [
            'total_requests' => 0,
            'error_count' => 0,
            'avg_response_time' => 0,
            'top_endpoints' => [],
            'error_types' => []
        ];

        if (!file_exists($logFile)) {
            return $stats;
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $responseTimes = [];
        $endpoints = [];
        $errors = [];

        foreach ($lines as $line) {
            if (strpos($line, $date) === false) {
                continue;
            }

            // 解析日志行
            if (preg_match('/\[(.*?)\].*?"(.*?)".*?(\d+\.\d+)ms/', $line, $matches)) {
                $stats['total_requests']++;
                $responseTimes[] = (float)$matches[3];

                $endpoint = $matches[2];
                $endpoints[$endpoint] = ($endpoints[$endpoint] ?? 0) + 1;
            }

            // 统计错误
            if (strpos($line, 'ERROR') !== false) {
                $stats['error_count']++;

                if (preg_match('/ERROR.*?"error":"(.*?)"/', $line, $errorMatch)) {
                    $error = $errorMatch[1];
                    $errors[$error] = ($errors[$error] ?? 0) + 1;
                }
            }
        }

        $stats['avg_response_time'] = count($responseTimes) > 0
            ? round(array_sum($responseTimes) / count($responseTimes), 2)
            : 0;

        arsort($endpoints);
        $stats['top_endpoints'] = array_slice($endpoints, 0, 10, true);

        arsort($errors);
        $stats['error_types'] = $errors;

        return $stats;
    }

    public function generateDailyReport(string $date): array
    {
        return [
            'date' => $date,
            'api_stats' => $this->analyzeApiLogs($date),
            'error_summary' => $this->getErrorSummary($date),
            'performance_metrics' => $this->getPerformanceMetrics($date)
        ];
    }
}
```

## 日志最佳实践

### 1. 日志级别选择

```php
// ✅ 正确的日志级别使用
App::log()->debug('SQL查询', ['query' => $sql, 'bindings' => $bindings]);
App::log()->info('用户登录', ['user_id' => $userId]);
App::log()->warning('API调用超时', ['endpoint' => $url, 'timeout' => $timeout]);
App::log()->error('数据库连接失败', ['error' => $e->getMessage()]);
App::log()->critical('支付网关不可用', ['gateway' => 'alipay']);

// ❌ 错误的日志级别使用
App::log()->error('用户登录');  // 应该用 info
App::log()->debug('支付失败'); // 应该用 error
```

### 2. 结构化信息

```php
// ✅ 结构化的上下文信息
App::log('order')->info('订单状态变更', [
    'order_id' => $order->id,
    'old_status' => $oldStatus,
    'new_status' => $newStatus,
    'user_id' => $order->user_id,
    'amount' => $order->amount,
    'reason' => $reason
]);

// ❌ 缺乏结构的信息
App::log()->info("订单{$order->id}从{$oldStatus}变更为{$newStatus}");
```

### 3. 敏感信息过滤

```php
class LogDataFilter
{
    private static array $sensitiveFields = [
        'password', 'password_confirmation', 'token', 'secret',
        'api_key', 'private_key', 'credit_card', 'ssn'
    ];

    public static function filter(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), self::$sensitiveFields)) {
                $data[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $data[$key] = self::filter($value);
            }
        }
        return $data;
    }
}

// 使用过滤器
$safeData = LogDataFilter::filter($requestData);
App::log('api')->info('API请求', ['data' => $safeData]);
```

### 4. 性能监控

```php
class PerformanceLogger
{
    public static function logSlowQuery(string $query, float $time, array $bindings = []): void
    {
        if ($time > 1.0) { // 超过1秒的查询
            App::log('performance')->warning('慢查询检测', [
                'query' => $query,
                'execution_time' => round($time * 1000, 2) . 'ms',
                'bindings' => $bindings,
                'memory_usage' => memory_get_usage(true)
            ]);
        }
    }

    public static function logMemoryUsage(string $context): void
    {
        $memoryUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);

        if ($memoryUsage > 50 * 1024 * 1024) { // 超过50MB
            App::log('performance')->warning('内存使用过高', [
                'context' => $context,
                'current_usage' => self::formatBytes($memoryUsage),
                'peak_usage' => self::formatBytes($peakUsage)
            ]);
        }
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . 'MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . 'KB';
        }
        return $bytes . 'B';
    }
}
```

## 日志维护

### 自动清理脚本

```php
use Core\Scheduler\Attribute\Scheduler;

class LogMaintenanceService
{
    #[Scheduler('0 2 * * *')] // 每天凌晨2点
    public function cleanOldLogs(): void
    {
        $logDir = App::$dataPath . '/logs';
        $maxAge = 30 * 24 * 60 * 60; // 30天

        $files = glob($logDir . '/*.log*');
        foreach ($files as $file) {
            if (time() - filemtime($file) > $maxAge) {
                unlink($file);
                App::log('maintenance')->info('删除过期日志文件', ['file' => basename($file)]);
            }
        }
    }

    #[Scheduler('0 3 * * 0')] // 每周日凌晨3点
    public function compressOldLogs(): void
    {
        $logDir = App::$dataPath . '/logs';
        $files = glob($logDir . '/*.log');

        foreach ($files as $file) {
            if (filesize($file) > 10 * 1024 * 1024) { // 大于10MB
                $compressedFile = $file . '.gz';

                $data = file_get_contents($file);
                file_put_contents($compressedFile, gzencode($data));
                unlink($file);

                App::log('maintenance')->info('压缩日志文件', [
                    'original_file' => basename($file),
                    'compressed_file' => basename($compressedFile),
                    'original_size' => filesize($file),
                    'compressed_size' => filesize($compressedFile)
                ]);
            }
        }
    }
}
```

通过 DuxLite 的日志系统，您可以有效地监控应用运行状态、追踪问题根源、分析性能瓶颈，为应用的稳定运行提供强有力的支持。