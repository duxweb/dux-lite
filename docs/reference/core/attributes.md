# 注解系统

DuxLite 基于 PHP 8+ 原生注解实现路由、资源、事件、任务等功能的声明式配置。开发者可以使用内置注解快速开发，也可以扩展自定义注解。

## 内置注解

### 路由注解

#### Route - 路由定义

```php
#[Route(methods: 'GET', route: '/users/{id}', name: 'users.show')]
public function show($request, $response, $args) {
    // 处理逻辑
}
```

参数：
- `methods`: HTTP方法数组或字符串
- `route`: 路由路径
- `name`: 路由名称（可选）
- `app`: 路由注册名（可选）
- `auth`: 是否需要认证（默认 true）
- `priority`: 路由优先级（默认 0）

#### RouteGroup - 路由组

```php
#[RouteGroup(app: 'api', route: '/api/v1', name: 'api.v1', middleware: [AuthMiddleware::class])]
class ApiController {
    #[Route(methods: 'GET', route: '/profile')]
    public function profile() {} // 实际路由: GET /api/v1/profile，名称: api.v1.profile
}
```

### 资源注解

#### Resource - 资源控制器

```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources {
    // 自动生成标准 RESTful 路由：
    // GET    /admin/users       -> list()
    // GET    /admin/users/{id}  -> show()
    // POST   /admin/users       -> create()
    // PUT    /admin/users/{id}  -> edit()
    // PATCH  /admin/users/{id}  -> store()
    // DELETE /admin/users/{id}  -> delete()
}
```

#### Action - 自定义资源动作

```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources {
    #[Action(methods: 'POST', route: '/{id}/activate', name: 'activate')]
    public function activate($request, $response, $args) {
        // 自定义动作: POST /admin/users/{id}/activate
    }
}
```

### 事件注解

#### Listener - 事件监听器

```php
class UserEventListener {
    #[Listener('user.created', priority: 100)]
    public function onUserCreated($event) {
        // 处理用户创建事件
    }
    
    #[Listener('user.updated')]
    #[Listener('user.deleted')]
    public function onUserChanged($event) {
        // 监听多个事件
    }
}
```

### 任务调度注解

#### Scheduler - 计划任务

```php
class ScheduledTasks {
    #[Scheduler('0 2 * * *')]  // 每天凌晨2点
    public function dailyBackup() {
        // 执行备份任务
    }
    
    #[Scheduler('*/15 * * * *')]  // 每15分钟
    public function processQueue() {
        // 处理队列任务
    }
}
```

## 扩展自定义注解

### 1. 定义注解类

```php
// 定义缓存注解
use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Cache {
    public function __construct(
        public int $ttl = 3600,        // 缓存时间
        public string $key = '',       // 缓存键
        public array $tags = []        // 缓存标签
    ) {}
}
```

### 2. 使用自定义注解

```php
class ProductController {
    #[Cache(ttl: 1800, key: 'products.list', tags: ['products'])]
    #[Route(methods: 'GET', route: '/products')]
    public function index() {
        // 产品列表，缓存30分钟
    }
}
```

### 3. 处理自定义注解

```php
// 在应用启动时处理缓存注解
class CacheAttributeProcessor {
    public function process() {
        $attributes = App::attributes();
        
        foreach ($attributes as $item) {
            foreach ($item['annotations'] as $annotation) {
                if ($annotation['name'] === Cache::class) {
                    $this->registerCacheMiddleware($annotation);
                }
            }
        }
    }
    
    private function registerCacheMiddleware($annotation) {
        $params = $annotation['params'];
        // 创建并注册缓存中间件
        // 实现具体的缓存逻辑
    }
}
```

## 获取注解信息

### 获取所有注解

```php
// 获取应用中所有注解
$attributes = App::attributes();

foreach ($attributes as $item) {
    $className = $item['class'];
    foreach ($item['annotations'] as $annotation) {
        $annotationName = $annotation['name'];
        $params = $annotation['params'];
        $method = $annotation['method'] ?? null;
    }
}
```

### 获取请求相关注解

```php
// 在中间件或控制器中获取当前请求的注解参数
$cacheConfig = Attribute::getRequestParams($request, 'cache');
if ($cacheConfig) {
    // 应用缓存配置
}
```

## 注解处理最佳实践

### 1. 注解设计原则

```php
// ✅ 推荐：参数明确，有默认值
#[Cache(ttl: 1800, tags: ['users'])]

// ❌ 避免：参数不明确
#[Cache(1800)]
```

### 2. 注解组织

```php
// ✅ 推荐：按功能组织，逻辑清晰
#[Route(methods: 'GET', route: '/users/{id}', name: 'users.show')]
#[Cache(ttl: 600, tags: ['users'])]
#[RequirePermission('user.read')]
public function show() {}
```

### 3. 自定义注解处理

```php
// ✅ 推荐：在应用启动时统一处理
class Bootstrap {
    public function loadApp() {
        // 处理框架内置注解
        App::route()->registerAttribute();
        App::resource()->registerAttribute();
        
        // 处理自定义注解
        $this->processCacheAttributes();
        $this->processPermissionAttributes();
    }
}
```

DuxLite 注解系统为声明式编程提供了强大而灵活的支持，既可以使用内置注解快速开发，也可以扩展自定义注解满足特定需求。
