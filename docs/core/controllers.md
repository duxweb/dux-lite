# 控制器

控制器是 DuxLite 应用程序中处理 HTTP 请求的核心组件。DuxLite 提供了灵活的控制器系统，完全兼容 PSR-7 HTTP 消息接口，支持多种控制器模式。

## 设计理念

DuxLite 控制器系统采用**简单、标准、可扩展的请求处理**设计：

- **PSR-7 兼容**：完全兼容 PSR-7 HTTP 消息接口标准
- **依赖注入**：支持构造函数依赖注入和服务容器
- **灵活路由**：支持注解路由和编程式路由定义
- **中间件集成**：无缝集成认证、权限、缓存等中间件

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

**参数说明：**
- **$request**: 包含所有请求信息（头信息、参数、请求体等）
- **$response**: 用于构建 HTTP 响应
- **$args**: 来自 URL 路径的路由参数（如 `/users/{id}` 中的 `id`）

## 控制器类型

### 1. 注解控制器

使用注解定义路由的控制器，推荐方式：

```php
<?php
namespace App\Web\Controllers;

use Core\Route\Attribute\Route;
use Core\Route\Attribute\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[RouteGroup(app: 'web', route: '/user')]
class UserController
{
    #[Route(methods: 'GET', route: '/profile')]
    public function profile(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 获取用户资料
        $user = $request->getAttribute('auth'); // 认证中间件注入的用户信息

        return send($response, '获取成功', [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]);
    }

    #[Route(methods: 'POST', route: '/update')]
    public function updateProfile(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = $request->getParsedBody();

        // 数据验证
        $validated = Validator::parser($data, [
            'username' => [['required', '用户名不能为空']],
            'email' => [['email', '邮箱格式无效']]
        ]);

        // 更新逻辑...

        return send($response, '更新成功', $validated->toArray());
    }

    #[Route(methods: 'GET', route: '/list/{page}')]
    public function list(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $page = (int) $args['page']; // 路由参数
        $search = $request->getQueryParams()['search'] ?? ''; // 查询参数

        // 分页查询逻辑...

        return send($response, '获取成功', [
            'page' => $page,
            'search' => $search,
            'users' => []
        ]);
    }
}
```

### 2. 资源控制器

用于快速实现 RESTful API 的控制器，详细内容请参考 [资源管理文档](./resources.md)：

```php
use Core\Resources\Action\Resources;
use Core\Resources\Attribute\Resource;

#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    protected string $model = User::class;

    // 自动提供 CRUD 操作：
    // GET    /admin/users      - 列表
    // GET    /admin/users/{id} - 详情
    // POST   /admin/users      - 创建
    // PUT    /admin/users/{id} - 更新
    // DELETE /admin/users/{id} - 删除
}
```

### 3. 函数式控制器

简单的闭包函数控制器：

```php
// 在路由定义中直接使用闭包
$route->map(['GET'], '/hello', function (
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $response->getBody()->write('Hello World!');
    return $response;
}, 'hello');
```

## 请求处理

### 获取请求数据

```php
public function handleRequest(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 1. 获取路由参数
    $userId = $args['id'] ?? null;

    // 2. 获取查询参数 (?page=1&limit=10)
    $queryParams = $request->getQueryParams();
    $page = (int) ($queryParams['page'] ?? 1);
    $limit = (int) ($queryParams['limit'] ?? 10);

    // 3. 获取请求体数据 (POST/PUT 数据)
    $bodyData = $request->getParsedBody();
    $name = $bodyData['name'] ?? '';

    // 4. 获取上传文件
    $uploadedFiles = $request->getUploadedFiles();
    $avatar = $uploadedFiles['avatar'] ?? null;

    // 5. 获取请求头
    $userAgent = $request->getHeaderLine('User-Agent');
    $contentType = $request->getHeaderLine('Content-Type');

    // 6. 获取认证信息（如果使用了认证中间件）
    $auth = $request->getAttribute('auth');
    $currentUserId = $auth['id'] ?? null;

    return send($response, '处理成功', [
        'user_id' => $userId,
        'page' => $page,
        'name' => $name,
        'has_avatar' => $avatar !== null,
        'user_agent' => $userAgent
    ]);
}
```

### 数据验证

```php
use Core\Validator\Validator;
use Core\Handlers\ExceptionValidator;

public function validateAndStore(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    try {
        $data = $request->getParsedBody();

        // 数据验证
        $validated = Validator::parser($data, [
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
                ['min', 18, '年龄不能小于18'],
                ['max', 65, '年龄不能超过65']
            ]
        ]);

        // 验证通过，使用验证后的数据
        $user = User::create([
            'username' => $validated->username,
            'email' => $validated->email,
            'age' => $validated->age
        ]);

        return send($response, '创建成功', $user->toArray());

    } catch (ExceptionValidator $e) {
        // 验证失败，自动返回 422 错误
        throw $e;
    }
}
```

## 响应处理

### 使用 send() 函数

DuxLite 提供了便捷的 `send()` 函数来构建标准化的API响应：

```php
// 基础用法
return send($response, '操作成功', $data);

// 完整参数
return send(
    response: $response,
    message: '操作成功',
    data: $data,
    extend: ['extra_field' => 'value'],
    code: 200
);

// 错误响应
return send($response, '操作失败', null, [], 400);

// 分页响应
return send($response, '获取成功', [
    'items' => $users,
    'pagination' => [
        'current_page' => 1,
        'per_page' => 10,
        'total' => 100
    ]
]);
```

### 自定义响应

```php
public function customResponse(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 1. JSON 响应
    $data = ['message' => 'Hello World'];
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');

    // 2. HTML 响应
    $html = '<h1>Hello World</h1>';
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');

    // 3. 重定向响应
    return $response
        ->withStatus(302)
        ->withHeader('Location', '/login');

    // 4. 文件下载响应
    $file = '/path/to/file.pdf';
    $response->getBody()->write(file_get_contents($file));
    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', 'attachment; filename="file.pdf"');
}
```

## 依赖注入

### 构造函数注入

```php
use Core\App;

class UserController
{
    private LoggerInterface $logger;
    private UserService $userService;

    public function __construct()
    {
        // 从服务容器获取依赖
        $this->logger = App::log();
        $this->userService = App::di()->get(UserService::class);
    }

    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $this->logger->info('访问用户列表');
        $users = $this->userService->getAllUsers();

        return send($response, '获取成功', $users);
    }
}
```

### 方法内注入

```php
public function processOrder(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 动态获取服务
    $orderService = App::di()->get(OrderService::class);
    $cache = App::cache();
    $queue = App::queue();

    $data = $request->getParsedBody();

    // 业务逻辑
    $order = $orderService->createOrder($data);

    // 缓存订单信息
    $cache->set("order:{$order->id}", $order->toArray(), 3600);

    // 推送到队列处理
    $queue->push('process_order', ['order_id' => $order->id]);

    return send($response, '订单创建成功', $order->toArray());
}
```

## 错误处理

### 业务异常处理

```php
use Core\Handlers\ExceptionBusiness;

public function deleteUser(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $userId = $args['id'];

    $user = User::find($userId);
    if (!$user) {
        throw new ExceptionBusiness('用户不存在', 404);
    }

    if ($user->status === 'admin') {
        throw new ExceptionBusiness('不能删除管理员用户', 403);
    }

    $user->delete();

    return send($response, '删除成功');
}
```

### 系统异常处理

```php
public function processData(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    try {
        // 可能出错的业务逻辑
        $result = $this->riskOperation();

        return send($response, '处理成功', $result);

    } catch (ExceptionBusiness $e) {
        // 业务异常，直接抛出（框架会自动处理）
        throw $e;

    } catch (\Exception $e) {
        // 系统异常，记录日志后返回通用错误
        App::log()->error('系统异常', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        throw new ExceptionBusiness('系统繁忙，请稍后重试', 500);
    }
}
```

## 中间件集成

### 使用认证中间件

```php
use Core\Auth\AuthMiddleware;
use Core\Route\Attribute\RouteGroup;

#[RouteGroup(
    app: 'admin',
    route: '/admin',
    middleware: [AuthMiddleware::class]
)]
class AdminController
{
    public function dashboard(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 获取认证信息
        $auth = $request->getAttribute('auth');
        $userId = $auth['id'];
        $userRole = $auth['role'];

        return send($response, '仪表板数据', [
            'user_id' => $userId,
            'role' => $userRole,
            'stats' => $this->getDashboardStats()
        ]);
    }
}
```

### 跳过中间件

```php
use Core\Route\Attribute\Route;

class PublicController
{
    #[Route(methods: 'GET', route: '/public-info', auth: false)]
    public function publicInfo(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 这个方法跳过认证检查
        return send($response, '公开信息', [
            'app_name' => App::config('use')->get('app.name'),
            'version' => App::$version
        ]);
    }
}
```

## 文件上传处理

### 单文件上传

```php
use Core\Handlers\ExceptionBusiness;

public function uploadAvatar(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $uploadedFiles = $request->getUploadedFiles();

    if (!isset($uploadedFiles['avatar'])) {
        throw new ExceptionBusiness('请选择要上传的文件', 400);
    }

    $uploadedFile = $uploadedFiles['avatar'];

    // 检查上传错误
    if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
        throw new ExceptionBusiness('文件上传失败', 400);
    }

    // 验证文件类型
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($uploadedFile->getClientMediaType(), $allowedTypes)) {
        throw new ExceptionBusiness('只能上传图片文件', 400);
    }

    // 验证文件大小 (2MB)
    if ($uploadedFile->getSize() > 2 * 1024 * 1024) {
        throw new ExceptionBusiness('文件大小不能超过2MB', 400);
    }

    // 保存文件
    $filename = uniqid() . '.' . pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $uploadPath = public_path('uploads/avatars/' . $filename);

    // 确保目录存在
    $uploadDir = dirname($uploadPath);
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uploadedFile->moveTo($uploadPath);

    return send($response, '上传成功', [
        'filename' => $filename,
        'url' => '/uploads/avatars/' . $filename,
        'size' => $uploadedFile->getSize()
    ]);
}
```

### 多文件上传

```php
public function uploadFiles(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $uploadedFiles = $request->getUploadedFiles();

    if (!isset($uploadedFiles['files']) || !is_array($uploadedFiles['files'])) {
        throw new ExceptionBusiness('请选择要上传的文件', 400);
    }

    $results = [];

    foreach ($uploadedFiles['files'] as $uploadedFile) {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            continue; // 跳过错误文件
        }

        $filename = uniqid() . '.' . pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $uploadPath = public_path('uploads/files/' . $filename);

        $uploadedFile->moveTo($uploadPath);

        $results[] = [
            'original_name' => $uploadedFile->getClientFilename(),
            'filename' => $filename,
            'url' => '/uploads/files/' . $filename,
            'size' => $uploadedFile->getSize()
        ];
    }

    return send($response, '上传成功', $results);
}
```

## 最佳实践

### 1. 控制器职责分离

```php
// ✅ 好的实践：控制器专注于请求处理
class UserController
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = App::di()->get(UserService::class);
    }

    public function createUser(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 1. 验证请求数据
        $validated = Validator::parser($request->getParsedBody(), [
            'username' => [['required', '用户名不能为空']],
            'email' => [['email', '邮箱格式无效']]
        ]);

        // 2. 调用服务层处理业务逻辑
        $user = $this->userService->createUser($validated->toArray());

        // 3. 返回响应
        return send($response, '创建成功', $user);
    }
}

// ❌ 避免：在控制器中处理复杂业务逻辑
class BadUserController
{
    public function createUser(...): ResponseInterface
    {
        // 不要在控制器中写复杂的业务逻辑
        // 如数据处理、邮件发送、文件操作等
    }
}
```

### 2. 统一错误处理

```php
// ✅ 使用标准异常处理
public function sensitiveOperation(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    try {
        $result = $this->performOperation();
        return send($response, '操作成功', $result);

    } catch (ValidationException $e) {
        throw new ExceptionValidator($e->getErrors());

    } catch (BusinessException $e) {
        throw new ExceptionBusiness($e->getMessage(), $e->getCode());

    } catch (\Exception $e) {
        App::log()->error('意外错误', ['error' => $e->getMessage()]);
        throw new ExceptionBusiness('系统错误', 500);
    }
}
```

### 3. 合理使用中间件

```php
// ✅ 在路由级别配置中间件
#[RouteGroup(
    app: 'api',
    route: '/api/user',
    middleware: [AuthMiddleware::class, RateLimitMiddleware::class]
)]
class ApiUserController
{
    #[Route(methods: 'GET', route: '/profile')]
    public function profile(...): ResponseInterface
    {
        // 自动享受认证和限流保护
    }

    #[Route(methods: 'GET', route: '/public', auth: false)]
    public function publicInfo(...): ResponseInterface
    {
        // 跳过认证，但仍然受限流保护
    }
}
```

### 4. 响应格式统一

```php
// ✅ 使用统一的响应格式
public function getUserList(...): ResponseInterface
{
    $users = User::paginate(10);

    return send($response, '获取成功', [
        'items' => $users->items(),
        'pagination' => [
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
            'last_page' => $users->lastPage()
        ]
    ]);
}
```

通过这些基础知识，您可以创建灵活、可维护的DuxLite控制器。对于复杂的CRUD操作，建议使用[资源控制器](./resources.md)来快速实现标准化的API接口。