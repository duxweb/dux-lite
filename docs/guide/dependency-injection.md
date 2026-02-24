# 依赖注入

DuxLite 内置了依赖注入（DI）容器，基于 [PHP-DI](https://php-di.org/) 实现。

## 设计理念

**DuxLite 的依赖注入理念：依赖注入只在必要时使用，而不是过度依赖。**

DuxLite 优先使用显式编程风格：

- **显式 > 隐式**：优先使用明确的静态方法调用，如 `App::db()`、`App::cache()`
- **IDE 友好**：显式调用能提供完整的代码提示和类型推断
- **可读性优先**：代码意图清晰，依赖关系一目了然

```php
// ✅ DuxLite 推荐：显式调用，IDE 完整提示
$users = App::db()->table('users')->get();
$cache = App::cache('redis');
$config = App::config('database');

// ❌ 过度依赖注入：增加复杂性
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

## 基本使用

### 1. 获取容器

```php
use Core\App;

// 获取容器实例
$container = App::di();
```

### 2. 注册服务

```php
// 注册具体实例
App::di()->set('userService', new UserService());

// 使用工厂函数（推荐）
App::di()->set('userService', function() {
    return new UserService(App::db(), App::cache());
});
```

### 3. 获取服务

```php
// 获取服务
$userService = App::di()->get('userService');

// 检查服务是否存在
if (App::di()->has('userService')) {
    $userService = App::di()->get('userService');
}
```

## 框架内置服务

DuxLite 框架自动注册了许多内置服务：

```php
// 数据库服务
$db = App::db();
$db = App::di()->get('db');

// 缓存服务
$cache = App::cache();
$cache = App::cache('redis');
$cache = App::di()->get('cache.redis');

// 配置服务
$config = App::config('use');
$config = App::di()->get('config.use');

// 事件服务
$events = App::event();
$events = App::di()->get('events');

// 队列服务
$queue = App::queue();
$queue = App::di()->get('queue');
```

## 服务命名约定

### 基础服务
```php
'db'           // 数据库连接
'events'       // 事件调度器
'permission'   // 权限管理
'route'        // 路由管理
'trans'        // 翻译服务
```

### 带类型的服务
```php
'cache.file'      // 文件缓存
'cache.redis'     // Redis 缓存
'storage.local'   // 本地存储
'config.use'      // 应用配置
```

## 在模块中使用

### 在 App.php 中注册服务

```php
class App extends AppExtend
{
    public function init(Bootstrap $bootstrap): void
    {
        // 注册简单服务
        App::di()->set('myModule.version', '1.0.0');
        
        // 注册配置服务
        App::di()->set('myModule.config', function() {
            return App::config('mymodule');
        });
    }

    public function register(Bootstrap $bootstrap): void
    {
        // 注册复杂服务
        App::di()->set('userService', function() {
            return new UserService(App::db(), App::cache());
        });

        // 注册带条件的服务
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

## 控制器依赖注入

DuxLite 支持在控制器和中间件中使用依赖注入：

```php
class UserController
{
    public function __construct(
        private UserService $userService,
        private ValidatorService $validator
    ) {}

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = \Core\Utils\RequestParam::body($request);
        
        $this->validator->validate($data, UserCreateRules::class);
        $user = $this->userService->create($data);

        $response->getBody()->write(json_encode($user));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

## 高级用法

### 1. 接口绑定

```php
// 绑定接口到具体实现
App::di()->set(UserRepositoryInterface::class, function() {
    return new DatabaseUserRepository(App::db());
});

// 使用接口名获取
$repository = App::di()->get(UserRepositoryInterface::class);
```

### 2. 条件服务注册

```php
public function register(Bootstrap $bootstrap): void
{
    // 根据环境注册不同服务
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

### 3. 服务装饰器

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

## 最佳实践

### 1. 服务命名规范

```php
// ✅ 推荐的命名方式
App::di()->set('user.service', $userService);
App::di()->set('payment.gateway.stripe', $stripeGateway);
App::di()->set('cache.redis', $redisCache);

// ❌ 避免的命名方式
App::di()->set('UserService', $userService);  // 大写开头
App::di()->set('user_service', $userService); // 下划线分隔
App::di()->set('us', $userService);           // 过于简短
```

### 2. 使用工厂函数

```php
// ✅ 工厂函数：有依赖的服务
App::di()->set('userService', function() {
    return new UserService(App::db(), App::cache());
});

// ✅ 直接实例：简单配置
App::di()->set('api.version', '1.0');
App::di()->set('api.config', ['timeout' => 30]);

// ❌ 错误：有依赖但直接实例化
App::di()->set('userService', new UserService()); // 依赖未满足
```

### 3. 避免循环依赖

```php
// ❌ 错误：循环依赖
App::di()->set('serviceA', function() {
    return new ServiceA(App::di()->get('serviceB'));
});
App::di()->set('serviceB', function() {
    return new ServiceB(App::di()->get('serviceA')); // 循环依赖
});

// ✅ 正确：使用事件解耦
App::di()->set('serviceA', function() {
    return new ServiceA(App::event());
});
App::di()->set('serviceB', function() {
    return new ServiceB(App::event());
});
```

### 4. 测试友好的设计

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
App::di()->set('userRepository', new MockUserRepository());
```

## 调试和故障排除

### 检查服务注册

```php
// 检查特定服务是否已注册
if (App::di()->has('userService')) {
    echo "UserService is registered\n";
}

// 获取所有已注册的服务名称
$services = App::di()->getKnownEntryNames();
foreach ($services as $serviceName) {
    echo "Registered: $serviceName\n";
}
```

### 常见错误处理

```php
// 服务未注册错误
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

## 性能优化

### 延迟加载

```php
// ✅ 延迟加载：只在需要时创建
App::di()->set('expensiveService', function() {
    return new ExpensiveService(); // 只在第一次 get() 时创建
});

// ❌ 立即加载：在注册时就创建
App::di()->set('expensiveService', new ExpensiveService()); // 立即创建
```

### 缓存配置对象

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

通过合理使用依赖注入，您可以构建更加模块化、可测试和可维护的应用程序。
