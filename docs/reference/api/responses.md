# 响应处理

DuxLite 基于 PSR-7 标准提供了简洁的响应处理机制，通过助手函数可以快速构建各种类型的响应。

## 基本响应

### send() 函数

用于构建标准的 JSON 响应：

```php
function send(
    ResponseInterface $response,
    string $message,
    array|object|null $data = null,
    array $meta = [],
    int $code = 200
): ResponseInterface
```

#### 基础用法

```php
// 成功响应
return send($response, '操作成功');

// 带数据的响应
return send($response, '获取成功', [
    'id' => 1,
    'name' => '张三',
    'email' => 'zhangsan@example.com'
]);

// 带元数据的响应（分页等）
return send($response, '获取成功', $users, [
    'total' => 100,
    'page' => 1,
    'limit' => 10
]);

// 自定义状态码
return send($response, '创建成功', $newUser, [], 201);

// 错误情况应该抛出异常，而不是返回错误响应
throw new \Core\Handlers\ExceptionBusiness('操作失败');
```

#### 标准响应格式

```json
{
  "code": 200,
  "message": "操作成功",
  "data": {
    "id": 1,
    "name": "张三",
    "email": "zhangsan@example.com"
  },
  "meta": {
    "total": 100,
    "page": 1,
    "limit": 10
  }
}
```

### sendText() 函数

用于返回纯文本响应：

```php
// 基础文本响应
return sendText($response, '这是纯文本内容');

// 带状态码的文本响应
return sendText($response, '页面未找到', 404);

// 多行文本
$content = "第一行\n第二行\n第三行";
return sendText($response, $content);
```

### sendTpl() 函数

用于渲染模板并返回 HTML（基于内置视图渲染器）：

```php
return sendTpl($response, 'user/profile', [
    'user' => $user->transform()
], 'web');
```

## 原生 SlimPHP 响应

除了助手函数，也可以使用原生的 PSR-7 响应方法：

### JSON 响应

```php
public function customJson(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $data = [
        'status' => 'success',
        'message' => '自定义响应',
        'data' => ['key' => 'value']
    ];

    $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
    $response->getBody()->write($payload);

    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
}
```

### 文件下载

```php
public function downloadFile(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $filePath = '/path/to/file.pdf';
    
    if (!file_exists($filePath)) {
        throw new \Core\Handlers\ExceptionNotFound('文件不存在');
    }

    $fileContent = file_get_contents($filePath);
    $response->getBody()->write($fileContent);

    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', 'attachment; filename="file.pdf"')
        ->withHeader('Content-Length', (string) filesize($filePath))
        ->withStatus(200);
}
```

### 重定向

```php
public function redirect(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 临时重定向 (302)
    return $response
        ->withHeader('Location', '/login')
        ->withStatus(302);
}

public function permanentRedirect(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 永久重定向 (301)
    return $response
        ->withHeader('Location', 'https://newdomain.com/page')
        ->withStatus(301);
}
```

## 分页响应

使用 `format_data()` 函数处理分页数据：

```php
public function getUsers(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 分页查询
    $users = User::paginate(20);

    // 格式化分页数据
    ["data" => $data, "meta" => $meta] = format_data($users, function ($user) {
        return $user->transform();
    });

    return send($response, '获取成功', $data, $meta);
}
```

## 错误响应

**推荐做法：直接抛出异常，不建议使用 send() 返回错误响应。**

框架会自动处理异常并返回统一格式的错误响应：

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


## 响应头设置

### 常用响应头

```php
public function setHeaders(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $data = ['message' => 'Hello World'];

    return send($response, '成功', $data)
        // 安全头
        ->withHeader('X-Content-Type-Options', 'nosniff')
        ->withHeader('X-Frame-Options', 'DENY')
        
        // 缓存控制
        ->withHeader('Cache-Control', 'public, max-age=3600')
        
        // 自定义头
        ->withHeader('X-API-Version', '1.0');
}
```

### CORS 头

```php
public function corsResponse(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $data = ['message' => 'CORS enabled'];

    return send($response, '成功', $data)
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
}
```

## 常用状态码

**成功响应使用 send()：**
```php
return send($response, '操作成功', $data, [], 200);      // OK
return send($response, '创建成功', $data, [], 201);      // Created
return send($response, '删除成功', null, [], 204);       // No Content
```

**错误情况直接抛出异常：**
```php
// 客户端错误
throw new \Core\Handlers\ExceptionBusiness('请求参数错误');     // 400 Bad Request
throw new \Core\Handlers\ExceptionNotAuth('未授权访问');       // 401 Unauthorized  
throw new \Core\Handlers\ExceptionBusiness('权限不足', 403);   // 403 Forbidden
throw new \Core\Handlers\ExceptionNotFound('资源不存在');      // 404 Not Found
throw new \Core\Handlers\ExceptionValidator($errors);         // 422 Validation Error

// 服务器错误
throw new \Core\Handlers\ExceptionError('系统内部错误');       // 500 Internal Server Error
```

## 响应示例

### JSON 响应字段说明

使用 `send()` 函数返回的 JSON 响应包含以下字段：

```json
{
  "code": 200,
  "message": "操作成功",
  "data": {
    // 实际数据
  },
  "meta": {
    // 元数据（分页信息等）
  }
}
```

#### 字段说明

| 字段 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `code` | `int` | ✅ | HTTP 状态码，表示请求处理结果 |
| `message` | `string` | ✅ | 响应消息，描述操作结果 |
| `data` | `object\|array\|null` | ❌ | 实际返回的数据，成功时包含业务数据 |
| `meta` | `object` | ❌ | 元数据，通常包含分页、统计等信息 |

#### 常见响应示例

**成功响应（带数据）**
```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "id": 1,
    "name": "张三",
    "email": "zhangsan@example.com"
  },
  "meta": null
}
```

**成功响应（分页数据）**
```json
{
  "code": 200,
  "message": "获取成功",
  "data": [
    {"id": 1, "name": "张三"},
    {"id": 2, "name": "李四"}
  ],
  "meta": {
    "total": 100,
    "page": 1,
    "limit": 20
  }
}
```

**创建成功响应**
```json
{
  "code": 201,
  "message": "创建成功",
  "data": {
    "id": 101,
    "name": "新用户",
    "email": "newuser@example.com"
  },
  "meta": null
}
```

**错误响应（由异常自动生成）**
```json
{
  "code": 400,
  "message": "请求参数错误",
  "data": {
    "name": ["姓名不能为空"],
    "email": ["邮箱格式不正确"]
  },
  "meta": null
}
```

**无数据成功响应**
```json
{
  "code": 204,
  "message": "删除成功",
  "data": null,
  "meta": null
}
```

DuxLite 的响应处理简单直接，通过 `send()` 和 `sendText()` 函数可以快速构建标准化的响应，同时也支持使用原生 PSR-7 方法构建复杂响应。
