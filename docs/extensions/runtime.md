# Dux Lite Runtime

`duxweb/dux-lite-runtime` 是 DuxLite 的运行时扩展包。

它提供：

- Go runtime 二进制
- PHP master 控制进程
- 共享 PHP worker pool
- 队列调度与执行
- 计划任务调度与执行
- WebSocket 网关
- PHP <-> Go Goridge 通讯桥接

## 安装

```bash
composer require duxweb/dux-lite-runtime
```

如果是本地开发环境，可执行一次：

```bash
php dux plugin:refresh
```

## 适用场景

适合需要以下能力的项目：

- 队列常驻消费
- 秒级计划任务
- WebSocket 长连接
- PHP worker 共享池
- 用单个 runtime 统一承载 queue / scheduler / ws

## 运行模型

职责拆分：

- FrankenPHP 或普通 PHP HTTP 服务负责 Web / API
- PHP runtime master 负责控制面
- Go runtime 负责 queue / scheduler / ws 常驻服务
- PHP runtime worker 负责执行业务任务

当前队列模型：

1. PHP 负责入队
2. PHP 输出 dispatcher 配置
3. Go 周期性向 PHP 拉取 dispatcher 列表
4. Go 根据 dispatcher 并发做调度
5. Go 将任务分发到共享 PHP worker pool
6. PHP worker 执行业务
7. Go 将 ack / fail 回传给 PHP

## 配置

如需覆盖默认值，可在 `config/use.toml` 或 `config/use.dev.toml` 中增加。

以下参数均为默认参数，非必填：

```toml
[runtime]
# Go runtime 二进制命令，留空时自动按当前平台查找内置二进制
go_command = ""

# Go realtime 服务端口
port = 9504

# Go -> PHP master 控制 endpoint
control_socket = "data/runtime/master.sock"

# PHP -> Go gateway 控制 endpoint
gateway_socket = "data/runtime/gateway.sock"

# runtime master 进程 pid 文件
pid_file = "data/runtime/master.pid"

# PHP worker 启动命令
worker_command = "php dux runtime --worker"

# 单个 PHP worker 最多处理多少个任务后回收重建
worker_max_jobs = 1000

# 超过最小池的空闲 worker 保留秒数
worker_idle_ttl = 300

# 启动时最小 PHP worker 数
min_workers = 4

# 最大 PHP worker 数，0 表示不限制
max_workers = 0

# 没有空闲 worker 时每次扩容数量
scale_up_step = 1

# 单任务超时秒数，超时后当前 worker 会被销毁重建
task_timeout = 30

# 队列轮询间隔
queue_poll_interval = "1s"

# 每次从 PHP 拉取的队列任务上限
queue_pull_limit = 8

# 队列 dispatcher 配置刷新间隔
queue_config_refresh = "10s"

# 计划任务轮询间隔
schedule_poll_interval = "1s"

# 每次从 PHP 拉取的计划任务上限
schedule_pull_limit = 8

# runtime --watch 状态刷新间隔
status_interval = 10

# 是否启用扩展内置 WS fallback listener
ws_fallback = true

# 自定义 WS 鉴权回调，留空时走事件监听
ws_auth_callback = ""

# 是否启用 PHP 文件型队列统计，默认建议关闭
queue_metrics = false
```

关键点：

- `min_workers` 启动时拉起最小 worker 数
- `max_workers` 为 0 表示不限制
- `scale_up_step` 控制无空闲 worker 时的扩容步长
- `worker_idle_ttl` 控制超出最小池的空闲 worker 回收时间
- `queue_metrics` 默认建议关闭

Windows 说明：

- Windows 默认不使用 Unix socket
- `control_socket` 和 `gateway_socket` 会自动退化为本地 TCP endpoint
- 默认使用 `tcp://127.0.0.1:0` 随机端口模式
- runtime 启动后会将实际地址写入 `data/runtime/control.endpoint` 和 `data/runtime/gateway.endpoint`

## 命令

### `runtime`

启动 PHP master 和 Go runtime：

```bash
php dux runtime
```

### `runtime --worker`

内部 PHP worker 入口：

```bash
php dux runtime --worker
```

### `runtime:status`

查看运行时状态：

```bash
php dux runtime:status
```

### `runtime:restart`

重启 runtime 服务：

```bash
php dux runtime:restart
```

## 依赖

按队列后端选择安装：

- Redis 队列需要 `ext-redis` 和 `symfony/redis-messenger`
- AMQP 队列需要 `ext-amqp` 和 `symfony/amqp-messenger`

如果缺少对应 bridge 包，队列启动时会直接提示安装命令。

## 运维接口

- `ws://host:port/ws`
- `http://host:port/healthz`
- `http://host:port/metrics`

## 仓库

- GitHub: `https://github.com/duxweb/dux-lite-runtime`
