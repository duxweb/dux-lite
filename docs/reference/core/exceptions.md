# 异常处理系统

DuxLite 提供了分层的异常处理体系，不同异常类型自动对应相应的HTTP状态码和响应格式。

## 异常类型

### 异常层次结构

```
Core\Handlers\Exception                    // 基础异常
├── ExceptionBusiness        // 业务异常 (500)
├── ExceptionBusinessLang    // 多语言业务异常 (500)
├── ExceptionNotFound        // 未找到异常 (404)
├── ExceptionData            // 数据异常 (400)
│   └── ExceptionValidator   // 验证异常 (422)
└── ExceptionInternal        // 内部异常 (500)
```

### ExceptionBusiness - 业务异常

**HTTP状态码：** 500  
**用途：** 业务逻辑错误
**重要：** ExceptionBusiness 及其继承的异常不会自动写入日志

```php
// 基本使用
throw new ExceptionBusiness('订单已发货，无法修改');
throw new ExceptionBusiness('账户余额不足');

// 使用场景
- 权限验证失败
- 业务规则违反  
- 操作条件不满足
- 资源状态异常
```

### ExceptionBusinessLang - 多语言业务异常

**HTTP状态码：** 500  
**用途：** 支持多语言的业务异常
**重要：** 继承自 ExceptionBusiness，不会自动写入日志

```php
// 基本使用
throw new ExceptionBusinessLang('user.username_exists', ['username' => $name]);
throw new ExceptionBusinessLang('order.status_error');

// 构造函数
new ExceptionBusinessLang(string $message, array $parameters = [], string $domain = '')
```

### ExceptionValidator - 验证异常

**HTTP状态码：** 422  
**用途：** 数据验证失败

```php
// 基本使用
if (!$validator->validate()) {
    throw new ExceptionValidator($validator->errors());
}

// 自定义验证错误
$errors = ['username' => ['用户名已存在'], 'email' => ['邮箱格式错误']];
throw new ExceptionValidator($errors);
```

### ExceptionNotFound - 未找到异常

**HTTP状态码：** 404  
**用途：** 资源不存在

```php
// 基本使用
$user = User::find($id);
if (!$user) {
    throw new ExceptionNotFound('用户不存在');
}

throw new ExceptionNotFound('文件不存在');
throw new ExceptionNotFound('页面不存在');
```

### ExceptionData - 数据异常

**HTTP状态码：** 400（可自定义）  
**用途：** 携带额外数据的异常

```php
// 基本使用
throw new ExceptionData('支付失败', 400, [
    'payment_id' => $paymentId,
    'error_code' => $errorCode
]);

// 构造函数
new ExceptionData(string $message, int $code = 400, array $data = [])

// 获取数据
$exception->getData(); // 返回数组
```

### ExceptionInternal - 内部异常

**HTTP状态码：** 500  
**用途：** 系统内部错误

```php
// 基本使用
throw new ExceptionInternal('配置文件不存在');
throw new ExceptionInternal('数据库连接失败');
throw new ExceptionInternal('缓存服务异常');

// 使用场景
- 配置文件缺失或错误
- 依赖服务不可用
- 系统资源不足
```

## 自定义异常

### 创建自定义异常

```php
// 支付相关异常（不会自动记录日志）
class PaymentException extends ExceptionBusiness
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message);
        
        // 如需记录日志，需手动调用
        App::log('payment')->error($message, $context);
    }
}
```

DuxLite 异常处理系统为开发者提供了清晰的异常分层结构，不同类型的异常自动对应相应的HTTP状态码。注意 ExceptionBusiness 系列异常不会自动记录日志，适合处理预期的业务逻辑错误。