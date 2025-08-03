# 生产部署

DuxLite 应用生产环境部署指南，入口文件为 `public/index.php`，部署方式与 Laravel 类似。

## 快速部署

### 1. 上传代码

```bash
# 安装生产依赖
composer install --no-dev --optimize-autoloader

# 设置权限
chmod -R 755 .
chmod -R 775 data/
chmod 600 config/*.toml
```

### 2. 配置生产环境

```toml
# config/use.toml
[app]
debug = false
secret = "your-32-character-secret-key"
domain = "https://yourdomain.com"
```

## Web 服务器配置

**重要提示**：网站根目录必须指向 `public` 文件夹，入口文件为 `public/index.php`

### Nginx

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/html/public;  # 重要：指向 public 目录
    index index.php;

    # 伪静态规则
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 禁止访问配置文件
    location ~ /\.(env|toml) {
        deny all;
    }
}
```

### Apache

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/public  # 重要：指向 public 目录

    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
        
        # 伪静态规则
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>

    # 禁止访问配置文件
    <FilesMatch "\.(env|toml)$">
        Require all denied
    </FilesMatch>
</VirtualHost>
```

### 其他 Web 服务器

**IIS**：配置 `web.config` 文件，设置伪静态规则重写到 `public/index.php`

**Caddy**：设置根目录为 `public`，启用 PHP-FPM 和文件服务器

## 可视化面板配置

### 宝塔面板

1. **添加站点**：设置网站根目录为 `/www/wwwroot/yoursite/public`
2. **PHP 版本**：选择 PHP 8.2 或更高版本
3. **伪静态**：选择 "Laravel" 模板（规则相同）
4. **SSL**：配置 SSL 证书（推荐 Let's Encrypt）

### 1Panel

1. **创建网站**：根目录设置为 `public` 文件夹
2. **PHP 配置**：启用必要的 PHP 扩展
3. **伪静态规则**：
```
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 部署检查

### 1. 访问测试

```bash
# 检查网站是否正常访问
curl -I http://yourdomain.com

# 检查 PHP 是否正常工作
echo "<?php phpinfo();" > public/test.php
```

### 2. 常见问题

- **404 错误**：检查伪静态规则是否正确配置
- **500 错误**：查看 Web 服务器错误日志
- **权限问题**：确保 `data/` 目录可写
- **配置不生效**：检查 `config/*.toml` 文件语法

DuxLite 部署简单快捷，配置方式与 Laravel 基本相同，适合各种 PHP 主机环境。