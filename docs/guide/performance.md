# 性能优化

DuxLite 应用性能优化的最佳实践和技巧。

## PHP 性能优化

### OPcache 配置

```ini
; php.ini
[opcache]
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=64
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
opcache.save_comments=1

; 生产环境优化
opcache.validate_timestamps=0
opcache.max_wasted_percentage=10
```

### JIT 编译器（PHP 8.0+）

```ini
; php.ini
opcache.enable=1
opcache.jit=tracing
opcache.jit_buffer_size=100M
```

### 内存优化

```php
// 设置合理的内存限制
ini_set('memory_limit', '512M');

// 及时释放大对象
unset($largeArray);

// 使用生成器处理大数据集
function processLargeDataset(): \Generator
{
    foreach (User::cursor() as $user) {
        yield $user->transform();
    }
}
```

## 数据库性能优化

### 查询优化

```php
// ✅ 使用索引字段查询
User::where('email', 'test@example.com')->first();

// ✅ 预加载关联数据
$users = User::with(['orders', 'profile'])->get();

// ✅ 选择必要字段
User::select('id', 'name', 'email')->get();

// ❌ 避免 N+1 查询
foreach ($users as $user) {
    echo $user->orders->count(); // N+1 问题
}

// ✅ 正确的方式
$users = User::withCount('orders')->get();
foreach ($users as $user) {
    echo $user->orders_count;
}
```

### 分页优化

```php
// ✅ 使用分页
$users = User::paginate(20);

// ✅ 简单分页（无总数统计）
$users = User::simplePaginate(20);

// ✅ 游标分页（大数据集）
$users = User::cursorPaginate(20);
```

### 数据库索引

```php
// 创建索引的迁移文件
Schema::table('users', function (Blueprint $table) {
    $table->index('email');
    $table->index(['status', 'created_at']);
    $table->unique(['username', 'domain']);
});

// 复合索引优化
$users = User::where('status', 'active')
             ->where('created_at', '>', now()->subDays(30))
             ->get();
```

### 慢查询监控

```php
// 监控慢查询
\Core\App::db()->listen(function ($query) {
    if ($query->time > 1000) { // 超过1秒
        \Core\App::log()->warning('Slow query detected', [
            'sql' => $query->sql,
            'time' => $query->time . 'ms',
            'bindings' => $query->bindings
        ]);
    }
});
```

## 缓存优化

### 多层缓存策略

```php
// 1. 内存缓存（最快）
class MemoryCache
{
    private static array $cache = [];
    
    public static function get(string $key)
    {
        return self::$cache[$key] ?? null;
    }
    
    public static function set(string $key, $value): void
    {
        self::$cache[$key] = $value;
    }
}

// 2. Redis 缓存（持久化）
class CacheService
{
    public function getUserData(int $userId): array
    {
        // 先查内存缓存
        $key = "user:{$userId}";
        if ($data = MemoryCache::get($key)) {
            return $data;
        }
        
        // 再查 Redis 缓存
        $cache = \Core\App::cache('redis');
        if ($data = $cache->get($key)) {
            MemoryCache::set($key, $data);
            return $data;
        }
        
        // 最后查数据库
        $user = User::find($userId);
        $data = $user->transform();
        
        // 缓存到 Redis（1小时）
        $cache->set($key, $data, 3600);
        MemoryCache::set($key, $data);
        
        return $data;
    }
}
```

### 查询结果缓存

```php
class UserRepository
{
    public function getActiveUsers(): Collection
    {
        return \Core\App::cache()->remember('active_users', 1800, function () {
            return User::where('is_active', true)
                      ->select('id', 'name', 'email')
                      ->get();
        });
    }
    
    public function getUserStats(): array
    {
        return \Core\App::cache()->remember('user_stats', 3600, function () {
            return [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'new_today' => User::whereDate('created_at', today())->count(),
            ];
        });
    }
}
```

### 缓存标签

```php
// 使用缓存标签管理相关缓存
class ProductService
{
    public function getProduct(int $id): Product
    {
        return \Core\App::cache()->tags(['products', "product:{$id}"])
            ->remember("product:{$id}", 3600, function () use ($id) {
                return Product::find($id);
            });
    }
    
    public function updateProduct(int $id, array $data): Product
    {
        $product = Product::find($id);
        $product->update($data);
        
        // 清除相关缓存
        \Core\App::cache()->tags(["product:{$id}"])->flush();
        
        return $product;
    }
}
```

## HTTP 性能优化

### 响应压缩

```php
// 启用 Gzip 压缩中间件
class CompressionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        
        if ($this->shouldCompress($request, $response)) {
            $body = $response->getBody();
            $compressedBody = gzencode((string)$body, 6);
            
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $compressedBody);
            rewind($stream);
            
            return $response
                ->withBody(new \Slim\Psr7\Stream($stream))
                ->withHeader('Content-Encoding', 'gzip')
                ->withHeader('Content-Length', strlen($compressedBody));
        }
        
        return $response;
    }
}
```

### HTTP 缓存头

```php
class CacheHeaderMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        
        // 静态资源缓存
        if ($this->isStaticResource($request)) {
            return $response
                ->withHeader('Cache-Control', 'public, max-age=31536000') // 1年
                ->withHeader('Expires', gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        }
        
        // API 响应缓存
        if ($this->isApiRequest($request)) {
            return $response
                ->withHeader('Cache-Control', 'public, max-age=300') // 5分钟
                ->withHeader('ETag', md5((string)$response->getBody()));
        }
        
        return $response;
    }
}
```

### 条件请求

```php
class ConditionalRequestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 检查 If-None-Match 头
        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        
        if ($ifNoneMatch) {
            $response = $handler->handle($request);
            $etag = $response->getHeaderLine('ETag');
            
            if ($ifNoneMatch === $etag) {
                return new \Slim\Psr7\Response(304); // Not Modified
            }
            
            return $response;
        }
        
        return $handler->handle($request);
    }
}
```

## 静态资源优化

### 资源合并和压缩

```php
class AssetManager
{
    public function getCombinedCss(array $files): string
    {
        $cacheKey = 'css:' . md5(implode(',', $files));
        
        return \Core\App::cache()->remember($cacheKey, 86400, function () use ($files) {
            $combined = '';
            
            foreach ($files as $file) {
                $content = file_get_contents(public_path($file));
                $combined .= $this->minifyCss($content);
            }
            
            return $combined;
        });
    }
    
    private function minifyCss(string $css): string
    {
        // 移除注释
        $css = preg_replace('!/\*.*?\*/!s', '', $css);
        
        // 移除多余空白
        $css = preg_replace('/\s+/', ' ', $css);
        
        return trim($css);
    }
}
```

### CDN 配置

```php
class CdnHelper
{
    public static function asset(string $path): string
    {
        $cdnUrl = \Core\App::config('app')->get('cdn_url');
        
        if ($cdnUrl && app()->environment('production')) {
            return rtrim($cdnUrl, '/') . '/' . ltrim($path, '/');
        }
        
        return asset($path);
    }
}
```

## 队列性能优化

### 队列工作进程优化

```bash
# supervisor 配置
[program:duxlite-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/dux queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/duxlite-worker.log
```

### 批量处理

```php
class BatchEmailJob extends \Core\Queue\QueueMsg
{
    public function __construct(private array $userIds) {}
    
    public function handle(): void
    {
        // 批量获取用户
        $users = User::whereIn('id', $this->userIds)->get();
        
        // 批量发送邮件
        foreach ($users->chunk(50) as $userChunk) {
            $this->sendBatchEmail($userChunk);
        }
    }
}

// 使用批量任务
$userIds = User::where('active', true)->pluck('id')->toArray();
$chunks = array_chunk($userIds, 100);

foreach ($chunks as $chunk) {
    \Core\App::queue()->push(new BatchEmailJob($chunk));
}
```

## 内存优化

### 大数据集处理

```php
// ✅ 使用游标处理大数据集
class DataExportService
{
    public function exportUsers(): void
    {
        $file = fopen('users.csv', 'w');
        
        // 使用游标避免内存溢出
        User::chunk(1000, function ($users) use ($file) {
            foreach ($users as $user) {
                fputcsv($file, $user->toArray());
            }
        });
        
        fclose($file);
    }
}

// ✅ 使用生成器
function processLargeFile(string $filename): \Generator
{
    $handle = fopen($filename, 'r');
    
    while (($line = fgets($handle)) !== false) {
        yield trim($line);
    }
    
    fclose($handle);
}
```

### 内存泄漏预防

```php
class MemoryEfficientProcessor
{
    public function processData(array $data): void
    {
        foreach ($data as $item) {
            $this->processItem($item);
            
            // 及时释放内存
            unset($item);
            
            // 检查内存使用
            if (memory_get_usage() > 500 * 1024 * 1024) { // 500MB
                gc_collect_cycles();
            }
        }
    }
}
```

## 监控和分析

### 性能监控

```php
class PerformanceMonitor
{
    private static float $startTime;
    private static int $startMemory;
    
    public static function start(): void
    {
        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage(true);
    }
    
    public static function end(string $operation): void
    {
        $duration = microtime(true) - self::$startTime;
        $memoryUsed = memory_get_usage(true) - self::$startMemory;
        
        \Core\App::log()->info('Performance metrics', [
            'operation' => $operation,
            'duration' => round($duration * 1000, 2) . 'ms',
            'memory' => round($memoryUsed / 1024 / 1024, 2) . 'MB',
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB'
        ]);
    }
}
```

### APM 集成

```php
class ApmMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $transactionName = $this->getTransactionName($request);
        
        // 开始事务监控
        $this->startTransaction($transactionName);
        
        try {
            $response = $handler->handle($request);
            
            // 记录成功
            $this->recordSuccess($response->getStatusCode());
            
            return $response;
        } catch (\Throwable $e) {
            // 记录错误
            $this->recordError($e);
            throw $e;
        } finally {
            // 结束事务
            $this->endTransaction();
        }
    }
}
```

## 性能测试

### 基准测试

```php
class BenchmarkTest
{
    public function testDatabasePerformance(): void
    {
        $iterations = 1000;
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            User::find(1);
        }
        
        $duration = microtime(true) - $startTime;
        $avgTime = ($duration / $iterations) * 1000;
        
        echo "Average query time: {$avgTime}ms\n";
    }
    
    public function testCachePerformance(): void
    {
        $cache = \Core\App::cache();
        $iterations = 10000;
        
        // 写入测试
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $cache->set("key:{$i}", "value:{$i}");
        }
        $writeTime = microtime(true) - $startTime;
        
        // 读取测试
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $cache->get("key:{$i}");
        }
        $readTime = microtime(true) - $startTime;
        
        echo "Cache write time: " . round($writeTime * 1000, 2) . "ms\n";
        echo "Cache read time: " . round($readTime * 1000, 2) . "ms\n";
    }
}
```

## 生产环境优化

### 部署优化

```bash
# Composer 优化
composer install --no-dev --optimize-autoloader

# 预编译配置
php dux config:cache

# 路由缓存
php dux route:cache

# 清理开发文件
rm -rf tests/
rm -rf docs/
rm .env.example
```

### 服务器配置

```nginx
# Nginx 优化配置
server {
    # 启用 Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
    
    # 静态文件缓存
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header X-Cache-Status "HIT";
    }
    
    # 启用 HTTP/2
    listen 443 ssl http2;
    
    # 连接优化
    keepalive_timeout 65;
    keepalive_requests 100;
}
```

通过这些性能优化措施，可以显著提升 DuxLite 应用的响应速度和处理能力。