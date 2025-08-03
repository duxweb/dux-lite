# 环境配置

DuxLite 环境配置和生产部署的完整指南。

## 环境要求

### 系统要求

```bash
# 最低要求
PHP >= 8.2
Memory >= 128M
Disk >= 1GB

# 推荐配置
PHP >= 8.3
Memory >= 512M
Disk >= 10GB
```

### PHP 扩展

```bash
# 必需扩展
php-pdo
php-mbstring
php-openssl
php-zlib
php-curl
php-xml

# 推荐扩展（性能优化）
php-opcache
php-redis
php-swoole
```

### 外部依赖

```bash
# 数据库（选择之一）
MySQL >= 8.0
PostgreSQL >= 13
SQLite >= 3.35

# 缓存（可选）
Redis >= 6.0

# 队列（可选）
RabbitMQ >= 3.8
```

## 环境配置管理

DuxLite 推荐使用 TOML 配置文件管理不同环境的配置，实现配置的版本控制和团队协作。

### 配置文件结构

```bash
config/
├── use.toml              # 生产环境应用配置
├── use.dev.toml          # 开发环境应用配置
├── database.toml         # 生产环境数据库配置
├── database.dev.toml     # 开发环境数据库配置
├── cache.toml            # 缓存配置
└── server.toml           # 服务器配置
```

### 开发环境配置示例

**应用配置 (config/use.dev.toml)**

```toml
[app]
name = "我的应用-开发版"
debug = true
secret = "dev-secret-key-32-characters"
timezone = "Asia/Shanghai"
lang = "zh-CN"
domain = "http://localhost:8000"

[cache]
type = "file"
prefix = "dev_cache_"
defaultLifetime = 3600
```

**数据库配置 (config/database.dev.toml)**

```toml
[db.drivers.default]
driver = "mysql"
host = "localhost"
port = 3306
database = "myapp_dev"
username = "root"
password = ""
charset = "utf8mb4"

[redis]
host = "localhost"
port = 6379
password = ""
database = 1
```

### 生产环境配置示例

**应用配置 (config/use.toml)**

```toml
[app]
name = "我的应用"
debug = false
secret = "production-secret-key-32-chars"
timezone = "Asia/Shanghai"
lang = "zh-CN"
domain = "https://myapp.com"

[cache]
type = "redis"
prefix = "prod_cache_"
defaultLifetime = 7200
```

**数据库配置 (config/database.toml)**

```toml
[db.drivers.default]
driver = "mysql"
host = "db.example.com"
port = 3306
database = "myapp_prod"
username = "app_user"
password = "secure-password-here"
charset = "utf8mb4"

[redis]
host = "redis.example.com"
port = 6379
password = "redis-password"
database = 0
```

### 环境变量（备用方案）

对于敏感信息或需要动态配置的场景，可以使用环境变量：

```bash
# .env（仅在特殊情况下使用）
DB_PASSWORD=secret-password
REDIS_PASSWORD=redis-secret
MAIL_PASSWORD=mail-secret
```

在 TOML 中引用环境变量：

```toml
[db.drivers.default]
password = "%env(DB_PASSWORD)%"

[redis]
password = "%env(REDIS_PASSWORD)%"
```

## 配置最佳实践

### 文件权限

```bash
# 设置基本权限
chmod -R 755 .
chmod -R 775 data/
chmod 600 config/*.toml
```

DuxLite 环境配置简单高效，通过 TOML 配置文件实现不同环境的灵活管理。详细的服务器配置和部署说明请参考 [部署指南](./deployment.md)。