# 异常类型

DuxLite 框架提供了完整的异常处理体系，包含多种预定义的异常类型，用于处理不同场景下的错误情况。

## 异常层次结构

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

## 基础异常类

### Exception

**类名：** `Core\Handlers\Exception`
**继承：** `\RuntimeException`
**用途：** 框架的基础异常类，所有其他异常类的父类

```php
<?php

use Core\Handlers\Exception;

// 抛出基础异常
throw new Exception('发生了错误');
```

## 业务异常类

### ExceptionBusiness

**类名：** `Core\Handlers\ExceptionBusiness`
**继承：** `Core\Handlers\Exception`
**HTTP 状态码：** 500
**用途：** 处理业务逻辑异常

```php
<?php

use Core\Handlers\ExceptionBusiness;

// 业务逻辑错误
if (!$user->hasPermission('delete')) {
    throw new ExceptionBusiness('用户没有删除权限');
}
```

#### 使用场景
- 权限验证失败
- 业务规则违反
- 操作条件不满足
- 资源状态异常

### ExceptionBusinessLang

**类名：** `Core\Handlers\ExceptionBusinessLang`
**继承：** `Core\Handlers\Exception`
**用途：** 支持多语言的业务异常

```php
<?php

use Core\Handlers\ExceptionBusinessLang;

// 支持多语言的业务异常
throw new ExceptionBusinessLang('error.permission_denied');
```

#### 特性
- 自动根据当前语言环境翻译错误消息
- 支持占位符参数
- 与翻译系统集成

## 数据异常类

### ExceptionData

**类名：** `Core\Handlers\ExceptionData`
**继承：** `Core\Handlers\Exception`
**用途：** 处理数据相关异常，支持携带额外数据

```php
<?php

use Core\Handlers\ExceptionData;

// 携带额外数据的异常
throw new ExceptionData('数据处理失败', 400, [
    'errors' => ['field1' => '字段验证失败'],
    'data' => $inputData
]);
```

#### 属性
- `$data` - 附加数据数组，可包含错误详情、调试信息等

### ExceptionValidator

**类名：** `Core\Handlers\ExceptionValidator`
**继承：** `Core\Handlers\ExceptionData`
**HTTP 状态码：** 422
**用途：** 处理数据验证异常

```php
<?php

use Core\Handlers\ExceptionValidator;

// 验证失败异常
$errors = [
    'name' => ['名称不能为空'],
    'email' => ['邮箱格式不正确', '邮箱已存在']
];

throw new ExceptionValidator($errors);
```

#### 构造函数

```php
public function __construct(array $data)
```

**参数：**
- `$data` - 验证错误数组，格式为 `['field' => ['error1', 'error2']]`

#### 特性
- 自动提取第一个错误作为主错误消息
- 保留完整的验证错误数据
- 自动设置 422 状态码
- 与表单验证系统集成

## 系统异常类

### ExceptionNotFound

**类名：** `Core\Handlers\ExceptionNotFound`
**继承：** `Core\Handlers\Exception`
**HTTP 状态码：** 404
**用途：** 处理资源未找到异常

```php
<?php

use Core\Handlers\ExceptionNotFound;

// 资源未找到
$user = User::find($id);
if (!$user) {
    throw new ExceptionNotFound('用户不存在');
}
```

### ExceptionInternal

**类名：** `Core\Handlers\ExceptionInternal`
**继承：** `\Exception`
**用途：** 处理内部系统异常

```php
<?php

use Core\Handlers\ExceptionInternal;

// 系统内部错误
if (!file_exists($configFile)) {
    throw new ExceptionInternal('配置文件不存在');
}
```

## 异常处理机制

### 自动异常处理

框架内置了完整的异常处理机制，会自动捕获并处理异常：

```php
// 在控制器中抛出异常
public function deleteUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    $id = $request->getAttribute('id');
    $user = User::find($id);

    if (!$user) {
        // 自动返回 404 响应
        throw new ExceptionNotFound('用户不存在');
    }

    if (!$this->hasPermission('user.delete')) {
        // 自动返回 500 响应
        throw new ExceptionBusiness('没有删除权限');
    }

    // 验证失败自动返回 422 响应
    $validator = new Validator($request->getParsedBody());
    if (!$validator->validate()) {
        throw new ExceptionValidator($validator->errors());
    }

    $user->delete();
    return response()->json(['message' => '删除成功']);
}
```

### 响应格式

#### JSON 响应格式

```json
{
    "status": false,
    "code": 422,
    "message": "名称不能为空",
    "data": {
        "name": ["名称不能为空"],
        "email": ["邮箱格式不正确"]
    }
}
```

#### HTML 响应格式

显示友好的错误页面，支持自定义错误模板。

### 自定义异常渲染器

可以为不同的内容类型注册自定义异常渲染器：

```php
// 在 Bootstrap::loadRoute() 中注册
$errorHandler->registerErrorRenderer("application/json", CustomJsonRenderer::class);
$errorHandler->registerErrorRenderer("text/html", CustomHtmlRenderer::class);
```

## 异常使用最佳实践

### 1. 选择合适的异常类型

```php
// ✅ 正确：业务逻辑错误使用 ExceptionBusiness
if ($account->balance < $amount) {
    throw new ExceptionBusiness('账户余额不足');
}

// ✅ 正确：资源不存在使用 ExceptionNotFound
$product = Product::find($id);
if (!$product) {
    throw new ExceptionNotFound('产品不存在');
}

// ✅ 正确：验证错误使用 ExceptionValidator
if (!$validator->validate()) {
    throw new ExceptionValidator($validator->errors());
}
```

### 2. 提供有意义的错误消息

```php
// ❌ 错误：消息过于简单
throw new ExceptionBusiness('错误');

// ✅ 正确：描述具体的错误原因
throw new ExceptionBusiness('订单已发货，无法取消');
```

### 3. 携带必要的上下文数据

```php
// ✅ 为调试提供上下文信息
throw new ExceptionData('支付处理失败', 400, [
    'order_id' => $orderId,
    'payment_method' => $paymentMethod,
    'error_code' => $apiErrorCode
]);
```

### 4. 国际化错误消息

```php
// ✅ 使用翻译键
throw new ExceptionBusinessLang('order.cannot_cancel_shipped');

// 在语言文件中定义
// zh-CN: "订单已发货，无法取消"
// en-US: "Cannot cancel shipped order"
```

### 5. 验证异常的标准格式

```php
// ✅ 标准的验证错误格式
$errors = [
    'name' => ['名称不能为空', '名称长度必须在2-50个字符之间'],
    'email' => ['邮箱格式不正确'],
    'age' => ['年龄必须大于18岁']
];

throw new ExceptionValidator($errors);
```

## 异常监控和日志

### 异常日志记录

框架会自动记录异常到日志文件：

```php
// 异常会自动记录到 data/logs/app.log
App::log('app')->error($exception->getMessage(), [
    'file' => $exception->getFile(),
    'line' => $exception->getLine(),
    'trace' => $exception->getTraceAsString()
]);
```

### 自定义异常处理

```php
// 创建自定义异常类
class PaymentException extends ExceptionBusiness
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message);

        // 记录支付相关日志
        App::log('payment')->error($message, $context);

        // 发送告警通知
        $this->sendAlert($message, $context);
    }

    private function sendAlert(string $message, array $context): void
    {
        // 发送告警逻辑
    }
}
```

## 总结

DuxLite 的异常体系提供了：

- **类型丰富**：覆盖常见的错误场景
- **自动处理**：无需手动处理 HTTP 响应
- **数据携带**：支持携带详细的错误信息
- **多语言支持**：错误消息可国际化
- **标准化**：统一的错误响应格式
- **可扩展**：支持自定义异常类型和处理器

通过合理使用这些异常类，可以构建健壮的错误处理机制，提供良好的用户体验和开发调试体验。