# 快速开始

本指南将帮助您在 5 分钟内搭建第一个 DuxLite 应用。

## 环境要求

```bash
# 必需环境
PHP >= 8.4
Composer >= 2.0

# 必需扩展
ext-pdo, ext-zlib
```

## 安装应用

### 使用模板创建（推荐）

```bash
# 创建新项目
composer create-project duxweb/dux-lite-starter my-app
cd my-app

# 设置权限
chmod -R 755 data/
chmod +x dux

# 创建开发配置
cp config/use.toml config/use.dev.toml
cp config/database.toml config/database.dev.toml
```

### 手动安装

```bash
# 安装核心包
composer require duxweb/dux-lite

# 创建基本结构
mkdir -p {config,data,public}
```

## 基础配置

### 1. 应用配置（config/use.dev.toml）

```toml
[app]
name = "我的应用"
debug = true
secret = "your-32-char-secret-key-here"
domain = "http://localhost:8000"

[cache]
type = "file"
prefix = "myapp_"
```

### 2. 数据库配置（config/database.dev.toml）

```toml
[db.drivers.default]
driver = "mysql"
host = "localhost"
port = 3306
database = "myapp"
username = "root"
password = ""
charset = "utf8mb4"
```

## 创建应用入口

### Web 入口（public/index.php）

```php
<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Core\App;

// 创建应用
App::create(
    basePath: dirname(__DIR__),
    debug: true
);

// 初始化并运行（create 内部已包含 init）
App::runWeb();
```

### 命令行入口（dux）

```php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Core\App;

App::create(basePath: __DIR__, debug: true);
App::run();
```

## 注册应用模块

### 1. 配置模块注册（config/app.toml）

```toml
registers = ["App\\Web\\App"]
```

### 2. 创建模块类（app/Web/App.php）

```php
<?php
namespace App\Web;

use Core\App\AppExtend;
use Core\Bootstrap;

class App extends AppExtend
{
    public function init(Bootstrap $bootstrap): void
    {
        // 模块初始化
    }

    public function register(Bootstrap $bootstrap): void
    {
        // 注册服务
    }

    public function boot(Bootstrap $bootstrap): void
    {
        // 启动配置
    }
}
```

## 创建第一个控制器

### 控制器类（app/Web/Controllers/HelloController.php）

```php
<?php
namespace App\Web\Controllers;

use Core\Route\Attribute\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HelloController
{
    #[Route(methods: 'GET', route: '/hello')]
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write('Hello, DuxLite!');
        return $response;
    }

    #[Route(methods: 'GET', route: '/hello/{name}')]
    public function greet(ServerRequestInterface $request, ResponseInterface $response, string $name): ResponseInterface
    {
        $response->getBody()->write("Hello, {$name}!");
        return $response;
    }
}
```

## 启动应用

### 开发服务器

```bash
# 使用 PHP 内置服务器
php -S localhost:8000 -t public
```

### 测试访问

```bash
# 基础路由
curl http://localhost:8000/hello
# 输出: Hello, DuxLite!

# 带参数路由
curl http://localhost:8000/hello/World
# 输出: Hello, World!
```

## 下一步

- 📖 了解[应用生命周期](/guide/lifecycle)
- ⚙️ 配置[数据库和缓存](/guide/configuration)
- 🛣️ 学习[路由系统](/reference/api/routing)
- 🎯 查看[控制器用法](/reference/api/controllers)

## 常见问题

**Q: 为什么访问返回 404？**
A: 检查 Web 服务器配置，确保请求被正确转发到 `public/index.php`

**Q: 如何启用调试模式？**
A: 在 `config/use.dev.toml` 中设置 `app.debug = true`，或在 `App::create()` 中设置 `debug: true`

**Q: 控制器路由不生效？**
A: 确保控制器类已注册到应用模块，检查命名空间和注解语法
