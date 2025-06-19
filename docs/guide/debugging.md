# 调试技巧

DuxLite 应用调试实用指南。

## 基础调试

### 开启调试模式

```toml
# config/use.toml
[app]
debug = true
env = "development"

[log]
level = "debug"
```

### 简单调试函数

```php
// 快速调试变量
function dd(...$vars): void
{
    foreach ($vars as $var) {
        echo "<pre>";
        var_dump($var);
        echo "</pre>";
    }
    die();
}

// 输出调试信息
function dump($var): void
{
    echo "<pre>";
    var_dump($var);
    echo "</pre>";
}

// 记录调试日志
function debug_log($message, $data = []): void
{
    App::log()->debug($message, is_array($data) ? $data : [$data]);
}
```

## 日志调试

### 基础日志记录

```php
class UserService
{
    public function createUser(array $data): User
    {
        App::log()->info('创建用户', ['email' => $data['email']]);

        try {
            $user = User::create($data);
            App::log()->info('用户创建成功', ['user_id' => $user->id]);
            return $user;
        } catch (\Exception $e) {
            App::log()->error('用户创建失败', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
}
```

### 查看日志文件

```bash
# 查看最新日志
tail -f data/logs/app.log

# 查看错误日志
tail -f data/logs/error.log

# 搜索特定内容
grep "用户创建" data/logs/app.log
```

## 数据库调试

### 启用查询日志

```php
// 在开发环境启用
if (App::config('app.debug')) {
    App::db()->listen(function ($query) {
        App::log()->debug('SQL查询', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time . 'ms'
        ]);
    });
}
```

### 查询调试

```php
// 查看生成的SQL
$query = User::where('status', 'active')->where('age', '>', 18);
dd($query->toSql(), $query->getBindings());

// 调试单个查询
$user = User::find(1);
if (!$user) {
    debug_log('用户不存在', ['id' => 1]);
}
```

## 异常调试

### 捕获和记录异常

```php
class OrderController
{
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = $request->getParsedBody();
            $order = $this->orderService->create($data);

            return send($response, '创建成功', $order->toArray());

        } catch (ExceptionValidator $e) {
            App::log()->warning('订单验证失败', $e->getErrors());
            throw $e;
        } catch (\Exception $e) {
            App::log()->error('订单创建异常', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => $data ?? []
            ]);
            throw new ExceptionBusiness('订单创建失败');
        }
    }
}
```

## 性能调试

### 简单性能监控

```php
class DebugMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = microtime(true);
        $startMemory = memory_get_usage();

        $response = $handler->handle($request);

        $time = round((microtime(true) - $start) * 1000, 2);
        $memory = round((memory_get_usage() - $startMemory) / 1024 / 1024, 2);

        App::log()->debug('请求性能', [
            'uri' => (string) $request->getUri(),
            'method' => $request->getMethod(),
            'time' => $time . 'ms',
            'memory' => $memory . 'MB'
        ]);

        return $response->withHeader('X-Debug-Time', $time . 'ms');
    }
}
```

### 内存使用检查

```php
function check_memory(string $point = ''): void
{
    $memory = round(memory_get_usage() / 1024 / 1024, 2);
    $peak = round(memory_get_peak_usage() / 1024 / 1024, 2);

    debug_log("内存使用 {$point}", [
        'current' => $memory . 'MB',
        'peak' => $peak . 'MB'
    ]);
}

// 使用示例
check_memory('开始');
$users = User::all();
check_memory('查询后');
```

## 队列调试

### 队列任务调试

```php
class SendEmailJob extends QueueMessage
{
    public function handle(): void
    {
        App::log()->debug('开始发送邮件', [
            'email' => $this->email,
            'subject' => $this->subject
        ]);

        try {
            // 发送邮件逻辑
            mail($this->email, $this->subject, $this->content);

            App::log()->info('邮件发送成功', ['email' => $this->email]);
        } catch (\Exception $e) {
            App::log()->error('邮件发送失败', [
                'email' => $this->email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
```

### 查看队列状态

```bash
# 查看队列任务
php dux queue:list

# 处理队列（调试模式）
php dux queue:work --verbose
```

## 缓存调试

### 缓存操作日志

```php
class CacheDebugger
{
    public static function logHit(string $key): void
    {
        App::log()->debug('缓存命中', ['key' => $key]);
    }

    public static function logMiss(string $key): void
    {
        App::log()->debug('缓存未命中', ['key' => $key]);
    }

    public static function logSet(string $key, $value): void
    {
        App::log()->debug('缓存设置', [
            'key' => $key,
            'size' => strlen(serialize($value))
        ]);
    }
}

// 在缓存操作中使用
$value = App::cache()->get($key);
if ($value === null) {
    CacheDebugger::logMiss($key);
} else {
    CacheDebugger::logHit($key);
}
```

## 请求调试

### 请求信息记录

```php
class RequestDebugMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 记录请求信息
        App::log()->debug('请求开始', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => $request->getHeaders(),
            'body' => $request->getParsedBody()
        ]);

        $response = $handler->handle($request);

        // 记录响应信息
        App::log()->debug('请求完成', [
            'status' => $response->getStatusCode(),
            'size' => $response->getBody()->getSize()
        ]);

        return $response;
    }
}
```

## 常用调试命令

### 框架调试命令

```bash
# 查看路由列表
php dux route:list

# 查看配置
php dux config:show

# 清理缓存
php dux cache:clear

# 数据库状态
php dux db:status
```

### 系统调试命令

```bash
# 查看PHP错误日志
tail -f /var/log/php_errors.log

# 查看Web服务器日志
tail -f /var/log/nginx/error.log
tail -f /var/log/apache2/error.log

# 查看系统资源
top
htop
free -h
df -h
```

## 调试技巧

### 1. 分步调试

```php
public function complexProcess($data)
{
    debug_log('步骤1：开始处理', $data);

    $validated = $this->validate($data);
    debug_log('步骤2：验证完成', $validated);

    $processed = $this->process($validated);
    debug_log('步骤3：处理完成', $processed);

    $result = $this->save($processed);
    debug_log('步骤4：保存完成', ['id' => $result->id]);

    return $result;
}
```

### 2. 条件调试

```php
public function debugIfNeeded($condition, $message, $data = [])
{
    if ($condition && App::config('app.debug')) {
        debug_log($message, $data);
    }
}

// 使用
$this->debugIfNeeded($user->isAdmin(), '管理员操作', [
    'user_id' => $user->id,
    'action' => 'delete_post'
]);
```

### 3. 环境相关调试

```php
// 只在开发环境输出
if (App::config('app.env') === 'development') {
    dd($debugData);
}

// 只在调试模式记录详细日志
if (App::config('app.debug')) {
    App::log()->debug('详细调试信息', $detailData);
}
```

通过这些简单实用的调试技巧，可以快速定位和解决 DuxLite 应用中的问题。