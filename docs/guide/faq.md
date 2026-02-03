# 常见问题

DuxLite 开发过程中的常见问题和解决方案。

## 安装和配置

### Q: 安装时提示 PHP 版本不符合要求？

**A:** DuxLite v2 需要 PHP 8.4 或更高版本。

```bash
# 检查 PHP 版本
php -v
```

### Q: 如何配置不同环境的设置？

**A:** 使用分层配置文件：

1. **开发环境**: 创建 `*.dev.toml` 文件
2. **生产环境**: 使用 `*.toml` 文件
3. **敏感信息**: 使用占位符语法

```toml
# config/use.dev.toml - 开发环境
[app]
name = "我的应用-开发版"
debug = true
secret = "dev-secret-key"

# config/use.toml - 生产环境
[app]
name = "我的应用"
debug = false
secret = "%env(APP_SECRET)%"
```

### Q: TOML 配置文件解析失败？

**A:** 检查 TOML 语法：

```toml
# ✅ 正确的 TOML 语法
[app]
name = "应用名称"
debug = true
timeout = 30

# ❌ 常见错误
[app
name = 应用名称          # 缺少引号
debug = True            # 应该是小写 true
timeout = "30"          # 数字不需要引号
```

## 路由和控制器

### Q: 路由不生效，返回 404？

**A:** 检查 Web 服务器伪静态配置：

```nginx
# Nginx 重写规则
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

```apache
# Apache .htaccess
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

### Q: 资源控制器的 Action 注解必须要 route 参数吗？

**A:** 是的，Action 注解需要 methods 和 route 两个必需参数：

```php
// ✅ 正确用法
#[Action(methods: 'GET', route: '')]
public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
{
    // 实现逻辑
}

// ❌ 错误用法
#[Action]
public function list(): ResponseInterface
{
    // 缺少必需参数
}
```

## 数据库和模型

### Q: 数据库连接失败？

**A:** 检查连接配置：

```toml
# config/database.toml
[db.drivers.default]
driver = "mysql"
host = "localhost"
port = 3306
database = "your_database"
username = "your_username"
password = "your_password"
charset = "utf8mb4"
```

### Q: 必须实现 transform() 方法吗？

**A:** 在使用资源控制器时，建议在资源控制器中实现 `transform()` 统一数据输出：

```php
class UserController extends Resources
{
    public function transform(object $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'email' => $item->email,
            'created_at' => $item->created_at,
        ];
    }
}
```

## 数据验证

### Q: 如何使用框架的数据验证？

**A:** 使用 Validator::parser() 方法：

```php
$validator = \Core\Validator\Validator::parser($data, [
    'name' => [['required', '用户名不能为空'], ['lengthMax', 50, '用户名过长']],
    'email' => [['required', '邮箱不能为空'], ['email', '邮箱格式不正确']],
]);

// 获取验证后的数据
$validatedData = $validator->toArray();
```

### Q: 常用的验证规则有哪些？

**A:** 框架支持的验证规则：

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

## 响应处理

### Q: 有哪些响应方法可以使用？

**A:** 框架提供三种响应方法：

```php
// JSON 响应
return send($response, 'ok', $data, $meta);

// 文本响应
return sendText($response, 'Hello World');

// 模板响应
return sendTpl($response, 'template/name', $data);
```

## 异常处理

### Q: 如何正确抛出业务异常？

**A:** 使用 ExceptionBusiness 抛出异常，而不是返回错误响应：

```php
// ✅ 正确：抛出异常
if (!$user) {
    throw new ExceptionBusiness('用户不存在', 404);
}

// ❌ 错误：返回错误响应
if (!$user) {
    return send($response, 'error', '用户不存在', code: 404);
}
```

## 中间件

### Q: 资源控制器如何配置中间件？

**A:** 使用类名数组配置中间件：

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
    // 控制器实现
}
```

## 文件上传

### Q: 如何处理文件上传？

**A:** 获取上传文件并保存：

```php
public function upload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
{
    $uploadedFiles = $request->getUploadedFiles();
    $file = $uploadedFiles['file'];
    
    if ($file->getError() !== UPLOAD_ERR_OK) {
        throw new ExceptionBusiness('文件上传失败');
    }
    
$path = 'uploads/file.jpg';
$stream = $file->getStream()->detach();
\Core\App::storage()->writeStream($path, $stream);
    
    return send($response, 'ok', [
    'url' => \Core\App::storage()->publicUrl($path),
]);
}
```

## 调试

### Q: 如何开启调试模式？

**A:** 在开发环境配置中启用：

```toml
# config/use.dev.toml
[app]
debug = true
```

### Q: 如何进行调试输出？

**A:** 使用框架提供的调试函数：

```php
// 输出变量并停止执行
dd($variable);

// 输出变量，继续执行
dump($variable);

// 写入日志
\Core\App::log()->debug('调试信息', ['data' => $data]);
```

## 获取帮助

如果以上解决方案无法解决您的问题：

1. **查看文档** - [完整文档](/guide/overview)
2. **GitHub Issues** - [问题反馈](https://github.com/duxweb/dux-lite/issues)
3. **社区讨论** - [GitHub Discussions](https://github.com/duxweb/dux-lite/discussions)
