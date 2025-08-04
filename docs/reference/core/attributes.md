# 注解系统

DuxLite 基于 PHP 8+ 的原生注解（Attributes）系统构建了完整的元数据编程支持，通过注解实现路由定义、资源管理、事件监听、任务调度等功能的自动发现和注册。这种声明式编程方式极大地简化了配置管理和提高了代码的可读性。

## 基本概念

### 设计理念

DuxLite 注解系统采用**声明式编程、自动发现、类型安全**的设计：

- **声明式编程**：通过注解声明组件的行为和配置，减少样板代码
- **自动发现**：框架启动时自动扫描并注册注解，无需手动配置
- **类型安全**：基于 PHP 原生注解，编译时类型检查和IDE支持
- **元数据驱动**：通过注解元数据驱动框架功能，实现配置与代码分离
- **可扩展性**：支持自定义注解和处理器，满足特定业务需求

### 核心特性

- **路由注解**：声明式路由定义和参数约束
- **资源注解**：RESTful 资源控制器自动注册
- **事件注解**：事件监听器的声明式定义
- **权限注解**：细粒度的访问控制声明
- **任务注解**：计划任务的 Cron 表达式配置
- **文档注解**：API 文档的自动生成支持

## 注解系统架构

### 注解扫描和加载

```php
// src/App/Attribute.php
class Attribute 
{
    /**
     * 加载应用注解
     */
    static function load(array $apps, bool $docs = false): array 
    {
        $data = [];
        
        foreach ($apps as $vo) {
            $reflection = new \ReflectionClass($vo);
            $appDir = dirname($reflection->getFileName());
            $appDirLen = strlen($appDir);
            
            // 查找所有 PHP 文件
            $files = Finder::findFiles("*/*.php")->from($appDir);
            
            foreach ($files as $file) {
                $dirName = str_replace('/', '\\', substr($file->getPath(), $appDirLen + 1));
                
                // 跳过测试文件
                if (str_ends_with($dirName, 'Test')) {
                    continue;
                }
                
                // 构建完整类名
                $class = $reflection->getNamespaceName() . "\\" . $dirName . "\\" . $file->getBasename(".php");
                
                if (!class_exists($class)) {
                    continue;
                }
                
                // 跳过文档注解类本身
                if ($docs && str_starts_with($class, 'Core\\Docs\\Attribute\\')) {
                    continue;
                }
                
                $classRef = new \ReflectionClass($class);
                
                // 收集类级别注解
                $classAttributes = [
                    'class' => $class,
                    'annotations' => []
                ];
                
                $attributes = $classRef->getAttributes();
                foreach ($attributes as $attribute) {
                    if (!class_exists($attribute->getName())) {
                        continue;
                    }
                    
                    $classAttributes['annotations'][] = [
                        'name' => $attribute->getName(),
                        'class' => $class,
                        'params' => $attribute->getArguments()
                    ];
                }
                
                // 收集方法级别注解
                $methods = $classRef->getMethods();
                foreach ($methods as $method) {
                    if ($docs && str_starts_with($method->getDeclaringClass()->getName(), 'Core\\Docs\\Attribute\\')) {
                        continue;
                    }
                    
                    $attributes = $method->getAttributes();
                    foreach ($attributes as $attribute) {
                        if (!class_exists($attribute->getName())) {
                            continue;
                        }
                        
                        $classAttributes['annotations'][] = [
                            'name' => $attribute->getName(),
                            'class' => $class . ":" . $method->getName(),
                            'method' => $method->getName(),
                            'params' => $attribute->getArguments()
                        ];
                    }
                }
                
                $data[] = $classAttributes;
            }
        }
        
        return $data;
    }
    
    /**
     * 获取缓存的注解数据
     */
    static function getCache(array $apps): array 
    {
        $cachePath = data_path("/cache/attributes.cache");
        
        if (!file_exists($cachePath)) {
            $data = self::load($apps);
            self::setCache($data);
            return $data;
        }
        
        $cache = file_get_contents($cachePath);
        if (!$cache) {
            $data = self::load($apps);
            self::setCache($data);
            return $data;
        }
        
        return unserialize($cache);
    }
    
    /**
     * 设置注解缓存
     */
    static function setCache(array $data): void 
    {
        $cacheDir = dirname(data_path("/cache/attributes.cache"));
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        file_put_contents(data_path("/cache/attributes.cache"), serialize($data));
    }
}
```

### 注解工具类

```php
// src/Utils/Attribute.php
class Attribute
{
    /**
     * 获取请求类或方法注解参数
     */
    public static function getRequestParams(Request $request, string $name): mixed
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        
        if (!$route) {
            return null;
        }
        
        $callable = $route->getCallable();
        
        // 解析控制器和方法
        if (!is_array($callable)) {
            [$class, $method] = explode(':', $callable);
        } else {
            $class = $callable[0];
            $method = $callable[1] ?? null;
        }
        
        $reflectionClass = new \ReflectionClass($class);
        
        // 先检查方法级别的注解
        if ($method) {
            $reflectionMethod = $reflectionClass->getMethod($method);
            $attributes = $reflectionMethod->getAttributes();
            
            foreach ($attributes as $attribute) {
                $args = $attribute->getArguments();
                if (isset($args[$name])) {
                    return $args[$name];
                }
            }
        }
        
        // 再检查类级别的注解
        $attributes = $reflectionClass->getAttributes();
        foreach ($attributes as $attribute) {
            $args = $attribute->getArguments();
            if (isset($args[$name])) {
                return $args[$name];
            }
        }
        
        return null;
    }
    
    /**
     * 获取类的所有注解
     */
    public static function getClassAttributes(string $class): array
    {
        if (!class_exists($class)) {
            return [];
        }
        
        $reflectionClass = new \ReflectionClass($class);
        $attributes = [];
        
        // 获取类级别注解
        foreach ($reflectionClass->getAttributes() as $attribute) {
            $attributes[] = [
                'name' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
                'instance' => $attribute->newInstance()
            ];
        }
        
        return $attributes;
    }
    
    /**
     * 获取方法的所有注解
     */
    public static function getMethodAttributes(string $class, string $method): array
    {
        if (!class_exists($class)) {
            return [];
        }
        
        $reflectionClass = new \ReflectionClass($class);
        
        if (!$reflectionClass->hasMethod($method)) {
            return [];
        }
        
        $reflectionMethod = $reflectionClass->getMethod($method);
        $attributes = [];
        
        foreach ($reflectionMethod->getAttributes() as $attribute) {
            $attributes[] = [
                'name' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
                'instance' => $attribute->newInstance()
            ];
        }
        
        return $attributes;
    }
    
    /**
     * 检查类是否有指定注解
     */
    public static function hasClassAttribute(string $class, string $attributeClass): bool
    {
        if (!class_exists($class) || !class_exists($attributeClass)) {
            return false;
        }
        
        $reflectionClass = new \ReflectionClass($class);
        $attributes = $reflectionClass->getAttributes($attributeClass);
        
        return !empty($attributes);
    }
    
    /**
     * 检查方法是否有指定注解
     */
    public static function hasMethodAttribute(string $class, string $method, string $attributeClass): bool
    {
        if (!class_exists($class) || !class_exists($attributeClass)) {
            return false;
        }
        
        $reflectionClass = new \ReflectionClass($class);
        
        if (!$reflectionClass->hasMethod($method)) {
            return false;
        }
        
        $reflectionMethod = $reflectionClass->getMethod($method);
        $attributes = $reflectionMethod->getAttributes($attributeClass);
        
        return !empty($attributes);
    }
}
```

## 内置注解类型

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
        public array $where = []                // 参数约束
    ) {}
}

// 使用示例
class UserController
{
    #[Route('/users', ['GET'], 'user.index')]
    public function index(): ResponseInterface 
    {
        // 获取用户列表
    }
    
    #[Route('/users/{id}', ['GET'], 'user.show', where: ['id' => '\d+'])]
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface 
    {
        // 显示用户详情
    }
    
    #[Route('/users', ['POST'], 'user.store', middleware: ['auth'])]
    public function store(ServerRequestInterface $request): ResponseInterface 
    {
        // 创建新用户
    }
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
        public array $where = []                // 参数约束
    ) {}
}

// 使用示例
#[RouteGroup('/api/v1', middleware: ['auth', 'throttle'], name: 'api.v1.')]
class ApiController
{
    #[Route('/profile', ['GET'], 'profile')]
    public function profile(): ResponseInterface 
    {
        // 实际路由: GET /api/v1/profile，名称: api.v1.profile
    }
    
    #[Route('/settings', ['PUT'], 'settings')]
    public function updateSettings(): ResponseInterface 
    {
        // 实际路由: PUT /api/v1/settings，名称: api.v1.settings
    }
}
```

### 资源注解

#### Resource 注解

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
class Resource
{
    public function __construct(
        public string $app = '',                // 应用名称
        public string $route = '',              // 路由前缀
        public string $name = '',               // 路由名称前缀
        public array $only = [],                // 仅包含的操作
        public array $except = [],              // 排除的操作
        public array $middleware = [],          // 中间件
        public bool $auth = true                // 是否需要认证
    ) {}
}

// 使用示例
#[Resource(app: 'admin', route: '/admin/users', name: 'admin.users')]
class UserController extends Resources
{
    protected string $model = User::class;
    
    // 自动生成路由：
    // GET    /admin/users       -> index()   名称: admin.users.index
    // GET    /admin/users/{id}  -> show()    名称: admin.users.show
    // POST   /admin/users       -> store()   名称: admin.users.store
    // PUT    /admin/users/{id}  -> update()  名称: admin.users.update
    // DELETE /admin/users/{id}  -> destroy() 名称: admin.users.destroy
}
```

#### Action 注解

```php
#[\Attribute(\Attribute::TARGET_METHOD)]
class Action
{
    public function __construct(
        public array $methods = ['GET'],        // HTTP方法
        public string $path = '',               // 路径
        public string $name = '',               // 名称
        public array $middleware = []           // 中间件
    ) {}
}

// 使用示例
#[Resource(app: 'admin', route: '/admin/users', name: 'admin.users')]
class UserController extends Resources
{
    // 标准资源操作会自动注册
    
    #[Action(['POST'], '/{id}/activate', 'activate')]
    public function activate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface 
    {
        // 自定义操作: POST /admin/users/{id}/activate
        // 路由名称: admin.users.activate
    }
    
    #[Action(['GET'], '/export', 'export', middleware: ['permission:user.export'])]
    public function export(): ResponseInterface 
    {
        // 导出用户: GET /admin/users/export
        // 路由名称: admin.users.export
    }
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
        public int $priority = 0                // 优先级
    ) {}
}

// 使用示例
class UserEventListener
{
    #[Listener('user.created', priority: 100)]
    public function onUserCreated(UserCreatedEvent $event): void
    {
        // 用户创建事件处理
        $user = $event->getUser();
        
        // 发送欢迎邮件
        $this->sendWelcomeEmail($user);
        
        // 记录日志
        App::log()->info('新用户注册', [
            'user_id' => $user->id,
            'username' => $user->username
        ]);
    }
    
    #[Listener('user.updated')]
    #[Listener('user.profile_updated')]
    public function onUserUpdated(UserUpdatedEvent $event): void
    {
        // 监听多个事件
        $user = $event->getUser();
        
        // 清除用户缓存
        Cache::tags(['user:' . $user->id])->flush();
    }
    
    #[Listener('user.deleted', priority: -100)]
    public function onUserDeleted(UserDeletedEvent $event): void
    {
        // 用户删除后的清理工作（低优先级）
        $userId = $event->getUserId();
        
        // 清理用户相关数据
        $this->cleanupUserData($userId);
    }
}
```

### 任务调度注解

#### Scheduler 注解

```php
#[\Attribute(\Attribute::TARGET_METHOD)]
class Scheduler 
{
    public function __construct(
        public string $cron                     // Cron 表达式
    ) {}
}

// 使用示例
class ScheduledTasks
{
    #[Scheduler('0 2 * * *')]  // 每天凌晨2点
    public function dailyBackup(): void
    {
        App::log('scheduler')->info('开始每日备份任务');
        
        // 执行数据库备份
        $this->backupDatabase();
        
        // 清理旧文件
        $this->cleanupOldFiles();
    }
    
    #[Scheduler('*/15 * * * *')]  // 每15分钟
    public function processQueue(): void
    {
        // 处理队列任务
        $queue = App::queue();
        
        while ($job = $queue->pop()) {
            try {
                $job->handle();
            } catch (Exception $e) {
                App::log('scheduler')->error('队列任务失败', [
                    'job' => get_class($job),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    #[Scheduler('0 0 1 * *')]  // 每月1号
    public function monthlyReport(): void
    {
        // 生成月度报告
        $this->generateMonthlyReport();
    }
}
```

### 权限注解

#### Permission 注解

```php
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class RequirePermission
{
    public function __construct(
        public string|array $permissions,       // 权限名称
        public string $operator = 'and'         // 操作符: and/or
    ) {}
}

// 使用示例
#[RequirePermission('admin.access')]
class AdminController
{
    #[RequirePermission('user.read')]
    public function index(): ResponseInterface 
    {
        // 需要 admin.access AND user.read 权限
    }
    
    #[RequirePermission(['user.create', 'user.manage'], operator: 'or')]
    public function store(): ResponseInterface 
    {
        // 需要 admin.access AND (user.create OR user.manage) 权限
    }
    
    #[RequirePermission('user.delete')]
    public function destroy(): ResponseInterface 
    {
        // 需要 admin.access AND user.delete 权限
    }
}
```

### 文档注解

#### API 文档注解

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
class Api
{
    public function __construct(
        public string $title = '',              // API 标题
        public string $description = '',        // API 描述
        public string $version = '1.0'          // API 版本
    ) {}
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Docs
{
    public function __construct(
        public string $summary = '',            // 接口摘要
        public string $description = '',        // 接口描述
        public array $tags = []                 // 标签
    ) {}
}

// 使用示例
#[Api(title: '用户管理 API', description: '用户相关接口', version: '2.0')]
#[Resource(app: 'api', route: '/api/users', name: 'api.users')]
class UserApiController extends Resources
{
    #[Docs(summary: '获取用户列表', description: '分页获取用户列表', tags: ['用户'])]
    #[Params(['page' => '页码', 'limit' => '每页数量'])]
    #[Result(['users' => '用户列表', 'total' => '总数量'])]
    public function index(): ResponseInterface 
    {
        // 获取用户列表
    }
    
    #[Docs(summary: '创建用户', description: '创建新用户账户', tags: ['用户'])]
    #[Payload(['username' => '用户名', 'email' => '邮箱', 'password' => '密码'])]
    #[ResultMessage('用户创建成功')]
    public function store(): ResponseInterface 
    {
        // 创建用户
    }
}
```

## 注解处理器

### 路由注解处理器

```php
class RouteAttributeProcessor
{
    /**
     * 处理路由注解
     */
    public function processRouteAttributes(array $attributes): void
    {
        $routeCollector = App::web()->getRouteCollector();
        
        foreach ($attributes as $item) {
            foreach ($item['annotations'] as $annotation) {
                if ($annotation['name'] === Route::class) {
                    $this->registerRoute($routeCollector, $annotation);
                } elseif ($annotation['name'] === RouteGroup::class) {
                    $this->registerRouteGroup($routeCollector, $annotation, $item);
                }
            }
        }
    }
    
    private function registerRoute($routeCollector, array $annotation): void
    {
        $params = $annotation['params'];
        $class = $annotation['class'];
        
        $path = $params['path'] ?? '/';
        $methods = $params['methods'] ?? ['GET'];
        $name = $params['name'] ?? '';
        $middleware = $params['middleware'] ?? [];
        $where = $params['where'] ?? [];
        
        $route = $routeCollector->map($methods, $path, $class);
        
        if ($name) {
            $route->setName($name);
        }
        
        if (!empty($middleware)) {
            foreach ($middleware as $mw) {
                $route->add($mw);
            }
        }
        
        if (!empty($where)) {
            foreach ($where as $param => $pattern) {
                $route->setArgument($param, $pattern);
            }
        }
    }
    
    private function registerRouteGroup($routeCollector, array $annotation, array $item): void
    {
        $params = $annotation['params'];
        
        $prefix = $params['prefix'] ?? '';
        $middleware = $params['middleware'] ?? [];
        $namePrefix = $params['name'] ?? '';
        $where = $params['where'] ?? [];
        
        // 为组内的路由添加前缀和中间件
        $group = $routeCollector->group($prefix, function ($group) use ($item, $namePrefix, $middleware, $where) {
            $this->processGroupRoutes($group, $item, $namePrefix, $middleware, $where);
        });
        
        if (!empty($middleware)) {
            foreach ($middleware as $mw) {
                $group->add($mw);
            }
        }
    }
    
    private function processGroupRoutes($group, array $item, string $namePrefix, array $groupMiddleware, array $groupWhere): void
    {
        foreach ($item['annotations'] as $annotation) {
            if ($annotation['name'] === Route::class) {
                $params = $annotation['params'];
                
                $path = $params['path'] ?? '/';
                $methods = $params['methods'] ?? ['GET'];
                $name = $params['name'] ?? '';
                $middleware = array_merge($groupMiddleware, $params['middleware'] ?? []);
                $where = array_merge($groupWhere, $params['where'] ?? []);
                
                $route = $group->map($methods, $path, $annotation['class']);
                
                if ($name) {
                    $route->setName($namePrefix . $name);
                }
                
                if (!empty($middleware)) {
                    foreach ($middleware as $mw) {
                        $route->add($mw);
                    }
                }
                
                if (!empty($where)) {
                    foreach ($where as $param => $pattern) {
                        $route->setArgument($param, $pattern);
                    }
                }
            }
        }
    }
}
```

### 事件注解处理器

```php
class EventAttributeProcessor
{
    /**
     * 处理事件监听器注解
     */
    public function processEventAttributes(array $attributes): void
    {
        $eventDispatcher = App::event();
        
        foreach ($attributes as $item) {
            foreach ($item['annotations'] as $annotation) {
                if ($annotation['name'] === Listener::class) {
                    $this->registerEventListener($eventDispatcher, $annotation);
                }
            }
        }
    }
    
    private function registerEventListener($eventDispatcher, array $annotation): void
    {
        $params = $annotation['params'];
        $class = $annotation['class'];
        
        $event = $params['event'];
        $priority = $params['priority'] ?? 0;
        
        [$className, $method] = explode(':', $class);
        
        $eventDispatcher->listen($event, function (...$args) use ($className, $method) {
            $instance = new $className();
            return call_user_func_array([$instance, $method], $args);
        }, $priority);
    }
}
```

## 自定义注解

### 创建自定义注解

```php
// 定义缓存注解
#[\Attribute(\Attribute::TARGET_METHOD)]
class Cache
{
    public function __construct(
        public int $ttl = 3600,                 // 缓存时间（秒）
        public string $key = '',                // 缓存键
        public array $tags = []                 // 缓存标签
    ) {}
}

// 使用自定义注解
class ProductController
{
    #[Cache(ttl: 1800, key: 'products.list', tags: ['products'])]
    #[Route('/products', ['GET'], 'products.index')]
    public function index(): ResponseInterface 
    {
        // 产品列表，缓存30分钟
    }
    
    #[Cache(ttl: 7200, key: 'product.{id}', tags: ['products', 'product:{id}'])]
    #[Route('/products/{id}', ['GET'], 'products.show')]
    public function show(array $args): ResponseInterface 
    {
        // 产品详情，缓存2小时
    }
}
```

### 自定义注解处理器

```php
class CacheAttributeProcessor
{
    /**
     * 处理缓存注解
     */
    public function processCacheAttributes(array $attributes): void
    {
        foreach ($attributes as $item) {
            foreach ($item['annotations'] as $annotation) {
                if ($annotation['name'] === Cache::class) {
                    $this->registerCacheMiddleware($annotation);
                }
            }
        }
    }
    
    private function registerCacheMiddleware(array $annotation): void
    {
        $params = $annotation['params'];
        $class = $annotation['class'];
        
        // 创建缓存中间件
        $middleware = new CacheMiddleware(
            $params['ttl'] ?? 3600,
            $params['key'] ?? '',
            $params['tags'] ?? []
        );
        
        // 将中间件绑定到路由
        // 这里需要与路由系统集成
    }
}

class CacheMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $ttl,
        private string $keyTemplate,
        private array $tags
    ) {}
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 生成缓存键
        $cacheKey = $this->generateCacheKey($request);
        
        // 检查缓存
        $cache = App::cache();
        if ($cache->has($cacheKey)) {
            $cachedResponse = $cache->get($cacheKey);
            return $this->createResponseFromCache($cachedResponse);
        }
        
        // 执行处理器
        $response = $handler->handle($request);
        
        // 缓存响应
        if ($response->getStatusCode() === 200) {
            $cacheData = [
                'body' => (string) $response->getBody(),
                'headers' => $response->getHeaders(),
                'status' => $response->getStatusCode()
            ];
            
            if (!empty($this->tags)) {
                $cache->tags($this->tags)->set($cacheKey, $cacheData, $this->ttl);
            } else {
                $cache->set($cacheKey, $cacheData, $this->ttl);
            }
        }
        
        return $response;
    }
    
    private function generateCacheKey(ServerRequestInterface $request): string
    {
        if ($this->keyTemplate) {
            $key = $this->keyTemplate;
            
            // 替换路由参数
            $route = RouteContext::fromRequest($request)->getRoute();
            if ($route) {
                $args = $route->getArguments();
                foreach ($args as $name => $value) {
                    $key = str_replace('{' . $name . '}', $value, $key);
                }
            }
            
            return $key;
        }
        
        // 默认键生成策略
        $uri = $request->getUri();
        return 'route:' . md5($uri->getPath() . '?' . $uri->getQuery());
    }
    
    private function createResponseFromCache(array $cacheData): ResponseInterface
    {
        $response = new Response($cacheData['status']);
        $response->getBody()->write($cacheData['body']);
        
        foreach ($cacheData['headers'] as $name => $values) {
            $response = $response->withHeader($name, $values);
        }
        
        return $response->withHeader('X-Cache', 'HIT');
    }
}
```

## 注解管理命令

### 注解缓存命令

```php
class AttributeCacheCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('attribute:cache')
             ->setDescription('生成注解缓存')
             ->addOption('clear', 'c', InputOption::VALUE_NONE, '清除缓存');
    }
    
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('clear')) {
            return $this->clearCache($output);
        }
        
        return $this->generateCache($output);
    }
    
    private function generateCache(OutputInterface $output): int
    {
        $output->writeln('<info>正在生成注解缓存...</info>');
        
        $apps = App::$registerApp;
        $attributes = \Core\App\Attribute::load($apps);
        
        \Core\App\Attribute::setCache($attributes);
        
        $output->writeln('<info>注解缓存生成完成</info>');
        $output->writeln('- 扫描应用: ' . count($apps));
        $output->writeln('- 发现类: ' . count($attributes));
        
        $annotationCount = 0;
        foreach ($attributes as $item) {
            $annotationCount += count($item['annotations']);
        }
        
        $output->writeln('- 注解总数: ' . $annotationCount);
        
        return Command::SUCCESS;
    }
    
    private function clearCache(OutputInterface $output): int
    {
        $cachePath = data_path("/cache/attributes.cache");
        
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $output->writeln('<info>注解缓存已清除</info>');
        } else {
            $output->writeln('<comment>注解缓存文件不存在</comment>');
        }
        
        return Command::SUCCESS;
    }
}
```

### 注解列表命令

```php
class AttributeListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('attribute:list')
             ->setDescription('显示所有注解')
             ->addOption('type', 't', InputOption::VALUE_OPTIONAL, '注解类型过滤')
             ->addOption('class', 'c', InputOption::VALUE_OPTIONAL, '类名过滤');
    }
    
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $typeFilter = $input->getOption('type');
        $classFilter = $input->getOption('class');
        
        $attributes = App::attributes();
        
        $table = new Table($output);
        $table->setHeaders(['类型', '类/方法', '参数']);
        
        foreach ($attributes as $item) {
            foreach ($item['annotations'] as $annotation) {
                // 应用过滤器
                if ($typeFilter && !str_contains($annotation['name'], $typeFilter)) {
                    continue;
                }
                
                if ($classFilter && !str_contains($annotation['class'], $classFilter)) {
                    continue;
                }
                
                $type = basename(str_replace('\\', '/', $annotation['name']));
                $class = $annotation['class'];
                $params = json_encode($annotation['params'], JSON_UNESCAPED_UNICODE);
                
                if (strlen($params) > 50) {
                    $params = substr($params, 0, 47) . '...';
                }
                
                $table->addRow([$type, $class, $params]);
            }
        }
        
        $table->render();
        
        return Command::SUCCESS;
    }
}
```

## 最佳实践

### 1. 注解设计原则

```php
// ✅ 推荐：使用明确的注解参数
#[Route('/users/{id}', ['GET'], 'users.show', where: ['id' => '\d+'])]
public function show(): ResponseInterface {}

// ❌ 避免：参数不明确
#[Route('/users/{id}', ['GET'])]
public function show(): ResponseInterface {}
```

### 2. 注解组织

```php
// ✅ 推荐：按功能组织注解
#[RequirePermission('user.read')]
#[Cache(ttl: 1800, tags: ['users'])]
#[Docs(summary: '获取用户详情', tags: ['用户管理'])]
#[Route('/users/{id}', ['GET'], 'users.show')]
public function show(): ResponseInterface {}

// ✅ 推荐：使用类级别注解减少重复
#[RouteGroup('/api/v1', middleware: ['auth'], name: 'api.v1.')]
#[RequirePermission('api.access')]
class ApiController {}
```

### 3. 性能优化

```php
// ✅ 推荐：生产环境使用注解缓存
if (App::$debug) {
    $attributes = Attribute::load($apps);
} else {
    $attributes = Attribute::getCache($apps);
}

// ✅ 推荐：定期清理注解缓存
# crontab
0 2 * * * php /path/to/project/dux attribute:cache --clear && php /path/to/project/dux attribute:cache
```

### 4. 错误处理

```php
// ✅ 推荐：优雅的注解处理错误
try {
    $attributes = App::attributes();
} catch (ReflectionException $e) {
    App::log()->error('注解处理失败', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    // 使用默认配置
    $attributes = [];
}
```

### 5. 调试和开发

```php
// ✅ 推荐：开发环境启用详细的注解日志
if (App::$debug) {
    App::log()->debug('加载注解', [
        'class' => $class,
        'annotations' => $annotations
    ]);
}

// ✅ 推荐：使用命令行工具查看注解
php dux attribute:list --type=Route
php dux attribute:list --class=UserController
```

通过遵循这些最佳实践，您可以充分利用 DuxLite 注解系统的强大功能，构建出清晰、可维护的应用程序。注解系统不仅简化了配置管理，还提高了代码的可读性和开发效率。