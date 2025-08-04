# 异常处理系统

DuxLite 提供了完整的异常处理体系，包含多种预定义的异常类型和统一的错误处理机制。通过分层次的异常设计和自动化的错误响应，帮助开发者构建健壮的应用程序并提供友好的用户体验。

## 基本概念

### 设计理念

DuxLite 异常处理系统采用**分层异常、自动处理、用户友好**的设计：

- **分层异常**：不同类型的异常承担不同的职责，便于分类处理
- **自动处理**：框架自动捕获异常并转换为合适的HTTP响应
- **用户友好**：在保证安全的前提下提供有意义的错误信息
- **开发友好**：详细的调试信息和错误追踪功能
- **国际化支持**：错误消息支持多语言显示

### 核心特性

- **异常继承**：清晰的异常继承体系，便于统一处理
- **HTTP状态码**：异常自动映射到对应的HTTP状态码
- **数据携带**：异常可携带额外的上下文数据
- **自动响应**：根据请求类型自动生成JSON或HTML响应
- **日志记录**：异常自动记录到日志系统

## 异常继承体系

### 异常层次结构

```
\RuntimeException
  └── Core\Handlers\Exception                    // 基础异常
      ├── Core\Handlers\ExceptionBusiness        // 业务异常 (500)
      ├── Core\Handlers\ExceptionBusinessLang    // 业务异常(支持多语言)
      ├── Core\Handlers\ExceptionNotFound        // 未找到异常 (404)
      └── Core\Handlers\ExceptionData            // 数据异常
          └── Core\Handlers\ExceptionValidator   // 验证异常 (422)

\Exception
  └── Core\Handlers\ExceptionInternal           // 内部异常
```

### 基础异常类

```php
// src/Handlers/Exception.php
namespace Core\Handlers;

class Exception extends \RuntimeException 
{
    // 基础异常类，继承自 RuntimeException
    // 所有业务异常都应该继承自此类
}
```

## 异常类型详解

### ExceptionBusiness - 业务异常

**HTTP 状态码：** 500  
**用途：** 处理业务逻辑异常  

```php
// src/Handlers/ExceptionBusiness.php
namespace Core\Handlers;

class ExceptionBusiness extends Exception 
{
    // 业务逻辑异常，用于处理业务规则违反等情况
}

// 使用示例
class OrderService
{
    public function cancelOrder(int $orderId): void
    {
        $order = Order::find($orderId);
        
        if (!$order) {
            throw new ExceptionBusiness('订单不存在');
        }
        
        if ($order->status === 'shipped') {
            throw new ExceptionBusiness('订单已发货，无法取消');
        }
        
        if ($order->status === 'cancelled') {
            throw new ExceptionBusiness('订单已取消，无需重复操作');
        }
        
        // 执行取消逻辑
        $order->cancel();
    }
    
    public function processPayment(int $orderId, float $amount): void
    {
        $order = Order::find($orderId);
        
        if ($amount != $order->total_amount) {
            throw new ExceptionBusiness('支付金额与订单金额不匹配');
        }
        
        $user = $order->user;
        if ($user->account_balance < $amount) {
            throw new ExceptionBusiness('账户余额不足，请先充值');
        }
        
        // 处理支付逻辑
    }
}
```

**使用场景：**
- 权限验证失败
- 业务规则违反
- 操作条件不满足
- 资源状态异常
- 库存不足等业务限制

### ExceptionBusinessLang - 多语言业务异常

**用途：** 支持多语言的业务异常

```php
// src/Handlers/ExceptionBusinessLang.php
namespace Core\Handlers;

class ExceptionBusinessLang extends ExceptionBusiness
{
    public function __construct(string $message, array $parameters = [], string $domain = '')
    {
        // 自动翻译错误消息
        $translatedMessage = __($message, $parameters, $domain);
        parent::__construct($translatedMessage);
    }
}

// 使用示例
class UserService
{
    public function createUser(array $data): User
    {
        // 检查用户名是否已存在
        if (User::where('username', $data['username'])->exists()) {
            throw new ExceptionBusinessLang('user.username_exists', [
                'username' => $data['username']
            ], 'validation');
        }
        
        // 检查邮箱是否已存在
        if (User::where('email', $data['email'])->exists()) {
            throw new ExceptionBusinessLang('user.email_exists', [
                'email' => $data['email']
            ], 'validation');
        }
        
        return User::create($data);
    }
    
    public function updateUserStatus(int $userId, string $status): void
    {
        $user = User::find($userId);
        
        if (!$user) {
            throw new ExceptionBusinessLang('user.not_found');
        }
        
        if (!in_array($status, ['active', 'inactive', 'banned'])) {
            throw new ExceptionBusinessLang('user.invalid_status', [
                'status' => $status,
                'valid_statuses' => 'active, inactive, banned'
            ]);
        }
        
        $user->update(['status' => $status]);
    }
}

// 语言文件示例 (src/Langs/validation.zh-CN.toml)
[user]
username_exists = "用户名 {username} 已存在"
email_exists = "邮箱 {email} 已被使用"
not_found = "用户不存在"
invalid_status = "无效的状态 {status}，有效状态: {valid_statuses}"
```

### ExceptionValidator - 验证异常

**HTTP 状态码：** 422  
**用途：** 处理数据验证异常

```php
// src/Handlers/ExceptionValidator.php
namespace Core\Handlers;

class ExceptionValidator extends ExceptionData 
{
    public function __construct(array $data) 
    {
        // 提取第一个错误作为主错误消息
        $errors = array_values($data);
        $message = $errors[0] ? $errors[0][0] : '';
        
        // 设置 422 状态码
        parent::__construct($message, 422);
        
        // 保存完整的验证错误数据
        $this->data = $data;
    }
}

// 使用示例
class UserController
{
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        
        // 数据验证
        $validator = new Validator($data, [
            'username' => ['required', 'minLength:3', 'maxLength:20'],
            'email' => ['required', 'email'],
            'password' => ['required', 'minLength:8'],
            'age' => ['required', 'integer', 'min:18']
        ]);
        
        if (!$validator->validate()) {
            // 自动返回 422 响应
            throw new ExceptionValidator($validator->errors());
        }
        
        $user = User::create($data);
        return send($response, '用户创建成功', $user->transform());
    }
    
    public function update(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $userId = (int) $args['id'];
        $data = $request->getParsedBody();
        
        $user = User::find($userId);
        if (!$user) {
            throw new ExceptionNotFound('用户不存在');
        }
        
        // 自定义验证逻辑
        $errors = [];
        
        if (isset($data['username'])) {
            if (strlen($data['username']) < 3) {
                $errors['username'][] = '用户名长度不能少于3个字符';
            }
            
            if (User::where('username', $data['username'])
                    ->where('id', '!=', $userId)
                    ->exists()) {
                $errors['username'][] = '用户名已被使用';
            }
        }
        
        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'][] = '邮箱格式不正确';
            }
            
            if (User::where('email', $data['email'])
                    ->where('id', '!=', $userId)
                    ->exists()) {
                $errors['email'][] = '邮箱已被使用';
            }
        }
        
        if (!empty($errors)) {
            throw new ExceptionValidator($errors);
        }
        
        $user->update($data);
        return send($response, '用户更新成功', $user->transform());
    }
}
```

### ExceptionNotFound - 未找到异常

**HTTP 状态码：** 404  
**用途：** 处理资源未找到异常

```php
// src/Handlers/ExceptionNotFound.php
namespace Core\Handlers;

class ExceptionNotFound extends Exception
{
    // 资源未找到异常，自动返回 404 状态码
}

// 使用示例
class ProductController
{
    public function show(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $productId = (int) $args['id'];
        
        $product = Product::find($productId);
        if (!$product) {
            throw new ExceptionNotFound('商品不存在');
        }
        
        // 检查商品是否已下架
        if (!$product->is_active) {
            throw new ExceptionNotFound('商品已下架');
        }
        
        return send($response, '获取成功', $product->transform());
    }
    
    public function updateStock(int $productId, int $quantity): void
    {
        $product = Product::find($productId);
        if (!$product) {
            throw new ExceptionNotFound("商品 ID {$productId} 不存在");
        }
        
        $product->update(['stock' => $quantity]);
    }
}

class FileController
{
    public function download(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $fileId = $args['id'];
        
        $file = File::find($fileId);
        if (!$file) {
            throw new ExceptionNotFound('文件不存在');
        }
        
        $filePath = storage_path($file->path);
        if (!file_exists($filePath)) {
            throw new ExceptionNotFound('文件已被删除或移动');
        }
        
        // 返回文件下载响应
        return $this->createFileResponse($filePath, $file->name);
    }
}
```

### ExceptionData - 数据异常

**用途：** 处理数据相关异常，支持携带额外数据

```php
// src/Handlers/ExceptionData.php
namespace Core\Handlers;

class ExceptionData extends Exception
{
    protected array $data = [];
    
    public function __construct(string $message, int $code = 400, array $data = [])
    {
        parent::__construct($message, $code);
        $this->data = $data;
    }
    
    public function getData(): array
    {
        return $this->data;
    }
    
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }
}

// 使用示例
class PaymentService
{
    public function processPayment(array $paymentData): array
    {
        try {
            // 调用第三方支付接口
            $result = $this->callPaymentAPI($paymentData);
            
            if (!$result['success']) {
                throw new ExceptionData('支付处理失败', 400, [
                    'payment_id' => $paymentData['payment_id'],
                    'error_code' => $result['error_code'],
                    'error_message' => $result['error_message'],
                    'amount' => $paymentData['amount'],
                    'currency' => $paymentData['currency']
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw new ExceptionData('支付系统异常', 500, [
                'payment_id' => $paymentData['payment_id'],
                'original_error' => $e->getMessage(),
                'timestamp' => time(),
                'request_data' => $paymentData
            ]);
        }
    }
    
    public function refundPayment(string $paymentId, float $amount): void
    {
        $payment = Payment::find($paymentId);
        
        if (!$payment) {
            throw new ExceptionNotFound('支付记录不存在');
        }
        
        if ($payment->status !== 'completed') {
            throw new ExceptionData('无法退款', 400, [
                'payment_id' => $paymentId,
                'current_status' => $payment->status,
                'required_status' => 'completed',
                'reason' => '只有已完成的支付才能退款'
            ]);
        }
        
        if ($amount > $payment->amount) {
            throw new ExceptionData('退款金额超出限制', 400, [
                'payment_id' => $paymentId,
                'requested_amount' => $amount,
                'maximum_amount' => $payment->amount,
                'available_amount' => $payment->amount - $payment->refunded_amount
            ]);
        }
        
        // 处理退款逻辑
    }
}
```

### ExceptionInternal - 内部异常

**用途：** 处理内部系统异常

```php
// src/Handlers/ExceptionInternal.php
namespace Core\Handlers;

class ExceptionInternal extends \Exception
{
    // 内部系统异常，用于处理配置错误、依赖缺失等问题
}

// 使用示例
class ConfigService
{
    public function loadConfig(string $configName): array
    {
        $configFile = config_path($configName . '.toml');
        
        if (!file_exists($configFile)) {
            throw new ExceptionInternal("配置文件不存在: {$configFile}");
        }
        
        if (!is_readable($configFile)) {
            throw new ExceptionInternal("配置文件不可读: {$configFile}");
        }
        
        try {
            $config = parse_toml_file($configFile);
            return $config;
            
        } catch (Exception $e) {
            throw new ExceptionInternal("配置文件解析失败: {$configFile}，错误: {$e->getMessage()}");
        }
    }
}

class DatabaseService
{
    public function connect(): void
    {
        $config = App::config('database');
        
        if (!$config->has('db.drivers.mysql')) {
            throw new ExceptionInternal('数据库配置缺失: db.drivers.mysql');
        }
        
        $dbConfig = $config->get('db.drivers.mysql');
        
        if (empty($dbConfig['host']) || empty($dbConfig['database'])) {
            throw new ExceptionInternal('数据库配置不完整，缺少host或database配置');
        }
        
        try {
            // 建立数据库连接
            $this->createConnection($dbConfig);
            
        } catch (PDOException $e) {
            throw new ExceptionInternal("数据库连接失败: {$e->getMessage()}");
        }
    }
}
```

## 异常处理中间件

### 错误处理中间件

```php
class ErrorHandler
{
    /**
     * 处理异常并返回合适的响应
     */
    public function handleException(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        // 记录异常日志
        $this->logException($exception, $request);
        
        // 根据异常类型确定状态码
        $statusCode = $this->getStatusCode($exception);
        
        // 根据请求类型返回响应
        if ($this->expectsJson($request)) {
            return $this->createJsonResponse($exception, $statusCode);
        } else {
            return $this->createHtmlResponse($exception, $statusCode, $request);
        }
    }
    
    /**
     * 记录异常日志
     */
    private function logException(Throwable $exception, ServerRequestInterface $request): void
    {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'url' => (string) $request->getUri(),
            'method' => $request->getMethod(),
            'ip' => $this->getClientIp($request),
            'user_agent' => $request->getHeaderLine('User-Agent')
        ];
        
        // 添加用户信息
        $auth = $request->getAttribute('auth');
        if ($auth) {
            $context['user_id'] = $auth['user_id'];
            $context['username'] = $auth['username'];
        }
        
        // 根据异常类型选择日志级别
        if ($exception instanceof ExceptionInternal) {
            App::log('error')->critical('系统内部异常', $context);
        } elseif ($exception instanceof ExceptionBusiness) {
            App::log('app')->warning('业务异常', $context);
        } elseif ($exception instanceof ExceptionValidator) {
            App::log('app')->info('数据验证失败', $context);
        } elseif ($exception instanceof ExceptionNotFound) {
            App::log('app')->info('资源未找到', $context);
        } else {
            App::log('error')->error('未处理异常', array_merge($context, [
                'trace' => $exception->getTraceAsString()
            ]));
        }
    }
    
    /**
     * 确定HTTP状态码
     */
    private function getStatusCode(Throwable $exception): int
    {
        if ($exception instanceof ExceptionValidator) {
            return 422;
        } elseif ($exception instanceof ExceptionNotFound) {
            return 404;
        } elseif ($exception instanceof ExceptionBusiness || $exception instanceof ExceptionBusinessLang) {
            return 500;
        } elseif ($exception instanceof ExceptionData) {
            return $exception->getCode() ?: 400;
        } else {
            return 500;
        }
    }
    
    /**
     * 创建JSON响应
     */
    private function createJsonResponse(Throwable $exception, int $statusCode): ResponseInterface
    {
        $data = [
            'code' => $statusCode,
            'message' => $exception->getMessage(),
            'data' => null
        ];
        
        // 添加验证错误数据
        if ($exception instanceof ExceptionValidator) {
            $data['data'] = $exception->getData();
        } elseif ($exception instanceof ExceptionData) {
            $data['data'] = $exception->getData();
        }
        
        // 开发环境添加调试信息
        if (App::$debug) {
            $data['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        $response = new Response($statusCode);
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * 创建HTML响应
     */
    private function createHtmlResponse(Throwable $exception, int $statusCode, ServerRequestInterface $request): ResponseInterface
    {
        $templateData = [
            'title' => $this->getErrorTitle($statusCode),
            'message' => $exception->getMessage(),
            'code' => $statusCode
        ];
        
        // 开发环境添加调试信息
        if (App::$debug) {
            $templateData['exception'] = $exception;
            $templateData['request'] = $request;
        }
        
        // 选择错误模板
        $template = $this->getErrorTemplate($statusCode);
        
        try {
            $latte = App::view();
            $html = $latte->renderToString($template, $templateData);
            
            $response = new Response($statusCode);
            $response->getBody()->write($html);
            
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
            
        } catch (Exception $e) {
            // 模板渲染失败，返回简单的HTML
            return $this->createSimpleHtmlResponse($exception, $statusCode);
        }
    }
    
    /**
     * 检查是否期望JSON响应
     */
    private function expectsJson(ServerRequestInterface $request): bool
    {
        $acceptHeader = $request->getHeaderLine('Accept');
        $contentType = $request->getHeaderLine('Content-Type');
        
        return str_contains($acceptHeader, 'application/json') || 
               str_contains($contentType, 'application/json') ||
               str_starts_with($request->getUri()->getPath(), '/api/');
    }
    
    private function getErrorTitle(int $statusCode): string
    {
        return match($statusCode) {
            404 => '页面不存在',
            422 => '数据验证失败',
            500 => '服务器错误',
            default => '请求错误'
        };
    }
    
    private function getErrorTemplate(int $statusCode): string
    {
        $template = match($statusCode) {
            404 => 'errors/404.latte',
            422 => 'errors/422.latte',
            500 => 'errors/500.latte',
            default => 'errors/error.latte'
        };
        
        // 检查模板是否存在，不存在则使用通用模板
        $templatePath = App::$basePath . '/templates/' . $template;
        if (!file_exists($templatePath)) {
            return 'errors/error.latte';
        }
        
        return $template;
    }
    
    private function createSimpleHtmlResponse(Throwable $exception, int $statusCode): ResponseInterface
    {
        $title = $this->getErrorTitle($statusCode);
        $message = App::$debug ? $exception->getMessage() : '服务暂时不可用';
        
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; }
        .error { background: #f8f8f8; padding: 20px; border-radius: 5px; }
        .code { font-size: 2em; color: #666; }
    </style>
</head>
<body>
    <div class="error">
        <div class="code">{$statusCode}</div>
        <h1>{$title}</h1>
        <p>{$message}</p>
    </div>
</body>
</html>
HTML;

        $response = new Response($statusCode);
        $response->getBody()->write($html);
        
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
    
    private function getClientIp(ServerRequestInterface $request): string
    {
        $headers = ['X-Forwarded-For', 'X-Real-IP', 'X-Client-IP'];
        
        foreach ($headers as $header) {
            $ip = $request->getHeaderLine($header);
            if ($ip) {
                return explode(',', $ip)[0];
            }
        }
        
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}
```

## 自定义异常

### 创建自定义异常类

```php
// 支付相关异常
class PaymentException extends ExceptionBusiness
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message);
        
        // 记录支付异常日志
        App::log('payment')->error($message, $context);
        
        // 发送告警通知
        $this->sendPaymentAlert($message, $context);
    }
    
    private function sendPaymentAlert(string $message, array $context): void
    {
        // 实现支付异常告警逻辑
        // 可以发送邮件、短信或推送到监控系统
    }
}

// 权限相关异常
class PermissionException extends ExceptionBusiness
{
    private string $requiredPermission;
    private array $userPermissions;
    
    public function __construct(string $requiredPermission, array $userPermissions = [])
    {
        $this->requiredPermission = $requiredPermission;
        $this->userPermissions = $userPermissions;
        
        $message = "权限不足，需要 {$requiredPermission} 权限";
        parent::__construct($message);
    }
    
    public function getRequiredPermission(): string
    {
        return $this->requiredPermission;
    }
    
    public function getUserPermissions(): array
    {
        return $this->userPermissions;
    }
}

// 配额限制异常
class QuotaExceededException extends ExceptionBusiness
{
    private string $quotaType;
    private int $currentUsage;
    private int $quotaLimit;
    
    public function __construct(string $quotaType, int $currentUsage, int $quotaLimit)
    {
        $this->quotaType = $quotaType;
        $this->currentUsage = $currentUsage;
        $this->quotaLimit = $quotaLimit;
        
        $message = "超出{$quotaType}配额限制，当前使用: {$currentUsage}/{$quotaLimit}";
        parent::__construct($message);
    }
    
    public function getQuotaInfo(): array
    {
        return [
            'type' => $this->quotaType,
            'current' => $this->currentUsage,
            'limit' => $this->quotaLimit,
            'available' => max(0, $this->quotaLimit - $this->currentUsage)
        ];
    }
}

// 使用自定义异常
class PaymentService
{
    public function charge(int $userId, float $amount): void
    {
        $user = User::find($userId);
        
        if ($user->account_balance < $amount) {
            throw new PaymentException('账户余额不足', [
                'user_id' => $userId,
                'required_amount' => $amount,
                'current_balance' => $user->account_balance
            ]);
        }
        
        // 处理扣款
    }
}

class ApiService
{
    public function checkRateLimit(int $userId): void
    {
        $usage = $this->getCurrentUsage($userId);
        $limit = $this->getUserRateLimit($userId);
        
        if ($usage >= $limit) {
            throw new QuotaExceededException('API调用', $usage, $limit);
        }
    }
}
```

### 异常处理器注册

```php
class CustomExceptionHandler extends ErrorHandler
{
    protected array $customHandlers = [];
    
    public function __construct()
    {
        // 注册自定义异常处理器
        $this->customHandlers = [
            PaymentException::class => [$this, 'handlePaymentException'],
            PermissionException::class => [$this, 'handlePermissionException'],
            QuotaExceededException::class => [$this, 'handleQuotaException']
        ];
    }
    
    public function handleException(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        $exceptionClass = get_class($exception);
        
        // 检查是否有自定义处理器
        if (isset($this->customHandlers[$exceptionClass])) {
            return call_user_func($this->customHandlers[$exceptionClass], $exception, $request);
        }
        
        return parent::handleException($exception, $request);
    }
    
    private function handlePaymentException(PaymentException $exception, ServerRequestInterface $request): ResponseInterface
    {
        // 支付异常的特殊处理
        $data = [
            'code' => 402, // Payment Required
            'message' => $exception->getMessage(),
            'error_type' => 'payment_error',
            'timestamp' => time(),
            'request_id' => $this->generateRequestId()
        ];
        
        $response = new Response(402);
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Error-Type', 'payment');
    }
    
    private function handlePermissionException(PermissionException $exception, ServerRequestInterface $request): ResponseInterface
    {
        $data = [
            'code' => 403,
            'message' => $exception->getMessage(),
            'error_type' => 'permission_denied',
            'required_permission' => $exception->getRequiredPermission(),
            'user_permissions' => $exception->getUserPermissions()
        ];
        
        if (App::$debug) {
            $data['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ];
        }
        
        $response = new Response(403);
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Error-Type', 'permission');
    }
    
    private function handleQuotaException(QuotaExceededException $exception, ServerRequestInterface $request): ResponseInterface
    {
        $quotaInfo = $exception->getQuotaInfo();
        
        $data = [
            'code' => 429, // Too Many Requests
            'message' => $exception->getMessage(),
            'error_type' => 'quota_exceeded',
            'quota_info' => $quotaInfo,
            'retry_after' => $this->calculateRetryAfter($quotaInfo['type'])
        ];
        
        $response = new Response(429);
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        
        $retryAfter = $this->calculateRetryAfter($quotaInfo['type']);
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Error-Type', 'quota')
            ->withHeader('Retry-After', (string) $retryAfter)
            ->withHeader('X-RateLimit-Limit', (string) $quotaInfo['limit'])
            ->withHeader('X-RateLimit-Remaining', (string) $quotaInfo['available']);
    }
    
    private function generateRequestId(): string
    {
        return uniqid('req_', true);
    }
    
    private function calculateRetryAfter(string $quotaType): int
    {
        return match($quotaType) {
            'API调用' => 3600, // 1小时后重试
            '文件上传' => 86400, // 1天后重试
            default => 1800 // 默认30分钟
        };
    }
}
```

## 异常监控和告警

### 异常统计收集

```php
class ExceptionMonitor
{
    /**
     * 记录异常统计
     */
    public function recordException(Throwable $exception, ServerRequestInterface $request): void
    {
        $exceptionData = [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'url' => (string) $request->getUri(),
            'method' => $request->getMethod(),
            'ip' => $this->getClientIp($request),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'timestamp' => time(),
            'hash' => $this->generateExceptionHash($exception)
        ];
        
        // 添加用户信息
        $auth = $request->getAttribute('auth');
        if ($auth) {
            $exceptionData['user_id'] = $auth['user_id'];
        }
        
        // 保存到数据库或缓存
        $this->saveExceptionRecord($exceptionData);
        
        // 检查是否需要告警
        $this->checkAlertThreshold($exceptionData);
    }
    
    /**
     * 生成异常唯一标识
     */
    private function generateExceptionHash(Throwable $exception): string
    {
        return md5(
            get_class($exception) . 
            $exception->getMessage() . 
            $exception->getFile() . 
            $exception->getLine()
        );
    }
    
    /**
     * 保存异常记录
     */
    private function saveExceptionRecord(array $data): void
    {
        $cache = App::cache();
        $hash = $data['hash'];
        $today = date('Y-m-d');
        
        // 更新今日异常计数
        $countKey = "exception_count:{$today}:{$hash}";
        $currentCount = $cache->get($countKey, 0);
        $cache->set($countKey, $currentCount + 1, 86400); // 保存24小时
        
        // 保存异常详情（如果是新异常）
        $detailKey = "exception_detail:{$hash}";
        if (!$cache->has($detailKey)) {
            $cache->set($detailKey, $data, 604800); // 保存7天
        }
        
        // 更新最后发生时间
        $lastSeenKey = "exception_last_seen:{$hash}";
        $cache->set($lastSeenKey, $data['timestamp'], 604800);
    }
    
    /**
     * 检查告警阈值
     */
    private function checkAlertThreshold(array $data): void
    {
        $hash = $data['hash'];
        $today = date('Y-m-d');
        
        $cache = App::cache();
        $countKey = "exception_count:{$today}:{$hash}";
        $count = $cache->get($countKey, 0);
        
        // 设置告警阈值
        $thresholds = [
            5,   // 5次
            20,  // 20次
            100  // 100次
        ];
        
        foreach ($thresholds as $threshold) {
            if ($count == $threshold) {
                $this->sendAlert($data, $count);
                break;
            }
        }
    }
    
    /**
     * 发送告警
     */
    private function sendAlert(array $exceptionData, int $count): void
    {
        $message = sprintf(
            "异常告警: %s\n错误: %s\n文件: %s:%d\n今日发生次数: %d\n时间: %s",
            $exceptionData['type'],
            $exceptionData['message'],
            $exceptionData['file'],
            $exceptionData['line'],
            $count,
            date('Y-m-d H:i:s', $exceptionData['timestamp'])
        );
        
        // 记录告警日志
        App::log('alert')->warning('异常告警', [
            'exception_hash' => $exceptionData['hash'],
            'count' => $count,
            'exception_data' => $exceptionData
        ]);
        
        // 发送通知（邮件、短信、钉钉等）
        $this->sendNotification($message, $exceptionData);
    }
    
    private function sendNotification(string $message, array $exceptionData): void
    {
        // 实现具体的通知发送逻辑
        // 这里只是示例，写入文件
        $alertFile = App::$dataPath . '/alerts/exceptions.log';
        $alertDir = dirname($alertFile);
        
        if (!is_dir($alertDir)) {
            mkdir($alertDir, 0755, true);
        }
        
        file_put_contents($alertFile, $message . "\n\n", FILE_APPEND);
    }
    
    /**
     * 获取异常统计报告
     */
    public function getStatistics(string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $cache = App::cache();
        
        $pattern = "exception_count:{$date}:*";
        $keys = $cache->get($pattern, []);
        
        $statistics = [
            'date' => $date,
            'total_exceptions' => 0,
            'unique_exceptions' => 0,
            'top_exceptions' => []
        ];
        
        $exceptionCounts = [];
        
        foreach ($keys as $key) {
            $count = $cache->get($key, 0);
            $hash = str_replace("exception_count:{$date}:", '', $key);
            
            $exceptionCounts[$hash] = $count;
            $statistics['total_exceptions'] += $count;
        }
        
        $statistics['unique_exceptions'] = count($exceptionCounts);
        
        // 获取Top异常
        arsort($exceptionCounts);
        $topHashes = array_slice(array_keys($exceptionCounts), 0, 10);
        
        foreach ($topHashes as $hash) {
            $detail = $cache->get("exception_detail:{$hash}");
            if ($detail) {
                $statistics['top_exceptions'][] = [
                    'hash' => $hash,
                    'type' => $detail['type'],
                    'message' => $detail['message'],
                    'file' => $detail['file'] . ':' . $detail['line'],
                    'count' => $exceptionCounts[$hash]
                ];
            }
        }
        
        return $statistics;
    }
}
```

## 最佳实践

### 1. 异常类型选择

```php
// ✅ 推荐：根据业务场景选择合适的异常类型

// 业务逻辑错误
if ($order->status === 'shipped') {
    throw new ExceptionBusiness('订单已发货，无法修改');
}

// 资源不存在
$user = User::find($id);
if (!$user) {
    throw new ExceptionNotFound('用户不存在');
}

// 数据验证失败
if (!$validator->validate()) {
    throw new ExceptionValidator($validator->errors());
}

// 系统配置错误
if (!file_exists($configFile)) {
    throw new ExceptionInternal('配置文件缺失');
}
```

### 2. 异常消息设计

```php
// ✅ 推荐：提供有意义的错误消息
throw new ExceptionBusiness('订单金额不能超过账户余额，当前余额: ¥' . $balance);

// ❌ 避免：模糊的错误消息
throw new ExceptionBusiness('操作失败');

// ✅ 推荐：使用多语言支持
throw new ExceptionBusinessLang('order.insufficient_balance', [
    'balance' => $balance,
    'required' => $amount
]);
```

### 3. 异常数据携带

```php
// ✅ 推荐：携带有用的上下文信息
throw new ExceptionData('支付处理失败', 400, [
    'payment_id' => $paymentId,
    'error_code' => $apiResponse['error_code'],
    'amount' => $amount,
    'currency' => $currency
]);

// ✅ 推荐：敏感信息脱敏
throw new ExceptionData('用户认证失败', 401, [
    'username' => $username,
    'ip' => $request->getClientIp(),
    'password' => '***' // 密码脱敏
]);
```

### 4. 异常日志记录

```php
// ✅ 推荐：记录完整的异常上下文
try {
    $this->processPayment($paymentData);
} catch (Exception $e) {
    App::log('payment')->error('支付处理异常', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile() . ':' . $e->getLine(),
        'payment_data' => $this->sanitizePaymentData($paymentData),
        'user_id' => $this->getCurrentUserId(),
        'trace' => $e->getTraceAsString()
    ]);
    
    throw $e;
}
```

### 5. 异常监控

```php
// ✅ 推荐：设置异常监控和告警
class ExceptionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            // 记录异常统计
            App::di()->get(ExceptionMonitor::class)->recordException($e, $request);
            
            // 重新抛出异常让框架处理
            throw $e;
        }
    }
}
```

通过遵循这些最佳实践，您可以构建出健壮、用户友好的 DuxLite 异常处理系统。合理的异常设计不仅能提高应用程序的稳定性，还能为问题排查和系统监控提供有价值的信息。