# API 开发

基于路由的灵活 API 接口开发入门指南。

## 快速开始

### 1. 注册 API 路由

在模块的 `App.php` 中注册 API 路由：

```php
// app/Api/App.php
<?php
namespace App\Api;

use Core\App\AppExtend;
use Core\Bootstrap;
use Core\Route\Route;

class App extends AppExtend
{
    public function register(Bootstrap $bootstrap): void
    {
        // 注册 API 路由
        $apiRoute = new Route('/api/v1', 'api');
        \Core\App::route()->set('api', $apiRoute);
    }
}
```

### 2. 创建控制器

```php
// app/Api/UserController.php
<?php
namespace App\Api;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class UserController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $users = User::paginate();
        ["data" => $data, "meta" => $meta] = format_data($users, function ($item) {
            return $item->transform();
        });
        
        return send($response, 'ok', $data, $meta);
    }
    
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        
        // 数据验证
        $validator = \Core\Validator\Validator::parser($data, [
            'name' => [['required', '用户名不能为空']],
            'email' => [['required', '邮箱不能为空'], ['email', '邮箱格式不正确']],
        ]);
        
        $user = User::create($validator->toArray());
        
        return send($response, 'ok', $user->transform());
    }
}
```

### 3. 注册路由

```php
// 在 App.php 的 register 方法中继续添加
$apiRoute->get('/users', UserController::class . ':index', 'users.index');
$apiRoute->post('/users', UserController::class . ':create', 'users.create');
```

## 添加认证

```php
// 注册需要认证的路由
$authRoute = new Route('/api/auth', 'auth-api', new AuthMiddleware());
\Core\App::route()->set('auth-api', $authRoute);

// 添加需要认证的接口
$authRoute->get('/profile', UserController::class . ':profile', 'profile');
```

## 响应格式

```php
// 成功响应
return send($response, 'ok', $data, $meta);

// 错误响应（抛出异常）
throw new ExceptionBusiness('用户不存在', 404);
```

## 下一步

- 详细的 API 开发文档：[API 参考](/reference/api/routing)
- 中间件使用：[中间件参考](/reference/api/middleware)
- 数据验证：[验证器参考](/reference/data/validation)
- 错误处理：[异常处理](/reference/api/responses)