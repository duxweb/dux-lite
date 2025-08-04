# 核心架构

DuxLite 的核心架构包括应用引导、注解系统和异常处理，构成了框架的基础设施。

## 应用引导系统

### 设计理念

DuxLite 的引导系统负责应用的初始化、配置加载、服务注册和启动流程管理。

### 引导流程

应用生命周期分为以下阶段：

```
构造阶段 → 注册阶段 → 加载阶段 → 运行阶段
```

#### 1. 构造阶段
创建 Bootstrap 实例，初始化基础组件。

#### 2. 注册阶段
```php
1. registerFunc()    - 注册全局函数
2. registerConfig()  - 注册配置服务
3. registerWeb()     - 注册Web服务
```

#### 3. 加载阶段
```php
1. loadApp()     - 加载应用模块
2. loadRoute()   - 加载路由配置
3. loadCommand() - 加载命令配置
```

#### 4. 运行阶段
```php
- runWeb()  - 运行Web应用
- run()     - 运行命令行应用
```

### Bootstrap 核心组件

#### 属性

```php
public SlimApp $web;        // Web应用实例
public Render $view;        // 视图渲染器
public Application $command; // 命令行应用实例
```

#### 核心方法

```php
public function registerFunc(): void    // 注册全局辅助函数
public function registerConfig(): void  // 注册配置加载器
public function registerWeb(): void     // 注册Web应用和中间件
public function loadApp(): void         // 加载应用模块和注解
public function loadRoute(): void       // 加载路由配置
public function loadCommand(): void     // 加载命令行命令
public function run(): int              // 运行命令行应用
public function runWeb(): void          // 运行Web应用
```

### 中间件栈

#### Web中间件执行顺序

1. **ErrorMiddleware** - 错误处理中间件
2. **RoutingMiddleware** - 路由中间件
3. **BodyParsingMiddleware** - 请求体解析中间件
4. **CorsMiddleware** - CORS中间件
5. **LangMiddleware** - 语言中间件
6. **AuthMiddleware** - 认证中间件（可选）
7. **ApiMiddleware** - API中间件（可选）

#### 中间件配置

```php
// 错误处理中间件
$web->addErrorMiddleware(bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails);

// 路由中间件
$web->addRoutingMiddleware();

// 请求体解析中间件
$web->addBodyParsingMiddleware();

// 自定义中间件
$web->add(CorsMiddleware::class);
$web->add(LangMiddleware::class);
```

### 模块生命周期

#### 模块注册流程

1. **发现阶段**：扫描注册的应用模块
2. **实例化阶段**：创建模块实例
3. **初始化阶段**：调用模块的 `init()` 方法
4. **注册阶段**：调用模块的 `register()` 方法
5. **启动阶段**：调用模块的 `boot()` 方法

#### 模块接口

```php
interface ModuleInterface
{
    public function init(): void;      // 模块初始化，设置基础配置
    public function register(): void;  // 注册服务到容器
    public function boot(): void;      // 启动服务，执行业务逻辑
}
```

### 服务注册

#### 核心服务

| 服务名 | 类型 | 说明 |
|--------|------|------|
| `config` | `TomlLoader` | 配置加载器 |
| `web` | `SlimApp` | Web应用实例 |
| `view` | `Render` | 视图渲染器 |
| `command` | `Application` | 命令行应用 |

#### 服务访问

```php
// 通过App类访问服务
$config = App::config();
$web = App::web();
$view = App::view();
```

### 配置加载

#### 配置文件类型

- **TOML格式**：主要配置格式
- **环境变量**：运行时配置覆盖
- **默认配置**：框架内置默认值

#### 配置加载顺序

1. 加载框架默认配置
2. 加载应用配置文件
3. 加载环境变量覆盖
4. 合并配置数组

#### 配置文件路径

| 配置类型 | 文件路径 | 说明 |
|----------|----------|------|
| 应用配置 | `config/app.toml` | 应用基础配置 |
| 数据库配置 | `config/database.toml` | 数据库连接配置 |
| 缓存配置 | `config/cache.toml` | 缓存驱动配置 |
| 日志配置 | `config/log.toml` | 日志记录配置 |

### 错误处理

#### 错误中间件配置

```php
$errorMiddleware = $web->addErrorMiddleware(
    bool $displayErrorDetails,  // 是否显示错误详情
    bool $logErrors,           // 是否记录错误日志
    bool $logErrorDetails      // 是否记录错误详情
);
```

#### 错误处理器

| 处理器类 | 内容类型 | 说明 |
|----------|----------|------|
| `ErrorHtmlRenderer` | `text/html` | HTML错误页面 |
| `ErrorJsonRenderer` | `application/json` | JSON错误响应 |
| `ErrorPlainRenderer` | `text/plain` | 纯文本错误信息 |
| `ErrorXmlRenderer` | `application/xml` | XML错误响应 |

### 路由加载

#### 路由注册流程

1. 注册路由注解
2. 注册资源路由
3. 注册权限路由
4. 应用路由缓存

#### 路由类型

| 路由类型 | 注册方法 | 说明 |
|----------|----------|------|
| 注解路由 | `route()->registerAttribute()` | 基于注解的路由 |
| 资源路由 | `resource()->registerAttribute()` | RESTful资源路由 |
| 权限路由 | `permission()->registerAttribute()` | 权限控制路由 |

### 环境检测

#### 运行环境

- **Web环境**：HTTP请求处理
- **CLI环境**：命令行执行
- **测试环境**：单元测试运行

#### 环境变量

| 变量名 | 说明 | 默认值 |
|--------|------|--------|
| `APP_DEBUG` | 调试模式 | `false` |
| `APP_ENV` | 运行环境 | `production` |
| `APP_SECRET` | 应用密钥 | 随机生成 |

## 注解系统

### 设计理念

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

### 路由注解

#### Route 注解

```php
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public string $path,                    // 路由路径
        public array $methods = ['GET'],        // HTTP方法数组
        public string $name = '',               // 路由名称
        public array $middleware = [],          // 中间件数组
        public array $where = []                // 参数约束数组
    ) {}
}
```

#### RouteGroup 注解

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
class RouteGroup
{
    public function __construct(
        public string $prefix = '',             // 路由前缀
        public array $middleware = [],          // 中间件数组
        public string $name = '',               // 路由名称前缀
        public array $where = []                // 参数约束数组
    ) {}
}
```

### 资源注解

#### Resource 注解

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
class Resource
{
    public function __construct(
        public string $name,                    // 资源名称
        public array $only = [],                // 仅包含的操作
        public array $except = [],              // 排除的操作
        public array $middleware = [],          // 中间件数组
        public bool $api = true                 // 是否为API资源
    ) {}
}
```

#### Action 注解

```php
#[\Attribute(\Attribute::TARGET_METHOD)]
class Action
{
    public function __construct(
        public string $name,                    // 操作名称
        public array $methods = ['GET'],        // HTTP方法数组
        public string $path = '',               // 路径（可选）
        public array $middleware = []           // 中间件数组
    ) {}
}
```

### 事件注解

#### Listener 注解

```php
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Listener
{
    public function __construct(
        public string $event,                   // 事件名称
        public int $priority = 0                // 优先级（数字越大优先级越高）
    ) {}
}
```

### 命令注解

#### Command 注解

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
class Command
{
    public function __construct(
        public string $name,                    // 命令名称
        public string $description = ''         // 命令描述
    ) {}
}
```

### 注解处理机制

#### 自动发现

框架在启动时会自动扫描以下目录中的注解：
- `app/` 目录下的所有 PHP 文件
- `modules/` 目录下的所有 PHP 文件
- 扩展包中的指定目录

#### 注册流程

1. **扫描阶段**：遍历目录，解析 PHP 文件
2. **解析阶段**：使用反射 API 读取注解
3. **验证阶段**：验证注解参数的有效性
4. **注册阶段**：将注解信息注册到相应的管理器
5. **缓存阶段**：将解析结果缓存以提高性能

#### 缓存机制

- 注解解析结果会被缓存到 `data/cache/attributes.php`
- 开发模式下每次请求都会重新扫描
- 生产模式下使用缓存文件，需要手动清理缓存

### 注解约束

#### 路径约束

路由注解支持参数约束：

```php
#[Route('/user/{id}', where: ['id' => '\d+'])]
#[Route('/slug/{name}', where: ['name' => '[a-z-]+'])]
```

#### 方法约束

支持的 HTTP 方法：
- `GET`、`POST`、`PUT`、`PATCH`、`DELETE`、`HEAD`、`OPTIONS`

#### 中间件约束

中间件可以是：
- 中间件类名：`AuthMiddleware::class`
- 中间件别名：`'auth'`
- 带参数的中间件：`'auth:admin'`

### 注解优先级

#### 执行顺序

1. **路由注解**：优先级最高，最先处理
2. **资源注解**：其次处理
3. **事件注解**：按优先级排序
4. **命令注解**：启动时注册
5. **迁移注解**：按优先级执行
6. **计划任务注解**：按 Cron 表达式调度

#### 优先级数值

- 数值越大，优先级越高
- 默认优先级为 `0`
- 负数表示低优先级
- 建议使用 100 的倍数设置优先级

## 异常处理系统

### 设计理念

DuxLite 提供了完整的异常处理体系，包含多种预定义的异常类型，用于处理不同场景下的错误情况。

### 异常层次结构

```
\RuntimeException
  └── Core\Handlers\Exception                    // 基础异常
      ├── Core\Handlers\ExceptionBusiness        // 业务异常 (500)
      ├── Core\Handlers\ExceptionBusinessLang    // 业务异常(支持多语言)
      ├── Core\Handlers\ExceptionNotFound        // 未找到异常 (404)
      └── Core\Handlers\ExceptionData            // 数据异常
          └── Core\Handlers\ExceptionValidator   // 验证异常 (422)

\Exception
  └── Core\Handlers\ExceptionInternal           // 内部异常
```

### 异常类型详解

#### ExceptionBusiness - 业务异常

**HTTP 状态码：** 500
**用途：** 处理业务逻辑异常

```php
use Core\Handlers\ExceptionBusiness;

// 业务逻辑错误
if (!$user->hasPermission('delete')) {
    throw new ExceptionBusiness('用户没有删除权限');
}
```

**使用场景：**
- 权限验证失败
- 业务规则违反
- 操作条件不满足
- 资源状态异常

#### ExceptionBusinessLang - 多语言业务异常

**用途：** 支持多语言的业务异常

```php
use Core\Handlers\ExceptionBusinessLang;

// 支持多语言的业务异常
throw new ExceptionBusinessLang('error.permission_denied');
```

**特性：**
- 自动根据当前语言环境翻译错误消息
- 支持占位符参数
- 与翻译系统集成

#### ExceptionValidator - 验证异常

**HTTP 状态码：** 422
**用途：** 处理数据验证异常

```php
use Core\Handlers\ExceptionValidator;

// 验证失败异常
$errors = [
    'name' => ['名称不能为空'],
    'email' => ['邮箱格式不正确', '邮箱已存在']
];

throw new ExceptionValidator($errors);
```

**特性：**
- 自动提取第一个错误作为主错误消息
- 保留完整的验证错误数据
- 自动设置 422 状态码
- 与表单验证系统集成

#### ExceptionNotFound - 未找到异常

**HTTP 状态码：** 404
**用途：** 处理资源未找到异常

```php
use Core\Handlers\ExceptionNotFound;

// 资源未找到
$user = User::find($id);
if (!$user) {
    throw new ExceptionNotFound('用户不存在');
}
```

#### ExceptionData - 数据异常

**用途：** 处理数据相关异常，支持携带额外数据

```php
use Core\Handlers\ExceptionData;

// 携带额外数据的异常
throw new ExceptionData('数据处理失败', 400, [
    'errors' => ['field1' => '字段验证失败'],
    'data' => $inputData
]);
```

#### ExceptionInternal - 内部异常

**用途：** 处理内部系统异常

```php
use Core\Handlers\ExceptionInternal;

// 系统内部错误
if (!file_exists($configFile)) {
    throw new ExceptionInternal('配置文件不存在');
}
```

### 自动异常处理

框架内置了完整的异常处理机制，会自动捕获并处理异常：

```php
// 在控制器中抛出异常
public function deleteUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    $id = $request->getAttribute('id');
    $user = User::find($id);

    if (!$user) {
        // 自动返回 404 响应
        throw new ExceptionNotFound('用户不存在');
    }

    if (!$this->hasPermission('user.delete')) {
        // 自动返回 500 响应
        throw new ExceptionBusiness('没有删除权限');
    }

    // 验证失败自动返回 422 响应
    $validator = new Validator($request->getParsedBody());
    if (!$validator->validate()) {
        throw new ExceptionValidator($validator->errors());
    }

    $user->delete();
    return send($response, '删除成功');
}
```

### 响应格式

#### JSON 响应格式

```json
{
    "status": false,
    "code": 422,
    "message": "名称不能为空",
    "data": {
        "name": ["名称不能为空"],
        "email": ["邮箱格式不正确"]
    }
}
```

#### HTML 响应格式

显示友好的错误页面，支持自定义错误模板。

### 异常使用最佳实践

#### 1. 选择合适的异常类型

```php
// ✅ 正确：业务逻辑错误使用 ExceptionBusiness
if ($account->balance < $amount) {
    throw new ExceptionBusiness('账户余额不足');
}

// ✅ 正确：资源不存在使用 ExceptionNotFound
$product = Product::find($id);
if (!$product) {
    throw new ExceptionNotFound('产品不存在');
}

// ✅ 正确：验证错误使用 ExceptionValidator
if (!$validator->validate()) {
    throw new ExceptionValidator($validator->errors());
}
```

#### 2. 提供有意义的错误消息

```php
// ❌ 错误：消息过于简单
throw new ExceptionBusiness('错误');

// ✅ 正确：描述具体的错误原因
throw new ExceptionBusiness('订单已发货，无法取消');
```

#### 3. 携带必要的上下文数据

```php
// ✅ 为调试提供上下文信息
throw new ExceptionData('支付处理失败', 400, [
    'order_id' => $orderId,
    'payment_method' => $paymentMethod,
    'error_code' => $apiErrorCode
]);
```

#### 4. 国际化错误消息

```php
// ✅ 使用翻译键
throw new ExceptionBusinessLang('order.cannot_cancel_shipped');

// 在语言文件中定义
// zh-CN: "订单已发货，无法取消"
// en-US: "Cannot cancel shipped order"
```

#### 5. 验证异常的标准格式

```php
// ✅ 标准的验证错误格式
$errors = [
    'name' => ['名称不能为空', '名称长度必须在2-50个字符之间'],
    'email' => ['邮箱格式不正确'],
    'age' => ['年龄必须大于18岁']
];

throw new ExceptionValidator($errors);
```

## 扩展机制

### 自定义引导

```php
class CustomBootstrap extends Bootstrap
{
    public function registerCustomServices(): void
    {
        // 注册自定义服务
    }

    protected function configureMiddleware(): void
    {
        // 配置自定义中间件
    }
}
```

### 自定义异常

```php
// 创建自定义异常类
class PaymentException extends ExceptionBusiness
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message);

        // 记录支付相关日志
        App::log('payment')->error($message, $context);

        // 发送告警通知
        $this->sendAlert($message, $context);
    }

    private function sendAlert(string $message, array $context): void
    {
        // 发送告警逻辑
    }
}
```

### 自定义注解

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
class Cache
{
    public function __construct(
        public int $ttl = 3600,
        public string $key = ''
    ) {}
}
```

## 性能优化

### 启动优化

- 延迟加载非必需服务
- 缓存配置解析结果
- 预编译路由规则
- 优化自动加载路径

### 注解缓存

- 使用 OPcache 缓存 PHP 文件
- 预编译注解缓存文件
- 压缩缓存数据

### 异常处理优化

- 生产环境隐藏错误详情
- 安全的错误日志记录
- 防止信息泄露
- 错误响应标准化

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

### 缓存管理

```bash
# 清理所有缓存
php dux cache:clear

# 清理注解缓存
php dux cache:clear --attributes
```

DuxLite 的核心架构为应用程序提供了坚实的基础，通过引导系统、注解系统和异常处理的有机结合，构建出灵活、高效、可维护的框架架构。