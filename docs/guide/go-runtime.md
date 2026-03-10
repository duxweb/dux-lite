# Go Runtime

## Overview

Dux Lite 的长期运行时建议拆成两层：

- `FrankenPHP` 负责 HTTP / API / 管理端 / 普通业务请求
- `Go Runtime Service` 负责 WebSocket、调度、队列分发和 PHP worker pool 管理

目标不是替代 FrankenPHP，而是补齐 HTTP 之外的常驻型能力，同时保持现有 Dux Lite 的 PHP 业务语义不变。

## Why

当前 Dux Lite 已具备以下能力：

- 队列消费：`queue:start`、`queue:consume`
- 调度运行：`scheduler:run`
- HTTP Worker：`worker:start`（FrankenPHP）

但这些能力的运行模型仍然是分散的：

- 队列依赖 PHP 常驻 worker 进程
- 调度依赖 PHP 常驻 loop
- HTTP 由 FrankenPHP worker 承担

如果后续需要统一承载：

- 高并发队列执行
- WebSocket Gateway
- 调度循环
- 节点在线状态

就需要一个统一的常驻 runtime。Go 更适合承担这层职责。

## Runtime Layout

推荐结构：

```text
FrankenPHP
  -> HTTP / API / 管理端

PHP Runtime Master (常驻)
  -> 启动 Dux bootstrap
  -> 启动 Go Runtime
  -> 提供控制面 RPC

Go Runtime Service (常驻)
  -> WebSocket Gateway
  -> Scheduler Loop
  -> Queue Dispatcher
  -> PHP Worker Pool Manager

PHP Runtime Worker x N (常驻子进程)
  -> 执行任务
```

## Responsibility Split

### FrankenPHP

负责：

- HTTP 请求
- 后台页面
- API
- 普通业务逻辑

不负责：

- 队列高并发执行
- 调度常驻循环
- WebSocket 长连接网关
- PHP 执行 worker 管理

### PHP Runtime Master

这是一个常驻 PHP 守护进程，不是 HTTP 入口。

负责：

- 启动 Dux bootstrap
- 启动 Go Runtime Service
- 暴露控制面 RPC 给 Go
- 提供取任务、确认结果、鉴权能力

建议暴露的方法：

- `Ws.Auth`
- `Queue.Pull`
- `Queue.Ack`
- `Queue.Fail`
- `Schedule.Pull`
- `Schedule.Report`

原则：

- 只做控制面
- 不执行重任务
- 不承载高并发执行面

### Go Runtime Service

负责：

- WebSocket 连接和 topic 路由
- 在线状态
- 调度 tick
- 队列轮询
- 并发控制
- worker pool 生命周期管理
- 调 PHP master RPC
- 调 PHP worker RPC

### PHP Runtime Worker

负责：

- 接收任务
- 执行业务
- 返回结果

不负责：

- 拉任务
- 鉴权
- 确认结果
- WebSocket

## Communication

## PHP Master <-> Go

推荐：

- `Goridge over Unix socket`

原因：

- PHP master 与 Go 都常驻
- 同机调用开销低
- 不暴露额外端口
- 适合控制面 RPC

用于：

- `Queue.Pull`
- `Queue.Ack`
- `Queue.Fail`
- `Schedule.Pull`
- `Schedule.Report`
- `Ws.Auth`

## Go <-> PHP Worker

第一版推荐：

- `Goridge over pipes`

原因：

- worker 由 Go 直接拉起
- 每个 worker 自带 stdin/stdout
- 更适合固定 worker 池

后续也可扩展到：

- `Goridge over Unix socket`

## Important Note About Goridge

Goridge 只是 Go 和 PHP 之间的 RPC / IPC 协议桥，本身不会提供：

- worker pool
- 进程管理
- 并发调度
- 崩溃恢复

如果只有一个 PHP 常驻进程，Go 只能直接调用这个进程的方法，无法自然获得高并发执行能力。

因此必须区分：

- 控制面：单个 PHP master
- 执行面：多个 PHP worker

## Queue Integration

## Current Queue State

当前 Dux Lite 队列核心在：

- `Core\Queue\Queue`
- `QueueConsumeCommand`
- `QueueConsumeTask`

现有队列基于 Symfony Messenger，支持 Redis / AMQP。

这套定义方式要保留，不让 Go 直接耦合业务数据库或消息后端。

## New Queue Flow

### Control Plane

Go 调 PHP master：

- `Queue.Pull(queue, limit)`

PHP 返回统一任务信封。

### Execution Plane

Go 从空闲 worker 池中取一个 PHP worker，把任务发过去执行。

任务执行结束后，Go 再调：

- `Queue.Ack(jobId, result)`
- `Queue.Fail(jobId, error, retryable)`

这样可以做到：

- Go 不直接读业务存储
- 任务定义仍由 PHP 维护
- 并发由 worker pool 提供

## Scheduler Integration

当前 Dux Lite 调度核心在：

- `Core\Scheduler\Scheduler`
- `scheduler:run`
- `scheduler:gen`
- `data/scheduler/jobs.php`

继续保留这套定义来源。

### New Scheduler Flow

Go scheduler loop 每秒 tick：

- 调 PHP master：`Schedule.Pull(now, limit)`
- 获得当前到期任务
- 派发给空闲 PHP worker 执行
- 执行后调：`Schedule.Report(taskId, result)`

这样：

- 调度定义在 PHP
- 调度时钟和并发在 Go

## WebSocket Integration

Go Runtime 统一承担 WebSocket Gateway。

建议 topic 命名：

- `node.{nodeId}.command`
- `node.{nodeId}.result`
- `node.{nodeId}.event`
- `chat.session.{sessionId}`
- `notify.user.{userId}`

连接鉴权由 Go 调 PHP master：

- `Ws.Auth(app, token, meta)`

PHP 返回：

- `client_id`
- `client_type`
- `allow_subscribe`
- `allow_publish`
- `meta`

## Worker Pool

## Why Worker Pool Exists

单个 PHP 进程一次通常只能执行一条阻塞任务。

因此：

- 单 PHP master 适合控制面
- 真正高并发执行必须由多个 PHP worker 完成

并发不是来自“单个 worker 同时跑多条任务”，而是来自：

- Go 管理多个 worker
- 每个 worker 同一时刻只执行一条任务

## Lifecycle

Go Runtime 启动时：

- 预启动固定数量 PHP worker
- 建立 Goridge 通讯
- 管理 worker 状态：
  - `starting`
  - `idle`
  - `busy`
  - `dead`

调度流程：

1. 从 `idle` 池取 worker
2. 派发任务
3. 标记 `busy`
4. 任务结束
5. 放回 `idle`

异常流程：

- crash -> 自动补起
- timeout -> kill 并补起
- 达到 `max_jobs` -> 空闲时重启

## Suggested Config

第一版至少支持：

- `num_workers`
- `task_timeout_seconds`
- `max_jobs`
- `idle_ttl_seconds`
- `restart_on_crash`
- `socket_path`
- `go_binary_path`

第一版不做：

- 动态扩缩容
- 多队列分池
- 多实例协调
- Redis/NATS 广播同步

## New Commands

建议新增一个公开命令：

### `runtime`

负责：

- 启动 Dux bootstrap
- 启动 Go Runtime
- 提供控制面 RPC
- 作为运维唯一公开入口

内部再提供 worker 模式：

### `runtime --worker`

负责：

- 启动 Dux bootstrap
- 循环等待任务
- 执行任务并返回结果

不要直接用：

- `queue:consume`
- `scheduler:run`

作为 worker pool 的内部执行入口。

这两个旧命令继续保留，作为兼容与调试工具。

推荐 Go 启动 PHP worker 时使用：

```bash
php dux runtime --worker
```

这样比直接执行 `./dux` 更容易兼容 Windows 环境。

生产环境建议：

- Go runtime 预编译为二进制
- PHP `runtime` 主命令直接拉起当前平台对应二进制
- 不依赖本机安装 `go`

## Task Envelope

Go -> PHP Worker：

```json
{
  "id": "job_123",
  "type": "queue",
  "name": "App\\\\Jobs\\\\DemoJob",
  "payload": {
    "args": {},
    "meta": {}
  },
  "attempt": 1,
  "timeout": 30
}
```

PHP Worker -> Go：

```json
{
  "id": "job_123",
  "ok": true,
  "result": {},
  "error": "",
  "retryable": false
}
```

第一版只支持：

- `queue`
- `schedule`

不开放任意方法远程执行。

## Repository Layout

当前已拆分为独立扩展仓库 `duxweb/dux-runtime`，推荐结构：

```text
dux-runtime/
  composer.json
  src/
  go.mod
  runtime/
    README.md
    cmd/dux-runtime/main.go
    internal/app/
    internal/config/
    internal/realtime/
    internal/scheduler/
    internal/queue/
    internal/workerpool/
    internal/phpmaster/
    internal/phpworker/
```

## Implementation Phases

### Phase 1

- 文档落地
- Go Runtime 骨架
- `runtime:master`
- `runtime:worker`
- PHP master 控制面 RPC
- 固定数量 worker 池
- Queue / Scheduler 最小闭环

### Phase 2

- worker 生命周期参数化
- metrics
- topic ACL
- 更精细的超时和重启策略

## Validation Targets

后续实现至少验证：

- `runtime:master` 能启动 Go 子进程
- Go 能调 PHP master 的 `Queue.Pull`
- Go 能拉起多个 PHP worker
- worker 崩溃自动补起
- queue 并发执行不阻塞 PHP master
- scheduler 能由 Go tick 触发
- WS 鉴权和连接链路跑通
- 旧 `queue:consume` / `scheduler:run` / `worker:start` 仍可正常使用

## Final Recommendation

长期推荐结构：

- `FrankenPHP` 负责 HTTP
- `Go Runtime` 负责常驻型基础服务
- `PHP Master` 提供控制面 RPC
- `PHP Worker Pool` 提供执行面并发

这是在不改变当前 Dux Lite 业务写法前提下，最适合引入高并发与长连接能力的路线。
