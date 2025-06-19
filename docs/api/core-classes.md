# 核心类

DuxLite 框架的核心类定义和 API 规格说明。

## App 类

**命名空间：** `Core\App`

### 静态属性

| 属性 | 类型 | 说明 |
|------|------|------|
| `$basePath` | `string` | 应用根目录路径 |
| `$configPath` | `string` | 配置文件目录路径 |
| `$dataPath` | `string` | 数据存储目录路径 |
| `$debug` | `bool` | 调试模式开关 |

### 静态方法

#### 应用生命周期

```php
public static function create(string $basePath = ''): void
```
- **参数：** `$basePath` - 应用根目录路径（可选）
- **返回：** `void`
- **说明：** 创建应用实例

```php
public static function init(): void
```
- **参数：** 无
- **返回：** `void`
- **说明：** 初始化应用

```php
public static function run(): void
```
- **参数：** 无
- **返回：** `void`
- **说明：** 运行命令行应用

```php
public static function runWeb(): void
```
- **参数：** 无
- **返回：** `void`
- **说明：** 运行 Web 应用

#### 服务访问

```php
public static function di(): \DI\Container
```
- **返回：** `\DI\Container` - 依赖注入容器

```php
public static function web(): \Slim\App
```
- **返回：** `\Slim\App` - Slim Web 应用实例

```php
public static function config(string $name): \Noodlehaus\Config
```
- **参数：** `$name` - 配置名称
- **返回：** `\Noodlehaus\Config` - 配置对象

```php
public static function db(?string $name = null): \Illuminate\Database\Connection
```
- **参数：** `$name` - 数据库连接名称（可选）
- **返回：** `\Illuminate\Database\Connection` - 数据库连接

```php
public static function dbMigrate(): \Core\Database\Migrate
```
- **返回：** `\Core\Database\Migrate` - 数据库迁移实例

```php
public static function cache(?string $name = null): \Psr\SimpleCache\CacheInterface
```
- **参数：** `$name` - 缓存驱动名称（可选）
- **返回：** `\Psr\SimpleCache\CacheInterface` - 缓存接口

```php
public static function redis(?string $name = null): mixed
```
- **参数：** `$name` - Redis 连接名称（可选）
- **返回：** `mixed` - Redis 连接实例

```php
public static function lock(): \Core\Lock\Lock
```
- **返回：** `\Core\Lock\Lock` - 原子锁实例

```php
public static function log(string $name = 'default'): \Psr\Log\LoggerInterface
```
- **参数：** `$name` - 日志通道名称
- **返回：** `\Psr\Log\LoggerInterface` - 日志记录器

```php
public static function view(): \Latte\Engine
```
- **返回：** `\Latte\Engine` - 模板引擎

```php
public static function trans(): \Symfony\Contracts\Translation\TranslatorInterface
```
- **返回：** `\Symfony\Contracts\Translation\TranslatorInterface` - 翻译器

#### 注册器访问

```php
public static function route(): \Core\Route\Register
```
- **返回：** `\Core\Route\Register` - 路由注册器

```php
public static function resource(): \Core\Resources\Register
```
- **返回：** `\Core\Resources\Register` - 资源注册器

```php
public static function permission(): \Core\Permission\Register
```
- **返回：** `\Core\Permission\Register` - 权限注册器

```php
public static function event(): \Core\Event\Event
```
- **返回：** `\Core\Event\Event` - 事件管理器

```php
public static function scheduler(): \Core\Scheduler\Register
```
- **返回：** `\Core\Scheduler\Register` - 任务调度器

```php
public static function queue(): \Core\Queue\Queue
```
- **返回：** `\Core\Queue\Queue` - 队列管理器

```php
public static function storage(?string $name = null): \Core\Storage\Contracts\StorageInterface
```
- **参数：** `$name` - 存储驱动名称（可选）
- **返回：** `\Core\Storage\Contracts\StorageInterface` - 存储接口

```php
public static function attributes(): array
```
- **返回：** `array` - 注解属性数组

## Bootstrap 类

**命名空间：** `Core\Bootstrap`

### 属性

| 属性 | 类型 | 说明 |
|------|------|------|
| `$web` | `\Slim\App` | Slim Web 应用实例 |
| `$view` | `\Latte\Engine` | Latte 模板引擎实例 |
| `$command` | `\Symfony\Component\Console\Application` | 命令行应用实例 |

### 方法

```php
public function registerFunc(): void
```
- **参数：** 无
- **返回：** `void`
- **说明：** 注册全局辅助函数

```php
public function registerConfig(): void
```
- **参数：** 无
- **返回：** `void`
- **说明：** 注册配置加载器

```php
public function registerWeb(): void
```
- **参数：** 无
- **返回：** `void`
- **说明：** 注册 Web 应用组件

```php
public function loadApp(): void
```
- **参数：** 无
- **返回：** `void`
- **说明：** 加载应用模块

```php
public function loadRoute(): void
```
- **参数：** 无
- **返回：** `void`
- **说明：** 加载路由配置

```php
public function loadCommand(): void
```
- **参数：** 无
- **返回：** `void`
- **说明：** 加载命令行工具

```php
public function run(): void
```
- **参数：** 无
- **返回：** `void`
- **说明：** 运行命令行应用

```php
public function runWeb(): void
```
- **参数：** 无
- **返回：** `void`
- **说明：** 运行 Web 应用

## TomlLoader 类

**命名空间：** `Core\Config\TomlLoader`

### 方法

```php
public function __construct()
```
- **说明：** 构造函数

```php
public function load(string $path): array
```
- **参数：** `$path` - TOML 文件路径
- **返回：** `array` - 解析后的配置数组
- **异常：** `\Exception` - 文件不存在或解析失败时抛出