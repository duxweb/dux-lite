# 快速开始

本指南将帮助您在几分钟内创建第一个 DuxLite 应用程序。

## 环境要求

在开始之前，请确保您的开发环境满足以下要求：

- PHP >= 8.2
- Composer >= 2.0
- 必需扩展：ext-pdo, ext-zlib

## 安装 DuxLite

### 方式一：使用项目模板（推荐）

使用官方项目模板快速创建应用：

```bash
# 使用项目模板创建新项目
composer create-project duxweb/dux-lite-starter my-app

# 进入项目目录
cd my-app

# 设置权限
chmod -R 755 data/
chmod +x dux
```

### 方式二：手动安装

手动安装框架并创建项目结构：

```bash
composer require duxweb/dux-lite:^2.0
```

## 创建应用

### 使用项目模板（推荐路径）

如果您已经使用了项目模板创建项目，可以直接跳到 [运行应用](#运行应用) 部分。项目模板已经包含了完整的项目结构和配置文件。

只需要：
1. 编辑 `config/database.toml` 配置数据库连接
2. 运行 `php dux db:sync` 初始化数据库
3. 启动开发服务器测试

### 手动创建项目（完整流程）

如果您选择手动安装，请按照以下步骤创建项目：

#### 1. 项目结构

创建基本的项目结构：

```
my-app/
├── public/
│   └── index.php                 # Web 入口
├── app/
│   └── Web/
│       ├── App.php              # 应用注册类
│       └── Controllers/
│           └── HelloController.php
├── config/
│   ├── use.toml                 # 应用配置
│   └── app.toml                 # 模块注册
├── dux                          # 命令行工具
└── composer.json
```

#### 2. 创建入口文件

##### Web 入口文件

在 `public/index.php` 中创建应用入口：

```php
<?php
$file = __DIR__ . '/../vendor/autoload.php';

if (!is_file($file)) {
    exit('Please run "composer install" to install the dependencies, Composer is not installed, please install <a href="https://getcomposer.org/" target="_blank">Composer</a>.');
}

require $file;

use Core\App;

App::create(basePath: dirname(__DIR__), debug: true, timezone: 'UTC');

App::runWeb();
```

##### 命令行工具文件

在项目根目录创建 `dux` 文件（无扩展名）：

```php
#!/usr/bin/env php

<?php

set_time_limit(0);
ini_set('max_execution_time', 0);

$file = __DIR__ . '/vendor/autoload.php';

if (!is_file($file)) {
    exit('Please run "composer install" to install the dependencies, Composer is not installed, please install <a href="https://getcomposer.org/" target="_blank">Composer</a>.');
}

require $file;

use Core\App;

App::create(basePath: __DIR__, debug: true, timezone: 'UTC');

App::run();
```

设置文件执行权限：

```bash
chmod +x dux
```

#### 3. 配置文件

##### 应用配置 `config/use.toml`

```toml
[app]
name = "我的 DuxLite 应用"
debug = true
timezone = "Asia/Shanghai"
secret = "your-app-secret-key"
domain = "http://localhost:8000"
```

##### 模块注册 `config/app.toml`

```toml
# 注册应用模块
registers = [
    "App\\Web\\App"
]
```

#### 4. 创建应用模块

创建 `app/Web/App.php`：

```php
<?php
namespace App\Web;

use Core\App\AppExtend;
use Core\Bootstrap;
use Core\Route\Route;
use Core\App;

class App extends AppExtend
{
    public function init(Bootstrap $bootstrap): void
    {
        // 注册路由应用 - 控制器中的 RouteGroup 需要对应这里注册的名称
        App::route()->set("web", new Route());
    }

    public function register(Bootstrap $bootstrap): void
    {
        // 注册服务
    }

    public function boot(Bootstrap $bootstrap): void
    {
        // 应用启动
    }
}
```

#### 5. 第一个路由

创建 `app/Web/Controllers/HelloController.php`：

```php
<?php
namespace App\Web\Controllers;

use Core\Route\Attribute\Route;
use Core\Route\Attribute\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[RouteGroup(app: 'web', route: '')]  // 对应 App.php 中注册的 "web" 路由应用
class HelloController
{
    #[Route(methods: 'GET', route: '/hello')]
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $response->getBody()->write('Hello, DuxLite!');
        return $response;
    }
}
```

### 工作原理

1. **App.php** 中注册路由应用 `"web"`
2. **控制器** 使用 `RouteGroup(app: 'web')` 指定使用哪个路由应用
3. **框架** 自动匹配路由应用和控制器

## 运行应用

### 启动服务器

```bash
php -S localhost:8000 -t public
```

### 测试访问

打开浏览器访问：`http://localhost:8000/hello`

您应该看到页面显示：**Hello, DuxLite!**

## 验证命令行工具

```bash
# 查看所有可用命令
php dux
```

## 恭喜！

您已经成功创建了第一个 DuxLite 应用！🎉

## 下一步

现在您已经有了一个基本的应用，可以学习更多功能：

- [框架概况](/guide/overview) - 深入了解框架特性
- [路由系统](/core/routing) - 学习更多路由功能
- [数据库 ORM](/core/database/eloquent) - 添加数据库功能