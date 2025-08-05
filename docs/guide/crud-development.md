# CRUD 开发

基于资源控制器的快速 CRUD 开发入门指南。

## 快速开始

### 1. 注册路由

在模块的 `App.php` 中注册路由：

```php
// app/Admin/App.php
<?php
namespace App\Admin;

use Core\App\AppExtend;
use Core\Bootstrap;
use Core\Route\Route;

class App extends AppExtend
{
    public function register(Bootstrap $bootstrap): void
    {
        // 注册管理后台路由
        $adminRoute = new Route('/admin', 'admin');
        \Core\App::route()->set('admin', $adminRoute);
    }
}
```

### 2. 创建资源控制器

```php
// app/Admin/UserController.php
<?php
namespace App\Admin;

use Core\Resources\Attribute\Resource;
use Core\Resources\Action\Resources;

#[Resource(app: 'admin', route: '/users', name: 'users')]
class UserController extends Resources
{
    protected string $model = User::class;
    
    // 自动提供 CRUD 接口：
    // GET    /admin/users        - 列表
    // POST   /admin/users        - 创建  
    // GET    /admin/users/{id}   - 详情
    // PUT    /admin/users/{id}   - 更新
    // DELETE /admin/users/{id}   - 删除
}
```

### 3. 创建模型

```php
// app/Models/User.php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    
    // 必须实现 transform 方法
    public function transform(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
```

## 自定义验证

```php
class UserController extends Resources
{
    protected string $model = User::class;
    
    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'name' => [['required', '用户名不能为空'], ['lengthMax', 50, '用户名过长']],
            'email' => [['required', '邮箱不能为空'], ['email', '邮箱格式不正确']],
        ];
    }
}
```

## 自定义方法

```php
class UserController extends Resources
{
    protected string $model = User::class;
    
    #[Action(methods: 'POST', route: '/batch')]
    public function batch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();
        $action = $data['action'];
        $ids = $data['ids'];
        
        switch ($action) {
            case 'delete':
                User::whereIn('id', $ids)->delete();
                break;
            case 'enable':
                User::whereIn('id', $ids)->update(['status' => 1]);
                break;
        }
        
        return send($response, 'ok', '操作成功');
    }
}
```

## 添加认证

```php
// 注册需要认证的路由
$adminRoute = new Route('/admin', 'admin', new AuthMiddleware());
\Core\App::route()->set('admin', $adminRoute);
```

## 下一步

- 详细的 CRUD 开发文档：[资源控制器参考](/reference/crud/resources)
- 数据验证：[验证器参考](/reference/core/validator)
- 模型使用：[模型参考](/reference/core/models)
- 权限控制：[权限参考](/reference/crud/permissions)