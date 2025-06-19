# Attributes 注解

DuxLite 框架的注解系统定义和 API 规格说明。

## 注解系统概览

DuxLite 使用 PHP 8+ 的原生注解（Attributes）系统，通过注解实现自动发现和注册功能。

### 支持的注解类型

| 注解类 | 用途 | 目标 |
|--------|------|------|
| `Route` | 定义路由 | 方法 |
| `RouteGroup` | 定义路由组 | 类 |
| `Resource` | 定义资源控制器 | 类 |
| `Action` | 定义资源操作 | 方法 |
| `Listener` | 定义事件监听器 | 方法 |
| `Command` | 定义命令行命令 | 类 |
| `AutoMigrate` | 定义自动迁移 | 类 |
| `Scheduler` | 定义计划任务 | 方法 |

## 路由注解

### Route 注解

**命名空间：** `Core\Route\Attribute\Route`

```php
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public string $path,
        public array $methods = ['GET'],
        public string $name = '',
        public array $middleware = [],
        public array $where = []
    ) {}
}
```

**属性：**
- `$path` - 路由路径
- `$methods` - HTTP方法数组
- `$name` - 路由名称
- `$middleware` - 中间件数组
- `$where` - 参数约束数组

### RouteGroup 注解

**命名空间：** `Core\Route\Attribute\RouteGroup`

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
class RouteGroup
{
    public function __construct(
        public string $prefix = '',
        public array $middleware = [],
        public string $name = '',
        public array $where = []
    ) {}
}
```

**属性：**
- `$prefix` - 路由前缀
- `$middleware` - 中间件数组
- `$name` - 路由名称前缀
- `$where` - 参数约束数组

## 资源注解

### Resource 注解

**命名空间：** `Core\Resources\Attribute\Resource`

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
class Resource
{
    public function __construct(
        public string $name,
        public array $only = [],
        public array $except = [],
        public array $middleware = [],
        public bool $api = true
    ) {}
}
```

**属性：**
- `$name` - 资源名称
- `$only` - 仅包含的操作
- `$except` - 排除的操作
- `$middleware` - 中间件数组
- `$api` - 是否为API资源

### Action 注解

**命名空间：** `Core\Resources\Attribute\Action`

```php
#[\Attribute(\Attribute::TARGET_METHOD)]
class Action
{
    public function __construct(
        public string $name,
        public array $methods = ['GET'],
        public string $path = '',
        public array $middleware = []
    ) {}
}
```

**属性：**
- `$name` - 操作名称
- `$methods` - HTTP方法数组
- `$path` - 路径（可选）
- `$middleware` - 中间件数组

## 事件注解

### Listener 注解

**命名空间：** `Core\Event\Attribute\Listener`

```php
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Listener
{
    public function __construct(
        public string $event,
        public int $priority = 0
    ) {}
}
```

**属性：**
- `$event` - 事件名称
- `$priority` - 优先级（数字越大优先级越高）

## 命令注解

### Command 注解

**命名空间：** `Core\Command\Attribute\Command`

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
class Command
{
    public function __construct(
        public string $name,
        public string $description = ''
    ) {}
}
```

**属性：**
- `$name` - 命令名称
- `$description` - 命令描述

## 数据库注解

### AutoMigrate 注解

**命名空间：** `Core\Database\Attribute\AutoMigrate`

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
class AutoMigrate
{
    public function __construct(
        public int $priority = 0
    ) {}
}
```

**属性：**
- `$priority` - 迁移优先级

## 计划任务注解

### Scheduler 注解

**命名空间：** `Core\Scheduler\Attribute\Scheduler`

```php
#[\Attribute(\Attribute::TARGET_METHOD)]
class Scheduler
{
    public function __construct(
        public string $cron,
        public string $name = '',
        public bool $withoutOverlapping = false,
        public int $maxRuntime = 0
    ) {}
}
```

**属性：**
- `$cron` - Cron表达式
- `$name` - 任务名称
- `$withoutOverlapping` - 是否防止重叠执行
- `$maxRuntime` - 最大运行时间（秒）

## 注解处理机制

### 自动发现

框架在启动时会自动扫描以下目录中的注解：
- `app/` 目录下的所有 PHP 文件
- `modules/` 目录下的所有 PHP 文件
- 扩展包中的指定目录

### 注册流程

1. **扫描阶段**：遍历目录，解析 PHP 文件
2. **解析阶段**：使用反射 API 读取注解
3. **验证阶段**：验证注解参数的有效性
4. **注册阶段**：将注解信息注册到相应的管理器
5. **缓存阶段**：将解析结果缓存以提高性能

### 缓存机制

- 注解解析结果会被缓存到 `data/cache/attributes.php`
- 开发模式下每次请求都会重新扫描
- 生产模式下使用缓存文件，需要手动清理缓存

## 注解约束

### 路径约束

路由注解支持参数约束：

```php
#[Route('/user/{id}', where: ['id' => '\d+'])]
#[Route('/slug/{name}', where: ['name' => '[a-z-]+'])]
```

### 方法约束

支持的 HTTP 方法：
- `GET`
- `POST`
- `PUT`
- `PATCH`
- `DELETE`
- `HEAD`
- `OPTIONS`

### 中间件约束

中间件可以是：
- 中间件类名：`AuthMiddleware::class`
- 中间件别名：`'auth'`
- 带参数的中间件：`'auth:admin'`

## 注解优先级

### 执行顺序

1. **路由注解**：优先级最高，最先处理
2. **资源注解**：其次处理
3. **事件注解**：按优先级排序
4. **命令注解**：启动时注册
5. **迁移注解**：按优先级执行
6. **计划任务注解**：按 Cron 表达式调度

### 优先级数值

- 数值越大，优先级越高
- 默认优先级为 `0`
- 负数表示低优先级
- 建议使用 100 的倍数设置优先级

## 注解继承

### 类级注解继承

子类会继承父类的类级注解：
- `RouteGroup`
- `Resource`
- `Command`
- `AutoMigrate`

### 方法级注解不继承

方法级注解不会被继承，需要在子类中重新定义：
- `Route`
- `Action`
- `Listener`
- `Scheduler`

## 注解验证

### 参数验证

框架会验证注解参数的有效性：
- 路径格式验证
- HTTP 方法验证
- 中间件存在性验证
- Cron 表达式验证

### 冲突检测

- 路由路径冲突检测
- 命令名称冲突检测
- 资源名称冲突检测

### 错误处理

注解解析错误会抛出相应异常：
- `InvalidArgumentException` - 参数无效
- `LogicException` - 逻辑错误
- `RuntimeException` - 运行时错误

## 注解缓存

### 缓存结构

```php
[
    'routes' => [
        // 路由注解数据
    ],
    'resources' => [
        // 资源注解数据
    ],
    'listeners' => [
        // 事件监听器数据
    ],
    'commands' => [
        // 命令数据
    ],
    'migrations' => [
        // 迁移数据
    ],
    'schedulers' => [
        // 计划任务数据
    ],
    'hash' => 'file_hash_value'
]
```

### 缓存更新

缓存在以下情况下会更新：
- 文件内容发生变化
- 文件修改时间变化
- 手动清理缓存
- 开发模式下每次请求

### 缓存清理

```bash
# 清理所有缓存
php dux cache:clear

# 清理注解缓存
php dux cache:clear --attributes
```

## 性能优化

### 扫描优化

- 只扫描包含注解的文件
- 使用文件哈希避免重复解析
- 并行处理多个文件

### 内存优化

- 及时释放反射对象
- 使用生成器处理大量文件
- 分批处理注解数据

### 缓存优化

- 使用 OPcache 缓存 PHP 文件
- 预编译注解缓存文件
- 压缩缓存数据

## 调试工具

### 注解列表命令

```bash
# 查看所有路由注解
php dux route:list

# 查看所有事件监听器
php dux event:list

# 查看所有命令
php dux command:list

# 查看所有计划任务
php dux schedule:list
```

### 注解验证

```bash
# 验证注解语法
php dux attribute:validate

# 检查注解冲突
php dux attribute:check
```

## 最佳实践

### 注解组织

- 将相关的注解放在一起
- 使用有意义的路由名称
- 合理设置中间件
- 避免过深的路由嵌套

### 命名约定

- 路由名称使用点号分隔：`user.profile.edit`
- 资源名称使用复数：`users`, `posts`
- 事件名称使用点号分隔：`user.created`
- 命令名称使用冒号分隔：`cache:clear`

### 性能考虑

- 避免在注解中使用复杂逻辑
- 合理使用缓存
- 定期清理无用的注解
- 监控注解解析性能

### 维护性

- 保持注解的简洁性
- 使用常量定义重复的参数
- 定期重构注解结构
- 编写注解使用文档