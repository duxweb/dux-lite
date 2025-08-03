# 调试技巧

DuxLite 开发调试的常用方法。

## 开启调试模式

```toml
# config/use.dev.toml
[app]
debug = true
```

## 调试输出

### 快速调试函数

```php
// 输出变量并停止执行
dd($variable);

// 输出变量，继续执行
dump($variable);

// 控制台输出
error_log(json_encode($data));
```

### 日志调试

```php
// 写入调试日志
\Core\App::log()->debug('调试信息', ['data' => $data]);
\Core\App::log()->info('运行状态', ['status' => 'ok']);
\Core\App::log()->error('错误信息', ['error' => $exception->getMessage()]);
```

## Xdebug 断点调试

### 配置 Xdebug

```ini
; php.ini
[xdebug]
xdebug.mode = debug
xdebug.start_with_request = yes
xdebug.client_host = localhost
xdebug.client_port = 9003
```

### IDE 调试配置

配置 IDE（如 VSCode、PhpStorm）连接 Xdebug，设置断点进行逐步调试。

## 查看日志

```bash
# 查看应用日志
tail -f data/logs/app.log

# 查看 PHP 错误日志
tail -f /var/log/php/error.log
```

简单实用的调试方法，快速定位问题。