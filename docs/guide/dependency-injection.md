# 依赖注入

DuxLite 内置了依赖注入（DI）容器，基于 [PHP-DI](https://php-di.org/) 实现。

## 设计理念

**DuxLite 的依赖注入理念：依赖注入只在必要时使用，而不是像 Laravel 那样过度依赖。**

DuxLite 的设计思想类似于 Go 语言的显式编程风格：

- **显式 > 隐式**：优先使用明确的静态方法调用，如 `App::db()`、`App::cache()`
- **IDE 友好**：显式调用能提供完整的代码提示和类型推断
- **可读性优先**：代码意图清晰，依赖关系一目了然
- **性能考虑**：避免不必要的容器解析开销

```php
// ✅ DuxLite 推荐：显式调用，IDE 完整提示
$users = App::db()->table('users')->get();
$cache = App::cache('redis');
$config = App::config('database');

// ❌ 过度依赖注入：增加复杂性，IDE 提示不完整
public function __construct(
    private DatabaseManager $db,
    private CacheManager $cache,
    private Config $config
) {}
```

**何时使用依赖注入：**
- 框架内部服务管理
- 需要模拟测试的复杂服务
- 多态实现的服务切换
- 第三方库集成

**何时使用静态方法：**
- 框架核心功能访问
- 日常业务逻辑开发
- 简单直接的服务调用

容器管理应用中的服务和依赖关系，实现控制反转和依赖倒置，让代码更加模块化和可测试。

## 容器概述

### 什么是依赖注入？

依赖注入是一种设计模式，用于实现控制反转（IoC）。它将依赖关系的创建和管理从类内部转移到外部容器。

```php
// ❌ 传统方式：类直接创建依赖
class UserService
{
    private $repository;

    public function __construct()
    {
        $this->repository = new UserRepository(); // 硬编码依赖
    }
}

// ✅ 依赖注入：依赖由外部提供
class UserService
{
    public function __construct(
        private UserRepository $repository // 依赖注入
    ) {}
}
```

### DuxLite 的 DI 容器

DuxLite 的 DI 容器是全局单例，通过 `App::di()` 访问：

```php
use Core\App;

// 获取容器实例
$container = App::di();

// 注册服务
$container->set('serviceName', $serviceInstance);

// 获取服务
$service = $container->get('serviceName');

// 检查服务是否存在
if ($container->has('serviceName')) {
    // 服务已注册
}
```

## 基本使用

### 1. 注册服务

#### 直接注册实例

```php
// 注册具体实例
App::di()->set('userService', new UserService());

// 注册配置对象
App::di()->set('apiConfig', [
    'base_url' => 'https://api.example.com',
    'timeout' => 30
]);
```

#### 工厂函数注册（推荐）

```php
// 使用工厂函数延迟实例化
App::di()->set('userService', function() {
    return new UserService(
        App::db(),              // 依赖数据库
        App::cache(),           // 依赖缓存
        App::config('api')      // 依赖配置
    );
});

// 复杂服务的工厂注册
App::di()->set('emailService', function() {
    $config = App::config('mail');

    if ($config->get('driver') === 'smtp') {
        return new SmtpEmailService($config->get('smtp'));
    } else {
        return new SendmailEmailService();
    }
});
```

### 2. 获取服务

```php
// 直接获取服务
$userService = App::di()->get('userService');

// 使用服务
$users = $userService->getAllUsers();
```

### 3. 检查服务是否存在

```php
if (App::di()->has('userService')) {
    $userService = App::di()->get('userService');
    // 使用服务
} else {
    // 服务未注册，提供默认行为
    throw new Exception('UserService not registered');
}
```

## 框架内置服务

DuxLite 框架自动注册了许多内置服务，你可以直接使用：

### 1. 数据库服务

```php
// 获取数据库连接管理器
$db = App::db();

// 或者通过容器获取
$db = App::di()->get('db');

// 使用数据库
$users = $db->table('users')->get();
```

### 2. 缓存服务

```php
// 获取默认缓存
$cache = App::cache();

// 获取 Redis 缓存
$cache = App::cache('redis');

// 通过容器获取（带类型标识）
$cache = App::di()->get('cache.redis');
```

### 3. 配置服务

```php
// 获取应用配置
$config = App::config('use');

// 通过容器获取
$config = App::di()->get('config.use');
```

### 4. 事件服务

```php
// 获取事件调度器
$events = App::event();

// 通过容器获取
$events = App::di()->get('events');
```

### 5. 队列服务

```php
// 获取默认队列
$queue = App::queue();

// 获取 Redis 队列
$queue = App::queue('redis');

// 通过容器获取
$queue = App::di()->get('queue.redis');
```

## 服务命名约定

DuxLite 使用一套清晰的服务命名约定：

### 1. 基础服务

```php
'db'           // 数据库连接
'events'       // 事件调度器
'attributes'   // 注解属性
'permission'   // 权限管理
'resource'     // 资源管理
'route'        // 路由管理
'trans'        // 翻译服务
'scheduler'    // 计划任务
```

### 2. 带类型的服务

```php
'cache.file'      // 文件缓存
'cache.redis'     // Redis 缓存
'lock.file'       // 文件锁
'lock.redis'      // Redis 锁
'queue.redis'     // Redis 队列
'queue.amqp'      // AMQP 队列
'redis.default'   // 默认 Redis 连接
'logger.app'      // 应用日志
'view.template'   // 模板引擎
'storage.local'   // 本地存储
'storage.s3'      // S3 存储
```

### 3. 配置服务

```php
'config.use'      // 应用配置
'config.database' // 数据库配置
'config.cache'    // 缓存配置
'config.queue'    // 队列配置
```

## 在模块中使用依赖注入

### 1. 在 `init()` 阶段注册基础服务

```php
class App extends AppExtend
{
    public function init(Bootstrap $bootstrap): void
    {
        // 注册简单服务
        App::di()->set('myModule.version', '1.0.0');

        // 注册基础工厂服务
        App::di()->set('myModule.config', function() {
            return App::config('mymodule');
        });
    }
}
```

### 2. 在 `register()` 阶段注册复杂服务

```php
class App extends AppExtend
{
    public function register(Bootstrap $bootstrap): void
    {
        // 注册服务提供者
        App::di()->set('userRepository', function() {
            return new UserRepository(App::db());
        });

        // 注册带依赖的服务
        App::di()->set('userService', function() {
            return new UserService(
                App::di()->get('userRepository'),
                App::cache(),
                App::event()
            );
        });

        // 注册条件服务
        App::di()->set('paymentService', function() {
            $config = App::config('payment');

            switch ($config->get('provider')) {
                case 'stripe':
                    return new StripePaymentService($config->get('stripe'));
                case 'paypal':
                    return new PaypalPaymentService($config->get('paypal'));
                default:
                    return new MockPaymentService();
            }
        });
    }
}
```

### 3. 在 `boot()` 阶段配置已注册的服务

```php
class App extends AppExtend
{
    public function boot(Bootstrap $bootstrap): void
    {
        // 配置已注册的服务
        $userService = App::di()->get('userService');
        $userService->setDefaultRole('guest');

        // 设置服务间的关联
        $emailService = App::di()->get('emailService');
        $userService->setEmailService($emailService);
    }
}
```

## 高级用法

### 1. 单例模式

```php
// DI 容器默认使用单例模式
App::di()->set('expensiveService', function() {
    return new ExpensiveService(); // 只会创建一次
});

// 多次获取返回同一个实例
$service1 = App::di()->get('expensiveService');
$service2 = App::di()->get('expensiveService');
// $service1 === $service2 为 true
```

### 2. 接口绑定

```php
// 绑定接口到具体实现
App::di()->set(UserRepositoryInterface::class, function() {
    return new DatabaseUserRepository(App::db());
});

// 获取时使用接口名
$repository = App::di()->get(UserRepositoryInterface::class);
```

### 3. 带参数的服务工厂

```php
// 注册带参数的工厂
App::di()->set('logger', function() use ($container) {
    return function(string $channel = 'app') {
        return App::log($channel);
    };
});

// 使用工厂创建不同的服务实例
$loggerFactory = App::di()->get('logger');
$appLogger = $loggerFactory('app');
$userLogger = $loggerFactory('user');
```

### 4. 条件服务注册

```php
public function register(Bootstrap $bootstrap): void
{
    // 根据环境注册不同的服务
    if (App::$debug) {
        App::di()->set('debugService', function() {
            return new DebugService();
        });
    }

    // 根据配置注册服务
    $config = App::config('features');
    if ($config->get('email.enabled', false)) {
        App::di()->set('emailService', function() {
            return new EmailService(App::config('mail'));
        });
    }
}
```

### 5. 装饰器模式

```php
public function register(Bootstrap $bootstrap): void
{
    // 基础服务
    App::di()->set('baseUserService', function() {
        return new UserService(App::db());
    });

    // 装饰后的服务
    App::di()->set('userService', function() {
        $baseService = App::di()->get('baseUserService');
        $cachedService = new CachedUserService($baseService, App::cache());
        return new LoggedUserService($cachedService, App::log());
    });
}
```

## 容器与 Slim 集成

DuxLite 将 DI 容器集成到 Slim 框架中，支持控制器的依赖注入：

### 1. 控制器依赖注入

```php
class UserController
{
    public function __construct(
        private UserService $userService,
        private ValidatorService $validator
    ) {}

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();

        // 使用注入的服务
        $this->validator->validate($data, UserCreateRules::class);
        $user = $this->userService->create($data);

        $response->getBody()->write(json_encode($user));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

### 2. 中间件依赖注入

```php
class AuthMiddleware
{
    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository
    ) {}

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 使用注入的服务
        $token = $this->authService->extractToken($request);
        $user = $this->userRepository->findByToken($token);

        if (!$user) {
            throw new UnauthorizedException();
        }

        $request = $request->withAttribute('user', $user);
        return $handler->handle($request);
    }
}
```

## 最佳实践

### 1. 服务命名

::: tip 命名规范
- 使用小写字母和点号分隔：`user.service`、`email.provider`
- 带类型的服务：`cache.redis`、`queue.amqp`
- 接口使用完整类名：`App\Contracts\UserRepositoryInterface`
:::

```php
// ✅ 推荐的命名方式
App::di()->set('user.service', $userService);
App::di()->set('payment.gateway.stripe', $stripeGateway);
App::di()->set('notification.channel.email', $emailChannel);

// ❌ 避免的命名方式
App::di()->set('UserService', $userService);  // 大写开头
App::di()->set('user_service', $userService); // 下划线分隔
App::di()->set('us', $userService);           // 过于简短
```

### 2. 工厂函数 vs 直接实例

::: warning 选择原则
- **工厂函数**：服务有依赖、需要配置、或创建成本高
- **直接实例**：简单值、配置数组、或无依赖对象
:::

```php
// ✅ 工厂函数：有依赖的服务
App::di()->set('userService', function() {
    return new UserService(App::db(), App::cache());
});

// ✅ 直接实例：简单配置
App::di()->set('api.version', '1.0');
App::di()->set('api.endpoints', [
    'users' => '/api/users',
    'orders' => '/api/orders'
]);

// ❌ 错误：有依赖但直接实例化
App::di()->set('userService', new UserService()); // 依赖未满足
```

### 3. 服务注册时机

```php
class App extends AppExtend
{
    public function init(Bootstrap $bootstrap): void
    {
        // ✅ 注册无依赖的基础服务
        App::di()->set('module.version', '1.0.0');
        App::di()->set('module.name', 'MyModule');
    }

    public function register(Bootstrap $bootstrap): void
    {
        // ✅ 注册有依赖的复杂服务
        App::di()->set('userService', function() {
            return new UserService(App::db());
        });
    }

    public function boot(Bootstrap $bootstrap): void
    {
        // ✅ 配置已注册的服务
        if (App::di()->has('userService')) {
            $service = App::di()->get('userService');
            $service->configure();
        }
    }
}
```

### 4. 避免循环依赖

```php
// ❌ 错误：循环依赖
App::di()->set('serviceA', function() {
    return new ServiceA(App::di()->get('serviceB'));
});

App::di()->set('serviceB', function() {
    return new ServiceB(App::di()->get('serviceA')); // 循环依赖
});

// ✅ 正确：使用事件或第三方服务解耦
App::di()->set('serviceA', function() {
    return new ServiceA(App::event());
});

App::di()->set('serviceB', function() {
    return new ServiceB(App::event());
});
```

### 5. 测试友好的设计

```php
// 生产环境注册
public function register(Bootstrap $bootstrap): void
{
    if (!App::di()->has('userRepository')) {
        App::di()->set('userRepository', function() {
            return new DatabaseUserRepository(App::db());
        });
    }
}

// 测试中可以注入 Mock
// 在测试中：
App::di()->set('userRepository', new MockUserRepository());
```

## 调试和故障排除

### 1. 查看已注册的服务

```php
// 检查特定服务是否已注册
if (App::di()->has('userService')) {
    echo "UserService is registered\n";
}

// 获取所有已注册的服务名称（PHP-DI 特定方法）
$services = App::di()->getKnownEntryNames();
foreach ($services as $serviceName) {
    echo "Registered: $serviceName\n";
}
```

### 2. 服务创建调试

```php
App::di()->set('userService', function() {
    echo "Creating UserService...\n"; // 调试输出

    $service = new UserService(App::db());

    echo "UserService created successfully\n";
    return $service;
});
```

### 3. 常见错误

#### 服务未注册

```php
// 错误：NotFoundException
try {
    $service = App::di()->get('nonExistentService');
} catch (DI\NotFoundException $e) {
    echo "Service not found: " . $e->getMessage();
}

// 正确：先检查再获取
if (App::di()->has('myService')) {
    $service = App::di()->get('myService');
} else {
    // 提供默认行为或抛出自定义异常
}
```

#### 循环依赖

```php
// 错误信息类似：
// DI\DependencyException: Circular dependency detected while
// trying to resolve entry 'serviceA'
```

## 性能考虑

### 1. 延迟加载

```php
// ✅ 延迟加载：只在需要时创建
App::di()->set('expensiveService', function() {
    return new ExpensiveService(); // 只在第一次 get() 时创建
});

// ❌ 立即加载：在注册时就创建
App::di()->set('expensiveService', new ExpensiveService()); // 立即创建
```

### 2. 缓存配置对象

```php
// ✅ 缓存配置读取
App::di()->set('apiConfig', function() {
    static $config = null;
    if ($config === null) {
        $config = App::config('api')->toArray(); // 只读取一次
    }
    return $config;
});
```

通过合理使用依赖注入，您可以构建更加模块化、可测试和可维护的应用程序。DuxLite 的 DI 容器为您提供了强大而灵活的服务管理能力。