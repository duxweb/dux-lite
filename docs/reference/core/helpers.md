# 辅助函数

DuxLite 提供了丰富的辅助函数库，涵盖响应处理、路径管理、加密解密、数学计算、字符串处理等常用功能。这些全局函数简化了日常开发工作，提高了代码的可读性和开发效率。

## 基本概念

### 设计理念

DuxLite 辅助函数系统采用**全局可用、类型安全、功能专一**的设计：

- **全局可用**：无需导入即可在任何地方使用的全局函数
- **类型安全**：使用 PHP 8+ 类型声明，提供编译时类型检查
- **功能专一**：每个函数职责单一，便于理解和维护
- **向后兼容**：函数存在性检查，避免与其他库冲突
- **性能优化**：常用操作的高效实现

### 核心特性

- **响应处理**：JSON、HTML、模板响应的快速生成
- **路径管理**：各种路径的获取和拼接
- **数据格式化**：分页数据、URL生成等
- **加密解密**：AES加密的便捷接口
- **数学运算**：高精度数学计算
- **字符串处理**：字符串隐藏、格式化等

## 函数分类和使用

### 响应处理函数

#### send() - JSON响应

```php
/**
 * 发送JSON响应
 * 
 * @param ResponseInterface $response HTTP响应对象
 * @param string $message 响应消息
 * @param array|object|null $data 响应数据
 * @param array $meta 元数据
 * @param int $code HTTP状态码
 * @return ResponseInterface
 */
function send(ResponseInterface $response, string $message, array|object|null $data = null, array $meta = [], int $code = 200): ResponseInterface

// 使用示例
class UserController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $users = User::paginate();
        [$data, $meta] = format_data($users, fn($user) => $user->transform());
        
        return send($response, '获取成功', $data, $meta);
    }
    
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = User::find($args['id']);
        
        if (!$user) {
            return send($response, '用户不存在', null, [], 404);
        }
        
        return send($response, '获取成功', $user->transform());
    }
    
    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        
        // 数据验证
        $validator = new Validator($data, [
            'username' => ['required', 'minLength:3'],
            'email' => ['required', 'email']
        ]);
        
        if (!$validator->validate()) {
            return send($response, '数据验证失败', $validator->errors(), [], 422);
        }
        
        $user = User::create($data);
        
        return send($response, '用户创建成功', $user->transform(), [], 201);
    }
}

// 响应格式
{
    "code": 200,
    "message": "获取成功",
    "data": {
        "id": 1,
        "username": "john",
        "email": "john@example.com"
    },
    "meta": {
        "total": 100,
        "page": 1
    }
}
```

#### sendText() - HTML文本响应

```php
/**
 * 发送HTML文本响应
 * 
 * @param ResponseInterface $response HTTP响应对象
 * @param string $message 响应内容
 * @param int $code HTTP状态码
 * @return ResponseInterface
 */
function sendText(ResponseInterface $response, string $message, int $code = 200): ResponseInterface

// 使用示例
class WebController
{
    public function robots(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $robots = "User-agent: *\nAllow: /\nSitemap: " . url('sitemap', []);
        
        return sendText($response, $robots)
            ->withHeader('Content-Type', 'text/plain');
    }
    
    public function status(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $status = "OK - " . date('Y-m-d H:i:s');
        
        return sendText($response, $status);
    }
    
    public function maintenance(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head><title>系统维护中</title></head>
        <body>
            <h1>系统维护中</h1>
            <p>系统正在进行维护，请稍后再试。</p>
        </body>
        </html>
        HTML;
        
        return sendText($response, $html, 503);
    }
}
```

#### sendTpl() - 模板响应

```php
/**
 * 发送模板响应
 * 
 * @param ResponseInterface $response HTTP响应对象
 * @param string $tpl 模板路径
 * @param array $data 模板数据
 * @param string $name 视图引擎名称
 * @param int $code HTTP状态码
 * @return ResponseInterface
 */
function sendTpl(ResponseInterface $response, string $tpl, array $data = [], string $name = 'web', int $code = 200): ResponseInterface

// 使用示例
class PageController
{
    public function home(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $posts = Post::published()->latest()->limit(5)->get();
        $categories = Category::active()->get();
        
        return sendTpl($response, 'pages/home', [
            'title' => '首页',
            'posts' => $posts->map(fn($post) => $post->transform()),
            'categories' => $categories->map(fn($cat) => $cat->transform()),
            'meta' => [
                'description' => '网站首页',
                'keywords' => '首页,博客,文章'
            ]
        ]);
    }
    
    public function about(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return sendTpl($response, 'pages/about', [
            'title' => '关于我们',
            'company' => [
                'name' => '我的公司',
                'founded' => '2020',
                'employees' => 50
            ]
        ]);
    }
    
    public function contact(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $message = '';
        
        if ($request->getMethod() === 'POST') {
            // 处理表单提交
            $this->handleContactForm($data);
            $message = '消息发送成功';
        }
        
        return sendTpl($response, 'pages/contact', [
            'title' => '联系我们',
            'message' => $message,
            'form_data' => $data ?: []
        ]);
    }
    
    // 使用管理后台模板引擎
    public function adminDashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $stats = $this->getDashboardStats();
        
        return sendTpl($response, 'dashboard/index', [
            'title' => '控制台',
            'stats' => $stats
        ], 'admin'); // 使用 admin 视图引擎
    }
}
```

### 路径管理函数

#### 各种路径获取函数

```php
/**
 * 基础路径函数
 */
function base_path(string $path = ""): string      // 项目根目录
function app_path(string $path = ""): string       // 应用目录
function data_path(string $path = ""): string      // 数据目录
function config_path(string $path = ""): string    // 配置目录
function public_path(string $path = ""): string    // 公共目录

// 使用示例
class FileService
{
    public function uploadFile(UploadedFileInterface $file): string
    {
        // 上传文件到数据目录
        $uploadDir = data_path('uploads/files');
        $filename = uniqid() . '.' . $file->getClientFilename();
        $filepath = $uploadDir . '/' . $filename;
        
        // 确保目录存在
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $file->moveTo($filepath);
        
        return $filename;
    }
    
    public function getConfigFile(string $name): array
    {
        $configFile = config_path($name . '.toml');
        
        if (!file_exists($configFile)) {
            throw new Exception("配置文件不存在: {$configFile}");
        }
        
        return parse_toml_file($configFile);
    }
    
    public function generateReport(): string
    {
        $reportDir = data_path('reports');
        $filename = 'report_' . date('Y-m-d_H-i-s') . '.pdf';
        $filepath = $reportDir . '/' . $filename;
        
        // 生成报告逻辑
        $this->createPDFReport($filepath);
        
        return public_path('reports/' . $filename);
    }
    
    public function loadTemplate(string $template): string
    {
        $templatePath = app_path('Templates/' . $template . '.latte');
        
        if (!file_exists($templatePath)) {
            throw new Exception("模板文件不存在: {$templatePath}");
        }
        
        return file_get_contents($templatePath);
    }
}

// 路径拼接示例
$logFile = data_path('logs/app.log');
$configFile = config_path('database.toml');
$uploadDir = public_path('uploads/images');
$appClass = app_path('Controllers/UserController.php');
```

#### url() - URL生成

```php
/**
 * 生成命名路由URL
 * 
 * @param string $name 路由名称
 * @param array $params 路由参数
 * @return string
 */
function url(string $name, array $params = []): string

// 使用示例
class UrlHelper
{
    public function generateNavigation(): array
    {
        return [
            'home' => url('home', []),
            'about' => url('about', []),
            'contact' => url('contact', []),
            'blog' => url('blog.index', []),
            'login' => url('auth.login', [])
        ];
    }
    
    public function generateUserLinks(int $userId): array
    {
        return [
            'profile' => url('user.profile', ['id' => $userId]),
            'edit' => url('user.edit', ['id' => $userId]),
            'posts' => url('user.posts', ['id' => $userId]),
            'settings' => url('user.settings', ['id' => $userId])
        ];
    }
    
    public function generatePaginationLinks(int $currentPage, int $totalPages): array
    {
        $links = [];
        
        // 上一页
        if ($currentPage > 1) {
            $links['prev'] = url('blog.index', ['page' => $currentPage - 1]);
        }
        
        // 页码链接
        for ($i = 1; $i <= $totalPages; $i++) {
            $links['pages'][$i] = [
                'url' => url('blog.index', ['page' => $i]),
                'current' => $i === $currentPage
            ];
        }
        
        // 下一页
        if ($currentPage < $totalPages) {
            $links['next'] = url('blog.index', ['page' => $currentPage + 1]);
        }
        
        return $links;
    }
}

// 在模板中使用
// <a href="{url('user.profile', ['id' => $user->id])}">个人资料</a>
// <a href="{url('blog.show', ['id' => $post->id, 'slug' => $post->slug])}">阅读全文</a>
```

### 数据格式化函数

#### format_data() - 分页数据格式化

```php
/**
 * 格式化分页数据
 * 
 * @param Collection|LengthAwarePaginator|Model|null $data 数据
 * @param callable $callback 转换回调
 * @return array
 */
function format_data(Collection|LengthAwarePaginator|Model|null $data, callable $callback): array

// 使用示例
class ApiController
{
    public function getPosts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $posts = Post::with(['author', 'category'])
                    ->published()
                    ->paginate();
        
        [$data, $meta] = format_data($posts, function ($post) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'author' => $post->author->name,
                'category' => $post->category->name,
                'published_at' => $post->published_at->format('Y-m-d H:i:s'),
                'url' => url('blog.show', ['id' => $post->id])
            ];
        });
        
        return send($response, '获取成功', $data, $meta);
    }
    
    public function getUsers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $users = User::active()->paginate();
        
        [$data, $meta] = format_data($users, fn($user) => $user->transform());
        
        return send($response, '获取成功', $data, $meta);
    }
    
    public function getUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = User::find($args['id']);
        
        if (!$user) {
            return send($response, '用户不存在', null, [], 404);
        }
        
        // 单个模型也可以使用 format_data
        [$data, $meta] = format_data($user, fn($user) => $user->transform());
        
        return send($response, '获取成功', $data, $meta);
    }
    
    public function getUserPosts(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $args['id'];
        $posts = Post::where('user_id', $userId)->paginate();
        
        [$data, $meta] = format_data($posts, function ($post) use ($userId) {
            return array_merge($post->transform(), [
                'can_edit' => $post->user_id === $userId,
                'edit_url' => url('post.edit', ['id' => $post->id])
            ]);
        });
        
        return send($response, '获取成功', $data, $meta);
    }
}

// 返回格式示例
{
    "code": 200,
    "message": "获取成功",
    "data": [
        {"id": 1, "title": "文章1"},
        {"id": 2, "title": "文章2"}
    ],
    "meta": {
        "total": 100,
        "page": 1
    }
}
```

### 时间处理函数

#### now() - 获取当前时间

```php
/**
 * 获取当前时间
 * 
 * @param DateTimeZone|string|int|null $timezone 时区
 * @return Carbon
 */
function now(DateTimeZone|string|int|null $timezone = null): Carbon

// 使用示例
class TimeService
{
    public function createPost(array $data): Post
    {
        return Post::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'published_at' => now(), // 当前时间
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    public function scheduleTask(string $taskName, int $delayMinutes): void
    {
        $executeAt = now()->addMinutes($delayMinutes);
        
        Task::create([
            'name' => $taskName,
            'status' => 'pending',
            'execute_at' => $executeAt,
            'created_at' => now()
        ]);
    }
    
    public function generateReport(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        
        return [
            'period' => [
                'start' => $startOfMonth->format('Y-m-d'),
                'end' => $endOfMonth->format('Y-m-d')
            ],
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'data' => $this->collectReportData($startOfMonth, $endOfMonth)
        ];
    }
    
    public function isWorkingHours(): bool
    {
        $current = now();
        $workStart = now()->setTime(9, 0); // 9:00
        $workEnd = now()->setTime(18, 0);  // 18:00
        
        return $current->between($workStart, $workEnd) && 
               $current->isWeekday();
    }
    
    public function getBusinessDays(int $days): Carbon
    {
        $date = now();
        $addedDays = 0;
        
        while ($addedDays < $days) {
            $date->addDay();
            if ($date->isWeekday()) {
                $addedDays++;
            }
        }
        
        return $date;
    }
}
```

### 加密解密函数

#### encryption() 和 decryption()

```php
/**
 * 字符串加密
 * 
 * @param string $str 要加密的字符串
 * @param string $key 加密密钥
 * @param string $iv 初始化向量
 * @param string $method 加密方法
 * @return string
 */
function encryption(string $str, string $key = '', string $iv = '', $method = 'AES-256-CBC'): string

/**
 * 字符串解密
 * 
 * @param string $str 要解密的字符串
 * @param string $key 解密密钥
 * @param string $iv 初始化向量
 * @param string $method 解密方法
 * @return string|false
 */
function decryption(string $str, string $key = '', string $iv = '', $method = 'AES-256-CBC'): string|false

// 使用示例
class SecurityService
{
    public function encryptUserData(array $userData): string
    {
        $jsonData = json_encode($userData);
        return encryption($jsonData);
    }
    
    public function decryptUserData(string $encryptedData): array
    {
        $decrypted = decryption($encryptedData);
        
        if ($decrypted === false) {
            throw new Exception('数据解密失败');
        }
        
        return json_decode($decrypted, true);
    }
    
    public function createSecureToken(int $userId): string
    {
        $tokenData = [
            'user_id' => $userId,
            'timestamp' => time(),
            'random' => bin2hex(random_bytes(16))
        ];
        
        return encryption(json_encode($tokenData));
    }
    
    public function validateSecureToken(string $token): ?int
    {
        $decrypted = decryption($token);
        
        if ($decrypted === false) {
            return null;
        }
        
        $tokenData = json_decode($decrypted, true);
        
        // 检查令牌是否过期（24小时）
        if (time() - $tokenData['timestamp'] > 86400) {
            return null;
        }
        
        return $tokenData['user_id'];
    }
    
    public function encryptSensitiveConfig(array $config): array
    {
        $encryptedConfig = [];
        $sensitiveKeys = ['password', 'secret', 'key', 'token'];
        
        foreach ($config as $key => $value) {
            if (in_array($key, $sensitiveKeys) && is_string($value)) {
                $encryptedConfig[$key] = encryption($value);
            } else {
                $encryptedConfig[$key] = $value;
            }
        }
        
        return $encryptedConfig;
    }
}
```

### 数学计算函数

#### 高精度数学运算

```php
/**
 * 数值格式化
 */
function bc_format(int|float|string $value = 0, int $decimals = 2): string

/**
 * 高精度数学运算
 */
function bc_math(int|float|string $left = 0, string $symbol = '+', int|float|string $right = 0, int $default = 2): string

/**
 * 数值比较
 */
function bc_comp(int|float|string $left = 0, int|float|string $right = 0, int $scale = 2): int

// 使用示例
class FinancialService
{
    public function calculateOrderTotal(array $items): array
    {
        $subtotal = '0.00';
        $tax = '0.00';
        $shipping = '10.00';
        
        // 计算小计
        foreach ($items as $item) {
            $itemTotal = bc_math($item['price'], '*', $item['quantity']);
            $subtotal = bc_math($subtotal, '+', $itemTotal);
        }
        
        // 计算税费 (10%)
        $tax = bc_math($subtotal, '*', '0.10');
        
        // 计算总计
        $total = bc_math($subtotal, '+', $tax);
        $total = bc_math($total, '+', $shipping);
        
        return [
            'subtotal' => bc_format($subtotal),
            'tax' => bc_format($tax),
            'shipping' => bc_format($shipping),
            'total' => bc_format($total)
        ];
    }
    
    public function processPayment(string $orderAmount, string $paidAmount): array
    {
        $comparison = bc_comp($paidAmount, $orderAmount);
        
        if ($comparison < 0) {
            // 支付不足
            $shortage = bc_math($orderAmount, '-', $paidAmount);
            return [
                'status' => 'insufficient',
                'shortage' => bc_format($shortage)
            ];
        } elseif ($comparison > 0) {
            // 多付款，计算找零
            $change = bc_math($paidAmount, '-', $orderAmount);
            return [
                'status' => 'overpaid',
                'change' => bc_format($change)
            ];
        } else {
            // 金额正确
            return [
                'status' => 'exact',
                'change' => '0.00'
            ];
        }
    }
    
    public function calculateInterest(string $principal, string $rate, int $periods): array
    {
        $monthlyRate = bc_math($rate, '/', '12', 6); // 月利率
        $interest = '0.00';
        $schedule = [];
        
        for ($i = 1; $i <= $periods; $i++) {
            $monthlyInterest = bc_math($principal, '*', $monthlyRate);
            $interest = bc_math($interest, '+', $monthlyInterest);
            
            $schedule[] = [
                'period' => $i,
                'principal' => bc_format($principal),
                'interest' => bc_format($monthlyInterest),
                'total_interest' => bc_format($interest)
            ];
        }
        
        $totalAmount = bc_math($principal, '+', $interest);
        
        return [
            'principal' => bc_format($principal),
            'total_interest' => bc_format($interest),
            'total_amount' => bc_format($totalAmount),
            'schedule' => $schedule
        ];
    }
    
    public function splitBill(string $totalAmount, int $peopleCount): array
    {
        $perPerson = bc_math($totalAmount, '/', (string)$peopleCount);
        $roundedPerPerson = bc_format($perPerson);
        
        // 计算舍入误差
        $totalRounded = bc_math($roundedPerPerson, '*', (string)$peopleCount);
        $difference = bc_math($totalAmount, '-', $totalRounded);
        
        return [
            'total' => bc_format($totalAmount),
            'per_person' => $roundedPerPerson,
            'people_count' => $peopleCount,
            'difference' => bc_format($difference)
        ];
    }
}
```

### 字符串处理函数

#### str_hidden() - 字符串隐藏

```php
/**
 * 字符串部分隐藏
 * 
 * @param string $str 要隐藏的字符串
 * @param int $percent 隐藏百分比
 * @param string $hide 隐藏字符
 * @param string $explode 分隔符（用于邮箱等）
 * @return string
 */
function str_hidden(string $str, int $percent = 50, string $hide = '*', string $explode = ''): string

// 使用示例
class PrivacyService
{
    public function hideUserInfo(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => str_hidden($user->username, 40),
            'email' => str_hidden($user->email, 60, '*', '@'),
            'phone' => str_hidden($user->phone, 50, '*'),
            'id_card' => str_hidden($user->id_card, 70, '*'),
            'real_name' => str_hidden($user->real_name, 30, '*')
        ];
    }
    
    public function maskSensitiveData(array $data): array
    {
        $sensitiveFields = [
            'password' => 100,    // 完全隐藏
            'api_key' => 80,      // 隐藏80%
            'secret' => 90,       // 隐藏90%
            'token' => 70,        // 隐藏70%
            'card_number' => 60   // 隐藏60%
        ];
        
        foreach ($data as $key => $value) {
            if (isset($sensitiveFields[$key]) && is_string($value)) {
                $data[$key] = str_hidden($value, $sensitiveFields[$key]);
            }
        }
        
        return $data;
    }
    
    public function generatePublicProfile(User $user): array
    {
        return [
            'display_name' => $user->display_name ?: str_hidden($user->real_name, 30),
            'email' => str_hidden($user->email, 70, '*', '@'),
            'phone' => str_hidden($user->phone, 60),
            'location' => $user->city . ', ' . $user->province,
            'joined_at' => $user->created_at->format('Y-m')
        ];
    }
    
    public function logUserAction(User $user, string $action, array $context = []): void
    {
        $logData = [
            'user_id' => $user->id,
            'username' => str_hidden($user->username, 50),
            'action' => $action,
            'ip' => get_ip(),
            'context' => $this->maskSensitiveData($context),
            'timestamp' => now()->format('Y-m-d H:i:s')
        ];
        
        App::log('user_actions')->info('用户操作', $logData);
    }
}
```

### 文件处理函数

#### human_filesize() - 文件大小格式化

```php
/**
 * 人性化文件大小显示
 * 
 * @param int $bytes 字节数
 * @param int $decimals 小数位数
 * @return string
 */
function human_filesize(int $bytes, int $decimals = 2): string

// 使用示例
class FileManager
{
    public function getFileInfo(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception('文件不存在');
        }
        
        $size = filesize($filePath);
        $modified = filemtime($filePath);
        
        return [
            'path' => $filePath,
            'name' => basename($filePath),
            'size_bytes' => $size,
            'size_human' => human_filesize($size),
            'modified' => date('Y-m-d H:i:s', $modified),
            'extension' => pathinfo($filePath, PATHINFO_EXTENSION)
        ];
    }
    
    public function getDirectoryStats(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new Exception('目录不存在');
        }
        
        $totalSize = 0;
        $fileCount = 0;
        $files = [];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size = $file->getSize();
                $totalSize += $size;
                $fileCount++;
                
                $files[] = [
                    'name' => $file->getBasename(),
                    'path' => $file->getPathname(),
                    'size' => human_filesize($size),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime())
                ];
            }
        }
        
        // 按大小排序
        usort($files, function($a, $b) {
            return $b['size'] <=> $a['size'];
        });
        
        return [
            'directory' => $directory,
            'total_size' => human_filesize($totalSize),
            'file_count' => $fileCount,
            'largest_files' => array_slice($files, 0, 10) // 前10个最大文件
        ];
    }
    
    public function uploadFile(UploadedFileInterface $file): array
    {
        $size = $file->getSize();
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if ($size > $maxSize) {
            throw new Exception('文件大小超出限制，最大允许: ' . human_filesize($maxSize));
        }
        
        $filename = uniqid() . '_' . $file->getClientFilename();
        $uploadPath = data_path('uploads/' . $filename);
        
        $file->moveTo($uploadPath);
        
        return [
            'filename' => $filename,
            'original_name' => $file->getClientFilename(),
            'size' => human_filesize($size),
            'mime_type' => $file->getClientMediaType(),
            'upload_path' => $uploadPath
        ];
    }
}
```

### 网络和调试函数

#### get_ip() - 获取客户端IP

```php
/**
 * 获取客户端真实IP地址
 * 
 * @return string
 */
function get_ip(): string

// 使用示例
class SecurityService
{
    public function logLoginAttempt(string $username, bool $success): void
    {
        $ip = get_ip();
        
        LoginLog::create([
            'username' => $username,
            'ip_address' => $ip,
            'success' => $success,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'attempted_at' => now()
        ]);
        
        // 检查IP是否在黑名单中
        if ($this->isIpBlacklisted($ip)) {
            throw new SecurityException('IP地址已被封禁');
        }
        
        // 检查失败次数
        if (!$success) {
            $this->checkFailedAttempts($ip);
        }
    }
    
    public function checkRateLimit(string $action): bool
    {
        $ip = get_ip();
        $key = "rate_limit:{$action}:{$ip}";
        
        $attempts = Cache::get($key, 0);
        $limit = $this->getRateLimitForAction($action);
        
        if ($attempts >= $limit) {
            App::log('security')->warning('速率限制触发', [
                'ip' => $ip,
                'action' => $action,
                'attempts' => $attempts,
                'limit' => $limit
            ]);
            
            return false;
        }
        
        Cache::set($key, $attempts + 1, 3600); // 1小时过期
        return true;
    }
    
    public function getLocationByIp(?string $ip = null): array
    {
        $ip = $ip ?: get_ip();
        
        // 使用IP地理位置服务
        $location = $this->queryIpLocation($ip);
        
        return [
            'ip' => $ip,
            'country' => $location['country'] ?? 'Unknown',
            'region' => $location['region'] ?? 'Unknown',
            'city' => $location['city'] ?? 'Unknown',
            'timezone' => $location['timezone'] ?? 'UTC'
        ];
    }
    
    private function checkFailedAttempts(string $ip): void
    {
        $key = "failed_attempts:{$ip}";
        $attempts = Cache::get($key, 0) + 1;
        
        Cache::set($key, $attempts, 3600);
        
        if ($attempts >= 5) {
            // 临时封禁IP
            Cache::set("ip_blacklist:{$ip}", true, 1800); // 30分钟
            
            App::log('security')->error('IP临时封禁', [
                'ip' => $ip,
                'failed_attempts' => $attempts
            ]);
        }
    }
}
```

#### dd() - 调试输出

```php
/**
 * 调试输出并终止执行
 * 
 * @param mixed ...$vars 要输出的变量
 * @return void
 */
function dd(...$vars): void

// 使用示例（仅在开发环境使用）
class DebugController
{
    public function testQuery(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $users = User::with('posts')->get();
        
        // 在开发环境中调试输出
        if (App::$debug) {
            dd($users->toArray());
        }
        
        return send($response, '查询成功', $users->map(fn($u) => $u->transform()));
    }
    
    public function debugRequest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (App::$debug) {
            dd([
                'method' => $request->getMethod(),
                'uri' => (string) $request->getUri(),
                'headers' => $request->getHeaders(),
                'body' => $request->getParsedBody(),
                'attributes' => $request->getAttributes()
            ]);
        }
        
        return send($response, 'Debug完成');
    }
}
```

### 系统状态函数

#### is_service() - 检查服务状态

```php
/**
 * 检查是否运行在服务模式
 * 
 * @return bool
 */
function is_service(): bool

// 使用示例
class SystemService
{
    public function getSystemStatus(): array
    {
        return [
            'is_service' => is_service(),
            'debug_mode' => App::$debug,
            'php_version' => PHP_VERSION,
            'memory_usage' => human_filesize(memory_get_usage(true)),
            'memory_peak' => human_filesize(memory_get_peak_usage(true)),
            'uptime' => $this->getUptime(),
            'load_average' => sys_getloadavg()
        ];
    }
    
    public function healthCheck(): array
    {
        $checks = [];
        
        // 数据库连接检查
        try {
            App::db()->getPdo()->query('SELECT 1');
            $checks['database'] = ['status' => 'ok', 'message' => '数据库连接正常'];
        } catch (Exception $e) {
            $checks['database'] = ['status' => 'error', 'message' => '数据库连接失败'];
        }
        
        // 缓存检查
        try {
            $cache = App::cache();
            $testKey = 'health_check_' . time();
            $cache->set($testKey, 'test', 10);
            $result = $cache->get($testKey);
            $cache->delete($testKey);
            
            if ($result === 'test') {
                $checks['cache'] = ['status' => 'ok', 'message' => '缓存系统正常'];
            } else {
                $checks['cache'] = ['status' => 'error', 'message' => '缓存读写失败'];
            }
        } catch (Exception $e) {
            $checks['cache'] = ['status' => 'error', 'message' => '缓存系统异常'];
        }
        
        // 服务模式检查
        if (is_service()) {
            $checks['service'] = ['status' => 'ok', 'message' => '运行在服务模式'];
        } else {
            $checks['service'] = ['status' => 'info', 'message' => '运行在常规模式'];
        }
        
        return $checks;
    }
}
```

### 国际化函数

#### __() - 翻译函数

```php
/**
 * 翻译字符串
 * 
 * @param string $value 翻译键
 * @param mixed ...$params 参数
 * @return string
 */
function __(string $value, ...$params): string

// 使用示例
class LocalizationService
{
    public function getWelcomeMessage(User $user): string
    {
        return __('user.welcome', ['name' => $user->name], 'messages');
    }
    
    public function getValidationErrors(array $errors): array
    {
        $translatedErrors = [];
        
        foreach ($errors as $field => $fieldErrors) {
            $translatedErrors[$field] = array_map(function($error) {
                return __($error, [], 'validation');
            }, $fieldErrors);
        }
        
        return $translatedErrors;
    }
    
    public function formatOrderStatus(string $status): string
    {
        return match($status) {
            'pending' => __('order.status.pending'),
            'processing' => __('order.status.processing'),
            'shipped' => __('order.status.shipped'),
            'delivered' => __('order.status.delivered'),
            'cancelled' => __('order.status.cancelled'),
            default => __('order.status.unknown')
        };
    }
    
    public function generateEmailContent(string $template, array $data): array
    {
        return [
            'subject' => __("email.{$template}.subject", $data),
            'body' => __("email.{$template}.body", $data),
            'footer' => __('email.common.footer', [
                'company' => config('app.name'),
                'year' => date('Y')
            ])
        ];
    }
}

// 语言文件示例 (src/Langs/messages.zh-CN.toml)
[user]
welcome = "欢迎回来，{name}！"
profile_updated = "个人资料更新成功"

[order.status]
pending = "待处理"
processing = "处理中"
shipped = "已发货"
delivered = "已送达"
cancelled = "已取消"
```

## 最佳实践

### 1. 响应处理

```php
// ✅ 推荐：统一的响应格式
return send($response, '操作成功', $data, $meta, 200);

// ✅ 推荐：错误响应包含详细信息
return send($response, '数据验证失败', $errors, [], 422);

// ❌ 避免：直接返回原始数据
$response->getBody()->write(json_encode($data));
return $response;
```

### 2. 路径管理

```php
// ✅ 推荐：使用路径函数
$configFile = config_path('app.toml');
$uploadDir = data_path('uploads');

// ❌ 避免：硬编码路径
$configFile = '/var/www/app/config/app.toml';
```

### 3. 加密安全

```php
// ✅ 推荐：使用默认密钥和安全的初始化向量
$encrypted = encryption($data);

// ✅ 推荐：自定义密钥
$encrypted = encryption($data, $customKey);

// ❌ 避免：空密钥或弱密钥
$encrypted = encryption($data, '123456');
```

### 4. 数学计算

```php
// ✅ 推荐：使用高精度函数处理金融计算
$total = bc_math($price, '*', $quantity);
$tax = bc_math($total, '*', '0.1');

// ❌ 避免：直接使用浮点运算
$total = $price * $quantity; // 可能有精度问题
```

### 5. 调试和开发

```php
// ✅ 推荐：仅在开发环境使用调试函数
if (App::$debug) {
    dd($debugData);
}

// ✅ 推荐：使用条件调试
App::$debug && dd($data);

// ❌ 避免：在生产环境使用dd()
dd($data); // 会终止执行
```

通过合理使用这些辅助函数，您可以大大简化 DuxLite 应用的开发工作，提高代码质量和开发效率。这些函数经过精心设计，既保证了功能的完整性，又确保了使用的便利性。