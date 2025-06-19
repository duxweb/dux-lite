# 错误处理

DuxLite 提供了完整的错误处理机制，基于 SlimPHP 的错误处理系统，支持多种响应格式、自定义错误页面和详细的错误日志记录。

## 设计理念

**DuxLite 错误处理理念：显式且用户友好的错误处理**

- **分层异常设计**：业务异常、验证异常、系统异常分类处理
- **智能渲染**：根据请求类型自动选择 HTML、JSON、XML 响应格式
- **国际化支持**：错误信息支持多语言翻译
- **开发友好**：调试模式下显示详细错误信息
- **生产安全**：生产环境下隐藏敏感信息

## 异常类型

### 1. 基础异常类

```php
// 源码位置：src/Handlers/Exception.php
use Core\Handlers\Exception;

// 框架基础异常类，继承自 RuntimeException
class Exception extends \RuntimeException {}
```

### 2. 业务异常

用于处理业务逻辑错误，如权限不足、数据不存在等：

```php
// 源码位置：src/Handlers/ExceptionBusiness.php
use Core\Handlers\ExceptionBusiness;

// 抛出业务异常
throw new ExceptionBusiness('用户不存在', 404);
throw new ExceptionBusiness('权限不足', 403);
throw new ExceptionBusiness('操作失败', 500);

// 在控制器中使用
class UserController
{
    public function show(int $id): ResponseInterface
    {
        $user = User::find($id);
        if (!$user) {
            throw new ExceptionBusiness('用户不存在', 404);
        }

        return response()->json($user);
    }
}
```

### 3. 国际化业务异常

支持多语言的业务异常：

```php
// 源码位置：src/Handlers/ExceptionBusinessLang.php
use Core\Handlers\ExceptionBusinessLang;

// 使用语言包中的错误信息
throw new ExceptionBusinessLang('error.notFound');           // 页面不存在
throw new ExceptionBusinessLang('message.emptyData');       // 空数据
throw new ExceptionBusinessLang('validate.placeholder', '用户名'); // 请输入用户名

// 语言包配置（common.zh-CN.toml）
[error]
notFound = "页面不存在"
notFoundMessage = "您访问的页面不存在或者已被删除"

[validate]
placeholder = "请输入%name%"
```

### 4. 验证异常

用于表单验证错误：

```php
// 源码位置：src/Handlers/ExceptionValidator.php
use Core\Handlers\ExceptionValidator;

// 验证器自动抛出
$data = Validator::parser($request->getParsedBody(), [
    'name' => ['required', '姓名不能为空'],
    'email' => ['required|email', '请输入有效的邮箱地址']
]);

// 手动抛出验证异常
throw new ExceptionValidator([
    'name' => ['姓名不能为空'],
    'email' => ['邮箱格式不正确']
]);
```

### 5. 404 异常

专门处理资源不存在的情况：

```php
// 源码位置：src/Handlers/ExceptionNotFound.php
use Core\Handlers\ExceptionNotFound;

// 自动设置 404 状态码
throw new ExceptionNotFound();

// 在路由中使用
$app->get('/users/{id}', function ($request, $response, $args) {
    $user = User::find($args['id']);
    if (!$user) {
        throw new ExceptionNotFound();
    }
    return response()->json($user);
});
```

### 6. 数据异常

携带额外数据的异常：

```php
// 源码位置：src/Handlers/ExceptionData.php
use Core\Handlers\ExceptionData;

// 创建数据异常实例
$exception = new ExceptionData('操作失败', 422);
$exception->data = [
    'errors' => ['field1' => '错误信息1'],
    'metadata' => ['timestamp' => time()]
];
throw $exception;
```

### 7. 内部异常

系统内部错误，不对外暴露：

```php
// 源码位置：src/Handlers/ExceptionInternal.php
use Core\Handlers\ExceptionInternal;

// 用于框架内部错误
throw new ExceptionInternal('内部系统错误');
```

## 错误处理器

### ErrorHandler 核心功能

DuxLite 的错误处理器扩展了 SlimPHP 的基础功能：

```php
// 源码位置：src/Handlers/ErrorHandler.php
use Core\Handlers\ErrorHandler;

class ErrorHandler extends slimErrorHandler
{
    // 智能状态码判断
    protected function determineStatusCode(): int
    {
        if ($this->method === 'OPTIONS') {
            return 200;  // OPTIONS 请求返回 200
        }
        if ($this->exception instanceof HttpException || $this->exception instanceof Exception) {
            return $this->exception->getCode() ?: 500;
        }
        return 500;
    }

    // 智能错误日志记录
    protected function logError(string $error): void
    {
        // 不记录这些类型的错误日志
        if (
            $this->statusCode == 404 ||                                    // 404 错误
            $this->exception instanceof HttpSpecializedException ||        // HTTP 专用异常
            $this->exception instanceof LogicException ||                  // 逻辑异常
            $this->exception instanceof Exception                          // 框架异常
        ) {
            return;
        }

        // 记录详细错误信息
        App::log()->error($this->exception->getMessage(), [
            'uri' => $this->request->getUri(),
            'query' => $this->request->getQueryParams(),
            'file' => [
                'file' => $this->exception->getFile(),
                'line' => $this->exception->getLine()
            ],
            'trace' => $this->exception->getTrace()
        ]);
    }
}
```

## 错误渲染器

DuxLite 支持多种响应格式的错误渲染：

### 1. HTML 渲染器

用于 Web 页面的错误显示：

```php
// 源码位置：src/Handlers/ErrorHtmlRenderer.php
use Core\Handlers\ErrorHtmlRenderer;

// 自动选择模板
// 404 错误 -> 404.latte 模板
// 其他错误 -> error.latte 模板

// 模板变量
[
    "code" => 404,                    // 错误状态码
    "title" => "页面不存在",           // 错误标题
    "message" => "页面已被删除"        // 错误描述
]
```

### 2. JSON 渲染器

用于 API 接口的错误响应：

```php
// 源码位置：src/Handlers/ErrorJsonRenderer.php
use Core\Handlers\ErrorJsonRenderer;

// JSON 响应格式
{
    "code": 404,
    "message": "用户不存在",
    "data": []
}

// 验证错误的响应格式
{
    "code": 422,
    "message": "验证失败",
    "data": {
        "name": ["姓名不能为空"],
        "email": ["邮箱格式不正确"]
    }
}
```

### 3. XML 和纯文本渲染器

```php
// XML 渲染器
use Core\Handlers\ErrorXmlRenderer;

// 纯文本渲染器
use Core\Handlers\ErrorPlainRenderer;
```

## 自定义错误页面

### 1. 默认模板

框架提供了美观的默认错误页面：

```html
<!-- 404 页面模板：src/Tpl/404.latte -->
<!DOCTYPE html>
<html>
<head>
    <title>{__('error.notFound', 'common')}</title>
    <!-- Tailwind CSS 样式 -->
</head>
<body>
    <main class="grid h-[100vh] place-items-center">
        <div class="text-center">
            <p class="text-4xl font-semibold text-blue-600">{$code}</p>
            <h1 class="mt-4 text-4xl font-bold">{__('error.notFound', 'common')}</h1>
            <p class="mt-6 text-base">{__('error.notFoundMessage', 'common')}</p>
            <div class="mt-10 flex gap-x-6">
                <a href="/" class="btn-primary">{__('error.home', 'common')}</a>
                <a href="#" onclick="history.go(-1)">{__('error.back', 'common')}</a>
            </div>
        </div>
    </main>
</body>
</html>
```

### 2. 自定义错误模板

```php
// 注册自定义模板
App::di()->set('tpl.404', '/path/to/custom/404.latte');
App::di()->set('tpl.error', '/path/to/custom/error.latte');

// 在 Bootstrap.php 中配置
public static function loadApp(Slim\App $app): void
{
    // 自定义 404 页面
    App::di()->set('tpl.404', __DIR__ . '/templates/errors/404.latte');

    // 自定义错误页面
    App::di()->set('tpl.error', __DIR__ . '/templates/errors/error.latte');
}
```

### 3. 模板变量

错误模板可以使用以下变量：

```php
// 可用变量
$code       // 错误状态码：404, 500, 422
$title      // 错误标题
$message    // 错误描述信息

// 使用示例
{$code}                                    // 404
{$title}                                   // 页面不存在
{$message}                                 // 您访问的页面不存在
{__('error.home', 'common')}              // 回到首页（多语言）
```

## 错误信息特性

### ErrorRendererTrait

提供智能的错误信息处理：

```php
// 源码位置：src/Handlers/ErrorRendererTrait.php
trait ErrorRendererTrait
{
    // 获取错误标题
    protected function getErrorTitle(Throwable $exception): string
    {
        // 调试模式或特定异常：显示真实错误信息
        if (App::$debug || $exception instanceof HttpException || $exception instanceof Exception) {
            return $exception->getMessage();
        }
        // 生产模式：显示通用错误信息
        return __('error.errorTitle', 'common');  // "应用错误"
    }

    // 获取错误描述
    protected function getErrorDescription(Throwable $exception): string
    {
        if ($exception instanceof HttpException) {
            return $exception->getDescription();
        }
        return __('error.errorMessage', 'common');  // "操作遇到了错误，请稍后再试"
    }
}
```

## 实际使用示例

### 1. 控制器中的错误处理

```php
class UserController
{
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $id = (int) $args['id'];

            // 参数验证
            if ($id <= 0) {
                throw new ExceptionBusiness('无效的用户ID', 400);
            }

            // 查询用户
            $user = User::find($id);
            if (!$user) {
                throw new ExceptionNotFound();  // 自动 404
            }

            // 权限检查
            if (!$this->canViewUser($request, $user)) {
                throw new ExceptionBusiness('权限不足', 403);
            }

            return send($response, $user);

        } catch (ExceptionBusiness $e) {
            // 业务异常会被自动处理和渲染
            throw $e;
        } catch (\Exception $e) {
            // 系统异常转换为内部错误
            throw new ExceptionInternal('用户查询失败');
        }
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();

        // 验证器会自动抛出 ExceptionValidator
        $validData = Validator::parser($data, [
            'name' => ['required', '姓名不能为空'],
            'email' => ['required|email', '请输入有效的邮箱地址'],
            'phone' => ['regex:/^1[3-9]\d{9}$/', '请输入有效的手机号']
        ]);

        // 业务逻辑
        if (User::where('email', $validData->email)->exists()) {
            throw new ExceptionBusiness('邮箱已被使用', 422);
        }

        $user = User::create((array) $validData);
        return send($response, $user, '用户创建成功');
    }
}
```

### 2. 中间件中的错误处理

```php
class AuthMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $token = $request->getHeaderLine('Authorization');

        if (!$token) {
            throw new ExceptionBusiness('缺少认证令牌', 401);
        }

        try {
            $user = $this->validateToken($token);
            $request = $request->withAttribute('user', $user);

        } catch (TokenExpiredException $e) {
            throw new ExceptionBusiness('认证令牌已过期', 401);
        } catch (InvalidTokenException $e) {
            throw new ExceptionBusiness('无效的认证令牌', 401);
        }

        return $handler->handle($request);
    }
}
```

### 3. 全局异常处理

```php
// 在 Bootstrap.php 中配置错误处理
public static function loadApp(Slim\App $app): void
{
    // 设置错误处理器
    $errorHandler = new ErrorHandler(
        $app->getCallableResolver(),
        $app->getResponseFactory()
    );

    // 配置错误渲染器
    $errorHandler->registerErrorRenderer('text/html', ErrorHtmlRenderer::class);
    $errorHandler->registerErrorRenderer('application/json', ErrorJsonRenderer::class);
    $errorHandler->registerErrorRenderer('application/xml', ErrorXmlRenderer::class);
    $errorHandler->registerErrorRenderer('text/plain', ErrorPlainRenderer::class);

    $app->addErrorMiddleware(App::$debug, true, true)
        ->setDefaultErrorHandler($errorHandler);
}
```

## 调试模式

### 开发环境配置

```toml
# config/use.toml
[app]
debug = true    # 开启调试模式

# 调试模式特性
# 1. 显示详细错误信息和堆栈跟踪
# 2. 错误页面显示异常类名和文件位置
# 3. API 响应包含调试信息
```

### 生产环境配置

```toml
# config/use.toml
[app]
debug = false   # 关闭调试模式

# 生产模式特性
# 1. 隐藏敏感错误信息
# 2. 显示用户友好的错误页面
# 3. 详细错误信息仅记录到日志
```

## 最佳实践

### 1. 异常分类使用

```php
// ✅ 正确的异常使用
throw new ExceptionBusiness('用户不存在', 404);           // 业务逻辑错误
throw new ExceptionValidator($errors);                    // 验证错误
throw new ExceptionNotFound();                           // 资源不存在
throw new ExceptionInternal('数据库连接失败');            // 系统内部错误

// ❌ 错误的异常使用
throw new \Exception('用户不存在');                       // 不要使用通用异常
throw new ExceptionBusiness($e->getMessage());           // 不要暴露系统异常信息
```

### 2. 错误信息国际化

```php
// ✅ 使用语言包
throw new ExceptionBusinessLang('message.emptyData');
throw new ExceptionBusinessLang('validate.placeholder', '用户名');

// ❌ 硬编码错误信息
throw new ExceptionBusiness('数据为空');
```

### 3. 错误日志记录

```php
// ✅ 合理的日志记录
try {
    $result = $this->externalApiCall();
} catch (\Exception $e) {
    // 记录详细错误信息供调试
    App::log()->error('外部API调用失败', [
        'api' => 'getUserInfo',
        'params' => $params,
        'error' => $e->getMessage()
    ]);

    // 抛出用户友好的错误
    throw new ExceptionBusiness('获取用户信息失败，请稍后重试', 500);
}
```

### 4. 统一错误响应格式

```php
// API 响应统一格式
{
    "code": 404,
    "message": "用户不存在",
    "data": []
}

// 验证错误格式
{
    "code": 422,
    "message": "验证失败",
    "data": {
        "name": ["姓名不能为空"],
        "email": ["邮箱格式不正确"]
    }
}
```

通过 DuxLite 的错误处理系统，你可以构建健壮、用户友好且易于调试的应用程序。