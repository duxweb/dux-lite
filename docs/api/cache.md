# Cache 缓存

DuxLite 缓存系统的核心类定义和 API 规格说明。

## Cache 类

**命名空间：** `Core\Cache\Cache`

### 静态方法

```php
public static function init(array $config): \Psr\SimpleCache\CacheInterface
```
- **参数：** `$config` - 缓存配置数组
- **返回：** `\Psr\SimpleCache\CacheInterface` - 缓存接口实例

```php
public static function driver(string $name = null): \Psr\SimpleCache\CacheInterface
```
- **参数：** `$name` - 缓存驱动名称（可选）
- **返回：** `\Psr\SimpleCache\CacheInterface` - 缓存驱动实例

## PSR-16 缓存接口方法

### 基础操作

```php
public function get(string $key, mixed $default = null): mixed
```
- **参数：**
  - `$key` - 缓存键名
  - `$default` - 默认值（可选）
- **返回：** `mixed` - 缓存值或默认值
- **异常：** `\Psr\SimpleCache\InvalidArgumentException` - 键名无效时抛出

```php
public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
```
- **参数：**
  - `$key` - 缓存键名
  - `$value` - 缓存值
  - `$ttl` - 过期时间（秒数、DateInterval对象或null）
- **返回：** `bool` - 是否设置成功
- **异常：** `\Psr\SimpleCache\InvalidArgumentException` - 键名无效时抛出

```php
public function delete(string $key): bool
```
- **参数：** `$key` - 缓存键名
- **返回：** `bool` - 是否删除成功
- **异常：** `\Psr\SimpleCache\InvalidArgumentException` - 键名无效时抛出

```php
public function clear(): bool
```
- **参数：** 无
- **返回：** `bool` - 是否清空成功

```php
public function has(string $key): bool
```
- **参数：** `$key` - 缓存键名
- **返回：** `bool` - 缓存是否存在
- **异常：** `\Psr\SimpleCache\InvalidArgumentException` - 键名无效时抛出

### 批量操作

```php
public function getMultiple(iterable $keys, mixed $default = null): iterable
```
- **参数：**
  - `$keys` - 键名数组或可迭代对象
  - `$default` - 默认值（可选）
- **返回：** `iterable` - 键值对数组
- **异常：** `\Psr\SimpleCache\InvalidArgumentException` - 键名无效时抛出

```php
public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
```
- **参数：**
  - `$values` - 键值对数组或可迭代对象
  - `$ttl` - 过期时间（可选）
- **返回：** `bool` - 是否设置成功
- **异常：** `\Psr\SimpleCache\InvalidArgumentException` - 键名无效时抛出

```php
public function deleteMultiple(iterable $keys): bool
```
- **参数：** `$keys` - 键名数组或可迭代对象
- **返回：** `bool` - 是否删除成功
- **异常：** `\Psr\SimpleCache\InvalidArgumentException` - 键名无效时抛出

## 缓存配置结构

配置文件：`config/cache.toml`

### 基础配置

```toml
[default]
driver = "file"
path = "data/cache"
```

### 支持的驱动类型

| 驱动类型 | 说明 | 必需配置 |
|----------|------|----------|
| `file` | 文件缓存 | `path` - 缓存文件目录 |
| `redis` | Redis缓存 | `connection` - Redis连接名称 |
| `memcached` | Memcached缓存 | `servers` - 服务器列表 |
| `array` | 内存缓存 | 无额外配置 |

### Redis 驱动配置

```toml
[redis]
driver = "redis"
connection = "default"
prefix = "cache:"
```

### Memcached 驱动配置

```toml
[memcached]
driver = "memcached"
servers = [
    { host = "127.0.0.1", port = 11211, weight = 100 }
]
prefix = "cache:"
```

### 多缓存配置

```toml
[file]
driver = "file"
path = "data/cache/file"

[redis]
driver = "redis"
connection = "cache"

[session]
driver = "redis"
connection = "session"
prefix = "sess:"
```

## TTL 时间格式

### 支持的时间格式

| 格式 | 类型 | 说明 |
|------|------|------|
| `null` | `null` | 永不过期 |
| `60` | `int` | 60秒后过期 |
| `new \DateInterval('PT1H')` | `\DateInterval` | 1小时后过期 |

### DateInterval 格式说明

| 格式 | 说明 |
|------|------|
| `PT30S` | 30秒 |
| `PT5M` | 5分钟 |
| `PT1H` | 1小时 |
| `P1D` | 1天 |
| `P1W` | 1周 |
| `P1M` | 1个月 |
| `P1Y` | 1年 |

## 异常类型

| 异常类 | 说明 |
|--------|------|
| `\Psr\SimpleCache\InvalidArgumentException` | 无效参数异常（键名格式错误等） |
| `\Psr\SimpleCache\CacheException` | 缓存操作异常（连接失败、权限错误等） |

## 键名规范

### 有效键名规则

- 长度：1-64个字符
- 字符：`A-Z`、`a-z`、`0-9`、`_`、`.`、`-`
- 不能包含：`{}`、`()`、`/`、`\`、`@`、`:`、空格等特殊字符

### 推荐键名格式

| 格式 | 说明 |
|------|------|
| `user.123` | 用户信息 |
| `post.list.page_1` | 文章列表分页 |
| `config.app.settings` | 应用配置 |
| `cache.query.users_active` | 查询结果缓存 |

## 缓存标签支持

部分驱动支持缓存标签功能（需要扩展实现）：

```php
public function tags(array $tags): static
```
- **参数：** `$tags` - 标签数组
- **返回：** `static` - 带标签的缓存实例

```php
public function flush(): bool
```
- **返回：** `bool` - 是否清空标签缓存成功