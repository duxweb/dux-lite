# Worker 模式

DuxLite 支持基于 FrankenPHP 的 Worker 模式运行，提供极高的性能和并发处理能力。

## 性能优势

Worker 模式将应用常驻内存，通过多线程处理请求，相比传统 PHP-FPM 有显著性能提升：

- **3-10x 性能提升** - 毫秒级响应
- **零冷启动开销** - 应用预加载到内存
- **内存复用** - 减少重复初始化
- **自动管理** - 内存回收和进程重启

## 启动命令

### 基本启动

```bash
# 使用默认配置启动
./dux worker:start

# 指定端口启动
./dux worker:start --port=9000

# 设置工作进程数
./dux worker:start --workers=4
```

### 命令参数

| 参数 | 说明 | 默认值 |
|------|------|--------|
| `--port` | 监听端口 | 8080 |
| `--workers` | 工作进程数 | CPU 核心数 |
| `--max-requests` | 每进程最大请求数 | 1000 |

## 配置文件

### server.toml 配置

```toml
# config/server.toml
[worker]
host = "0.0.0.0"
port = 8080
workers = 4
max_requests = 1000

# PHP 运行时配置
[php]
memory_limit = "512M"
post_max_size = "100M"
upload_max_filesize = "100M"
max_execution_time = 30
```

## 开发注意事项

⚠️ **开发要点**：

**避免状态污染**
```php
// ❌ 错误：静态变量会在请求间共享
class BadService
{
    private static $cache = []; // 危险！
}

// ✅ 正确：使用外部缓存
class GoodService
{
    public function getData($key)
    {
        return \Core\App::cache()->get($key);
    }
}
```

**内存管理**
```php
// 处理大数据集时及时释放
foreach ($items as $item) {
    $this->process($item);
    unset($item); // 释放内存
}
```

## 使用场景

Worker 模式特别适合：

- **高并发 API 服务** - 接口响应速度要求高
- **微服务架构** - 服务间通信频繁
- **实时应用** - WebSocket、SSE 等场景
- **计算密集型任务** - 减少进程创建开销

### 注意事项

⚠️ **重要提醒**：
- 静态变量和全局变量会在请求间共享
- 避免在类属性中存储请求相关数据
- 大型应用建议配合传统 PHP-FPM 使用
- 开发阶段可能需要重启 Worker 查看代码更改

Worker 模式为 DuxLite 提供了卓越的性能表现，适合对响应速度有高要求的应用场景。