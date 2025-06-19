# Logs 日志

DuxLite 日志系统的核心类定义和 API 规格说明。

## LogHandler 类

**命名空间：** `Core\Logs\LogHandler`

### 方法

```php
public static function init(string $name, Level $level): Logger
```
- **参数：**
  - `$name` - 日志名称（对应日志文件名）
  - `$level` - 最低日志级别
- **返回：** `Monolog\Logger` - 日志记录器实例
- **说明：** 创建并配置日志记录器，自动设置文件轮转

## Logger 类（Monolog组件）

**命名空间：** `Monolog\Logger`

### 方法

```php
public function debug(string $message, array $context = []): void
```
- **参数：**
  - `$message` - 日志消息
  - `$context` - 上下文数据数组（可选）
- **返回：** `void`
- **说明：** 记录调试级别日志

```php
public function info(string $message, array $context = []): void
```
- **参数：**
  - `$message` - 日志消息
  - `$context` - 上下文数据数组（可选）
- **返回：** `void`
- **说明：** 记录信息级别日志

```php
public function notice(string $message, array $context = []): void
```
- **参数：**
  - `$message` - 日志消息
  - `$context` - 上下文数据数组（可选）
- **返回：** `void`
- **说明：** 记录注意级别日志

```php
public function warning(string $message, array $context = []): void
```
- **参数：**
  - `$message` - 日志消息
  - `$context` - 上下文数据数组（可选）
- **返回：** `void`
- **说明：** 记录警告级别日志

```php
public function error(string $message, array $context = []): void
```
- **参数：**
  - `$message` - 日志消息
  - `$context` - 上下文数据数组（可选）
- **返回：** `void`
- **说明：** 记录错误级别日志

```php
public function critical(string $message, array $context = []): void
```
- **参数：**
  - `$message` - 日志消息
  - `$context` - 上下文数据数组（可选）
- **返回：** `void`
- **说明：** 记录关键错误级别日志

```php
public function alert(string $message, array $context = []): void
```
- **参数：**
  - `$message` - 日志消息
  - `$context` - 上下文数据数组（可选）
- **返回：** `void`
- **说明：** 记录需要立即处理的日志

```php
public function emergency(string $message, array $context = []): void
```
- **参数：**
  - `$message` - 日志消息
  - `$context` - 上下文数据数组（可选）
- **返回：** `void`
- **说明：** 记录系统不可用级别日志

```php
public function log(mixed $level, string $message, array $context = []): void
```
- **参数：**
  - `$level` - 日志级别
  - `$message` - 日志消息
  - `$context` - 上下文数据数组（可选）
- **返回：** `void`
- **说明：** 记录指定级别的日志

```php
public function pushHandler(HandlerInterface $handler): static
```
- **参数：** `$handler` - 日志处理器
- **返回：** `static` - 当前实例
- **说明：** 添加日志处理器

```php
public function popHandler(): HandlerInterface
```
- **返回：** `HandlerInterface` - 移除的处理器
- **说明：** 移除最近添加的处理器

```php
public function useLoggingLoopDetection(bool $detectCycles): static
```
- **参数：** `$detectCycles` - 是否检测循环日志
- **返回：** `static` - 当前实例
- **说明：** 设置是否检测循环日志

## 日志级别

| 级别 | 数值 | 用途 |
|------|------|------|
| `DEBUG` | 100 | 调试信息 |
| `INFO` | 200 | 一般信息 |
| `NOTICE` | 250 | 注意事项 |
| `WARNING` | 300 | 警告信息 |
| `ERROR` | 400 | 错误信息 |
| `CRITICAL` | 500 | 关键错误 |
| `ALERT` | 550 | 需要立即处理 |
| `EMERGENCY` | 600 | 系统不可用 |

## 日志文件配置

### 默认配置

- **文件路径：** `data/logs/{name}.log`
- **轮转策略：** 保留15个文件
- **文件权限：** 0777
- **自动创建目录：** 是

### 支持的日志通道

| 通道名 | 文件名 | 用途 |
|--------|--------|------|
| `app` | `app.log` | 应用主日志 |
| `api` | `api.log` | API请求日志 |
| `database` | `database.log` | 数据库操作日志 |
| `queue` | `queue.log` | 队列任务日志 |
| `scheduler` | `scheduler.log` | 计划任务日志 |
| `user` | `user.log` | 用户操作日志 |
| `order` | `order.log` | 订单处理日志 |
| `error` | `error.log` | 错误日志 |
| `debug` | `debug.log` | 调试日志 |