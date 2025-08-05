# 控制器开发

控制器是 DuxLite API 开发的核心组件，负责处理 HTTP 请求并返回响应。

## 基本概念

### 控制器结构

DuxLite 控制器使用标准的三参数模式：

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function getUser(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 控制器逻辑
        return send($response, '获取成功', $data);
    }
}
```

### 核心特性

- **PSR-7 兼容**：遵循 PSR-7 HTTP 消息接口标准
- **三参数模式**：统一的 `(Request, Response, Args)` 签名
- **响应统一**：使用 `send()` 函数统一响应格式

## 请求处理

### 获取查询参数

```php
public function getUsers(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 获取查询参数
    $params = $request->getQueryParams();
    $page = (int) ($params['page'] ?? 1);
    $limit = (int) ($params['limit'] ?? 20);
    $search = $params['search'] ?? '';
    
    // 构建查询
    $query = User::query();
    
    if ($search) {
        $query->where('name', 'like', "%{$search}%");
    }
    
    // 使用框架的分页方法
    $users = $query->paginate($limit);
    
    return send($response, '获取成功', $users);
}
```

### 获取请求头

```php
public function handleRequest(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 获取请求头
    $userAgent = $request->getHeaderLine('User-Agent');
    $authorization = $request->getHeaderLine('Authorization');
    $contentType = $request->getHeaderLine('Content-Type');
    $acceptLanguage = $request->getHeaderLine('Accept-Language');
    
    return send($response, '请求头获取成功', [
        'user_agent' => $userAgent,
        'content_type' => $contentType,
        'accept_language' => $acceptLanguage
    ]);
}
```

### 获取请求体数据

```php
public function createUser(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 获取请求体数据
    $data = $request->getParsedBody();
    
    // 数据验证
    $validated = \Core\Validator\Validator::parser($data, [
        'name' => [
            ['required', '姓名不能为空'],
            ['lengthMin', 2, '姓名至少需要2个字符']
        ],
        'email' => [
            ['required', '邮箱不能为空'],
            ['email', '邮箱格式不正确']
        ],
        'status' => [
            ['optional', '状态值可选'],
            ['integer', '状态值必须是整数']
        ]
    ]);
    
    // 创建用户（使用验证后的数据）
    $user = User::create([
        'name' => $validated->name,
        'email' => $validated->email,
        'status' => (int) ($validated->status ?? 1)
    ]);
    
    return send($response, '创建成功', $user->transform(), [], 201);
}
```

### 文件上传处理

```php
public function uploadAvatar(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 获取上传文件
    $uploadedFiles = $request->getUploadedFiles();
    $avatar = $uploadedFiles['avatar'] ?? null;
    
    if (!$avatar || $avatar->getError() !== UPLOAD_ERR_OK) {
        throw new \Core\Handlers\ExceptionBusiness('请选择头像文件');
    }
    
    // 验证文件类型
    $allowedTypes = ['image/jpeg', 'image/png'];
    if (!in_array($avatar->getClientMediaType(), $allowedTypes)) {
        throw new \Core\Handlers\ExceptionBusiness('只支持 JPEG、PNG 格式');
    }
    
    // 保存文件
    $filename = uniqid() . '.jpg';
    $directory = '/uploads/avatars';
    $avatar->moveTo($directory . '/' . $filename);
    
    return send($response, '上传成功', [
        'avatar_url' => $directory . '/' . $filename
    ]);
}
```

## 数据验证

使用 DuxLite 内置的验证器进行数据验证：

```php
public function updateUser(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $userId = (int) $args['id'];
    $data = $request->getParsedBody();
    
    // 验证规则
    $validated = \Core\Validator\Validator::parser($data, [
        'name' => [
            ['required', '姓名不能为空'],
            ['lengthMin', 2, '姓名至少需要2个字符'],
            ['lengthMax', 50, '姓名最多50个字符']
        ],
        'email' => [
            ['required', '邮箱不能为空'],
            ['email', '邮箱格式不正确']
        ],
        'age' => [
            ['optional', '年龄可选'],
            ['integer', '年龄必须是整数'],
            ['min', 18, '年龄不能小于18岁'],
            ['max', 100, '年龄不能大于100岁']
        ]
    ]);
    
    // 更新用户
    $user = User::find($userId);
    if (!$user) {
        throw new \Core\Handlers\ExceptionNotFound('用户不存在');
    }
    
    $user->update([
        'name' => $validated->name,
        'email' => $validated->email,
        'age' => $validated->age
    ]);
    
    return send($response, '更新成功', $user->transform());
}
```

## 分页处理

使用模型的 `paginate()` 方法处理分页：

```php
public function getPosts(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $params = $request->getQueryParams();
    $limit = (int) ($params['limit'] ?? 20);
    
    // 构建查询
    $query = Post::query();
    
    // 添加筛选条件
    if (!empty($params['category'])) {
        $query->where('category', $params['category']);
    }
    
    if (!empty($params['status'])) {
        $query->where('status', (int) $params['status']);
    }
    
    // 分页查询 - 框架自动处理分页参数
    $posts = $query->paginate($limit);
    
    return send($response, '获取成功', $posts);
}
```

## 错误处理

直接抛出异常，框架会自动处理：

```php
public function deleteUser(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $userId = (int) $args['id'];
    
    $user = User::find($userId);
    if (!$user) {
        throw new \Core\Handlers\ExceptionNotFound('用户不存在');
    }
    
    // 业务检查
    if ($user->posts()->exists()) {
        throw new \Core\Handlers\ExceptionBusiness('用户下还有文章，无法删除');
    }
    
    $user->delete();
    
    return send($response, '删除成功');
}
```

### 常用异常类型

```php
// 404 错误
throw new \Core\Handlers\ExceptionNotFound('资源不存在');

// 业务逻辑错误
throw new \Core\Handlers\ExceptionBusiness('操作失败的原因');

// 数据验证错误
throw new \Core\Handlers\ExceptionValidator($validator->errors());

// 权限错误  
throw new \Core\Handlers\ExceptionBusiness('权限不足', 403);
```

## HTTP 方法示例

```php
#[RouteGroup(app: 'api', route: '/posts')]
class PostController
{
    // GET /posts - 获取列表
    #[Route(methods: 'GET', route: '')]
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $posts = Post::paginate(20);
        return send($response, '获取成功', $posts);
    }
    
    // GET /posts/{id} - 获取详情
    #[Route(methods: 'GET', route: '/{id}')]
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $post = Post::findOrFail($args['id']);
        return send($response, '获取成功', $post->transform());
    }
    
    // POST /posts - 创建
    #[Route(methods: 'POST', route: '')]
    public function store(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $request->getParsedBody();
        $post = Post::create($data);
        return send($response, '创建成功', $post->transform(), [], 201);
    }
    
    // PUT /posts/{id} - 更新
    #[Route(methods: 'PUT', route: '/{id}')]
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $post = Post::findOrFail($args['id']);
        $post->update($request->getParsedBody());
        return send($response, '更新成功', $post->transform());
    }
    
    // DELETE /posts/{id} - 删除
    #[Route(methods: 'DELETE', route: '/{id}')]
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $post = Post::findOrFail($args['id']);
        $post->delete();
        return send($response, '删除成功');
    }
}
```

DuxLite 控制器开发简单直接，专注于处理 HTTP 请求和响应，复杂的业务逻辑应该放在服务层或模型中处理。