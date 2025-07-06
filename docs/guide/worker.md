# Worker 模式（FrankenPHP）

DuxLite 支持基于 [FrankenPHP](https://frankenphp.dev/) 的 Worker 模式，极大提升 PHP 应用的性能和吞吐量。

## 什么是 Worker 模式？

Worker 模式下，应用只初始化一次，常驻内存，所有请求由内置线程池高效复用，避免了传统 PHP-FPM 每次请求都重新加载框架的开销。

- **3-10倍性能提升**，毫秒级响应
- **自动内存回收与重启**，防止内存泄漏
- **完全兼容现有代码**，无需大改
- **支持配置文件和命令行参数**

## 启动方式

使用命令一键启动 Worker 服务：

```bash
./dux worker:start
```

常用参数：

| 参数           | 说明                       | 示例                  |
|----------------|----------------------------|-----------------------|
| --port         | 监听端口                   | --port=8080           |
| --workers      | Worker 进程数              | --workers=4           |
| --max-requests | 每进程最大请求数，0不限制   | --max-requests=1000   |

> 其他 PHP 相关配置（如 memory_limit、post_max_size 等）请通过 `config/server.toml` 配置文件设置。

## 配置示例

```toml
# config/server.toml
[worker]
host = "0.0.0.0"
port = 8080
max_requests = 1000
workers = 4

[php]
memory_limit = "512M"
post_max_size = "100M"
upload_max_filesize = "100M"
```

## 典型用法

```bash
# 使用配置文件默认值
./dux worker:start

# 自定义端口和进程数
./dux worker:start --port=9000 --workers=2

# 限制每进程最大请求数
./dux worker:start --max-requests=500
```

## 注意事项

- Worker 模式下，**静态变量、全局变量、单例对象会在请求间共享**，请勿存储请求相关数据，避免状态污染。
- 推荐通过 `$request->withAttribute()` 传递请求上下文。
- 支持自动重启、热更新、最大请求数等高级特性。

## 参考资料

- [FrankenPHP 官方文档](https://frankenphp.dev/docs/worker/)
- [DuxLite GitHub](https://github.com/duxweb/dux-lite)