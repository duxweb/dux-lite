# 部署指南

DuxLite 应用部署指南，推荐简单实用的本地部署工具。

## 环境要求

### 基本要求

- **PHP**: 8.2 或更高版本
- **扩展**: PDO、JSON、OpenSSL、Fileinfo、Mbstring
- **数据库**: MySQL 5.7+、SQLite 3.8+

### PHP 配置

```ini
; php.ini 基本配置
memory_limit = 128M
post_max_size = 20M
upload_max_filesize = 20M
max_execution_time = 60
```

## 本地开发环境

### 推荐工具：FlyEnv (推荐)

FlyEnv 是一个简单易用的本地 PHP 开发环境：

- **官网**：https://flyenv.com/
- **特点**：一键安装、图形界面、支持多 PHP 版本（推荐 PHP 8.2+）
- **支持系统**：Windows、macOS、Linux

#### 安装步骤

1. 访问 https://flyenv.com/ 下载对应系统版本
2. 安装后启动 Nginx 和 MySQL 服务
3. 将项目放到 `www` 目录下
4. 配置虚拟主机指向项目的 `public` 目录

#### 配置示例

```nginx
# FlyEnv Nginx 虚拟主机配置
server {
    listen 80;
    server_name myapp.local;
    root C:/flyenv/www/myapp/public;
    index index.php index.html;

    # DuxLite 伪静态规则
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 安全配置
    location ~ /\.(toml|env) {
        deny all;
    }
}
```

### 其他开发环境

#### Windows

**WNMP**：
- 下载：https://www.wnmp.x10host.com/
- 包含：Nginx + MySQL + PHP 8.2+

**Laragon**：
- 下载：https://laragon.org/
- 支持 Nginx、多 PHP 版本（选择 PHP 8.2+）

#### macOS

**Homebrew 安装**：
```bash
# 安装 Homebrew
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# 安装 Nginx、PHP 8.2 和 MySQL
brew install nginx php@8.2 mysql

# 启动服务
brew services start nginx
brew services start php@8.2
brew services start mysql
```

**Laravel Valet**：
```bash
composer global require laravel/valet
valet install
valet use php@8.2  # 使用 PHP 8.2
valet link myapp   # 访问 http://myapp.test
```

#### Linux

**Docker Compose**：
```yaml
# docker-compose.yml
version: '3.8'
services:
  web:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
      - db

  php:
    image: php:8.2-fpm-alpine
    volumes:
      - ./:/var/www/html
    depends_on:
      - db

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: myapp
    ports:
      - "3306:3306"
```

## 项目配置

### 环境检查

确保 PHP 版本符合要求：

```bash
# 检查 PHP 版本（需要 8.2+）
php -v

# 检查必需扩展
php -m | grep -E "(pdo|pdo_mysql|mbstring|json|openssl)"
```

### 依赖安装

```bash
# 安装 Composer 依赖
composer install

# 开发环境安装（包含开发工具）
composer install --dev

# 生产环境安装（优化性能）
composer install --no-dev --optimize-autoloader
```

### 数据库配置

```toml
# config/database.toml
[default]
driver = "mysql"
host = "127.0.0.1"
port = 3306
database = "myapp"
username = "root"
password = "your_password"
charset = "utf8mb4"
collation = "utf8mb4_unicode_ci"
```

### 权限设置

```bash
# Linux/macOS
chmod -R 755 data/
chmod +x dux

# 确保 Web 服务器可以写入
sudo chown -R www-data:www-data data/  # Linux
sudo chown -R _www:_www data/          # macOS
```

### 初始化数据库

```bash
# 同步数据库结构
php dux db:sync

# 查看可用命令
php dux
```

通过这些简单的工具和配置，您可以快速搭建 DuxLite 开发和部署环境。

## 线上环境部署

### Docker 部署 (推荐)

#### 1. 创建 Dockerfile

```dockerfile
# Dockerfile
FROM php:8.2-fpm-alpine

# 安装 PHP 扩展
RUN docker-php-ext-install pdo pdo_mysql

# 安装 Nginx
RUN apk add --no-cache nginx

# 复制项目文件
COPY . /var/www/html

# 设置权限
RUN chown -R www-data:www-data /var/www/html/data
RUN chmod -R 755 /var/www/html/data

# 复制 Nginx 配置
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# 启动脚本
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
```

#### 2. Nginx 配置文件

```nginx
# docker/nginx.conf
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php index.html;

    # DuxLite 伪静态规则
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 安全配置
    location ~ /\.(toml|env) {
        deny all;
    }

    location ~ /(config|data|vendor) {
        deny all;
    }

    # 静态文件缓存
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

#### 3. 启动脚本

```bash
#!/bin/sh
# docker/start.sh

# 启动 PHP-FPM
php-fpm -D

# 启动 Nginx
nginx -g "daemon off;"
```

#### 4. Docker Compose 生产环境

```yaml
# docker-compose.prod.yml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "80:80"
    volumes:
      - ./data:/var/www/html/data
    environment:
      - APP_ENV=production
    depends_on:
      - db
      - redis

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data

volumes:
  mysql_data:
  redis_data:
```

#### 5. 部署命令

```bash
# 构建和启动
docker-compose -f docker-compose.prod.yml up -d

# 初始化数据库
docker-compose exec app php dux db:sync

# 查看日志
docker-compose logs -f app
```

### 宝塔面板部署

#### 1. 安装宝塔面板

访问宝塔官网获取最新安装脚本：

- **官网地址**：https://www.bt.cn/
- **安装文档**：https://www.bt.cn/bbs/thread-19376-1-1.html

```bash
# Linux 一键安装脚本（请访问官网获取最新版本）
# CentOS/RHEL/Fedora
yum install -y wget && wget -O install.sh https://download.bt.cn/install/install_6.0.sh && sh install.sh ed8484bec

# Ubuntu/Debian
wget -O install.sh https://download.bt.cn/install/install-ubuntu_6.0.sh && sudo bash install.sh ed8484bec

# 建议直接访问 https://www.bt.cn/ 获取最新安装命令
```

#### 2. 环境配置

1. 登录宝塔面板
2. 安装 LNMP 环境（Nginx + MySQL + PHP 8.2+）
3. 创建网站，设置运行目录为 `public`

#### 3. Nginx 伪静态规则

在宝塔面板网站设置中添加伪静态规则：

```nginx
# DuxLite 伪静态规则
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/tmp/php-cgi-82.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}

# 安全配置
location ~ /\.(toml|env) {
    deny all;
}

location ~ /(config|data|vendor) {
    deny all;
}
```

#### 4. 宝塔部署步骤

1. **上传代码**：将项目文件上传到网站目录
2. **设置权限**：
   ```bash
   chmod -R 755 data/
   chown -R www:www data/
   ```
3. **安装依赖**：
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
4. **配置数据库**：在宝塔面板创建数据库，修改 `config/database.toml`
5. **初始化数据库**：
   ```bash
   php dux db:sync
   ```
6. **设置伪静态**：在网站设置中添加上述伪静态规则
7. **配置 SSL**：在宝塔面板申请并配置 SSL 证书

### 通用 Nginx 配置

#### 生产环境完整配置

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/your-app/public;
    index index.php;

    # DuxLite 伪静态
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;  # 或 unix:/var/run/php/php8.2-fpm.sock
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 安全配置
    location ~ /\.(toml|env) {
        deny all;
    }

    location ~ /(config|data|vendor) {
        deny all;
    }

    # 静态文件缓存
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Gzip 压缩
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
}
```