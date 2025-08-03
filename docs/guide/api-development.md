# API 开发

基于路由组的灵活 API 接口开发，适用于自定义业务逻辑和复杂交互场景。

## 路由组配置

### 基础路由组

```php
// config/route.php
use Core\Route\Route;

$route = new Route();

// API 路由组
$api = $route->group('/api/v1', middleware: ['cors']);

// 用户相关 API
$userApi = $api->group('/users');
$userApi->get('/', UserController::class . ':index');
$userApi->post('/', UserController::class . ':create');
$userApi->get('/{id}', UserController::class . ':show');
$userApi->put('/{id}', UserController::class . ':update');
$userApi->delete('/{id}', UserController::class . ':delete');

return $route;
```

### 带中间件的路由组

```php
// 需要认证的 API
$authApi = $api->group('/auth', middleware: ['auth']);
$authApi->get('/profile', UserController::class . ':profile');
$authApi->put('/profile', UserController::class . ':updateProfile');

// 管理员 API
$adminApi = $api->group('/admin', middleware: ['auth', 'admin']);
$adminApi->get('/users', UserController::class . ':adminList');
```

## 控制器开发

### 基础控制器

```php
// app/Controller/UserController.php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Core\Handlers\ExceptionBusiness;

class UserController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // 获取查询参数
        $params = $request->getQueryParams();
        $keyword = $params['keyword'] ?? '';
        
        $query = User::query();
        
        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }
        
        $list = $query->paginate();
        ["data" => $data, "meta" => $meta] = format_data($list, function ($item) {
            return $item->transform();
        });
        
        return send($response, 'ok', $data, $meta);
    }
    
    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // 获取请求体数据
        $data = $request->getParsedBody();
        
        // 数据验证
        $validator = \Core\Validator\Validator::parser($data, [
            'name' => [['required', '用户名不能为空'], ['lengthMax', 50, '用户名过长']],
            'email' => [['required', '邮箱不能为空'], ['email', '邮箱格式不正确']],
        ]);
        
        $user = User::create($validator->toArray());
        
        return send($response, 'ok', $user->transform());
    }
    
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // 获取路径参数
        $id = $args['id'];
        
        $user = User::find($id);
        if (!$user) {
            throw new ExceptionBusiness('用户不存在', 404);
        }
        
        return send($response, 'ok', $user->transform());
    }
    
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $data = $request->getParsedBody();
        
        $user = User::find($id);
        if (!$user) {
            throw new ExceptionBusiness('用户不存在', 404);
        }
        
        $user->update($data);
        
        return send($response, 'ok', $user->transform());
    }
    
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        
        $user = User::find($id);
        if (!$user) {
            throw new ExceptionBusiness('用户不存在', 404);
        }
        
        $user->delete();
        
        return send($response, 'ok', '删除成功');
    }
}
```

## 数据处理

### 获取请求数据

```php
// 获取查询参数
$params = $request->getQueryParams();
$page = $params['page'] ?? 1;
$keyword = $params['keyword'] ?? '';

// 获取请求体数据
$data = $request->getParsedBody();

// 获取路径参数
$id = $args['id'];
$category = $args['category'] ?? '';

// 获取请求头
$token = $request->getHeaderLine('Authorization');
$contentType = $request->getHeaderLine('Content-Type');
```

### 数据验证

```php
public function validateData(array $data): \Core\Validator\Data
{
    return \Core\Validator\Validator::parser($data, [
        'name' => [['required', '用户名不能为空'], ['lengthMax', 50, '用户名过长']],
        'email' => [['required', '邮箱不能为空'], ['email', '邮箱格式不正确']],
        'phone' => [['required', '手机号不能为空'], ['regex', '/^1[3-9]\d{9}$/', '手机号格式不正确']],
    ]);
}
```

### 常用验证规则

```php
[
    'field' => [
        ['required', '字段不能为空'],
        ['lengthMax', 50, '字段过长'],
        ['lengthMin', 6, '字段过短'],
        ['email', '邮箱格式不正确'],
        ['numeric', '必须是数字'],
        ['regex', '/^pattern$/', '格式不正确'],
    ]
]
```

## 文件上传

### 文件上传

```php
public function upload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
{
    // 获取上传文件
    $uploadedFiles = $request->getUploadedFiles();
    $file = $uploadedFiles['file'];
    
    if ($file->getError() !== UPLOAD_ERR_OK) {
        throw new ExceptionBusiness('文件上传失败');
    }
    
    // 保存文件
    $path = 'uploads/file.jpg';
    \Core\App::storage()->put($path, $file->getStream()->getContents());
    
    return send($response, 'ok', [
        'url' => \Core\App::storage()->url($path),
    ]);
}
```

## 响应处理

### JSON 响应

```php
// 成功响应
return send($response, 'ok', $data, $meta);

// 分页响应
["data" => $data, "meta" => $meta] = format_data($list, function ($item) {
    return $item->transform();
});
return send($response, 'ok', $data, $meta);
```

**响应示例：**
```json
{
    "code": 200,
    "message": "ok",
    "data": [...],
    "meta": {
        "total": 100,
        "page": 1
    }
}
```

### 文本响应

```php
// 纯文本响应
return sendText($response, 'Hello World');

// HTML 响应
return sendText($response, '<h1>欢迎</h1><p>这是 HTML 内容</p>');

// 自定义状态码
return sendText($response, '页面不存在', 404);
```

### 模板响应

```php
// 渲染模板
return sendTpl($response, 'user/profile', [
    'user' => $user,
    'title' => '用户资料'
]);

// 指定模板引擎
return sendTpl($response, 'admin/dashboard', $data, 'admin');

// 错误页面
return sendTpl($response, 'error/404', [], 'web', 404);
```

## 异常处理

### 业务异常

```php
// 抛出业务异常
if (!$user) {
    throw new ExceptionBusiness('用户不存在', 404);
}

// 验证失败异常
if ($data['age'] < 18) {
    throw new ExceptionBusiness('年龄必须大于18岁');
}
```

### 系统异常

```php
try {
    $result = $this->processData($data);
} catch (\Exception $e) {
    \Core\App::log()->error('处理失败', [
        'error' => $e->getMessage(),
        'data' => $data
    ]);
    throw new ExceptionBusiness('系统处理失败');
}
```

通过路由组方式开发 API，你可以获得最大的灵活性，适合复杂的业务逻辑和自定义交互需求。