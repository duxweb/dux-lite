# 最佳实践

DuxLite 框架开发最佳实践指南。

## 项目结构规范

### 目录组织

```
app/
├── Controllers/        # 控制器
│   ├── Api/           # API 控制器
│   └── Web/           # Web 控制器
├── Models/            # 数据模型
├── Services/          # 业务服务
├── Middleware/        # 中间件
├── Jobs/              # 队列任务
└── Events/            # 事件处理
```

### 命名约定

```php
// 类名使用 PascalCase
class UserController extends Resources {}
class OrderService {}

// 方法名使用 camelCase
public function getUserById(int $id): ?User {}
public function createOrder(array $data): Order {}

// 变量名使用 camelCase
$userId = 123;
$orderData = [];

// 常量使用 UPPER_CASE
const MAX_UPLOAD_SIZE = 1024 * 1024;
const DEFAULT_CACHE_TTL = 3600;
```

## 控制器最佳实践

### 资源控制器

```php
// 使用资源控制器简化 CRUD 操作
#[Resource(route: '/api/users')]
class UserController extends Resources
{
    protected string $model = User::class;

    // 自动提供：index、show、store、update、destroy

    // 自定义验证规则
    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6'
        ];
    }

    // 自定义数据转换
    protected function transform($item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'email' => $item->email,
            'created_at' => $item->created_at->format('Y-m-d H:i:s')
        ];
    }
}
```

### 普通控制器

```php
class AuthController
{
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = $request->getParsedBody();

            // 验证数据
            $validator = App::validator($data, [
                'email' => 'required|email',
                'password' => 'required'
            ]);

            if ($validator->fails()) {
                throw new ExceptionValidator('验证失败', $validator->errors());
            }

            // 业务逻辑
            $token = App::auth()->attempt($data);
            if (!$token) {
                throw new ExceptionBusiness('登录失败');
            }

            return send($response, '登录成功', ['token' => $token]);

        } catch (ExceptionValidator $e) {
            return send($response, $e->getMessage(), $e->getErrors(), 422);
        } catch (ExceptionBusiness $e) {
            return send($response, $e->getMessage(), null, 400);
        }
    }
}
```

## 模型最佳实践

### 基础模型

```php
class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password'];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // 关联关系
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    // 访问器
    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar ? url('uploads/' . $this->avatar) : url('images/default-avatar.png');
    }

    // 修改器
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }
}
```

### 模型作用域

```php
class Post extends Model
{
    // 本地作用域
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // 使用示例
    // Post::published()->byCategory(1)->get();
}
```

## 服务层设计

### 业务服务

```php
class UserService
{
    public function createUser(array $data): User
    {
        // 数据验证
        $validator = App::validator($data, [
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            throw new ExceptionValidator('数据验证失败', $validator->errors());
        }

        // 事务处理
        return App::db()->transaction(function () use ($data) {
            $user = User::create($data);

            // 创建用户配置
            $user->profile()->create([
                'avatar' => 'default.png',
                'bio' => ''
            ]);

            // 发送欢迎邮件
            App::queue()->push(new SendWelcomeEmailJob($user->email));

            return $user;
        });
    }

    public function updateUser(int $id, array $data): User
    {
        $user = User::findOrFail($id);

        // 移除空值
        $data = array_filter($data, fn($value) => $value !== null);

        $user->update($data);

        // 清除相关缓存
        App::cache()->delete("user_{$id}");

        return $user;
    }
}
```

## 数据库最佳实践

### 查询优化

```php
class PostService
{
    // ✅ 好的查询
    public function getPostsWithAuthors(int $page = 1): array
    {
        return Post::select(['id', 'title', 'content', 'user_id', 'created_at'])
            ->with('user:id,name') // 预加载关联
            ->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->paginate(20, ['*'], 'page', $page)
            ->toArray();
    }

    // ❌ 避免的查询
    public function getBadPosts(): array
    {
        $posts = Post::all(); // 加载所有数据
        $result = [];

        foreach ($posts as $post) {
            $result[] = [
                'title' => $post->title,
                'author' => $post->user->name // N+1 查询问题
            ];
        }

        return $result;
    }
}
```

### 事务使用

```php
class OrderService
{
    public function createOrder(array $orderData, array $items): Order
    {
        return App::db()->transaction(function () use ($orderData, $items) {
            // 创建订单
            $order = Order::create($orderData);

            // 创建订单项
            foreach ($items as $item) {
                $order->items()->create($item);

                // 更新库存
                Product::where('id', $item['product_id'])
                    ->decrement('stock', $item['quantity']);
            }

            return $order;
        });
    }
}
```

## 缓存策略

### 查询缓存

```php
class ProductService
{
    public function getProduct(int $id): ?Product
    {
        $cacheKey = "product_{$id}";

        return App::cache()->remember($cacheKey, 3600, function () use ($id) {
            return Product::with(['category', 'images'])->find($id);
        });
    }

    public function updateProduct(int $id, array $data): Product
    {
        $product = Product::findOrFail($id);
        $product->update($data);

        // 清除缓存
        App::cache()->delete("product_{$id}");

        return $product;
    }
}
```

### 标签缓存

```php
class CategoryService
{
    public function getCategories(): array
    {
        return App::cache()->tags(['categories'])->remember('all_categories', 3600, function () {
            return Category::orderBy('sort')->get()->toArray();
        });
    }

    public function updateCategory(int $id, array $data): Category
    {
        $category = Category::findOrFail($id);
        $category->update($data);

        // 清除标签缓存
        App::cache()->tags(['categories'])->flush();

        return $category;
    }
}
```

## 队列任务

### 基础队列任务

```php
class SendEmailJob extends QueueMessage
{
    public function __construct(
        private string $email,
        private string $subject,
        private string $content
    ) {}

    public function handle(): void
    {
        try {
            // 发送邮件逻辑
            mail($this->email, $this->subject, $this->content);

            App::log()->info('邮件发送成功', ['email' => $this->email]);
        } catch (\Exception $e) {
            App::log()->error('邮件发送失败', [
                'email' => $this->email,
                'error' => $e->getMessage()
            ]);

            throw $e; // 重新抛出以触发重试
        }
    }
}
```

### 批量处理任务

```php
class ProcessUsersJob extends QueueMessage
{
    public function __construct(private array $userIds) {}

    public function handle(): void
    {
        // 分批处理，避免内存问题
        $chunks = array_chunk($this->userIds, 100);

        foreach ($chunks as $chunk) {
            User::whereIn('id', $chunk)->chunk(10, function ($users) {
                foreach ($users as $user) {
                    $this->processUser($user);
                }
            });
        }
    }

    private function processUser(User $user): void
    {
        // 处理单个用户
    }
}
```

## 错误处理

### 异常分类

```php
// 业务异常
class UserNotFoundException extends ExceptionBusiness
{
    public function __construct(int $userId)
    {
        parent::__construct("用户不存在: {$userId}", 404);
    }
}

// 验证异常
class InvalidEmailException extends ExceptionValidator
{
    public function __construct(string $email)
    {
        parent::__construct('邮箱格式无效', ['email' => $email]);
    }
}
```

### 统一错误处理

```php
class BaseController
{
    protected function handleException(\Throwable $e): ResponseInterface
    {
        // 记录错误日志
        App::log()->error('控制器异常', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        // 根据异常类型返回不同响应
        if ($e instanceof ExceptionValidator) {
            return send(new Response(), $e->getMessage(), $e->getErrors(), 422);
        }

        if ($e instanceof ExceptionBusiness) {
            return send(new Response(), $e->getMessage(), null, $e->getCode() ?: 400);
        }

        // 默认错误响应
        $message = App::config('app.debug') ? $e->getMessage() : '服务器内部错误';
        return send(new Response(), $message, null, 500);
    }
}
```

## 配置管理

### 环境配置

```toml
# config/use.toml
[app]
name = "我的应用"
debug = true
env = "development"

[database]
driver = "mysql"
host = "localhost"
database = "myapp"
username = "root"
password = ""

[cache]
default = "file"

[queue]
default = "database"
```

### 配置访问

```php
// 获取配置
$appName = App::config('app.name');
$dbHost = App::config('database.host');

// 设置默认值
$cacheDriver = App::config('cache.default', 'file');

// 检查配置存在
if (App::config()->has('redis.host')) {
    // Redis 配置存在
}
```

## 测试建议

### 单元测试

```php
class UserServiceTest extends TestCase
{
    public function testCreateUser(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $service = new UserService();
        $user = $service->createUser($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($userData['name'], $user->name);
        $this->assertEquals($userData['email'], $user->email);
    }
}
```

## 安全建议

### 输入验证

```php
// 始终验证用户输入
$validator = App::validator($request->getParsedBody(), [
    'email' => 'required|email',
    'password' => 'required|min:8'
]);

if ($validator->fails()) {
    throw new ExceptionValidator('验证失败', $validator->errors());
}
```

### SQL 注入防护

```php
// ✅ 使用参数绑定
User::where('email', $email)->first();

// ❌ 避免直接拼接 SQL
// User::whereRaw("email = '{$email}'")->first();
```

### XSS 防护

```php
// 输出时转义
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// 或使用模板引擎自动转义
```

## 性能建议

### 数据库优化

```php
// 使用索引
// 在迁移中添加索引
$table->index('user_id');
$table->index(['status', 'created_at']);

// 预加载关联
$posts = Post::with(['user', 'category'])->get();

// 只选择需要的字段
$users = User::select(['id', 'name', 'email'])->get();
```

### 缓存使用

```php
// 缓存查询结果
$popularPosts = App::cache()->remember('popular_posts', 3600, function () {
    return Post::orderBy('views', 'desc')->limit(10)->get();
});

// 缓存计算结果
$userCount = App::cache()->remember('user_count', 1800, function () {
    return User::count();
});
```

通过遵循这些最佳实践，可以编写出更加健壮、可维护和高性能的 DuxLite 应用。