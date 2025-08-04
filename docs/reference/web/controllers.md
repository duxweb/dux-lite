# 控制器系统

DuxLite 控制器系统基于 PSR-7 标准，支持标准控制器和资源控制器两种模式，提供完整的 RESTful API 开发解决方案。

## 设计理念

### 基础控制器理念
- **PSR-7 兼容**：完全兼容 PSR-7 HTTP 消息接口标准
- **依赖注入**：支持构造函数依赖注入和服务容器
- **灵活路由**：支持注解路由和编程式路由定义
- **中间件集成**：无缝集成认证、权限、缓存等中间件

### 资源控制器理念
- **标准化 CRUD**：自动提供完整的增删改查操作
- **路由注解驱动**：使用 `#[Resource]` 注解自动生成路由
- **自动验证**：集成数据验证和格式化机制
- **权限自动集成**：自动配置权限检查中间件
- **事件钩子系统**：支持操作前后的业务扩展

## 基础控制器

### PSR-7 控制器方法签名

所有控制器方法必须遵循标准的 PSR-7 方法签名：

```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

public function action(
    ServerRequestInterface $request,    // HTTP 请求对象
    ResponseInterface $response,        // HTTP 响应对象
    array $args                        // 路由参数数组
): ResponseInterface {
    // 控制器逻辑
    return $response;
}
```

### 控制器类型

#### 注解控制器

```php
use Core\Attribute\Route;
use Core\Attribute\RouteGroup;

#[RouteGroup(pattern: '/user')]
class UserController
{
    #[Route(methods: 'GET', pattern: '/profile')]
    public function profile(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = $request->getAttribute('auth');
        
        return send($response, 'ok', [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]);
    }
    
    #[Route(methods: 'POST', pattern: '/update')]
    public function updateProfile(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = $request->getParsedBody();
        
        $validator = \Core\Validator\Validator::parser($data, [
            'username' => [['required', '用户名不能为空']],
            'email' => [['email', '邮箱格式无效']]
        ]);
        
        return send($response, 'ok', $validator->toArray());
    }
}
```

#### 资源控制器

```php
use Core\Attribute\Resource;
use Core\Controller\Resources;

#[Resource(app: 'api', route: '/users')]
class UserController extends Resources
{
    protected string $model = User::class;
    
    // 自动提供 CRUD 操作：
    // GET    /users        list()    列表
    // POST   /users        create()  创建  
    // GET    /users/{id}   show()    详情
    // PUT    /users/{id}   store()   更新
    // DELETE /users/{id}   delete()  删除
}
```

### 请求处理

#### 获取请求数据

```php
public function handleRequest(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 获取路由参数
    $userId = $args['id'] ?? null;
    
    // 获取查询参数
    $queryParams = $request->getQueryParams();
    $page = (int) ($queryParams['page'] ?? 1);
    
    // 获取请求体数据
    $bodyData = $request->getParsedBody();
    $name = $bodyData['name'] ?? '';
    
    // 获取上传文件
    $uploadedFiles = $request->getUploadedFiles();
    $avatar = $uploadedFiles['avatar'] ?? null;
    
    // 获取请求头
    $userAgent = $request->getHeaderLine('User-Agent');
    
    // 获取认证信息
    $auth = $request->getAttribute('auth');
    
    return send($response, 'ok', [
        'user_id' => $userId,
        'page' => $page,
        'name' => $name,
        'has_avatar' => $avatar !== null
    ]);
}
```

#### 数据验证

```php
use Core\Validator\Validator;
use Core\Handlers\ExceptionBusiness;

public function validateAndStore(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $data = $request->getParsedBody();
    
    $validator = Validator::parser($data, [
        'username' => [
            ['required', '用户名不能为空'],
            ['lengthMin', 3, '用户名至少3个字符'],
            ['regex', '/^[a-zA-Z0-9_]+$/', '用户名格式无效']
        ],
        'email' => [
            ['required', '邮箱不能为空'],
            ['email', '邮箱格式无效']
        ],
        'age' => [
            ['numeric', '年龄必须是数字'],
            ['min', 18, '年龄不能小于18']
        ]
    ]);
    
    $user = User::create($validator->toArray());
    
    return send($response, 'ok', $user->transform());
}
```

### 响应处理

#### 标准响应

```php
// 成功响应
return send($response, 'ok', $data);

// 分页响应
return send($response, 'ok', $data, $meta);

// 错误响应（通过异常）
throw new ExceptionBusiness('用户不存在', 404);
```

#### 文本和模板响应

```php
// 文本响应
return sendText($response, '<h1>Hello World</h1>');

// 模板响应
return sendTpl($response, 'user/profile', ['user' => $user]);
```

### 文件上传处理

```php
public function uploadAvatar(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $uploadedFiles = $request->getUploadedFiles();
    $file = $uploadedFiles['avatar'];
    
    if ($file->getError() !== UPLOAD_ERR_OK) {
        throw new ExceptionBusiness('文件上传失败');
    }
    
    // 保存文件
    $path = 'uploads/avatar.jpg';
    \Core\App::storage()->put($path, $file->getStream()->getContents());
    
    return send($response, 'ok', [
        'url' => \Core\App::storage()->url($path),
    ]);
}
```

## 资源控制器

### Resource 注解详解

#### 基础用法

```php
use Core\Attribute\Resource;
use Core\Attribute\Action;
use Core\Controller\Resources;

#[Resource(
    app: 'api',           // 必需：路由应用名
    route: '/users',      // 必需：资源路由前缀
)]
class UserController extends Resources
{
    protected string $model = User::class;
}
```

#### 自动生成的路由

| HTTP方法 | 路由路径 | 控制器方法 | 路由名称 | 功能描述 |
|----------|----------|-----------|----------|----------|
| **GET** | `/users` | `list()` | `api.users.list` | 获取用户列表 |
| **POST** | `/users` | `create()` | `api.users.create` | 创建新用户 |
| **GET** | `/users/{id}` | `show()` | `api.users.show` | 获取单个用户 |
| **PUT** | `/users/{id}` | `store()` | `api.users.store` | 更新用户 |
| **DELETE** | `/users/{id}` | `delete()` | `api.users.delete` | 删除用户 |

#### Resource 注解参数

```php
#[Resource(
    app: 'api',                              // 路由应用名
    route: '/users',                         // 资源路由前缀
    actions: ['list', 'show', 'create'],    // 可选：限制启用的操作
    middleware: [AuthMiddleware::class],     // 可选：自定义中间件
    softDelete: true                         // 可选：启用软删除功能
)]
class UserController extends Resources {}
```

### Action 注解详解

#### 自定义操作

```php
#[Resource(app: 'api', route: '/users')]
class UserController extends Resources
{
    protected string $model = User::class;
    
    #[Action(methods: 'POST', route: '/batch')]
    public function batch(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = $request->getParsedBody();
        $action = $data['action'] ?? '';
        $ids = $data['ids'] ?? [];
        
        if (empty($ids)) {
            throw new ExceptionBusiness('请选择要操作的数据');
        }
        
        switch ($action) {
            case 'delete':
                User::whereIn('id', $ids)->delete();
                break;
            case 'enable':
                User::whereIn('id', $ids)->update(['status' => 1]);
                break;
            default:
                throw new ExceptionBusiness('不支持的操作');
        }
        
        return send($response, 'ok', '操作成功');
    }
    
    #[Action(methods: 'GET', route: '/stats')]
    public function stats(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = [
            'total' => User::count(),
            'active' => User::where('status', 1)->count(),
            'today' => User::whereDate('created_at', today())->count(),
        ];
        
        return send($response, 'ok', $data);
    }
}
```

### 数据验证和格式化

#### 验证规则定义

```php
#[Resource(app: 'api', route: '/users')]
class UserController extends Resources
{
    protected string $model = User::class;
    
    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'name' => [
                ['required', '用户名不能为空'],
                ['lengthMin', 3, '用户名至少3个字符'],
                ['lengthMax', 20, '用户名不能超过20个字符']
            ],
            'email' => [
                ['required', '邮箱不能为空'],
                ['email', '邮箱格式无效']
            ],
            'password' => [
                ['required', '密码不能为空'],
                ['lengthMin', 8, '密码至少8个字符']
            ]
        ];
    }
    
    public function format(\Core\Validator\Data $data, ServerRequestInterface $request, array $args): array
    {
        $formatted = [
            'name' => $data->name,
            'email' => strtolower($data->email),
            'status' => (int) ($data->status ?? 1),
        ];
        
        if (isset($data->password)) {
            $formatted['password'] = password_hash($data->password, PASSWORD_DEFAULT);
        }
        
        return $formatted;
    }
}
```

### 查询和过滤

#### 查询定制

```php
class UserController extends Resources
{
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $params = $request->getQueryParams();
        
        // 搜索功能
        if (!empty($params['keyword'])) {
            $query->where(function($q) use ($params) {
                $q->where('name', 'like', "%{$params['keyword']}%")
                  ->orWhere('email', 'like', "%{$params['keyword']}%");
            });
        }
        
        // 状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }
        
        // 时间范围筛选
        if (!empty($params['start_date'])) {
            $query->where('created_at', '>=', $params['start_date']);
        }
    }
    
    public function queryOne(Builder $query, ServerRequestInterface $request, array $args): void
    {
        // 预加载关联数据
        $query->with(['profile', 'roles']);
    }
}
```

### 事件钩子系统

#### 操作钩子

```php
class UserController extends Resources
{
    public function createBefore(\Core\Validator\Data $data, mixed $model): void
    {
        // 创建前处理
        $auth = \Core\App::auth();
        $model->created_by = $auth['id'];
    }
    
    public function createAfter(\Core\Validator\Data $data, mixed $model): void
    {
        // 创建后处理
        \Core\App::log()->info('用户创建', ['user_id' => $model->id]);
        
        // 发送欢迎邮件
        $this->sendWelcomeEmail($model);
    }
    
    public function storeBefore(\Core\Validator\Data $data, mixed $model): void
    {
        // 更新前处理
        $model->updated_by = \Core\App::auth()['id'];
    }
    
    public function storeAfter(\Core\Validator\Data $data, mixed $model): void
    {
        // 更新后处理
        \Core\App::cache()->forget("user.{$model->id}");
    }
    
    public function delBefore(mixed $model): void
    {
        // 删除前检查
        if ($model->posts()->exists()) {
            throw new ExceptionBusiness('该用户还有关联文章，无法删除');
        }
    }
    
    public function delAfter(mixed $model): void
    {
        // 删除后清理
        $model->profile()->delete();
    }
}
```

## 中间件集成

### 使用认证中间件

```php
use Core\Auth\AuthMiddleware;
use Core\Permission\PermissionMiddleware;

#[Resource(
    app: 'api',
    route: '/users',
    middleware: [
        AuthMiddleware::class,
        PermissionMiddleware::class
    ]
)]
class UserController extends Resources
{
    // 中间件会自动验证用户登录和权限
}
```

### 跳过中间件

```php
#[Action(methods: 'GET', route: '/public', auth: false)]
public function publicInfo(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 跳过认证检查
    return send($response, 'ok', ['message' => '公开信息']);
}
```

## 完整示例

### 产品管理系统

```php
use Core\Attribute\Resource;
use Core\Attribute\Action;
use Core\Controller\Resources;
use Core\Auth\AuthMiddleware;

#[Resource(
    app: 'admin',
    route: '/admin/products',
    middleware: [AuthMiddleware::class]
)]
class ProductController extends Resources
{
    protected string $model = Product::class;
    
    // 分页配置
    protected array $pagination = [
        'status' => true,
        'pageSize' => 15,
    ];
    
    // 字段控制
    public array $includesMany = ['id', 'name', 'price', 'stock', 'status', 'created_at'];
    public array $excludesMany = ['description', 'content'];
    
    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'name' => [
                ['required', '产品名称不能为空'],
                ['lengthMax', 100, '产品名称不能超过100个字符']
            ],
            'price' => [
                ['required', '价格不能为空'],
                ['numeric', '价格必须是数字'],
                ['min', 0.01, '价格不能小于0.01']
            ],
            'stock' => [
                ['required', '库存不能为空'],
                ['integer', '库存必须是整数'],
                ['min', 0, '库存不能小于0']
            ]
        ];
    }
    
    public function format(\Core\Validator\Data $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'name' => $data->name,
            'price' => number_format((float)$data->price, 2),
            'stock' => (int) $data->stock,
            'status' => (int) ($data->status ?? 1),
        ];
    }
    
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $params = $request->getQueryParams();
        
        // 预加载关联
        $query->with(['category']);
        
        // 搜索
        if (!empty($params['keyword'])) {
            $query->where('name', 'like', "%{$params['keyword']}%");
        }
        
        // 状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }
        
        // 价格范围
        if (!empty($params['min_price'])) {
            $query->where('price', '>=', $params['min_price']);
        }
        if (!empty($params['max_price'])) {
            $query->where('price', '<=', $params['max_price']);
        }
    }
    
    public function createBefore(\Core\Validator\Data $data, mixed $model): void
    {
        $auth = \Core\App::auth();
        $model->created_by = $auth['id'];
    }
    
    public function createAfter(\Core\Validator\Data $data, mixed $model): void
    {
        // 记录操作日志
        \Core\App::log()->info('产品创建', ['product_id' => $model->id]);
    }
    
    // 批量上架
    #[Action(methods: 'POST', route: '/batch-publish')]
    public function batchPublish(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = $request->getParsedBody();
        $ids = $data['ids'] ?? [];
        
        if (empty($ids)) {
            throw new ExceptionBusiness('请选择要上架的产品');
        }
        
        $count = Product::whereIn('id', $ids)->update(['status' => 1]);
        
        return send($response, 'ok', ['updated_count' => $count]);
    }
    
    // 库存预警
    #[Action(methods: 'GET', route: '/stock-alert')]
    public function stockAlert(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $lowStockProducts = Product::where('stock', '<', 10)
            ->with('category')
            ->get()
            ->map(function($product) {
                return $product->transform();
            });
        
        return send($response, 'ok', [
            'low_stock_count' => $lowStockProducts->count(),
            'products' => $lowStockProducts
        ]);
    }
}
```

## Resources 基类 API

### 核心属性

```php
protected string $key = "id"              // 主键字段名
protected string $label = ""              // 资源标签
protected string $model                   // 关联的模型类名
protected bool $tree = false              // 是否为树形结构
protected array $pagination = [           // 分页配置
    'status' => true, 
    'pageSize' => 10
];
public array $includesMany = []           // 多条数据允许字段
public array $excludesMany = []           // 多条数据排除字段
public array $includesOne = []            // 单条数据允许字段
public array $excludesOne = []            // 单条数据排除字段
```

### 核心方法

```php
// 初始化方法
public function init(ServerRequestInterface $request, ResponseInterface $response, array $args): void

// 获取模型查询构造器
public function queryModel(string $model): Builder

// 数据转换方法
public function transform(object $item): array

// 通用查询条件
public function query(Builder $query): void

// 多条数据查询条件
public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void

// 单条数据查询条件
public function queryOne(Builder $query, ServerRequestInterface $request, array $args): void

// 数据验证规则
public function validator(array $data, ServerRequestInterface $request, array $args): array

// 数据入库格式化
public function format(\Core\Validator\Data $data, ServerRequestInterface $request, array $args): array

// 多条数据元数据
public function metaMany(object|array $query, array $data, ServerRequestInterface $request, array $args): array

// 单条数据元数据
public function metaOne(mixed $data, ServerRequestInterface $request, array $args): array
```

## 最佳实践

### 1. 控制器职责分离

```php
// ✅ 推荐：控制器专注于请求处理
class UserController
{
    private UserService $userService;
    
    public function __construct()
    {
        $this->userService = \Core\App::di()->get(UserService::class);
    }
    
    public function createUser(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 验证请求数据
        $validated = \Core\Validator\Validator::parser($request->getParsedBody(), [
            'username' => [['required', '用户名不能为空']],
            'email' => [['email', '邮箱格式无效']]
        ]);
        
        // 调用服务层处理业务逻辑
        $user = $this->userService->createUser($validated->toArray());
        
        // 返回响应
        return send($response, 'ok', $user);
    }
}
```

### 2. 统一错误处理

```php
public function sensitiveOperation(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    try {
        $result = $this->performOperation();
        return send($response, 'ok', $result);
        
    } catch (\Exception $e) {
        \Core\App::log()->error('意外错误', ['error' => $e->getMessage()]);
        throw new ExceptionBusiness('系统错误', 500);
    }
}
```

### 3. 合理使用中间件

```php
// ✅ 推荐：在路由级别配置中间件
#[Resource(
    app: 'api',
    route: '/users',
    middleware: [AuthMiddleware::class, RateLimitMiddleware::class]
)]
class UserController extends Resources
{
    // 所有方法自动享受认证和限流保护
}
```

### 4. 资源控制器最佳实践

#### 合理使用钩子

```php
// ✅ 推荐：在钩子中处理业务逻辑
public function createAfter(\Core\Validator\Data $data, mixed $model): void
{
    // 发送通知
    $this->sendNotification($model);
    
    // 更新缓存
    \Core\App::cache()->tags(['users'])->flush();
    
    // 记录日志
    \Core\App::log()->info('用户创建', ['user_id' => $model->id]);
}
```

#### 数据验证

```php
// ✅ 推荐：根据操作类型设置不同验证规则
public function validator(array $data, ServerRequestInterface $request, array $args): array
{
    $method = $request->getMethod();
    
    $rules = [
        'name' => [['required', '名称不能为空']],
        'email' => [['required', '邮箱不能为空'], ['email', '邮箱格式无效']],
    ];
    
    if ($method === 'POST') {
        // 创建时需要密码
        $rules['password'] = [['required', '密码不能为空']];
    }
    
    return $rules;
}
```

#### 查询优化

```php
// ✅ 推荐：预加载关联避免N+1问题
public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
{
    $query->with(['category', 'tags']);
}

// ✅ 推荐：限制查询字段
public array $includesMany = ['id', 'name', 'price', 'status', 'created_at'];
public array $excludesMany = ['description', 'content']; // 排除大字段
```

通过这套完整的控制器系统，可以快速构建功能完整、安全可靠的 RESTful API，同时保持代码的简洁和可维护性。