# 配置文件

DuxLite 使用 TOML 格式的配置文件，提供了强大且灵活的配置管理系统。本章详细介绍各个配置文件的用途和配置选项。

## 配置文件概述

### 文件位置
所有配置文件位于项目根目录的 `config/` 文件夹中。

### 加载优先级
框架按以下优先级加载配置文件：
1. `{name}.dev.toml` - 开发环境配置（优先级最高）
2. `{name}.toml` - 生产环境配置
3. 空配置 - 如果文件不存在则使用默认值

### 环境变量支持

::: tip 重要说明
当前版本的 DuxLite 只加载 `.env` 文件，但没有集成到配置系统中。
:::

框架在启动时会加载 `.env` 环境变量文件：

```bash
# .env 文件位于项目根目录
APP_NAME=DuxLite应用
APP_DEBUG=true
APP_SECRET=your-32-char-secret-key-here

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=duxlite
DB_USERNAME=root
DB_PASSWORD=your_password

REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=
```

**环境变量加载规则：**
- 使用 `Dotenv::createImmutable()` 方法加载
- 调用 `safeLoad()` 方法安全加载，不会覆盖已存在的环境变量
- 在应用初始化时自动加载到 `$_ENV` 和 `$_SERVER` 中

::: warning 当前限制
- ❌ TOML 配置文件**不支持**环境变量插值（如 `${DB_HOST}`）
- ❌ 框架**没有提供** `env()` 助手函数
- ❌ 配置系统**完全基于** TOML 文件，不会自动读取环境变量
:::

**使用环境变量的正确方式：**

```php
// ✅ 在 PHP 代码中手动读取环境变量
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$appSecret = $_ENV['APP_SECRET'] ?? 'default-secret';

// ❌ 在 TOML 配置文件中无法使用（不支持）
// [app]
// secret = "${APP_SECRET}"  # 这样写不会生效
```

如果需要使用环境变量，建议在模块的 `App.php` 中读取并动态配置：

```php
// app/Web/App.php
public function init(Bootstrap $bootstrap): void
{
    // 从环境变量读取配置
    $dbHost = $_ENV['DB_HOST'] ?? 'localhost';

    // 然后在代码中使用这些值进行配置
    // 注意：无法直接修改已加载的 TOML 配置
}
```

## 应用配置 (`use.toml`)

应用的基础配置文件，包含核心设置。

```toml
[app]
# 应用名称
name = "我的 DuxLite 应用"

# 版权信息
copyright = "2024 Your Company"

# 语言设置（影响多语言和本地化）
lang = "zh-CN"

# 时区设置
timezone = "Asia/Shanghai"

# 调试模式（生产环境应设为 false）
debug = true

# 应用密钥（用于加密，32位字符）
secret = "your-32-char-secret-key-here"

# 应用域名
domain = "http://localhost:8000"

[cache]
# 缓存类型：file（文件缓存）或 redis（Redis缓存）
type = "file"

# 缓存前缀（避免冲突）
prefix = "duxlite_"

# 默认缓存生存时间（秒，0表示永不过期）
defaultLifetime = 3600

[lock]
# 锁类型：semaphore, flock, redis 等
type = "semaphore"
```

## 模块注册配置 (`app.toml`)

用于注册应用模块，这是框架的核心配置之一。

```toml
# 模块注册数组
registers = [
    "App\\Web\\App",
    "App\\Admin\\App",
    "App\\Api\\App",
    "App\\Common\\App"
]
```

**注册规则：**
- 每个模块必须有对应的 `App.php` 类
- 类必须继承 `Core\App\AppExtend`
- 类需要实现 `init()` 方法进行模块初始化

## 数据库配置 (`database.toml`)

数据库连接和相关服务配置。

```toml
# 数据库连接配置
[db.drivers.default]
driver = "mysql"
host = "localhost"
port = 3306
database = "duxlite"
username = "root"
password = "your_password"
charset = "utf8mb4"
collation = "utf8mb4_unicode_ci"
prefix = ""

# 多数据库支持
[db.drivers.business]
driver = "mysql"
host = "localhost"
port = 3306
database = "business_db"
username = "business_user"
password = "business_password"
charset = "utf8mb4"
prefix = "biz_"

# Redis 配置（支持多个连接）
[redis.drivers.default]
host = "localhost"
port = 6379
password = ""
database = 0
timeout = 2.5
# optPrefix 是可选的键前缀
optPrefix = ""

[redis.drivers.cache]
host = "localhost"
port = 6379
password = ""
database = 1
timeout = 2.5
optPrefix = "cache_"

# 队列专用 Redis（队列配置实际上使用 database.toml 中的 Redis 配置）
[redis.drivers.queue]
host = "localhost"
port = 6379
password = ""
database = 2
timeout = 2.5
optPrefix = "queue_"

# AMQP 配置（RabbitMQ）
[amqp.drivers.default]
host = "localhost"
port = 5672
vhost = "/"
username = "guest"
password = "guest"
persisted = false
prefix = ""
```

## 队列配置 (`queue.toml`)

::: info 队列配置说明
根据代码分析，队列配置主要依赖于 `database.toml` 中的 Redis/AMQP 配置，队列服务通过以下逻辑工作：
:::

```toml
# 队列服务类型：redis 或 amqp
type = "redis"

# 队列驱动器名称（对应 database.toml 中的配置）
driver = "default"
```

**队列工作原理：**
- `App::queue()` 方法从 `queue.toml` 读取 `type`（默认 "redis"）
- 然后从 `database.toml` 读取对应的连接配置
- 支持 Redis 和 AMQP（RabbitMQ）两种队列后端

## 存储配置 (`storage.toml`)

文件存储服务配置。

```toml
# 默认存储类型
type = "local"

# 本地存储驱动
[drivers.local]
type = "local"
# 存储根目录
root = "data/uploads"
# 公共访问域名
domain = "http://localhost:8000"
# URL 路径前缀
path = "uploads"

# S3 兼容存储驱动
[drivers.s3]
type = "s3"
# S3 存储桶
bucket = "my-bucket"
# 访问域名（可选，用于自定义域名）
domain = "https://cdn.example.com"
# API 端点
endpoint = "s3.amazonaws.com"
# 区域
region = "us-east-1"
# 是否使用 SSL
ssl = true
# API 版本
version = "latest"
# 访问密钥
access_key = "your-access-key"
# 密钥
secret_key = "your-secret-key"
# 是否不可变存储
immutable = false
```

## 命令行配置 (`command.toml`)

自定义命令注册配置。

```toml
# 注册自定义命令类
registers = [
    "App\\Commands\\CustomCommand",
    "App\\Commands\\DataImportCommand"
]
```

## 配置使用方法

### 读取配置

```php
// 读取应用配置
$config = App::config('use');
$appName = $config->get('app.name');
$debug = $config->get('app.debug', false); // 带默认值

// 读取数据库配置
$dbConfig = App::config('database');
$host = $dbConfig->get('db.drivers.default.host');

// 读取嵌套配置
$cacheConfig = App::config('use')->get('cache', []);
$cacheType = $cacheConfig['type'] ?? 'file';
```

### 环境变量访问

```php
// ✅ 在 PHP 代码中访问环境变量
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$appSecret = $_ENV['APP_SECRET'] ?? 'default-secret';

// ✅ 也可以使用 getenv() 函数
$dbPassword = getenv('DB_PASSWORD') ?: 'default-password';

// ❌ 无法在 TOML 配置文件中直接使用环境变量
// 配置系统不支持环境变量插值
```

### 服务配置获取

```php
// 缓存服务配置
$cache = App::cache(); // 使用默认配置
$cache = App::cache('redis'); // 指定类型

// 数据库服务配置
$db = App::db(); // 使用默认连接

// Redis 服务配置
$redis = App::redis(); // 默认连接
$redis = App::redis('cache'); // 指定连接名

// 队列服务配置
$queue = App::queue(); // 使用默认配置
$queue = App::queue('redis'); // 指定类型
```

## 最佳实践

### 1. 环境配置分离

```bash
# 开发环境
config/
├── use.dev.toml          # 开发配置
├── database.dev.toml     # 开发数据库
└── storage.dev.toml      # 开发存储

# 生产环境
config/
├── use.toml              # 生产配置
├── database.toml         # 生产数据库
└── storage.toml          # 生产存储
```

### 2. 安全配置

```toml
# config/use.toml - 生产环境
[app]
debug = false
secret = "randomly-generated-32-char-key"

# 敏感信息使用环境变量
# 在 .env 文件中设置，不要提交到版本控制
```

### 3. 缓存优化

```toml
# 生产环境推荐使用 Redis 缓存
[cache]
type = "redis"
prefix = "prod_app_"
defaultLifetime = 86400
```

### 4. .gitignore 设置

```shell
# 开发配置文件
config/*.dev.toml

# 环境变量文件
.env
.env.local

# 数据目录
/data/

# 缓存目录
/cache/
```

## 故障排除

### 配置文件语法错误

```bash
# 检查 TOML 语法
php -r "
$config = \Noodlehaus\Config::load('config/use.toml', new \Core\Config\TomlLoader());
var_dump($config->all());
"
```

### 配置不生效

1. **检查文件优先级** - `.dev.toml` 优先于 `.toml`
2. **验证文件路径** - 确保文件在 `config/` 目录中
3. **检查文件权限** - 确保 PHP 可以读取配置文件
4. **验证语法** - TOML 语法错误会导致解析失败

### 环境变量问题

```php
// 检查环境变量是否加载
var_dump($_ENV); // 查看所有环境变量

// 检查 .env 文件是否存在
if (!file_exists('.env')) {
    echo ".env 文件不存在\n";
}
```

### 服务连接失败

```php
// 测试数据库连接
try {
    $db = App::db();
    echo "数据库连接成功\n";
} catch (Exception $e) {
    echo "数据库连接失败: " . $e->getMessage() . "\n";
}

// 测试 Redis 连接
try {
    $redis = App::redis();
    $redis->ping();
    echo "Redis 连接成功\n";
} catch (Exception $e) {
    echo "Redis 连接失败: " . $e->getMessage() . "\n";
}
```