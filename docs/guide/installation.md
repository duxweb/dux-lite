# 安装配置

本指南将详细介绍如何安装和配置 DuxLite v2 框架。

## 环境要求

### 系统要求

- **PHP** >= 8.2
- **Composer** >= 2.0
- **Web 服务器** Apache/Nginx 或 PHP 内置服务器

### 必需的 PHP 扩展

```bash
ext-pdo          # 数据库操作
ext-zlib         # 压缩支持
ext-json         # JSON 支持
ext-mbstring     # 多字节字符串
ext-openssl      # 加密支持
```

### 可选的 PHP 扩展

```bash
ext-redis        # Redis 缓存支持
ext-curl         # HTTP 客户端
ext-gd           # 图像处理
ext-fileinfo     # 文件信息
```

## 安装方式

### 方式一：快速开始模板（推荐）

使用官方项目模板，快速创建包含完整项目结构和示例代码的新应用：

```bash
# 使用项目模板创建新项目
composer create-project duxweb/dux-lite-starter my-app

# 进入项目目录
cd my-app

# 设置目录权限
chmod -R 755 data/
chmod +x dux

```

项目模板包含：
- ✅ 完整的项目目录结构
- ✅ 预配置的入口文件和命令行工具
- ✅ 示例控制器和路由
- ✅ 完整的配置文件模板
- ✅ Web 服务器配置文件
- ✅ 开箱即用的开发环境

### 方式二：Composer 安装

#### 新项目安装

```bash
# 创建项目目录
mkdir my-app && cd my-app

# 安装 DuxLite
composer require duxweb/dux-lite:^2.0

# 初始化项目结构
mkdir -p public app config data/{logs,cache}
```

#### 现有项目添加

```bash
# 在现有项目中添加 DuxLite
composer require duxweb/dux-lite:^2.0
```

### 方式三：下载源码

```bash
# 克隆或下载源码
git clone https://github.com/duxweb/dux-lite.git my-app
cd my-app

# 安装依赖
composer install
```

## 目录结构

创建标准的项目目录结构：

```
my-app/
├── public/                    # Web 根目录
│   ├── index.php             # Web 入口文件
│   └── .htaccess             # Apache 重写规则
├── app/                      # 应用代码
│   └── Web/
│       ├── App.php           # 应用注册类
│       └── Controllers/      # 控制器目录
├── config/                   # 配置文件
│   ├── use.toml             # 应用配置
│   ├── app.toml             # 模块注册
│   ├── database.toml        # 数据库配置
│   └── command.toml         # 命令行配置
├── data/                  # 存储目录
│   ├── logs/                # 日志文件
│   ├── cache/               # 缓存文件
│   └── uploads/             # 上传文件
├── vendor/                   # Composer 依赖
├── dux                      # 命令行工具
└── composer.json            # 项目依赖
```

## 目录权限

设置正确的目录权限：

```bash
# 设置存储目录可写权限
chmod -R 755 data/
chmod -R 755 public/

# 设置命令行工具执行权限
chmod +x dux

# 如果使用 Apache，确保 .htaccess 可读
chmod 644 public/.htaccess
```

## 配置文件

### 应用配置 (`config/use.toml`)

```toml
[app]
# 应用名称
name = "我的 DuxLite 应用"

# 版权信息
copyright = "2024 Your Company"

# 语言设置
lang = "zh-CN"

# 时区设置
timezone = "Asia/Shanghai"

# 调试模式
debug = true

# 应用密钥（用于加密）
secret = "your-32-char-secret-key-here"

# 应用域名
domain = "http://localhost:8000"
```

### 模块注册 (`config/app.toml`)

```toml
# 注册应用模块
registers = [
    "App\\Web\\App",
    "App\\Admin\\App",
    "App\\Api\\App"
]
```

### 数据库配置 (`config/database.toml`)

```toml
# 默认数据库连接
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

# 业务数据库（多数据库示例）
[db.drivers.business]
driver = "mysql"
host = "localhost"
port = 3306
database = "business_db"
username = "business_user"
password = "business_password"
charset = "utf8mb4"
prefix = "biz_"

# Redis 缓存配置
[redis.drivers.default]
host = "localhost"
port = 6379
password = ""
database = 0
timeout = 2.5

[redis.drivers.cache]
host = "localhost"
port = 6379
password = ""
database = 1
```

### 命令行配置 (`config/command.toml`)

```toml
# 注册自定义命令
registers = [
    "App\\Commands\\CustomCommand"
]
```

## Web 服务器配置

### Apache 配置

创建 `public/.htaccess` 文件：

```apache
RewriteEngine On

# 处理前置斜杠
RewriteCond %{REQUEST_URI} ::$
RewriteRule ^(.*) $1/ [R=301,L]

# 处理尾随斜杠
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} /$
RewriteRule ^(.*)/$ $1 [R=301,L]

# 路由到前端控制器
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php [QSA,L]
```

### Nginx 配置

在 Nginx 虚拟主机配置中添加：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/your-app/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

## 环境配置

### 开发环境配置

框架自动优先加载 `.dev.toml` 后缀的开发配置文件。开发配置文件不应提交到版本控制系统。

创建开发配置文件：

```toml
# config/use.dev.toml （不要提交到代码仓库）
[app]
debug = true
domain = "http://localhost:8000"
secret = "dev-secret-key-32-characters"

# config/database.dev.toml （不要提交到代码仓库）
[db.drivers.default]
host = "localhost"
database = "duxlite_dev"
username = "root"
password = ""
```

### 生产环境配置

生产环境使用标准的 `.toml` 配置文件：

```toml
# config/use.toml （提交到代码仓库）
[app]
debug = false
domain = "https://your-domain.com"
secret = "production-secret-key-32-chars"

# config/database.toml （提交到代码仓库，敏感信息通过环境变量）
[db.drivers.default]
host = "prod-db-server"
database = "duxlite_prod"
username = "prod_user"
password = "secure-password"
```

### 配置加载优先级

1. 优先加载 `config/*.dev.toml` （开发环境）
2. 如果 `.dev.toml` 不存在，则加载 `config/*.toml` （生产环境）
3. 如果配置文件都不存在，使用空配置

### .gitignore 配置

在项目根目录创建 `.gitignore` 文件，确保开发配置不被提交：

```shell
# 数据目录
/data/

# 开发配置文件
config/*.dev.toml

# Vendor 目录
/vendor/

# 环境文件
.env
.env.local

# IDE 文件
.vscode/
.idea/
*.swp
*.swo

# 操作系统文件
.DS_Store
Thumbs.db
```

## 验证安装

启动内置服务器：

```bash
php -S localhost:8000 -t public
```

访问 `http://localhost:8000` 确认安装成功。

## 性能优化

### 1. OPcache 配置

在 `php.ini` 中启用 OPcache：

```ini
; 启用 OPcache
opcache.enable=1
opcache.enable_cli=1

; 内存设置
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000

; 验证设置
opcache.validate_timestamps=0  ; 生产环境设为 0
opcache.revalidate_freq=2
```

### 2. 应用缓存

配置应用级缓存：

```toml
# config/use.toml
[cache]
type = "redis"              # 缓存类型：redis 或 file
prefix = "duxlite_cache"    # 缓存前缀
defaultLifetime = 3600      # 默认缓存时间（秒）
```

## 常见问题

### 安装问题

**Q: Composer 安装失败？**

```bash
# 清除 Composer 缓存
composer clear-cache

# 使用国内镜像
composer config repo.packagist composer https://mirrors.aliyun.com/composer/

# 重新安装
composer install --no-dev --optimize-autoloader
```

**Q: PHP 扩展缺失？**

```bash
# Ubuntu/Debian
sudo apt-get install php8.2-pdo php8.2-mbstring php8.2-openssl

# CentOS/RHEL
sudo yum install php82-pdo php82-mbstring php82-openssl

# macOS (Homebrew)
brew install php@8.2
```

### 配置问题

**Q: 配置文件不生效？**

1. 检查文件路径是否正确
2. 验证 TOML 语法是否正确
3. 确认文件编码为 UTF-8

**Q: 数据库连接失败？**

1. 检查数据库服务是否启动
2. 验证连接参数是否正确
3. 确认用户权限是否足够

### 权限问题

**Q: 存储目录无法写入？**

```bash
# 设置正确权限
sudo chown -R www-data:www-data data/
sudo chmod -R 755 data/

# 或者设为 777（仅开发环境）
chmod -R 777 data/
```

## 下一步

安装完成后，您可以：

- [快速开始](/guide/getting-started) - 创建第一个应用
- [配置文件](/guide/configuration) - 详细了解配置选项
- [目录结构](/guide/directory-structure) - 理解项目组织