# 辅助函数

DuxLite 提供了丰富的辅助函数集合，覆盖路径处理、时间操作、字符串处理、数学运算、加密解密、网络工具等常用功能。这些函数都是全局可用的，无需导入即可使用。

## 设计理念

DuxLite 辅助函数采用**简单、实用、高效的工具函数集合**设计：

- **全局可用**：所有函数都在全局作用域中，无需导入
- **类型安全**：支持现代PHP类型声明，提供更好的IDE支持
- **防冲突**：使用 `function_exists()` 检查，避免与其他库冲突
- **性能优化**：内置缓存和优化机制
- **国际化支持**：错误信息和用户界面支持多语言

### 函数分类

- **路径处理函数**：base_path、app_path、data_path等
- **时间处理函数**：now() 函数
- **字符串处理函数**：str_hidden、human_filesize等
- **数学运算函数**：bc_format、bc_math、bc_comp高精度运算
- **加密解密函数**：encryption、decryption数据安全
- **网络和工具函数**：get_ip、is_service等
- **URL 生成函数**：url() 路由 URL 生成
- **调试和开发函数**：dd() 变量调试
- **国际化翻译函数**：__() 多语言翻译

## 路径处理函数

### 基础路径函数

```php
// 项目根目录路径
$basePath = base_path();           // /path/to/project
$configFile = base_path('config/use.toml');  // /path/to/project/config/use.toml

// 应用代码目录路径
$appPath = app_path();             // /path/to/project/app
$controller = app_path('Web/Controllers/UserController.php');

// 数据存储目录路径
$dataPath = data_path();           // /path/to/project/data
$logFile = data_path('logs/app.log');  // /path/to/project/data/logs/app.log

// 公共访问目录路径
$publicPath = public_path();       // /path/to/project/public
$uploadFile = public_path('uploads/avatar.jpg');

// 配置文件目录路径
$configPath = config_path();       // /path/to/project/config
$dbConfig = config_path('database.toml');
```

### 系统路径函数

```php
// 通用路径处理函数（底层实现）
$fullPath = sys_path('/base/path', 'sub/path');  // /base/path/sub/path
$normalizedPath = sys_path('C:\\Windows\\Path', 'file.txt');  // C:/Windows/Path/file.txt
```

**特性：**
- 自动处理不同操作系统的路径分隔符
- 规范化路径格式（统一使用 `/`）
- 移除多余的分隔符

### 实际应用示例

```php
// 配置文件加载
$configFile = config_path('app.toml');
if (file_exists($configFile)) {
    $config = parse_toml_file($configFile);
}

// 日志文件写入
$logDir = data_path('logs');
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents(data_path('logs/error.log'), $errorMessage, FILE_APPEND);

// 上传文件保存
$uploadDir = public_path('uploads/' . date('Y/m'));
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$uploadPath = public_path('uploads/' . date('Y/m') . '/' . $filename);

// 模板文件包含
$templateFile = app_path('Views/email.latte');
$template = file_get_contents($templateFile);
```

## 时间处理函数

### now() 函数

基于 Carbon 库的时间处理函数：

```php
use Carbon\Carbon;

// 基础用法
$currentTime = now();              // Carbon 实例
$timestamp = now()->timestamp;     // Unix 时间戳
$formatted = now()->format('Y-m-d H:i:s');  // 格式化时间

// 时区处理
$utcTime = now('UTC');            // UTC 时间
$localTime = now('Asia/Shanghai'); // 指定时区
$offsetTime = now('+08:00');      // 时区偏移

// 在模型中使用
class Post extends Model
{
    protected $fillable = ['title', 'content', 'published_at'];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            $post->published_at = now();  // 设置发布时间
        });
    }
}

// 在服务类中使用
class OrderService
{
    public function createOrder(array $data): Order
    {
        return Order::create([
            'order_no' => $this->generateOrderNo(),
            'amount' => $data['amount'],
            'created_at' => now(),
            'expires_at' => now()->addHours(2), // 2小时后过期
        ]);
    }
}

// 时间比较和计算
$startTime = now();
// ... 执行一些操作
$endTime = now();
$duration = $endTime->diffInSeconds($startTime);

// 常用时间操作
$tomorrow = now()->addDay();
$nextWeek = now()->addWeek();
$lastMonth = now()->subMonth();
$weekStart = now()->startOfWeek();
$monthEnd = now()->endOfMonth();
```

## 字符串处理函数

### str_hidden() - 字符串隐藏

用于隐藏敏感信息，如手机号、邮箱、身份证等：

```php
// 基础用法
$phone = '13888888888';
$hiddenPhone = str_hidden($phone, 50);  // 138****8888

// 自定义隐藏字符
$email = 'user@example.com';
$hiddenEmail = str_hidden($email, 60, '#');  // us####ample.com

// 邮箱特殊处理
$email = 'user@example.com';
$hiddenEmail = str_hidden($email, 50, '*', '@');  // us**@example.com

// 实际应用示例
class UserService
{
    public function getUserProfile(int $userId): array
    {
        $user = User::find($userId);

        return [
            'id' => $user->id,
            'username' => $user->username,
            // 隐藏手机号中间4位
            'phone' => str_hidden($user->phone, 40),
            // 隐藏邮箱用户名部分
            'email' => str_hidden($user->email, 50, '*', '@'),
            // 隐藏身份证号中间部分
            'id_card' => str_hidden($user->id_card, 70),
        ];
    }
}

// 不同隐藏程度
$text = 'sensitive-data';
$light = str_hidden($text, 20);    // 隐藏20%：sensiti##-data
$medium = str_hidden($text, 50);   // 隐藏50%：sen######data
$heavy = str_hidden($text, 80);    // 隐藏80%：s###########a
```

### human_filesize() - 文件大小格式化

将字节数转换为人类可读的文件大小：

```php
// 基础用法
echo human_filesize(1024);        // 1.00 kB
echo human_filesize(1048576);     // 1.00 MB
echo human_filesize(1073741824);  // 1.00 GB

// 自定义小数位数
echo human_filesize(1234567, 1);  // 1.2 MB
echo human_filesize(1234567, 3);  // 1.177 MB

// 实际应用示例
class FileController
{
    public function uploadFile(ServerRequestInterface $request): ResponseInterface
    {
        $uploadedFile = $request->getUploadedFiles()['file'];
        $fileSize = $uploadedFile->getSize();

        // 检查文件大小
        if ($fileSize > 10 * 1024 * 1024) {  // 10MB
            throw new ExceptionBusiness(
                '文件大小不能超过 ' . human_filesize(10 * 1024 * 1024)
            );
        }

        return send($response, '上传成功', [
            'filename' => $uploadedFile->getClientFilename(),
            'size' => human_filesize($fileSize),
            'size_bytes' => $fileSize
        ]);
    }

    public function getFileList(): array
    {
        $files = scandir(public_path('uploads'));
        $result = [];

        foreach ($files as $file) {
            if (in_array($file, ['.', '..'])) continue;

            $filePath = public_path('uploads/' . $file);
            $result[] = [
                'name' => $file,
                'size' => human_filesize(filesize($filePath)),
                'modified' => date('Y-m-d H:i:s', filemtime($filePath))
            ];
        }

        return $result;
    }
}
```

## 数学运算函数

DuxLite 提供了基于 BCMath 的高精度数学运算函数，避免浮点数精度问题：

### bc_format() - 数字格式化

```php
// 基础用法
echo bc_format(123.456);      // 123.46
echo bc_format(123.456, 1);   // 123.5
echo bc_format(123.456, 3);   // 123.456

// 金融计算中的应用
class OrderService
{
    public function calculateTotal(array $items): string
    {
        $total = 0;
        foreach ($items as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $total += $subtotal;
        }

        return bc_format($total, 2);  // 保证金额精度
    }
}
```

### bc_math() - 高精度运算

```php
// 基础运算
$sum = bc_math(10.15, '+', 20.25);      // 30.40
$diff = bc_math(100.50, '-', 20.15);    // 80.35
$product = bc_math(10.5, '*', 2.5);     // 26.25
$quotient = bc_math(100, '/', 3);       // 33.33
$remainder = bc_math(100, '%', 7);      // 2

// 自定义精度
$result = bc_math(10, '/', 3, 4);       // 3.3333

// 实际应用：购物车计算
class CartService
{
    public function calculateCart(array $cartItems): array
    {
        $subtotal = '0.00';
        $taxRate = '0.08';  // 8% 税率

        foreach ($cartItems as $item) {
            $itemTotal = bc_math($item['price'], '*', $item['quantity'], 2);
            $subtotal = bc_math($subtotal, '+', $itemTotal, 2);
        }

        $tax = bc_math($subtotal, '*', $taxRate, 2);
        $total = bc_math($subtotal, '+', $tax, 2);

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total
        ];
    }
}

// 金融利息计算
class LoanCalculator
{
    public function calculateInterest(string $principal, string $rate, int $months): string
    {
        // 月利率
        $monthlyRate = bc_math($rate, '/', '12', 6);

        // 复利计算：P * (1 + r)^n
        $amount = $principal;
        for ($i = 0; $i < $months; $i++) {
            $amount = bc_math($amount, '*', bc_math('1', '+', $monthlyRate, 6), 2);
        }

        return bc_math($amount, '-', $principal, 2);  // 利息 = 总额 - 本金
    }
}
```

### bc_comp() - 数值比较

```php
// 基础比较
$result = bc_comp('10.50', '10.25');    // 1 (大于)
$result = bc_comp('10.25', '10.50');    // -1 (小于)
$result = bc_comp('10.50', '10.50');    // 0 (等于)

// 自定义精度比较
$result = bc_comp('10.123', '10.124', 2);  // 0 (精度为2时相等)
$result = bc_comp('10.123', '10.124', 3);  // -1 (精度为3时小于)

// 实际应用：价格比较
class ProductService
{
    public function findCheaperProducts(string $maxPrice): array
    {
        $products = Product::all();
        $cheaperProducts = [];

        foreach ($products as $product) {
            if (bc_comp($product->price, $maxPrice, 2) <= 0) {
                $cheaperProducts[] = $product;
            }
        }

        return $cheaperProducts;
    }

    public function validatePriceRange(string $price, string $min, string $max): bool
    {
        return bc_comp($price, $min, 2) >= 0 && bc_comp($price, $max, 2) <= 0;
    }
}
```

## 加密解密函数

### encryption() / decryption() - 数据加密解密

基于 OpenSSL 的对称加密，用于敏感数据保护：

```php
// 基础用法（使用应用密钥）
$encrypted = encryption('sensitive data');
$decrypted = decryption($encrypted);

// 自定义密钥
$key = 'your-32-char-encryption-key-here';
$encrypted = encryption('sensitive data', $key);
$decrypted = decryption($encrypted, $key);

// 自定义IV和加密方法
$iv = random_bytes(16);
$encrypted = encryption('data', $key, $iv, 'AES-256-CBC');
$decrypted = decryption($encrypted, $key, $iv, 'AES-256-CBC');

// 实际应用：用户敏感信息加密
class UserService
{
    public function saveUserProfile(array $data): User
    {
        // 加密敏感信息
        if (isset($data['id_card'])) {
            $data['id_card'] = encryption($data['id_card']);
        }

        if (isset($data['bank_account'])) {
            $data['bank_account'] = encryption($data['bank_account']);
        }

        return User::create($data);
    }

    public function getUserProfile(int $userId): array
    {
        $user = User::find($userId);

        return [
            'id' => $user->id,
            'username' => $user->username,
            // 解密敏感信息
            'id_card' => $user->id_card ? decryption($user->id_card) : null,
            'bank_account' => $user->bank_account ? decryption($user->bank_account) : null,
        ];
    }
}

// 配置信息加密存储
class ConfigService
{
    public function saveApiKey(string $service, string $apiKey): void
    {
        Config::updateOrCreate(
            ['key' => "api_key_{$service}"],
            ['value' => encryption($apiKey)]
        );
    }

    public function getApiKey(string $service): ?string
    {
        $config = Config::where('key', "api_key_{$service}")->first();
        return $config ? decryption($config->value) : null;
    }
}

// 错误处理
try {
    $encrypted = encryption('data');
    $decrypted = decryption($encrypted);
} catch (ExceptionBusiness $e) {
    // 加密/解密失败处理
    App::log()->error('加密操作失败', ['error' => $e->getMessage()]);
}
```

## 网络和工具函数

### get_ip() - IP地址获取

智能获取用户真实IP地址，支持代理和负载均衡：

```php
// 基础用法
$userIp = get_ip();

// 获取IP的优先级：
// 1. HTTP_CLIENT_IP（客户端IP）
// 2. HTTP_X_REAL_IP（Nginx真实IP）
// 3. HTTP_X_FORWARDED_FOR（转发IP，取第一个）
// 4. REMOTE_ADDR（远程地址）
// 5. 默认返回 '0.0.0.0'

// 实际应用：用户访问记录
class AccessLogService
{
    public function logAccess(ServerRequestInterface $request): void
    {
        AccessLog::create([
            'ip' => get_ip(),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'url' => $request->getUri()->getPath(),
            'method' => $request->getMethod(),
            'created_at' => now()
        ]);
    }
}

// 安全限制：IP黑名单
class SecurityService
{
    private array $blacklistIps = ['192.168.1.100', '10.0.0.50'];

    public function checkIpBlacklist(): bool
    {
        $currentIp = get_ip();
        return in_array($currentIp, $this->blacklistIps);
    }
}

// 地域限制
class GeoService
{
    public function isAllowedRegion(): bool
    {
        $ip = get_ip();
        $geoInfo = $this->getGeoInfo($ip);

        return in_array($geoInfo['country'], ['CN', 'US', 'UK']);
    }
}
```

### is_service() - 服务检测

检查是否在服务模式下运行（如Swoole、ReactPHP等）：

```php
// 基础用法
if (is_service()) {
    // 服务模式下的特殊处理
    echo "运行在服务模式下\n";
} else {
    // 传统CGI/FPM模式
    echo "运行在传统模式下\n";
}

// 实际应用：缓存策略
class CacheService
{
    public function get(string $key)
    {
        if (is_service()) {
            // 服务模式：使用内存缓存
            return $this->getFromMemory($key);
        } else {
            // 传统模式：使用文件缓存
            return $this->getFromFile($key);
        }
    }
}

// 应用初始化
class AppBootstrap
{
    public function boot(): void
    {
        if (is_service()) {
            // 服务模式：预加载配置
            $this->preloadConfigs();
            $this->initializeConnections();
        } else {
            // 传统模式：按需加载
            $this->lazyLoadConfigs();
        }
    }
}
```

## 调试和开发函数

### dd() - 变量调试

基于 Symfony VarDumper 的调试函数：

```php
// 基础用法
$data = ['name' => 'DuxLite', 'version' => '2.0'];
dd($data);  // 输出变量内容并终止程序

// 多变量调试
$user = User::find(1);
$orders = $user->orders;
dd($user, $orders);  // 同时输出多个变量

// 条件调试
if ($debug) {
    dd($queryResult);
}

// 实际应用：调试API响应
class ApiController
{
    public function getUserData(int $userId): array
    {
        $user = User::with(['profile', 'orders'])->find($userId);

        // 开发环境下调试
        if (App::$debug) {
            dd($user->toArray());
        }

        return $user->toArray();
    }
}

// 调试复杂查询
class ReportService
{
    public function generateReport(): array
    {
        $query = User::query()
            ->with(['orders' => function($q) {
                $q->where('status', 'completed');
            }])
            ->withCount('orders')
            ->having('orders_count', '>', 10);

        // 调试SQL查询
        if (App::$debug) {
            dd($query->toSql(), $query->getBindings());
        }

        return $query->get()->toArray();
    }
}
```

## URL 生成函数

### url() - 路由 URL 生成

基于路由名称生成完整的 URL 地址：

```php
// 基础用法
$userListUrl = url('admin.users.list', []);  // /admin/users
$userDetailUrl = url('admin.users.show', ['id' => 123]);  // /admin/users/123

// 带参数的复杂路由
$resetPasswordUrl = url('admin.users.resetPassword', ['id' => 456]);  // /admin/users/reset-password/456

// 实际应用示例
class UserController extends Resources
{
    public function transform(object $item): array
    {
        return [
            'id' => $item->id,
            'username' => $item->username,
            'email' => $item->email,
            // 生成相关链接
            'links' => [
                'self' => url('admin.users.show', ['id' => $item->id]),
                'edit' => url('admin.users.edit', ['id' => $item->id]),
                'delete' => url('admin.users.delete', ['id' => $item->id]),
                'reset_password' => url('admin.users.resetPassword', ['id' => $item->id])
            ]
        ];
    }
}

// 在邮件模板中使用
class EmailService
{
    public function sendWelcomeEmail(User $user): void
    {
        $activationUrl = url('user.activate', ['token' => $user->activation_token]);
        $loginUrl = url('user.login', []);

        $emailData = [
            'user' => $user,
            'activation_url' => $activationUrl,
            'login_url' => $loginUrl
        ];

        Mail::send('emails.welcome', $emailData, $user->email);
    }
}

// API 响应中生成资源链接
class ApiProductController extends Resources
{
    public function transform(object $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'price' => $item->price,
            'category' => [
                'id' => $item->category_id,
                'name' => $item->category->name,
                'url' => url('api.categories.show', ['id' => $item->category_id])
            ],
            'images' => array_map(function($image) {
                return [
                    'url' => $image['url'],
                    'thumbnail' => url('api.images.thumbnail', ['id' => $image['id']])
                ];
            }, $item->images ?? []),
            '_links' => [
                'self' => url('api.products.show', ['id' => $item->id]),
                'category' => url('api.categories.show', ['id' => $item->category_id]),
                'reviews' => url('api.products.reviews', ['id' => $item->id])
            ]
        ];
    }
}

// 分页链接生成
class PaginationHelper
{
    public static function generatePaginationLinks(string $routeName, array $params, int $currentPage, int $totalPages): array
    {
        $links = [];

        // 上一页
        if ($currentPage > 1) {
            $prevParams = array_merge($params, ['page' => $currentPage - 1]);
            $links['prev'] = url($routeName, $prevParams);
        }

        // 下一页
        if ($currentPage < $totalPages) {
            $nextParams = array_merge($params, ['page' => $currentPage + 1]);
            $links['next'] = url($routeName, $nextParams);
        }

        // 首页和末页
        $links['first'] = url($routeName, array_merge($params, ['page' => 1]));
        $links['last'] = url($routeName, array_merge($params, ['page' => $totalPages]));

        return $links;
    }
}

// 表单action URL生成
class FormBuilder
{
    public function generateForm(string $action, array $data = []): string
    {
        $actionUrl = match($action) {
            'create' => url('admin.users.create', []),
            'edit' => url('admin.users.edit', ['id' => $data['id']]),
            'delete' => url('admin.users.delete', ['id' => $data['id']]),
            default => throw new InvalidArgumentException("Unknown action: {$action}")
        };

        return sprintf('<form action="%s" method="POST">...</form>', $actionUrl);
    }
}
```

**特性：**
- 基于 SlimPHP 路由解析器生成 URL
- 自动处理路由参数替换
- 支持嵌套路由名称（如 `admin.users.show`）
- 生成的 URL 相对于应用根目录
- 参数类型安全，自动转换为字符串

**注意事项：**
- 路由名称必须是已注册的有效路由
- 参数数组的键必须与路由中定义的参数名匹配
- 缺少必需参数会抛出异常
- 生成的是相对路径，需要配合域名使用时要额外处理

## 国际化翻译函数

### __() - 多语言翻译

支持参数化翻译和多域翻译：

```php
// 基础用法
echo __('welcome.message');  // 从当前语言包获取翻译

// 带参数的翻译
echo __('user.welcome', ['name' => 'John']);  // Hello, John!

// 指定翻译域
echo __('errors.not_found', [], 'admin');  // 从admin域获取翻译

// 参数和域同时使用
echo __('order.total', ['amount' => 100], 'shop');

// 语言包文件示例 (common.zh-CN.toml)
[welcome]
message = "欢迎使用DuxLite"

[user]
welcome = "你好，%name%！"
profile = "用户资料"

[errors]
not_found = "页面不存在"
access_denied = "访问被拒绝"

// 在控制器中使用
class UserController
{
    public function create(): ResponseInterface
    {
        $data = [
            'title' => __('user.create_title'),
            'description' => __('user.create_desc'),
            'form_fields' => [
                'username' => __('user.username'),
                'email' => __('user.email'),
                'password' => __('user.password')
            ]
        ];

        return send($response, __('common.success'), $data);
    }
}

// 在验证规则中使用
class UserValidator
{
    public static function rules(): array
    {
        return [
            'username' => [
                ['required', __('validation.required', ['field' => __('user.username')])],
                ['lengthMin', 3, __('validation.min_length', ['field' => __('user.username'), 'min' => 3])]
            ],
            'email' => [
                ['required', __('validation.required', ['field' => __('user.email')])],
                ['email', __('validation.email', ['field' => __('user.email')])]
            ]
        ];
    }
}

// 在模板中使用（Latte模板）
// {__('welcome.message')}
// {__('user.welcome', ['name' => $user->name])}
// {__('errors.not_found', [], 'admin')}

// 动态语言切换
class LanguageController
{
    public function switchLanguage(string $locale): ResponseInterface
    {
        // 设置新语言
        App::di()->set('lang', $locale);

        return send($response, __('language.switched'), [
            'current_language' => $locale,
            'message' => __('welcome.message')  // 返回新语言的欢迎信息
        ]);
    }
}
```

## 最佳实践

### 1. 路径处理

```php
// ✅ 正确：使用路径函数
$configFile = config_path('app.toml');
$logDir = data_path('logs');
$uploadPath = public_path('uploads/' . $filename);

// ❌ 避免：硬编码路径
$configFile = '/var/www/project/config/app.toml';
$logDir = '../data/logs';
```

### 2. 数学运算

```php
// ✅ 正确：金融计算使用BC函数
$total = bc_math($price, '*', $quantity, 2);
$tax = bc_math($total, '*', '0.08', 2);

// ❌ 避免：浮点数直接运算（精度问题）
$total = $price * $quantity;  // 可能有精度问题
$tax = $total * 0.08;         // 金融计算不准确
```

### 3. 敏感数据处理

```php
// ✅ 正确：敏感信息加密存储
$user->id_card = encryption($idCard);
$user->bank_account = encryption($bankAccount);

// ✅ 正确：显示时部分隐藏
$displayPhone = str_hidden($user->phone, 40);
$displayEmail = str_hidden($user->email, 50, '*', '@');

// ❌ 避免：明文存储敏感信息
$user->id_card = $idCard;  // 安全风险
```

### 4. 错误处理和调试

```php
// ✅ 正确：条件调试
if (App::$debug) {
    dd($complexData);
}

// ✅ 正确：异常处理
try {
    $encrypted = encryption($data);
} catch (ExceptionBusiness $e) {
    App::log()->error('加密失败', ['error' => $e->getMessage()]);
    throw new ExceptionBusiness(__('system.encryption_failed'));
}

// ❌ 避免：生产环境调试代码
dd($sensitiveData);  // 生产环境安全风险
```

### 5. 国际化处理

```php
// ✅ 正确：使用翻译函数
return send($response, __('operation.success'), $data);
throw new ExceptionBusiness(__('user.not_found'));

// ✅ 正确：参数化翻译
$message = __('user.welcome', ['name' => $user->name]);

// ❌ 避免：硬编码文本
return send($response, '操作成功', $data);  // 不支持国际化
throw new ExceptionBusiness('用户不存在');   // 不支持多语言
```

这些辅助函数构成了DuxLite框架的工具基础，合理使用它们可以大大提高开发效率，同时确保代码的安全性和可维护性。