# 授权系统

DuxLite 基于用户模型的 `permission` 字段提供自动权限验证。

## 权限存储

用户权限存储在用户模型的 `permission` 字段中：

```php
class User extends Model
{
    protected $casts = [
        'permission' => 'array'  // 权限字段自动转换为数组
    ];
    
    // 用户权限示例数据
    // permission: ["admin.users.list", "admin.users.create", "admin.posts.edit"]
}
```

## 权限配置

### 应用权限注册

```php
// 在 App.php 中注册权限
public function register(Bootstrap $app): void
{
    $permission = new Permission("管理后台");
    
    // 用户管理权限组
    $permission->group("users")
        ->label("用户管理")
        ->resources(['list', 'show', 'create', 'edit', 'delete']);
    
    // 文章管理权限组  
    $permission->group("posts")
        ->label("文章管理")
        ->resources(['list', 'show', 'create', 'edit', 'delete']);
    
    // 自定义权限
    $permission->group("reports")->label("报表管理")->add("export");
    
    // 注册到权限管理器
    App::permission()->set('admin', $permission);
}
```

### 权限中间件注册

```php
// 权限中间件注册
$adminRoute = new Route('/admin', 'admin',
    new AuthMiddleware('admin'),                          // 认证中间件
    new PermissionMiddleware('admin', \App\Models\User::class)  // 权限中间件
);
```

## 控制器中的权限使用

### 自动权限验证

```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    // 权限由中间件自动验证：
    // admin.users.list     - list()
    // admin.users.show     - show()
    // admin.users.create   - create()
    // admin.users.edit     - edit()
    // admin.users.store    - store()
    // admin.users.delete   - delete()
    
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // 权限已由中间件自动验证，直接执行业务逻辑
        $users = User::query()->paginate();
        
        ["data" => $data, "meta" => $meta] = format_data($users, function ($item) {
            return $item->transform();
        });
        
        return send($response, 'ok', $data, $meta);
    }
}
```

### 跳过权限验证

```php
#[Resource(app: 'admin', route: '/admin/users', name: 'users')]
class UserController extends Resources
{
    // 跳过权限检查
    #[Action(methods: 'GET', route: '/stats', name: 'stats', can: false)]
    public function getStats(): ResponseInterface {}

    // 跳过认证和权限  
    #[Action(methods: 'GET', route: '/public', name: 'public', auth: false)]
    public function getPublic(): ResponseInterface {}
}
```

## 权限命令

```bash
# 查看所有应用的权限列表
php dux permission:list

# 查看指定应用的权限
php dux permission:list admin
```
