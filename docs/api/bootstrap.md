# Bootstrap 引导

DuxLite 应用引导程序的核心类定义和 API 规格说明。

## Bootstrap 类

**命名空间：** `Core\Bootstrap`

### 属性

```php
public SlimApp $web
```
- **类型：** `SlimApp` - Slim框架应用实例
- **说明：** Web应用实例

```php
public Render $view
```
- **类型：** `Render` - 模板渲染器实例
- **说明：** 视图渲染器

```php
public Application $command
```
- **类型：** `Application` - Symfony控制台应用实例
- **说明：** 命令行应用实例

### 构造方法

```php
public function __construct()
```
- **说明：** 构造函数，初始化引导程序

### 注册方法

```php
public function registerFunc(): void
```
- **返回：** `void`
- **说明：** 注册全局辅助函数

```php
public function registerConfig(): void
```
- **返回：** `void`
- **说明：** 注册配置加载器

```php
public function registerWeb(): void
```
- **返回：** `void`
- **说明：** 注册Web应用和中间件

### 加载方法

```php
public function loadApp(): void
```
- **返回：** `void`
- **说明：** 加载应用模块和注解

```php
public function loadRoute(): void
```
- **返回：** `void`
- **说明：** 加载路由配置

```php
public function loadCommand(): void
```
- **返回：** `void`
- **说明：** 加载命令行命令

### 运行方法

```php
public function run(): int
```
- **返回：** `int` - 退出代码
- **说明：** 运行命令行应用

```php
public function runWeb(): void
```
- **返回：** `void`
- **说明：** 运行Web应用

## 应用生命周期

### 引导阶段

1. **构造阶段**：创建Bootstrap实例
2. **注册阶段**：注册核心服务和函数
3. **加载阶段**：加载应用模块和配置
4. **运行阶段**：启动Web或命令行应用

### 注册顺序

1. `registerFunc()` - 注册全局函数
2. `registerConfig()` - 注册配置服务
3. `registerWeb()` - 注册Web服务

### 加载顺序

1. `loadApp()` - 加载应用模块
2. `loadRoute()` - 加载路由配置
3. `loadCommand()` - 加载命令配置

## 中间件栈

### Web中间件执行顺序

1. **ErrorMiddleware** - 错误处理中间件
2. **RoutingMiddleware** - 路由中间件
3. **BodyParsingMiddleware** - 请求体解析中间件
4. **CorsMiddleware** - CORS中间件
5. **LangMiddleware** - 语言中间件
6. **AuthMiddleware** - 认证中间件（可选）
7. **ApiMiddleware** - API中间件（可选）

### 中间件配置

```php
// 错误处理中间件
$web->addErrorMiddleware(bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails)

// 路由中间件
$web->addRoutingMiddleware()

// 请求体解析中间件
$web->addBodyParsingMiddleware()

// 自定义中间件
$web->add(CorsMiddleware::class)
$web->add(LangMiddleware::class)
```

## 模块生命周期

### 模块注册流程

1. **发现阶段**：扫描注册的应用模块
2. **实例化阶段**：创建模块实例
3. **初始化阶段**：调用模块的`init()`方法
4. **注册阶段**：调用模块的`register()`方法
5. **启动阶段**：调用模块的`boot()`方法

### 模块接口

```php
interface ModuleInterface
{
    public function init(): void;
    public function register(): void;
    public function boot(): void;
}
```

### 模块方法说明

- `init()` - 模块初始化，设置基础配置
- `register()` - 注册服务到容器
- `boot()` - 启动服务，执行业务逻辑

## 服务注册

### 核心服务

| 服务名 | 类型 | 说明 |
|--------|------|------|
| `config` | `TomlLoader` | 配置加载器 |
| `web` | `SlimApp` | Web应用实例 |
| `view` | `Render` | 视图渲染器 |
| `command` | `Application` | 命令行应用 |

### 服务访问

```php
// 通过App类访问服务
$config = App::config();
$web = App::web();
$view = App::view();
```

## 配置加载

### 配置文件类型

- **TOML格式**：主要配置格式
- **环境变量**：运行时配置覆盖
- **默认配置**：框架内置默认值

### 配置加载顺序

1. 加载框架默认配置
2. 加载应用配置文件
3. 加载环境变量覆盖
4. 合并配置数组

### 配置文件路径

| 配置类型 | 文件路径 | 说明 |
|----------|----------|------|
| 应用配置 | `config/app.toml` | 应用基础配置 |
| 数据库配置 | `config/database.toml` | 数据库连接配置 |
| 缓存配置 | `config/cache.toml` | 缓存驱动配置 |
| 日志配置 | `config/log.toml` | 日志记录配置 |

## 错误处理

### 错误中间件配置

```php
$errorMiddleware = $web->addErrorMiddleware(
    bool $displayErrorDetails,  // 是否显示错误详情
    bool $logErrors,           // 是否记录错误日志
    bool $logErrorDetails      // 是否记录错误详情
);
```

### 错误处理器

| 处理器类 | 内容类型 | 说明 |
|----------|----------|------|
| `ErrorHtmlRenderer` | `text/html` | HTML错误页面 |
| `ErrorJsonRenderer` | `application/json` | JSON错误响应 |
| `ErrorPlainRenderer` | `text/plain` | 纯文本错误信息 |
| `ErrorXmlRenderer` | `application/xml` | XML错误响应 |

### 错误处理流程

1. 捕获异常或错误
2. 根据请求类型选择渲染器
3. 生成错误响应
4. 记录错误日志（如果启用）
5. 返回错误响应给客户端

## 路由加载

### 路由注册流程

1. 注册路由注解
2. 注册资源路由
3. 注册权限路由
4. 应用路由缓存

### 路由类型

| 路由类型 | 注册方法 | 说明 |
|----------|----------|------|
| 注解路由 | `route()->registerAttribute()` | 基于注解的路由 |
| 资源路由 | `resource()->registerAttribute()` | RESTful资源路由 |
| 权限路由 | `permission()->registerAttribute()` | 权限控制路由 |

### 路由中间件

- **全局中间件**：应用于所有路由
- **组中间件**：应用于路由组
- **路由中间件**：应用于单个路由

## 命令加载

### 命令注册类型

1. **框架命令**：框架内置命令
2. **应用命令**：应用自定义命令
3. **模块命令**：模块提供的命令

### 内置命令

| 命令名 | 说明 |
|--------|------|
| `db:migrate` | 数据库迁移 |
| `db:sync` | 数据库同步 |
| `cache:clear` | 清理缓存 |
| `route:list` | 路由列表 |
| `queue:work` | 队列处理 |

### 命令注册流程

1. 扫描命令注解
2. 实例化命令类
3. 注册到控制台应用
4. 设置命令描述和参数

## 环境检测

### 运行环境

- **Web环境**：HTTP请求处理
- **CLI环境**：命令行执行
- **测试环境**：单元测试运行

### 环境变量

| 变量名 | 说明 | 默认值 |
|--------|------|--------|
| `APP_DEBUG` | 调试模式 | `false` |
| `APP_ENV` | 运行环境 | `production` |
| `APP_SECRET` | 应用密钥 | 随机生成 |

### 环境配置

```php
// 检测运行环境
$isWeb = php_sapi_name() !== 'cli';
$isDebug = env('APP_DEBUG', false);
$environment = env('APP_ENV', 'production');
```

## 性能优化

### 启动优化

- 延迟加载非必需服务
- 缓存配置解析结果
- 预编译路由规则
- 优化自动加载路径

### 内存优化

- 及时释放临时对象
- 使用弱引用避免循环引用
- 优化中间件栈深度
- 控制服务实例数量

### 缓存策略

- 配置文件缓存
- 路由规则缓存
- 注解解析缓存
- 服务实例缓存

## 调试工具

### 调试信息

- 启动时间统计
- 内存使用监控
- 服务注册日志
- 中间件执行追踪

### 开发模式特性

- 自动重载配置
- 详细错误信息
- 性能分析报告
- 调试工具栏

### 生产模式优化

- 禁用调试信息
- 启用操作码缓存
- 压缩响应内容
- 优化错误处理

## 扩展点

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

### 模块扩展

```php
class CustomModule implements ModuleInterface
{
    public function init(): void
    {
        // 模块初始化
    }

    public function register(): void
    {
        // 注册服务
    }

    public function boot(): void
    {
        // 启动服务
    }
}
```

### 中间件扩展

```php
class CustomMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // 中间件逻辑
    }
}
```

## 安全考虑

### 配置安全

- 敏感配置加密存储
- 环境变量隔离
- 配置文件权限控制
- 默认安全配置

### 运行时安全

- 输入验证和过滤
- 输出编码和转义
- CSRF防护
- XSS防护

### 错误处理安全

- 生产环境隐藏错误详情
- 安全的错误日志记录
- 防止信息泄露
- 错误响应标准化