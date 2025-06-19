# 请求和响应

DuxLite 基于 PSR-7 HTTP 消息接口标准，提供了强大而灵活的请求和响应处理机制。本文档将详细介绍如何在 DuxLite 中处理 HTTP 请求和构建响应。

## 设计理念

DuxLite 请求响应系统采用**基于 PSR-7 标准的现代化 HTTP 处理**设计：

- **PSR-7 兼容**：完全兼容 [PSR-7 HTTP 消息接口](https://www.php-fig.org/psr/psr-7/)标准
- **不可变性**：所有 HTTP 消息对象都是不可变的
- **流式处理**：支持大文件和内存高效的处理
- **标准化**：统一的 HTTP 消息接口
- **自动验证**：集成强大的数据验证机制

```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

public function action(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 处理请求
    $data = $request->getParsedBody();

    // 构建响应
    return send($response, '操作成功', $data);
}
```

## 请求处理

### 数据验证器

DuxLite 提供了强大的 `Validator::parser` 验证器，基于 Valitron 库，支持丰富的验证规则：

```php
use Core\Validator\Validator;

// 验证器基本用法
$validated = Validator::parser($data, [
    'field_name' => [
        ['rule_name', 'error_message'],
        ['rule_name', $param1, $param2, 'error_message']
    ]
]);

// 获取验证后的数据
$value = $validated->field_name;
$array = $validated->toArray();
```

#### 常用验证规则

| 规则 | 参数 | 说明 | 示例 |
|------|------|------|------|
| `required` | - | 必填字段 | `['required', '字段不能为空']` |
| `email` | - | 邮箱格式 | `['email', '邮箱格式无效']` |
| `numeric` | - | 数字类型 | `['numeric', '必须是数字']` |
| `lengthMin` | 最小长度 | 最小字符长度 | `['lengthMin', 6, '至少6个字符']` |
| `lengthMax` | 最大长度 | 最大字符长度 | `['lengthMax', 50, '不超过50个字符']` |
| `length` | 固定长度 | 固定字符长度 | `['length', 11, '必须是11位']` |
| `min` | 最小值 | 数值最小值 | `['min', 1, '不能小于1']` |
| `max` | 最大值 | 数值最大值 | `['max', 100, '不能大于100']` |
| `regex` | 正则表达式 | 正则匹配 | `['regex', '/^1[3-9]\d{9}$/', '手机号格式无效']` |
| `url` | - | URL 格式 | `['url', 'URL格式无效']` |
| `date` | - | 日期格式 | `['date', '日期格式无效']` |
| `boolean` | - | 布尔值 | `['boolean', '必须是布尔值']` |

#### 验证示例

```php
$validated = Validator::parser($request->getParsedBody(), [
    // 用户名验证
    'username' => [
        ['required', '用户名不能为空'],
        ['lengthMin', 3, '用户名至少3个字符'],
        ['lengthMax', 20, '用户名不超过20个字符'],
        ['regex', '/^[a-zA-Z0-9_]+$/', '用户名只能包含字母、数字和下划线']
    ],

    // 邮箱验证
    'email' => [
        ['required', '邮箱不能为空'],
        ['email', '邮箱格式无效']
    ],

    // 年龄验证
    'age' => [
        ['numeric', '年龄必须是数字'],
        ['min', 18, '年龄不能小于18岁'],
        ['max', 65, '年龄不能超过65岁']
    ],

    // 手机号验证
    'phone' => [
        ['regex', '/^1[3-9]\d{9}$/', '手机号格式无效']
    ],

    // 密码验证
    'password' => [
        ['required', '密码不能为空'],
        ['lengthMin', 8, '密码至少8个字符'],
        ['regex', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', '密码必须包含大小写字母和数字']
    ]
]);
```

::: tip 验证器优势
- **自动异常处理**：验证失败自动抛出 `ExceptionValidator` 异常
- **丰富规则**：支持30+种内置验证规则
- **错误收集**：一次性收集所有字段的验证错误
- **类型安全**：返回验证后的 Data 对象，支持属性访问
:::

### 获取请求数据

#### 查询参数 (Query Parameters)

```php
public function handleQuery(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $queryParams = $request->getQueryParams();

    // 获取所有查询参数
    // ?page=1&limit=20&search=keyword
    $page = $queryParams['page'] ?? 1;
    $limit = $queryParams['limit'] ?? 10;
    $search = $queryParams['search'] ?? '';

        // 使用 Validator::parser 验证参数
    $validated = Validator::parser([
        'page' => $page,
        'limit' => $limit,
        'search' => $search
    ], [
        'page' => [
            ['numeric', '页码必须是数字'],
            ['min', 1, '页码不能小于1']
        ],
        'limit' => [
            ['numeric', '每页数量必须是数字'],
            ['min', 1, '每页数量不能小于1'],
            ['max', 100, '每页数量不能超过100']
        ],
        'search' => [
            ['lengthMax', 50, '搜索关键词不能超过50个字符']
        ]
    ]);

    return send($response, '获取成功', $validated->toArray());
}
```

#### 请求体数据 (Request Body)

```php
public function handleBody(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // JSON 数据（Content-Type: application/json）
    $jsonData = $request->getParsedBody();

    // 表单数据（Content-Type: application/x-www-form-urlencoded）
    $formData = $request->getParsedBody();

    // 多部分表单数据（Content-Type: multipart/form-data）
    $multipartData = $request->getParsedBody();
    $uploadedFiles = $request->getUploadedFiles();

    // 原始请求体
    $rawBody = $request->getBody()->getContents();

    // 示例：处理用户注册
    $userData = $request->getParsedBody();
    $name = $userData['name'] ?? '';
    $email = $userData['email'] ?? '';
    $password = $userData['password'] ?? '';

        // 使用 Validator::parser 验证数据
    $validated = Validator::parser($userData, [
        'name' => [
            ['required', '姓名不能为空'],
            ['lengthMin', 2, '姓名至少需要2个字符'],
            ['lengthMax', 50, '姓名不能超过50个字符']
        ],
        'email' => [
            ['required', '邮箱不能为空'],
            ['email', '邮箱格式无效']
        ],
        'password' => [
            ['required', '密码不能为空'],
            ['lengthMin', 6, '密码至少需要6个字符'],
            ['lengthMax', 32, '密码不能超过32个字符']
        ]
    ]);

    return send($response, '注册成功', [
        'name' => $validated->name,
        'email' => $validated->email
    ]);
}
```

#### 路由参数 (Route Parameters)

```php
// 路由定义：/user/{id}/posts/{postId}
public function getUserPost(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 从路由参数获取
    $userId = $args['id'];
    $postId = $args['postId'];

        // 使用 Validator::parser 验证路由参数
    $validated = Validator::parser($args, [
        'id' => [
            ['required', '用户ID不能为空'],
            ['numeric', '用户ID必须是数字'],
            ['min', 1, '用户ID必须大于0']
        ],
        'postId' => [
            ['required', '文章ID不能为空'],
            ['numeric', '文章ID必须是数字'],
            ['min', 1, '文章ID必须大于0']
        ]
    ]);

    $userId = $validated->id;
    $postId = $validated->postId;

    // 业务逻辑
    $post = Post::where('user_id', $userId)->find($postId);

    return send($response, '获取成功', $post);
}
```

### 获取请求头

```php
public function handleHeaders(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 获取单个请求头
    $contentType = $request->getHeaderLine('Content-Type');
    $authorization = $request->getHeaderLine('Authorization');
    $userAgent = $request->getHeaderLine('User-Agent');
    $acceptLanguage = $request->getHeaderLine('Accept-Language');

    // 获取所有请求头
    $allHeaders = $request->getHeaders();

    // 检查请求头是否存在
    if ($request->hasHeader('X-Requested-With')) {
        // 这是一个 AJAX 请求
        $isAjax = true;
    }

    // 获取多值请求头（返回数组）
    $acceptHeaders = $request->getHeader('Accept');

    // 示例：多语言处理
    $language = $this->parseAcceptLanguage($acceptLanguage);

    return send($response, '请求头信息', [
        'content_type' => $contentType,
        'language' => $language,
        'user_agent' => $userAgent,
        'is_ajax' => $isAjax ?? false
    ]);
}

private function parseAcceptLanguage(string $acceptLanguage): string
{
    if (empty($acceptLanguage)) {
        return 'zh-CN';
    }

    $languages = explode(',', $acceptLanguage);
    $primaryLang = trim($languages[0]);

    if (str_contains($primaryLang, ';')) {
        $primaryLang = explode(';', $primaryLang)[0];
    }

    return trim($primaryLang);
}
```

### 获取服务器信息

```php
public function getServerInfo(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $serverParams = $request->getServerParams();

    // 常用服务器信息
    $info = [
        'method' => $request->getMethod(),                     // GET, POST, PUT, DELETE
        'uri' => (string) $request->getUri(),                  // 完整 URI
        'path' => $request->getUri()->getPath(),               // 路径部分
        'query' => $request->getUri()->getQuery(),             // 查询字符串
        'scheme' => $request->getUri()->getScheme(),           // http/https
        'host' => $request->getUri()->getHost(),               // 主机名
        'port' => $request->getUri()->getPort(),               // 端口号
        'remote_addr' => $serverParams['REMOTE_ADDR'] ?? '',   // 客户端IP
        'user_agent' => $serverParams['HTTP_USER_AGENT'] ?? '',// 用户代理
        'referer' => $serverParams['HTTP_REFERER'] ?? '',      // 来源页面
        'protocol' => $serverParams['SERVER_PROTOCOL'] ?? '',  // 协议版本
        'request_time' => $serverParams['REQUEST_TIME'] ?? 0,  // 请求时间
    ];

    return send($response, '服务器信息', $info);
}
```

### 文件上传处理

```php
public function handleFileUpload(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $uploadedFiles = $request->getUploadedFiles();

    // 单文件上传
    if (isset($uploadedFiles['avatar'])) {
        $uploadedFile = $uploadedFiles['avatar'];

        // 检查上传错误
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new ExceptionBusiness('文件上传失败', 400);
        }

        // 获取文件信息
        $filename = $uploadedFile->getClientFilename();
        $mediaType = $uploadedFile->getClientMediaType();
        $size = $uploadedFile->getSize();

        // 验证文件类型和大小
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($mediaType, $allowedTypes)) {
            throw new ExceptionBusiness('不支持的文件类型', 400);
        }

        if ($size > 5 * 1024 * 1024) { // 5MB
            throw new ExceptionBusiness('文件大小超过限制', 400);
        }

        // 保存文件
        $newFilename = uniqid() . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        $uploadPath = 'uploads/avatars/' . $newFilename;

        $uploadedFile->moveTo($uploadPath);

        return send($response, '上传成功', [
            'filename' => $newFilename,
            'path' => $uploadPath,
            'size' => $size
        ]);
    }

    // 多文件上传
    if (isset($uploadedFiles['documents'])) {
        $documents = $uploadedFiles['documents'];
        $uploadedDocs = [];

        foreach ($documents as $document) {
            if ($document->getError() === UPLOAD_ERR_OK) {
                $filename = $document->getClientFilename();
                $newPath = 'uploads/docs/' . uniqid() . '_' . $filename;
                $document->moveTo($newPath);

                $uploadedDocs[] = [
                    'original_name' => $filename,
                    'path' => $newPath,
                    'size' => $document->getSize()
                ];
            }
        }

        return send($response, '批量上传成功', $uploadedDocs);
    }

    throw new ExceptionBusiness('没有找到上传文件', 400);
}
```

### 请求属性 (Attributes)

请求属性用于在中间件之间传递数据：

```php
// 在中间件中设置属性
public function setUserAttribute(
    ServerRequestInterface $request,
    RequestHandlerInterface $handler
): ResponseInterface {
    $userId = $this->extractUserId($request);
    $user = User::find($userId);

    // 设置用户属性
    $request = $request->withAttribute('user', $user);
    $request = $request->withAttribute('user_id', $userId);

    return $handler->handle($request);
}

// 在控制器中获取属性
public function getProfile(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 从请求属性获取用户信息
    $user = $request->getAttribute('user');
    $userId = $request->getAttribute('user_id');

    if (!$user) {
        throw new ExceptionBusiness('用户未登录', 401);
    }

    return send($response, '获取成功', [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email
    ]);
}
```

## 响应处理

### 标准 JSON 响应

DuxLite 提供了 `send()` 函数用于构建标准 JSON 响应：

```php
/**
 * send 函数签名
 * @param ResponseInterface $response 响应对象
 * @param string $message 响应消息
 * @param array|object|null $data 响应数据
 * @param array $meta 元数据
 * @param int $code HTTP状态码
 * @return ResponseInterface
 */
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

// 带元数据的响应
return send($response, '获取成功', $users, [
    'total' => 100,
    'page' => 1,
    'limit' => 10
]);

// 自定义状态码
return send($response, '创建成功', $newUser, [], 201);
```

#### 响应格式

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

### 分页响应

```php
public function getUserList(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $queryParams = $request->getQueryParams();
    $page = $queryParams['page'] ?? 1;
    $limit = $queryParams['limit'] ?? 10;

    // 获取分页数据
    $users = User::paginate($limit);

    // 使用 format_data 格式化数据
    $result = format_data($users, function($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at->format('Y-m-d H:i:s')
        ];
    });

    return send($response, '获取成功', $result['data'], $result['meta']);
}
```

### 文本响应

```php
public function getPlainText(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $content = "这是纯文本内容\n包含多行文本";

    return sendText($response, $content);
}

// 自定义状态码的文本响应
public function getNotFound(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    return sendText($response, '页面未找到', 404);
}
```

### 模板响应

```php
public function renderTemplate(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $data = [
        'title' => '用户列表',
        'users' => User::all(),
        'current_user' => $request->getAttribute('user')
    ];

    // 使用默认模板引擎 (web)
    return sendTpl($response, 'users/list', $data);

    // 使用指定的模板引擎
    return sendTpl($response, 'admin/users', $data, 'admin');
}
```

### 文件下载响应

```php
public function downloadFile(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $fileId = $args['id'];
    $file = File::find($fileId);

    if (!$file || !file_exists($file->path)) {
        throw new ExceptionBusiness('文件不存在', 404);
    }

    // 读取文件内容
    $fileContent = file_get_contents($file->path);

    // 设置响应头
    $response->getBody()->write($fileContent);

    return $response
        ->withHeader('Content-Type', $file->mime_type)
        ->withHeader('Content-Disposition', 'attachment; filename="' . $file->name . '"')
        ->withHeader('Content-Length', (string) filesize($file->path))
        ->withStatus(200);
}
```

### 重定向响应

```php
public function redirectToLogin(
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

### 流式响应

```php
public function streamData(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 创建流
    $stream = fopen('php://temp', 'r+');

    // 大量数据的流式处理
    for ($i = 0; $i < 1000; $i++) {
        $data = json_encode(['id' => $i, 'data' => str_repeat('x', 100)]) . "\n";
        fwrite($stream, $data);
    }

    rewind($stream);

    // 创建流响应
    $body = new \Slim\Psr7\Stream($stream);

    return $response
        ->withBody($body)
        ->withHeader('Content-Type', 'application/x-ndjson')
        ->withHeader('Transfer-Encoding', 'chunked')
        ->withStatus(200);
}
```

### 自定义响应

```php
public function customResponse(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $data = [
        'status' => 'success',
        'timestamp' => time(),
        'request_id' => uniqid(),
        'data' => ['key' => 'value']
    ];

    // 直接构建 JSON 响应
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
    $response->getBody()->write($payload);

    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withHeader('X-Request-ID', $data['request_id'])
        ->withHeader('Cache-Control', 'no-cache, no-store')
        ->withStatus(200);
}
```

## 高级功能

### 内容协商

根据客户端请求的内容类型返回不同格式的响应：

```php
public function contentNegotiation(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $accept = $request->getHeaderLine('Accept');
    $data = ['message' => 'Hello World', 'timestamp' => time()];

    if (str_contains($accept, 'application/xml')) {
        // 返回 XML 格式
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<response>';
        $xml .= '<message>' . htmlspecialchars($data['message']) . '</message>';
        $xml .= '<timestamp>' . $data['timestamp'] . '</timestamp>';
        $xml .= '</response>';

        $response->getBody()->write($xml);
        return $response->withHeader('Content-Type', 'application/xml');

    } elseif (str_contains($accept, 'text/plain')) {
        // 返回纯文本格式
        $text = "Message: {$data['message']}\nTimestamp: {$data['timestamp']}";
        return sendText($response, $text);

    } else {
        // 默认返回 JSON
        return send($response, 'success', $data);
    }
}
```

### 条件响应

```php
public function conditionalResponse(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $resourceId = $args['id'];
    $resource = Resource::find($resourceId);

    if (!$resource) {
        throw new ExceptionBusiness('资源不存在', 404);
    }

    // 检查 If-Modified-Since 头
    $ifModifiedSince = $request->getHeaderLine('If-Modified-Since');
    if ($ifModifiedSince) {
        $modifiedTime = strtotime($ifModifiedSince);
        $resourceTime = $resource->updated_at->getTimestamp();

        if ($resourceTime <= $modifiedTime) {
            // 资源未修改，返回 304
            return $response->withStatus(304);
        }
    }

    // 检查 If-None-Match 头 (ETag)
    $ifNoneMatch = $request->getHeaderLine('If-None-Match');
    $etag = md5($resource->updated_at . $resource->id);

    if ($ifNoneMatch === $etag) {
        return $response->withStatus(304);
    }

    // 返回资源并设置缓存头
    $responseData = [
        'id' => $resource->id,
        'data' => $resource->data,
        'updated_at' => $resource->updated_at->format('Y-m-d H:i:s')
    ];

    $response = send($response, '获取成功', $responseData);

    return $response
        ->withHeader('Last-Modified', $resource->updated_at->format('D, d M Y H:i:s') . ' GMT')
        ->withHeader('ETag', $etag)
        ->withHeader('Cache-Control', 'max-age=3600');
}
```

### 压缩响应

```php
public function compressedResponse(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 大量数据
    $largeData = array_fill(0, 1000, [
        'id' => rand(1, 1000),
        'data' => str_repeat('Lorem ipsum dolor sit amet. ', 50)
    ]);

    $acceptEncoding = $request->getHeaderLine('Accept-Encoding');

    // 检查客户端是否支持 gzip
    if (str_contains($acceptEncoding, 'gzip')) {
        $jsonData = json_encode(['data' => $largeData], JSON_UNESCAPED_UNICODE);
        $compressedData = gzencode($jsonData, 6);

        $response->getBody()->write($compressedData);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Encoding', 'gzip')
            ->withHeader('Content-Length', (string) strlen($compressedData))
            ->withStatus(200);
    }

    // 返回未压缩的响应
    return send($response, '获取成功', $largeData);
}
```

### 分块传输编码

```php
public function chunkedResponse(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 设置分块传输编码
    $response = $response
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Transfer-Encoding', 'chunked')
        ->withStatus(200);

    // 发送响应头
    if (!headers_sent()) {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            header($name . ': ' . implode(', ', $values));
        }
    }

    // 分块发送数据
    echo json_encode(['status' => 'started']) . "\n";
    ob_flush();
    flush();

    for ($i = 0; $i < 10; $i++) {
        sleep(1); // 模拟处理时间
        echo json_encode(['progress' => ($i + 1) * 10]) . "\n";
        ob_flush();
        flush();
    }

    echo json_encode(['status' => 'completed']) . "\n";
    ob_flush();
    flush();

    return $response;
}
```

## 错误响应处理

### 标准错误响应

```php
public function handleError(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    try {
        // 业务逻辑
        $result = $this->processData($request);
        return send($response, '处理成功', $result);

    } catch (ExceptionBusiness $e) {
        // 业务异常
        return send($response, $e->getMessage(), null, [], $e->getCode() ?: 400);

    } catch (ExceptionValidator $e) {
        // 验证异常 - 自动包含详细的字段错误信息
        return send($response, '数据验证失败', $e->getData(), [], 422);

    } catch (\Exception $e) {
        // 系统异常
        error_log($e->getMessage());
        return send($response, '系统错误', null, [], 500);
    }
}
```

### 验证异常处理

`Validator::parser` 验证失败时会自动抛出 `ExceptionValidator` 异常，包含详细的字段错误信息：

```php
// 验证异常的处理示例
public function handleValidationError(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    try {
        $validated = Validator::parser($request->getParsedBody(), [
            'name' => [['required', '姓名不能为空']],
            'email' => [['email', '邮箱格式无效']]
        ]);

        // 验证通过的处理逻辑...
        return send($response, '验证成功', $validated->toArray());

    } catch (ExceptionValidator $e) {
        // 验证异常会自动包含详细的字段错误
        $errors = $e->getData();
        /*
        $errors 格式:
        [
            'name' => ['姓名不能为空'],
            'email' => ['邮箱格式无效']
        ]
        */

        return send($response, '数据验证失败', $errors, [], 422);
    }
}
```

#### 自定义验证错误响应

```php
public function customValidationResponse(ExceptionValidator $e): ResponseInterface
{
    $errors = $e->getData();
    $firstError = array_values($errors)[0][0] ?? '数据验证失败';

    // 返回第一个错误作为主要消息
    return send($response, $firstError, [
        'errors' => $errors,
        'error_count' => count($errors)
    ], [], 422);
}
```

### 自定义错误格式

```php
public function customErrorResponse(
    ServerRequestInterface $request,
    ResponseInterface $response,
    string $errorMessage,
    int $errorCode = 400,
    array $details = []
): ResponseInterface {
    $errorResponse = [
        'success' => false,
        'error' => [
            'code' => $errorCode,
            'message' => $errorMessage,
            'details' => $details,
            'timestamp' => date('c'),
            'request_id' => uniqid()
        ]
    ];

    $payload = json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
    $response->getBody()->write($payload);

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($errorCode);
}
```

## 最佳实践

### 1. 输入验证和清理

::: tip 安全第一
始终验证和清理用户输入，永远不要信任客户端数据。
:::

```php
public function safeInputHandling(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $data = $request->getParsedBody();

    // 使用 Validator::parser 进行完整验证
    $validated = Validator::parser($data, [
        'name' => [
            ['required', '姓名不能为空'],
            ['lengthMin', 2, '姓名至少需要2个字符'],
            ['lengthMax', 50, '姓名不能超过50个字符'],
            ['regex', '/^[\x{4e00}-\x{9fa5}a-zA-Z\s]+$/u', '姓名只能包含中文、英文和空格']
        ],
        'email' => [
            ['required', '邮箱不能为空'],
            ['email', '邮箱格式无效'],
            ['lengthMax', 255, '邮箱长度不能超过255个字符']
        ],
        'age' => [
            ['required', '年龄不能为空'],
            ['numeric', '年龄必须是数字'],
            ['min', 1, '年龄不能小于1岁'],
            ['max', 120, '年龄不能超过120岁']
        ],
        'phone' => [
            ['regex', '/^1[3-9]\d{9}$/', '手机号格式无效']
        ]
    ]);

    // 继续处理验证通过的数据...
    return send($response, '验证通过', [
        'name' => $validated->name,
        'email' => $validated->email,
        'age' => $validated->age,
        'phone' => $validated->phone ?? null
    ]);
}
```

### 2. 响应缓存

```php
public function cachedResponse(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $cacheKey = 'api_data_' . md5($request->getUri()->getPath() . '?' . $request->getUri()->getQuery());

    // 尝试从缓存获取
    $cachedData = Cache::get($cacheKey);
    if ($cachedData) {
        return send($response, '获取成功', $cachedData)
            ->withHeader('X-Cache', 'HIT');
    }

    // 生成数据
    $data = $this->generateExpensiveData();

    // 缓存数据（5分钟）
    Cache::set($cacheKey, $data, 300);

    return send($response, '获取成功', $data)
        ->withHeader('X-Cache', 'MISS');
}
```

### 3. 请求日志记录

```php
public function loggedRequest(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $startTime = microtime(true);

    // 记录请求信息
    $requestLog = [
        'method' => $request->getMethod(),
        'uri' => (string) $request->getUri(),
        'headers' => $request->getHeaders(),
        'body' => $request->getParsedBody(),
        'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $request->getHeaderLine('User-Agent'),
        'timestamp' => date('Y-m-d H:i:s')
    ];

    try {
        // 处理业务逻辑
        $result = $this->processRequest($request);

        // 记录成功日志
        $responseTime = microtime(true) - $startTime;
        App::log('api')->info('Request processed', [
            'request' => $requestLog,
            'response_time' => $responseTime,
            'status' => 'success'
        ]);

        return send($response, '处理成功', $result);

    } catch (\Exception $e) {
        // 记录错误日志
        $responseTime = microtime(true) - $startTime;
        App::log('api')->error('Request failed', [
            'request' => $requestLog,
            'error' => $e->getMessage(),
            'response_time' => $responseTime,
            'status' => 'error'
        ]);

        throw $e;
    }
}
```

### 4. API 版本控制

```php
public function versionedApi(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 从头部获取 API 版本
    $apiVersion = $request->getHeaderLine('X-API-Version') ?: '1.0';

    // 从 URL 路径获取版本
    $pathVersion = $args['version'] ?? 'v1';

    switch ($apiVersion) {
        case '2.0':
            return $this->handleV2Request($request, $response, $args);
        case '1.1':
            return $this->handleV1_1Request($request, $response, $args);
        default:
            return $this->handleV1Request($request, $response, $args);
    }
}

private function handleV2Request(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
{
    // V2 API 逻辑
    $data = ['version' => '2.0', 'features' => ['new_feature_1', 'new_feature_2']];

    return send($response, 'API v2.0', $data)
        ->withHeader('X-API-Version', '2.0');
}
```

### 5. 请求限流和防护

```php
public function rateLimitedRequest(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitKey = "rate_limit:$clientIp";

    // 检查请求频率（每分钟最多30次）
    $requestCount = Cache::get($rateLimitKey, 0);
    if ($requestCount >= 30) {
        return send($response, '请求过于频繁，请稍后重试', null, [
            'retry_after' => 60
        ], 429)
        ->withHeader('X-RateLimit-Limit', '30')
        ->withHeader('X-RateLimit-Remaining', '0')
        ->withHeader('Retry-After', '60');
    }

    // 增加计数
    Cache::set($rateLimitKey, $requestCount + 1, 60);

    // 处理正常请求
    $data = $this->processNormalRequest($request);

    return send($response, '处理成功', $data)
        ->withHeader('X-RateLimit-Limit', '30')
        ->withHeader('X-RateLimit-Remaining', (string) (29 - $requestCount));
}
```

## 总结

DuxLite 的请求和响应处理基于 PSR-7 标准，提供了：

- **标准化接口**：符合 PSR-7 规范，与其他 PSR 兼容组件无缝集成
- **丰富的请求处理**：支持查询参数、请求体、文件上传、请求头等
- **灵活的响应构建**：JSON、文本、模板、文件下载等多种响应类型
- **高级功能**：内容协商、缓存控制、流式响应等
- **最佳实践**：安全验证、错误处理、日志记录等

通过合理使用这些功能，您可以构建出安全、高效、可维护的 Web 应用程序。