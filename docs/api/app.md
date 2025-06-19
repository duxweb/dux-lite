# App 类

DuxLite 框架核心入口类的 API 规格说明。

## App 类

**命名空间：** `Core\App`

**说明：** 框架核心入口类，提供应用生命周期管理和各种服务的访问接口。

### 静态属性

```php
public static string $basePath
```
- **说明：** 应用根目录路径

```php
public static string $configPath
```
- **说明：** 配置文件目录路径

```php
public static string $dataPath
```
- **说明：** 数据存储目录路径

```php
public static string $publicPath
```
- **说明：** 公共资源目录路径

```php
public static string $appPath
```
- **说明：** 应用代码目录路径

```php
public static Bootstrap $bootstrap
```
- **说明：** 引导程序实例

```php
public static Container $di
```
- **说明：** 依赖注入容器实例

```php
public static array $config
```
- **说明：** 配置数组

```php
public static string $version = '0.0.1'
```
- **说明：** 框架版本号

```php
public static array $registerApp = []
```
- **说明：** 注册的应用模块数组

```php
public static bool $debug = true
```
- **说明：** 调试模式开关

```php
public static string $logo = ''
```
- **说明：** 应用Logo文本

```php
public static string $lang = ''
```
- **说明：** 当前语言代码

```php
public static string $timezone = 'UTC'
```
- **说明：** 时区设置

```php
public static ?string $host = null
```
- **说明：** 主机地址

### 应用生命周期方法

```php
public static function create(string $basePath, string $lang = 'en-US', string $timezone = 'UTC', ?string $host = "0.0.0.0", ?bool $debug = true, ?string $logo = ''): void
```
- **参数：**
  - `$basePath` - 应用根目录路径
  - `$lang` - 默认语言代码（可选）
  - `$timezone` - 时区标识符（可选）
  - `$host` - 主机地址（可选）
  - `$debug` - 是否开启调试模式（可选）
  - `$logo` - 应用Logo文本（可选）
- **返回：** `void`
- **说明：** 创建应用实例并初始化基础配置

```php
public static function init(): void
```
- **返回：** `void`
- **说明：** 初始化应用基础服务

```php
public static function run(): void
```
- **返回：** `void`
- **说明：** 运行完整应用（Web + 命令行）

```php
public static function runWeb(): void
```
- **返回：** `void`
- **说明：** 仅运行Web应用

### 核心服务访问方法

```php
public static function di(): Container
```
- **返回：** `DI\Container` - 依赖注入容器实例
- **说明：** 获取依赖注入容器

```php
public static function web(): \Slim\App
```
- **返回：** `\Slim\App` - Slim应用实例
- **说明：** 获取Slim Web应用实例

```php
public static function config(string $name): Config
```
- **参数：** `$name` - 配置文件名（不含扩展名）
- **返回：** `Noodlehaus\Config` - 配置对象实例
- **说明：** 获取配置对象

### 数据库服务方法

```php
public static function db(): Manager
```
- **返回：** `Illuminate\Database\Capsule\Manager` - 数据库管理器实例
- **说明：** 获取数据库管理器

```php
public static function dbMigrate(): Migrate
```
- **返回：** `Core\Database\Migrate` - 迁移管理器实例
- **说明：** 获取数据库迁移管理器

### 缓存服务方法

```php
public static function cache(?string $type = null): Psr16Cache
```
- **参数：** `$type` - 缓存类型（file、redis等），为空时使用配置默认值（可选）
- **返回：** `Symfony\Component\Cache\Psr16Cache` - 缓存实例
- **说明：** 获取缓存实例

### Redis服务方法

```php
public static function redis(string $name = "default", int $database = 0): \Predis\ClientInterface|\Redis
```
- **参数：**
  - `$name` - 连接配置名称（可选）
  - `$database` - 数据库编号（可选）
- **返回：** `\Predis\ClientInterface|\Redis` - Redis客户端实例
- **说明：** 获取Redis连接

### 原子锁服务方法

```php
public static function lock(?string $type = null): LockFactory
```
- **参数：** `$type` - 锁类型（semaphore、flock、redis），为空时使用配置默认值（可选）
- **返回：** `Symfony\Component\Lock\LockFactory` - 锁工厂实例
- **说明：** 获取原子锁工厂

### 日志服务方法

```php
public static function log(string $app = "app", Level $level = Level::Debug): Logger
```
- **参数：**
  - `$app` - 应用名称，对应日志文件名（可选）
  - `$level` - 最低日志级别（可选）
- **返回：** `Monolog\Logger` - 日志记录器实例
- **说明：** 获取日志记录器

### 模板服务方法

```php
public static function view(string $name): Engine
```
- **参数：** `$name` - 模板配置名称
- **返回：** `Latte\Engine` - 模板引擎实例
- **说明：** 获取模板引擎

### 翻译服务方法

```php
public static function trans(): Translator
```
- **返回：** `Symfony\Component\Translation\Translator` - 翻译器实例
- **说明：** 获取翻译器

### 队列服务方法

public static function queue(string $type = ""): Queue
```
- **参数：** `$type` - 队列类型（可选）
- **返回：** `Core\Queue\Queue` - 队列实例
- **说明：** 获取队列管理器

### 存储服务方法

```php
public static function storage(string $type = ""): StorageInterface
```
- **参数：** `$type` - 存储类型（可选）
- **返回：** `Core\Storage\Contracts\StorageInterface` - 存储接口实例
- **说明：** 获取存储管理器

## 注册器访问方法

### route()

```php
public static function route(): Route\Register
```
- **返回：** `Core\Route\Register` - 路由注册器实例
- **说明：** 获取路由注册器

### resource()

```php
public static function resource(): Resources\Register
```
- **返回：** `Core\Resources\Register` - 资源注册器实例
- **说明：** 获取资源注册器

### permission()

```php
public static function permission(): Permission\Register
```
- **返回：** `Core\Permission\Register` - 权限注册器实例
- **说明：** 获取权限注册器

### event()

```php
public static function event(): Event
```
- **返回：** `Core\Event\Event` - 事件管理器实例
- **说明：** 获取事件管理器

### scheduler()

```php
public static function scheduler(): Scheduler
```
- **返回：** `Core\Scheduler\Scheduler` - 计划任务调度器实例
- **说明：** 获取计划任务调度器

### attributes()

```php
public static function attributes(): array
```
- **返回：** `array` - 注解属性数组
- **说明：** 获取注解属性数组

## 工具方法

### loadTrans()

```php
public static function loadTrans(string $dirPath, Translator $trans): void
```
- **参数：**
  - `$dirPath` - 翻译文件目录路径
  - `$trans` - 翻译器实例
- **返回：** `void`
- **说明：** 加载翻译文件

### geo()

```php
public static function geo(): XdbSearcher|null
```
- **返回：** `XdbSearcher|null` - 地理位置搜索器实例或null
- **说明：** 获取地理位置服务

### banner()

```php
public static function banner(array $data = [], array $extra = []): void
```
- **参数：**
  - `$data` - 横幅数据数组（可选）
  - `$extra` - 额外数据数组（可选）
- **返回：** `void`
- **说明：** 显示应用横幅