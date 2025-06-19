# Lock 原子锁

DuxLite 原子锁系统的核心类定义和 API 规格说明。

## Lock 类

**命名空间：** `Core\Lock\Lock`

### 方法

```php
public static function init(string $type = 'semaphore'): \Symfony\Component\Lock\LockFactory
```
- **参数：** `$type` - 锁类型（semaphore、flock、redis）
- **返回：** `\Symfony\Component\Lock\LockFactory` - 锁工厂实例
- **异常：** `Exception` - 锁类型不存在时抛出

## LockFactory 类（Symfony组件）

**命名空间：** `\Symfony\Component\Lock\LockFactory`

### 方法

```php
public function createLock(string $resource, ?float $ttl = null, bool $autoRelease = true): \Symfony\Component\Lock\LockInterface
```
- **参数：**
  - `$resource` - 锁资源标识符
  - `$ttl` - 锁的生存时间（秒，可选）
  - `$autoRelease` - 是否自动释放（可选）
- **返回：** `\Symfony\Component\Lock\LockInterface` - 锁接口实例

## LockInterface 类（Symfony组件）

**命名空间：** `\Symfony\Component\Lock\LockInterface`

### 方法

```php
public function acquire(bool $blocking = false): bool
```
- **参数：** `$blocking` - 是否阻塞等待（可选）
- **返回：** `bool` - 是否成功获取锁

```php
public function refresh(?float $ttl = null): void
```
- **参数：** `$ttl` - 新的生存时间（秒，可选）
- **返回：** `void`
- **说明：** 刷新锁的生存时间

```php
public function isAcquired(): bool
```
- **返回：** `bool` - 锁是否已被获取

```php
public function release(): void
```
- **返回：** `void`
- **说明：** 释放锁

```php
public function isExpired(): bool
```
- **返回：** `bool` - 锁是否已过期

```php
public function getRemainingLifetime(): ?float
```
- **返回：** `?float` - 剩余生存时间（秒）

## 支持的锁类型

| 类型 | 说明 | 适用场景 |
|------|------|----------|
| `semaphore` | 信号量锁（默认） | 单机环境，进程间同步 |
| `flock` | 文件锁 | 单机环境，文件操作同步 |
| `redis` | Redis锁 | 分布式环境，多服务器同步 |