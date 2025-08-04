# 模板控制器

模板控制器是处理页面请求和渲染模板的核心组件。DuxLite 提供了简洁的模板渲染方法，支持数据传递、布局管理和基本的渲染优化。

## 基本概念

### 设计理念

- **模板驱动**：基于 Latte 模板引擎的页面渲染
- **数据传递**：控制器向模板传递数据
- **布局管理**：支持布局继承和组件复用
- **错误处理**：优雅的模板错误处理机制

### 核心方法

- **view()**：渲染模板并返回响应
- **redirect()**：页面重定向
- **数据获取**：从请求中获取参数和数据
- **数据验证**：表单数据验证和处理

## 基础模板渲染

### 简单页面控制器

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
    #[Route('GET', '/', name: 'home', app: 'web')]
    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return view($response, 'home/index', [
            'title' => '欢迎访问',
            'message' => 'Hello DuxLite!',
            'current_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 关于页面
     */
    #[Route('GET', '/about', name: 'about', app: 'web')]
    public function about(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = [
            'title' => '关于我们',
            'company' => [
                'name' => 'DuxLite Framework',
                'founded' => '2023',
                'description' => '简洁高效的PHP框架'
            ],
            'team' => [
                ['name' => '张三', 'role' => '架构师'],
                ['name' => '李四', 'role' => '开发工程师'],
                ['name' => '王五', 'role' => '产品经理']
            ]
        ];

        return view($response, 'home/about', $data);
    }
}
```

## 表单处理

### 表单显示和处理

```php
class ContactController
{
    /**
     * 显示联系表单
     */
    #[Route('GET', '/contact', name: 'contact.form', app: 'web')]
    public function form(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return view($response, 'contact/form', [
            'title' => '联系我们',
            'form_action' => '/contact',
            'old_input' => [], // 用于表单回填
            'errors' => []     // 用于显示验证错误
        ]);
    }

    /**
     * 处理表单提交
     */
    #[Route('POST', '/contact', name: 'contact.submit', app: 'web')]
    public function submit(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = $request->getParsedBody();

        try {
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
                'subject' => [
                    ['required', '主题不能为空']
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
                'subject' => $validated->subject,
                'message' => $validated->message,
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // 发送邮件通知（可选）
            $this->sendContactNotification($validated);

            // 重定向到成功页面
            return redirect($response, '/contact/success');

        } catch (\Core\Handlers\ExceptionValidator $e) {
            // 验证失败，返回表单页面并显示错误
            return view($response, 'contact/form', [
                'title' => '联系我们',
                'form_action' => '/contact',
                'old_input' => $data,      // 回填表单数据
                'errors' => $e->getErrors()  // 显示验证错误
            ]);
        }
    }

    /**
     * 提交成功页面
     */
    #[Route('GET', '/contact/success', name: 'contact.success', app: 'web')]
    public function success(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return view($response, 'contact/success', [
            'title' => '提交成功',
            'message' => '感谢您的留言，我们会尽快回复！',
            'redirect_url' => '/',
            'redirect_delay' => 3
        ]);
    }

    private function sendContactNotification($data): void
    {
        // 发送邮件通知逻辑
        // 可以使用队列异步处理
    }
}
```

## 数据传递和处理

### 请求数据获取

```php
class UserController
{
    #[Route(['GET', 'POST'], '/profile', name: 'profile', app: 'web')]
    public function profile(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // 获取当前用户
        $user = $this->getCurrentUser();
        if (!$user) {
            return redirect($response, '/login?redirect=' . urlencode('/profile'));
        }

        if ($request->getMethod() === 'POST') {
            return $this->updateProfile($request, $response, $user);
        }

        // 获取查询参数
        $params = $request->getQueryParams();
        $tab = $params['tab'] ?? 'basic';

        return view($response, 'user/profile', [
            'title' => '个人资料',
            'user' => $user,
            'active_tab' => $tab,
            'tabs' => [
                'basic' => '基本信息',
                'security' => '安全设置',
                'privacy' => '隐私设置'
            ]
        ]);
    }

    private function updateProfile(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $user
    ): ResponseInterface {
        $data = $request->getParsedBody();

        try {
            // 根据不同的tab处理不同的数据
            switch ($data['tab'] ?? 'basic') {
                case 'basic':
                    $this->updateBasicInfo($user, $data);
                    break;
                case 'security':
                    $this->updatePassword($user, $data);
                    break;
                case 'privacy':
                    $this->updatePrivacySettings($user, $data);
                    break;
            }

            return redirect($response, '/profile?tab=' . ($data['tab'] ?? 'basic'));

        } catch (\Exception $e) {
            return view($response, 'user/profile', [
                'title' => '个人资料',
                'user' => $user,
                'active_tab' => $data['tab'] ?? 'basic',
                'error_message' => $e->getMessage(),
                'old_input' => $data
            ]);
        }
    }

    private function updateBasicInfo($user, $data): void
    {
        $rules = [
            'nickname' => [['required', '昵称不能为空']],
            'email' => [['required', '邮箱不能为空'], ['email', '邮箱格式无效']]
        ];

        $validated = \Core\Validator\Validator::parser($data, $rules);

        $user->update([
            'nickname' => $validated->nickname,
            'email' => $validated->email,
            'bio' => $data['bio'] ?? '',
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
}
```

## 文件上传处理

```php
class UploadController
{
    #[Route('GET', '/upload', name: 'upload.form', app: 'web')]
    public function form(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return view($response, 'upload/form', [
            'title' => '文件上传',
            'max_size' => '2MB',
            'allowed_types' => ['jpg', 'png', 'gif']
        ]);
    }

    #[Route('POST', '/upload', name: 'upload.submit', app: 'web')]
    public function upload(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        try {
            $uploadedFiles = $request->getUploadedFiles();
            $file = $uploadedFiles['file'] ?? null;

            if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
                throw new \Core\Handlers\ExceptionBusiness('文件上传失败');
            }

            // 验证文件类型
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file->getClientMediaType(), $allowedTypes)) {
                throw new \Core\Handlers\ExceptionBusiness('只允许上传图片文件');
            }

            // 验证文件大小 (2MB)
            if ($file->getSize() > 2 * 1024 * 1024) {
                throw new \Core\Handlers\ExceptionBusiness('文件大小不能超过2MB');
            }

            // 生成文件名和路径
            $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
            $filename = date('Y/m/d/') . uniqid() . '.' . $extension;
            $uploadPath = '/uploads/' . $filename;
            $fullPath = storage_path('public' . $uploadPath);

            // 创建目录
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // 移动文件
            $file->moveTo($fullPath);

            // 保存上传记录
            $upload = Upload::create([
                'original_name' => $file->getClientFilename(),
                'filename' => $filename,
                'path' => $uploadPath,
                'size' => $file->getSize(),
                'type' => $file->getClientMediaType(),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return view($response, 'upload/success', [
                'title' => '上传成功',
                'file' => $upload,
                'preview_url' => $uploadPath
            ]);

        } catch (\Exception $e) {
            return view($response, 'upload/form', [
                'title' => '文件上传',
                'error_message' => $e->getMessage(),
                'max_size' => '2MB',
                'allowed_types' => ['jpg', 'png', 'gif']
            ]);
        }
    }
}
```

## 错误处理

### 友好的错误页面

```php
class ErrorController
{
    #[Route('GET', '/404', name: 'error.404', app: 'web')]
    public function notFound(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return view($response, 'errors/404', [
            'title' => '页面未找到',
            'message' => '抱歉，您访问的页面不存在',
            'home_url' => '/',
            'suggestions' => [
                '检查网址是否正确',
                '返回首页重新浏览',
                '使用搜索功能查找内容'
            ]
        ])->withStatus(404);
    }

    #[Route('GET', '/500', name: 'error.500', app: 'web')]
    public function serverError(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return view($response, 'errors/500', [
            'title' => '服务器错误',
            'message' => '服务器遇到了错误，请稍后再试',
            'home_url' => '/',
            'contact_url' => '/contact'
        ])->withStatus(500);
    }
}

// 在控制器中使用错误处理
class BaseController
{
    protected function handleError(\Exception $e, ResponseInterface $response): ResponseInterface
    {
        // 记录错误日志
        \Core\App::log()->error('Controller error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        // 开发环境显示详细错误
        if (\Core\App::$debug) {
            throw $e;
        }

        // 生产环境显示友好错误页面
        return view($response, 'errors/500', [
            'title' => '系统错误',
            'message' => '系统遇到了错误，请稍后再试'
        ])->withStatus(500);
    }
}
```

## 响应助手

### 常用响应方法

```php
class ResponseHelper
{
    /**
     * 渲染模板
     */
    protected function render(
        ResponseInterface $response,
        string $template,
        array $data = [],
        int $status = 200
    ): ResponseInterface {
        return view($response, $template, $data)->withStatus($status);
    }

    /**
     * 重定向
     */
    protected function redirect(
        ResponseInterface $response,
        string $url,
        int $status = 302
    ): ResponseInterface {
        return redirect($response, $url, $status);
    }

    /**
     * JSON响应（用于AJAX）
     */
    protected function json(
        ResponseInterface $response,
        array $data,
        int $status = 200
    ): ResponseInterface {
        return send($response, 'success', $data, [], $status);
    }

    /**
     * 带消息的重定向
     */
    protected function redirectWithMessage(
        ResponseInterface $response,
        string $url,
        string $message,
        string $type = 'success'
    ): ResponseInterface {
        // 将消息存储到会话中
        session_start();
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;

        return redirect($response, $url);
    }
}
```

通过这些模板控制器的使用方法，可以构建完整的Web应用页面，支持数据渲染、表单处理、文件上传等常见功能。