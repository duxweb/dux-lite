# 目录结构

本指南介绍 DuxLite v2 项目的推荐目录结构。DuxLite 是一个灵活的模块化框架，您可以根据项目需要自由组织目录结构。

## 基础目录结构

```
my-app/
├── public/                    # Web 根目录
│   ├── index.php             # Web 应用入口文件
│   ├── .htaccess             # Apache 重写规则
│   └── assets/               # 静态资源
├── app/                      # 应用程序代码
│   ├── Web/                 # Web 应用模块
│   │   ├── App.php          # Web 应用注册类
│   │   └── Controllers/     # 控制器
├── config/                   # 配置文件
│   ├── use.toml             # 应用配置
│   ├── app.toml             # 模块注册
│   ├── database.toml        # 数据库配置
│   ├── queue.toml           # 队列配置
│   └── storage.toml         # 存储配置
├── data/                     # 数据存储目录
│   ├── logs/                # 日志文件
│   ├── cache/               # 缓存文件
│   └── uploads/             # 上传文件
├── database/                 # 数据库文件
│   └── migrations/          # 数据库迁移文件
├── vendor/                   # Composer 依赖包
├── composer.json            # Composer 配置
├── dux                      # 命令行工具入口
└── README.md                # 项目说明文档
```

## 核心目录说明

### `public/` - Web 根目录

Web 服务器的文档根目录，包含所有可通过 Web 访问的文件。

```php
// public/index.php - Web 应用入口
<?php
require __DIR__ . '/../vendor/autoload.php';

use Core\App;

App::create(basePath: dirname(__DIR__));
App::runWeb();
```

### `app/` - 应用程序代码

包含所有业务逻辑代码。框架通过配置文件注册模块：

```toml
# config/app.toml - 模块注册配置
registers = [
    "App\\Web\\App"
]
```

每个模块需要有对应的注册类：

```php
// app/Web/App.php - Web 模块注册类
<?php
namespace App\Web;

use Core\App\AppExtend;
use Core\Bootstrap;

class App extends AppExtend
{
    public function init(Bootstrap $bootstrap): void
    {
        // 模块初始化逻辑
    }
}
```

### `config/` - 配置文件

所有配置文件都使用 TOML 格式：

- `use.toml` - 应用基础配置
- `app.toml` - 模块注册配置
- `database.toml` - 数据库连接配置
- `queue.toml` - 队列服务配置
- `storage.toml` - 文件存储配置

### `data/` - 数据存储目录

存放应用运行时产生的各种数据文件：

- `logs/` - 应用日志文件
- `cache/` - 文件缓存数据

## 权限设置

### Linux/macOS 权限

```bash
# 基本权限设置
chmod -R 755 data/
chmod +x dux
```

### Windows 权限

```powershell
# 确保 Web 服务器可以写入数据目录
icacls data /grant IIS_IUSRS:(OI)(CI)F
```

## 灵活的目录组织

DuxLite 框架只有两个基本要求：

1. **模块注册类** - 每个模块目录下包含 `App.php` 类
2. **命名空间规范** - 遵循 PSR-4 自动加载规则

### 按功能分组示例

```
app/
├── User/                    # 用户管理
│   ├── App.php
│   └── Controllers/
├── Product/                 # 产品管理
│   ├── App.php
│   └── Controllers/
└── Order/                   # 订单处理
    ├── App.php
    └── Controllers/
```

### 按业务领域分组示例

```
app/
├── Frontend/               # 前台业务
│   ├── App.php
│   └── Controllers/
├── Backend/                # 后台管理
│   ├── App.php
│   └── Controllers/
└── Integration/            # 第三方集成
    ├── App.php
    └── Controllers/
```

通过这种灵活的目录结构设计，您可以根据项目规模和团队习惯，选择最适合的代码组织方式。