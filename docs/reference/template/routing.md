# 路由使用

DuxLite 模板开发使用传统的路由方式，通过在 App.php 中注册 web 路由应用，并使用 `#[Route]` 注解定义页面路由。

## 路由注册

### App.php 配置

首先需要在应用中注册 web 路由：

```php
<?php

namespace App;

use Core\App\AppExtend;
use Core\Bootstrap\Bootstrap;
use Core\Route\Route;

class App extends AppExtend
{
    public function register(Bootstrap $app): void
    {
        // 注册 web 路由应用
        \Core\App::route()->set('web', new Route());
        
        // 注册 API 路由应用
        \Core\App::route()->set('api', new Route());
    }
}
```

### 应用注册

在 `config/app.toml` 中注册应用：

```toml
# 应用注册
registers = [
    "App\\App"
]
```

## 基本路由

### 简单页面路由

```php
<?php

namespace App\Web;

use Core\Route\Attribute\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HomeController
{
    /**
     * 首页
     */
    #[Route('GET', '/', name: 'home')]
    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return view($response, 'home/index', [
            'title' => '欢迎访问',
            'message' => 'Hello DuxLite!'
        ]);
    }

    /**
     * 关于我们
     */
    #[Route('GET', '/about', name: 'about')]
    public function about(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return view($response, 'home/about', [
            'title' => '关于我们',
            'company' => 'DuxLite Framework'
        ]);
    }
}
```

### 带参数的路由

```php
class ArticleController
{
    /**
     * 文章列表
     */
    #[Route('GET', '/articles', name: 'articles')]
    public function list(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        
        $articles = Article::where('status', 1)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view($response, 'article/list', [
            'title' => '文章列表',
            'articles' => $articles,
            'page' => $page
        ]);
    }

    /**
     * 文章详情
     */
    #[Route('GET', '/articles/{id}', name: 'article')]
    public function detail(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $id = (int) $args['id'];
        
        $article = Article::find($id);
        if (!$article || $article->status !== 1) {
            throw new \Core\Handlers\ExceptionNotFound('文章不存在');
        }

        // 增加浏览量
        $article->increment('views');

        return view($response, 'article/detail', [
            'title' => $article->title,
            'article' => $article
        ]);
    }
}
```

## 表单处理

### GET 和 POST 路由

```php
class ContactController
{
    /**
     * 显示联系表单
     */
    #[Route('GET', '/contact', name: 'contact')]
    public function form(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return view($response, 'contact/form', [
            'title' => '联系我们'
        ]);
    }

    /**
     * 处理表单提交
     */
    #[Route('POST', '/contact', name: 'contact.submit')]
    public function submit(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = $request->getParsedBody();

        // 数据验证
        $rules = [
            'name' => [
                ['required', '姓名不能为空'],
                ['lengthMin', 2, '姓名至少2个字符']
            ],
            'email' => [
                ['required', '邮箱不能为空'],
                ['email', '邮箱格式无效']
            ],
            'message' => [
                ['required', '留言内容不能为空'],
                ['lengthMin', 10, '留言内容至少10个字符']
            ]
        ];

        $validated = \Core\Validator\Validator::parser($data, $rules);

        // 保存留言
        Contact::create([
            'name' => $validated->name,
            'email' => $validated->email,
            'message' => $validated->message,
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // 重定向到成功页面
        return redirect($response, '/contact/success');
    }

    /**
     * 提交成功页面
     */
    #[Route('GET', '/contact/success', name: 'contact.success')]
    public function success(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return view($response, 'contact/success', [
            'title' => '提交成功',
            'message' => '感谢您的留言，我们会尽快回复！'
        ]);
    }
}
```

## 请求处理

### 获取请求数据

```php
class UserController
{
    #[Route(['GET', 'POST'], '/profile', name: 'profile')]
    public function profile(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 获取查询参数
        $queryParams = $request->getQueryParams();
        $tab = $queryParams['tab'] ?? 'basic';

        // 获取POST数据
        $postData = $request->getParsedBody();
        
        // 获取请求头
        $headers = $request->getHeaders();
        $userAgent = $request->getHeaderLine('User-Agent');

        // 获取服务器信息
        $serverParams = $request->getServerParams();
        $ip = $serverParams['REMOTE_ADDR'] ?? '';

        // 获取Cookie
        $cookies = $request->getCookieParams();
        $sessionId = $cookies['session_id'] ?? '';

        if ($request->getMethod() === 'POST') {
            // 处理表单提交
            return $this->updateProfile($request, $response, $postData);
        } else {
            // 显示表单
            return view($response, 'user/profile', [
                'title' => '个人资料',
                'tab' => $tab,
                'user' => $this->getCurrentUser()
            ]);
        }
    }

    private function updateProfile(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $data
    ): ResponseInterface {
        // 处理用户资料更新
        $user = $this->getCurrentUser();
        $user->update($data);

        // 重定向回资料页面
        return redirect($response, '/profile?tab=' . ($data['tab'] ?? 'basic'));
    }
}
```

## 响应处理

### 模板响应

```php
// 渲染模板
return view($response, 'template/path', [
    'title' => '页面标题',
    'data' => $data
]);

// 带HTTP状态码的模板响应
return view($response, 'errors/404', ['title' => '页面未找到'], 404);
```

### 重定向响应

```php
// 简单重定向
return redirect($response, '/home');

// 带状态码的重定向
return redirect($response, '/login', 302);

// 永久重定向
return redirect($response, '/new-url', 301);
```

### JSON响应（AJAX接口）

```php
#[Route('POST', '/api/comments', name: 'api.comments')]
public function addComment(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    $data = $request->getParsedBody();

    // 验证和处理数据
    $comment = Comment::create($data);

    // 返回JSON响应
    return send($response, '评论发表成功', [
        'id' => $comment->id,
        'content' => $comment->content,
        'created_at' => $comment->created_at->format('Y-m-d H:i:s')
    ]);
}
```

通过这些路由配置，可以构建完整的模板驱动的 Web 应用，支持页面渲染、表单处理、文件上传等常见功能。