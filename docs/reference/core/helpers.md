# 辅助函数

DuxLite 提供了丰富的辅助函数库，简化日常开发工作。所有函数都是全局可用的，无需导入即可使用。

## JSON响应 - send()

JSON 响应处理函数

```php
// 发送JSON响应
function send(ResponseInterface $response, string $message, array|object|null $data = null, array $meta = [], int $code = 200): ResponseInterface

// 使用示例
return send($response, '获取成功', $user->transform());
return send($response, '数据验证失败', $errors, [], 422);
```

## HTML响应 - sendText()

HTML 文本响应处理函数

```php
// 发送HTML文本响应
function sendText(ResponseInterface $response, string $message, int $code = 200): ResponseInterface

// 使用示例
return sendText($response, $htmlContent);
```

## 模板响应 - sendTpl()

模板响应处理函数

```php
// 发送模板响应
function sendTpl(ResponseInterface $response, string $tpl, array $data = [], string $name = 'web', int $code = 200): ResponseInterface

// 使用示例
return sendTpl($response, 'pages/home', ['title' => '首页', 'posts' => $posts]);
```

## 根目录路径 - base_path()

项目根目录路径

```php
// 项目根目录路径
function base_path(string $path = ""): string

// 使用示例
$rootPath = base_path();
$configFile = base_path('config/app.toml');
```

## 应用路径 - app_path()

应用目录路径

```php
// 应用目录路径
function app_path(string $path = ""): string

// 使用示例
$appDir = app_path();
$controllerFile = app_path('Controllers/UserController.php');
```

## 数据路径 - data_path()

数据目录路径

```php
// 数据目录路径
function data_path(string $path = ""): string

// 使用示例
$dataDir = data_path();
$logFile = data_path('logs/app.log');
```

## 配置路径 - config_path()

配置目录路径

```php
// 配置目录路径
function config_path(string $path = ""): string

// 使用示例
$configDir = config_path();
$dbConfig = config_path('database.toml');
```

## 公共路径 - public_path()

公共目录路径

```php
// 公共目录路径
function public_path(string $path = ""): string

// 使用示例
$publicDir = public_path();
$uploadDir = public_path('uploads/images');
```

## 路由URL - url()

生成命名路由URL

```php
// 生成命名路由URL
function url(string $name, array $params = []): string

// 使用示例
$homeUrl = url('home', []);
$userUrl = url('user.profile', ['id' => $userId]);
```

## 分页格式化 - format_data()

分页数据格式化

```php
// 格式化分页数据
function format_data(Collection|LengthAwarePaginator|Model|null $data, callable $callback): array

// 使用示例
$posts = Post::paginate();
["data" => $data, "meta" => $meta] = format_data($posts, fn($post) => $post->transform());
return send($response, '获取成功', $data, $meta);
```

## 当前时间 - now()

获取当前时间

```php
// 获取当前时间
function now(DateTimeZone|string|int|null $timezone = null): Carbon

// 使用示例
$currentTime = now();
$createdAt = now();
$executeAt = now()->addMinutes(30);
```

## 字符串加密 - encryption()

字符串加密

```php
// 字符串加密
function encryption(string $str, string $key = '', string $iv = '', $method = 'AES-256-CBC'): string

// 使用示例
$encrypted = encryption($data);
$encryptedWithKey = encryption($data, $customKey);
```

## 字符串解密 - decryption()

字符串解密

```php
// 字符串解密
function decryption(string $str, string $key = '', string $iv = '', $method = 'AES-256-CBC'): string|false

// 使用示例
$decrypted = decryption($encryptedData);
if ($decrypted === false) {
    throw new Exception('解密失败');
}
```

## 数值格式化 - bc_format()

数值格式化

```php
// 数值格式化
function bc_format(int|float|string $value = 0, int $decimals = 2): string

// 使用示例
$price = bc_format(123.456, 2); // "123.46"
```

## 数学运算 - bc_math()

高精度数学运算

```php
// 高精度数学运算
function bc_math(int|float|string $left = 0, string $symbol = '+', int|float|string $right = 0, int $default = 2): string

// 使用示例
$result = bc_math('10.5', '+', '20.3'); // "30.80"
$total = bc_math($price, '*', $quantity);
```

## 数值比较 - bc_comp()

数值比较

```php
// 数值比较
function bc_comp(int|float|string $left = 0, int|float|string $right = 0, int $scale = 2): int

// 使用示例
$comparison = bc_comp('10.50', '10.30'); // 1 (大于)
if (bc_comp($paidAmount, $orderAmount) >= 0) {
    // 支付足够
}
```

## 字符串隐藏 - str_hidden()

字符串部分隐藏

```php
// 字符串部分隐藏
function str_hidden(string $str, int $percent = 50, string $hide = '*', string $explode = ''): string

// 使用示例
$maskedEmail = str_hidden('user@example.com', 60, '*', '@'); // "us**@example.com"
$maskedPhone = str_hidden('13812345678', 50, '*'); // "138****5678"
```

## 文件大小 - human_filesize()

人性化文件大小显示

```php
// 人性化文件大小显示
function human_filesize(int $bytes, int $decimals = 2): string

// 使用示例
$size = human_filesize(1024); // "1.00 kB"
$size = human_filesize(1048576); // "1.00 MB"
```

## 获取IP - get_ip()

获取客户端真实IP地址

```php
// 获取客户端真实IP地址  
function get_ip(): string

// 使用示例
$clientIp = get_ip();
App::log()->info('用户登录', ['ip' => get_ip()]);
```

## 调试输出 - dd()

调试输出并终止执行

```php
// 调试输出并终止执行
function dd(...$vars): void

// 使用示例（仅在开发环境使用）
if (App::$debug) {
    dd($user, $posts);
}
```

## 服务模式 - is_service()

检查是否运行在服务模式

```php
// 检查是否运行在服务模式
function is_service(): bool

// 使用示例
$isService = is_service();
if (is_service()) {
    // 服务模式特有逻辑
}
```

## 翻译字符串 - __()

翻译字符串

```php
// 翻译字符串
function __(string $value, ...$params): string

// 使用示例
$message = __('user.welcome', ['name' => $user->name]);
$error = __('validation.required', [], 'validation');
```

DuxLite 辅助函数系统提供了简洁而强大的全局工具函数，帮助开发者在日常开发中提高效率。所有函数都经过精心设计，既保证了功能的完整性，又确保了使用的便利性。
