# 视图系统

DuxLite 采用强大的 **Latte 模板引擎**作为视图系统，提供安全、高效且直观的模板渲染功能。Latte 是来自 Nette 框架的现代化模板引擎，具有智能的语法和强大的安全特性。

## 设计理念

DuxLite 视图系统采用**安全、高效且直观的模板渲染**设计：

- **自动转义**：防止 XSS 攻击
- **智能语法**：简洁而强大的模板语法
- **多引擎支持**：支持多个命名空间的模板引擎
- **缓存优化**：自动模板编译和缓存
- **多语言支持**：内置国际化函数

### 模板引擎特性

DuxLite 的视图系统基于 [Latte 模板引擎](https://latte.nette.org/)，提供强大的模板功能。

### 引擎初始化

```php
// 在应用的 init 方法中配置模板引擎
// app/Web/App.php
use Core\App\AppExtend;
use Core\Bootstrap;

class App extends AppExtend
{
    public function init(Bootstrap $bootstrap): void
    {
        // 配置前台模板引擎
        $webEngine = App::view('web');

        // 配置管理后台模板引擎
        $adminEngine = App::view('admin');

        // 配置邮件模板引擎
        $emailEngine = App::view('email');

        // 可以在这里添加自定义过滤器、函数等
        // $webEngine->addFilter('customFilter', function($value) {
        //     return strtoupper($value);
        // });
    }
}
```

```php
// 在控制器中使用
class PageController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // 直接使用已初始化的引擎
        $engine = App::view('web');
        return sendTpl($response, 'pages/index', $data);
    }
}
```

::: tip 引擎命名空间
每个引擎都有独立的缓存目录，位于 `data/tpl/{name}/` 中，这样可以为不同模块创建隔离的模板环境。引擎在首次调用 `App::view()` 时自动初始化。
:::

## 基础用法

### 渲染模板响应

```php
use function sendTpl;

class PageController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = [
            'title' => '欢迎页面',
            'users' => User::all(),
            'message' => '欢迎使用 DuxLite'
        ];

        // 使用默认 'web' 引擎渲染模板
        return sendTpl($response, 'pages/index', $data);
    }

    public function admin(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = [
            'title' => '管理后台',
            'stats' => $this->getStatistics()
        ];

        // 使用指定的 'admin' 引擎渲染
        return sendTpl($response, 'admin/dashboard', $data, 'admin');
    }
}
```

### 直接渲染字符串

```php
class EmailController
{
    public function renderEmailTemplate(array $emailData): string
    {
        $engine = App::view('email');

        // 渲染为字符串（用于邮件等场景）
        return $engine->renderToString('emails/welcome', $emailData);
    }
}
```

## 模板语法

### 变量输出

```html
{* 基本变量输出（自动转义） *}
<h1>{$title}</h1>
<p>{$message}</p>

{* 输出原始 HTML（慎用） *}
<div>{$content|noescape}</div>

{* 对象属性访问 *}
<p>用户名：{$user->name}</p>
<p>邮箱：{$user->email}</p>

{* 数组访问 *}
<p>配置值：{$config['app']['name']}</p>
```

### 条件语句

```html
{* if 条件 *}
{if $user}
    <p>欢迎，{$user->name}！</p>
{elseif $guest}
    <p>欢迎访客！</p>
{else}
    <p>请先登录</p>
{/if}

{* 简化条件 *}
{if isset($errors)}
    <div class="alert alert-danger">
        {foreach $errors as $error}
            <p>{$error}</p>
        {/foreach}
    </div>
{/if}
```

### 循环结构

```html
{* foreach 循环 *}
<ul>
{foreach $users as $user}
    <li>
        <strong>{$user->name}</strong> - {$user->email}
        {if $iterator->first}（第一个用户）{/if}
        {if $iterator->last}（最后一个用户）{/if}
    </li>
{/foreach}
</ul>

{* 带索引的循环 *}
<table>
{foreach $items as $index => $item}
    <tr class="{if $iterator->odd}odd{else}even{/if}">
        <td>{$index + 1}</td>
        <td>{$item->name}</td>
    </tr>
{/foreach}
</table>

{* 空集合处理 *}
{foreach $posts as $post}
    <article>{$post->title}</article>
{else}
    <p>没有找到文章</p>
{/foreach}
```

### 模板包含

```html
{* 包含其他模板 *}
{include 'components/header.latte'}

{* 传递变量到子模板 *}
{include 'components/user-card.latte', user: $currentUser, showActions: true}

{* 条件包含 *}
{if $showSidebar}
    {include 'components/sidebar.latte'}
{/if}
```

### 布局继承

```html
{* 主布局：layouts/app.latte *}
<!DOCTYPE html>
<html>
<head>
    <title>{block title}默认标题{/block}</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {block styles}
        <link rel="stylesheet" href="/css/app.css">
    {/block}
</head>
<body>
    <header>
        {include 'components/navigation.latte'}
    </header>

    <main>
        {block content}默认内容{/block}
    </main>

    <footer>
        {include 'components/footer.latte'}
    </footer>

    {block scripts}
        <script src="/js/app.js"></script>
    {/block}
</body>
</html>

{* 页面模板：pages/home.latte *}
{layout 'layouts/app.latte'}

{block title}首页 - DuxLite{/block}

{block content}
    <h1>欢迎使用 DuxLite</h1>
    <p>{$welcomeMessage}</p>

    <div class="user-list">
        {foreach $users as $user}
            {include 'components/user-card.latte', user: $user}
        {/foreach}
    </div>
{/block}

{block scripts}
    {include parent} {* 包含父模板的脚本 *}
    <script src="/js/home.js"></script>
{/block}
```

### 过滤器

```html
{* 内置字符串过滤器 *}
<p>{$text|upper}</p>                    {* 转大写 *}
<p>{$text|lower}</p>                    {* 转小写 *}
<p>{$text|capitalize}</p>               {* 首字母大写 *}
<p>{$text|length}</p>                   {* 字符串长度 *}

{* 日期格式化 *}
<p>{$date|date:'Y-m-d H:i:s'}</p>      {* 格式化日期 *}

{* 数字格式化 *}
<p>{$price|number:2}</p>                {* 保留2位小数 *}

{* HTML 相关 *}
<p>{$html|striptags}</p>                {* 移除 HTML 标签 *}
<p>{$html|truncate:100}</p>             {* 截断文本 *}

{* 条件过滤器 *}
<p>{$value|default:'暂无数据'}</p>        {* 默认值 *}

{* 自定义过滤器（需在应用初始化时注册） *}
<p>{$price|money}</p>                   {* 金额格式化：999.00 元 *}
<img src="{asset('images/logo.png')}" alt="Logo">  {* 资源路径 *}
<a href="{$path|adminUrl}">管理链接</a>    {* 后台 URL *}
<div>{$content|emailSafe}</div>         {* 邮件安全 HTML *}
```

### 宏定义

```html
{* 定义可复用的宏 *}
{define alert, $type = 'info', $message = ''}
<div class="alert alert-{$type}">
    <i class="icon-{$type}"></i>
    {$message}
</div>
{/define}

{* 使用宏 *}
{include alert, type: 'success', message: '操作成功！'}
{include alert, type: 'error', message: '操作失败！'}

{* 带内容的宏 *}
{define panel, $title = ''}
<div class="panel">
    <div class="panel-header">
        <h3>{$title}</h3>
    </div>
    <div class="panel-body">
        {include content}
    </div>
</div>
{/define}

{* 使用带内容的宏 *}
{include panel, title: '用户信息'}
    <p>姓名：{$user->name}</p>
    <p>邮箱：{$user->email}</p>
{/include}
```

## 国际化支持

DuxLite 在模板中提供了强大的多语言支持：

### 翻译函数

```html
{* 基本翻译 *}
<h1>{__('welcome.title', 'web')}</h1>
<p>{__('welcome.message', 'web')}</p>

{* 带参数的翻译 *}
<p>{__('user.greeting', ['name' => $user->name], 'web')}</p>

{* 使用默认域 *}
<p>{__('common.save')}</p>
<p>{__('common.cancel')}</p>

{* 条件翻译 *}
{if $locale === 'zh-CN'}
    <p>{__('chinese.only', 'special')}</p>
{else}
    <p>{__('general.message', 'common')}</p>
{/if}
```

### 翻译文件结构

```toml
# resources/langs/web.zh-CN.toml
[welcome]
title = "欢迎使用 DuxLite"
message = "这是一个现代化的 PHP 框架"

[user]
greeting = "你好，{{name}}！"
profile = "个人资料"
settings = "设置"

# resources/langs/common.zh-CN.toml
[common]
save = "保存"
cancel = "取消"
delete = "删除"
edit = "编辑"
```

## 错误页面模板

DuxLite 提供了美观的默认错误页面模板：

### 404 错误页面

```html
<!-- src/Tpl/404.latte -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{__('error.notFound', 'common')}</title>
    <style n:syntax="off">
        /* Tailwind CSS 内联样式 */
    </style>
</head>
<body>
<main class="grid h-[100vh] place-items-center bg-white px-6 py-24">
    <div class="text-center">
        <p class="text-4xl font-semibold text-blue-600">{$code}</p>
        <h1 class="mt-4 text-4xl font-bold">{__('error.notFound', 'common')}</h1>
        <p class="mt-6 text-base">{__('error.notFoundMessage', 'common')}</p>
        <div class="mt-10 flex items-center justify-center gap-x-6">
            <a href="/" class="btn-primary">{__('error.home', 'common')}</a>
            <a href="#" onclick="window.history.go(-1)" class="btn-secondary">
                {__('error.back', 'common')} <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
    </div>
</main>
</body>
</html>
```

### 自定义错误模板

```php
// 在应用的 init 方法中注册自定义错误模板
// app/Web/App.php
class App extends AppExtend
{
    public function init(Bootstrap $bootstrap): void
    {
        // 初始化模板引擎
        $webEngine = App::view('web');

        // 自定义 404 页面模板
        \Core\App::di()->set('tpl.404', __DIR__ . '/resources/views/errors/404.latte');

        // 自定义错误页面模板
        \Core\App::di()->set('tpl.error', __DIR__ . '/resources/views/errors/error.latte');
    }
}
```

## 高级功能

### 引擎配置与扩展

```php
// 在应用初始化时配置模板引擎
// app/Web/App.php
class App extends AppExtend
{
    public function init(Bootstrap $bootstrap): void
    {
        // 配置前台模板引擎
        $webEngine = \Core\App::view('web');
        $this->configureWebEngine($webEngine);

        // 配置管理后台模板引擎
        $adminEngine = \Core\App::view('admin');
        $this->configureAdminEngine($adminEngine);

        // 配置邮件模板引擎
        $emailEngine = \Core\App::view('email');
        $this->configureEmailEngine($emailEngine);
    }

    private function configureWebEngine(\Latte\Engine $engine): void
    {
        // 添加自定义过滤器
        $engine->addFilter('money', function($value) {
            return number_format($value, 2) . ' 元';
        });

        // 添加自定义函数
        $engine->addFunction('asset', function($path) {
            return '/assets/' . ltrim($path, '/');
        });
    }

    private function configureAdminEngine(\Latte\Engine $engine): void
    {
        // 后台专用过滤器
        $engine->addFilter('adminUrl', function($path) {
            return '/admin/' . ltrim($path, '/');
        });
    }

    private function configureEmailEngine(\Latte\Engine $engine): void
    {
        // 邮件模板专用配置
        $engine->addFilter('emailSafe', function($html) {
            return strip_tags($html, '<b><i><u><strong><em>');
        });
    }
}
```

### 多引擎管理

```php
class TemplateService
{
    public function renderUserTemplate(array $data): string
    {
        // 前台用户模板（已配置自定义过滤器）
        $webEngine = \Core\App::view('web');
        return $webEngine->renderToString('user/profile', $data);
    }

    public function renderAdminTemplate(array $data): string
    {
        // 后台管理模板（已配置管理专用过滤器）
        $adminEngine = \Core\App::view('admin');
        return $adminEngine->renderToString('admin/users', $data);
    }

    public function renderEmailTemplate(array $data): string
    {
        // 邮件模板（已配置邮件安全过滤器）
        $emailEngine = \Core\App::view('email');
        return $emailEngine->renderToString('emails/notification', $data);
    }
}
```

### 模板缓存

```php
// 模板引擎自动处理缓存
// 缓存目录：data/tpl/{engine_name}/

// 清理模板缓存（生产环境部署时）
class TemplateCommand extends Command
{
    public function clearCache(): void
    {
        $cacheDir = App::$dataPath . '/tpl/';
        if (is_dir($cacheDir)) {
            $this->recursiveDelete($cacheDir);
        }
        echo "模板缓存已清理\n";
    }
}
```

### 安全特性

```html
{* 自动 HTML 转义（默认） *}
<p>{$userInput}</p>  {* 自动转义，防止 XSS *}

{* 禁用转义（谨慎使用） *}
<div>{$trustedHtml|noescape}</div>

{* URL 安全 *}
<a href="{$url|escapeurl}">链接</a>

{* JavaScript 安全 *}
<script>
var data = {$data|escapejs};
</script>

{* CSS 安全 *}
<style>
.dynamic { color: {$color|escapecss}; }
</style>
```

### 性能优化

```php
class ViewOptimizationService
{
    public function precompileTemplates(): void
    {
        $templates = [
            'layouts/app.latte',
            'pages/home.latte',
            'components/navigation.latte'
        ];

        $engine = App::view('web');

        foreach ($templates as $template) {
            // 预编译常用模板
            $engine->compile($template);
        }
    }

    public function warmUpCache(): void
    {
        // 预热模板缓存
        $commonData = [
            'title' => '测试',
            'users' => []
        ];

        $engine = App::view('web');
        $engine->renderToString('layouts/app', $commonData);
    }
}
```

## 实际应用示例

### 用户列表页面

```php
// 控制器
class UserController
{
    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $users = User::with('profile')->paginate(10);
        $stats = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count()
        ];

        return sendTpl($response, 'users/list', [
            'users' => format_data($users),
            'stats' => $stats,
            'title' => '用户管理'
        ]);
    }
}
```

```html
{* users/list.latte *}
{layout 'layouts/admin.latte'}

{block title}用户管理 - 后台{/block}

{block content}
<div class="page-header">
    <h1>{$title}</h1>
    <div class="stats">
        <span class="stat">总用户: {$stats.total}</span>
        <span class="stat">活跃用户: {$stats.active}</span>
    </div>
</div>

<div class="user-table">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>姓名</th>
                <th>邮箱</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            {foreach $users.data as $user}
            <tr>
                <td>{$user.id}</td>
                <td>
                    <div class="user-info">
                        <img src="{$user.avatar|default:'/images/default-avatar.png'}"
                             alt="{$user.name}" class="avatar">
                        <span>{$user.name}</span>
                    </div>
                </td>
                <td>{$user.email}</td>
                <td>
                    <span class="badge badge-{$user.status}">
                        {__("user.status.{$user.status}", 'admin')}
                    </span>
                </td>
                <td>
                    <a href="/admin/users/{$user.id}/edit" class="btn btn-sm">
                        {__('common.edit', 'admin')}
                    </a>
                    <button onclick="deleteUser({$user.id})" class="btn btn-sm btn-danger">
                        {__('common.delete', 'admin')}
                    </button>
                </td>
            </tr>
            {else}
            <tr>
                <td colspan="5" class="text-center">
                    {__('user.empty', 'admin')}
                </td>
            </tr>
            {/foreach}
        </tbody>
    </table>
</div>

{* 分页 *}
{if $users.meta.total > $users.meta.per_page}
<div class="pagination-wrapper">
    {include 'components/pagination.latte', meta: $users.meta}
</div>
{/if}
{/block}

{block scripts}
{include parent}
<script>
function deleteUser(id) {
    if (confirm('{__("user.deleteConfirm", "admin")}')) {
        fetch(`/admin/users/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => {
            if (response.ok) {
                location.reload();
            }
        });
    }
}
</script>
{/block}
```

### 可复用组件

```html
{* components/pagination.latte *}
{if $meta.last_page > 1}
<nav class="pagination">
    {if $meta.current_page > 1}
        <a href="?page={$meta.current_page - 1}" class="page-link">
            &laquo; {__('pagination.previous', 'common')}
        </a>
    {/if}

    {for $page = 1; $page <= $meta.last_page; $page++}
        {if $page == $meta.current_page}
            <span class="page-link active">{$page}</span>
        {else}
            <a href="?page={$page}" class="page-link">{$page}</a>
        {/if}
    {/for}

    {if $meta.current_page < $meta.last_page}
        <a href="?page={$meta.current_page + 1}" class="page-link">
            {__('pagination.next', 'common')} &raquo;
        </a>
    {/if}
</nav>
{/if}
```

## 最佳实践

### 目录结构建议

```
resources/
├── views/
│   ├── layouts/          # 布局模板
│   │   ├── app.latte
│   │   ├── admin.latte
│   │   └── email.latte
│   ├── pages/            # 页面模板
│   │   ├── home.latte
│   │   ├── about.latte
│   │   └── contact.latte
│   ├── users/            # 功能模块模板
│   │   ├── list.latte
│   │   ├── show.latte
│   │   └── edit.latte
│   ├── components/       # 可复用组件
│   │   ├── header.latte
│   │   ├── footer.latte
│   │   ├── navigation.latte
│   │   └── pagination.latte
│   └── emails/           # 邮件模板
│       ├── welcome.latte
│       └── notification.latte
└── langs/                # 多语言文件
    ├── common.zh-CN.toml
    ├── admin.zh-CN.toml
    └── web.zh-CN.toml
```

### 模板组织原则

1. **布局分离** - 使用继承减少重复代码
2. **组件化** - 创建可复用的模板组件
3. **命名规范** - 使用描述性的文件名和变量名
4. **安全第一** - 谨慎使用 `noescape` 过滤器
5. **性能考虑** - 避免在模板中进行复杂的业务逻辑

### 变量传递规范

```php
// ✅ 推荐：结构化数据传递
return sendTpl($response, 'users/list', [
    'users' => format_data($users),
    'meta' => [
        'title' => '用户列表',
        'breadcrumbs' => ['首页', '用户管理', '用户列表']
    ],
    'permissions' => [
        'canEdit' => $user->can('user.edit'),
        'canDelete' => $user->can('user.delete')
    ]
]);

// ❌ 避免：混乱的变量传递
return sendTpl($response, 'users/list', [
    'data' => $users,
    'title' => '用户列表',
    'canEdit' => true,
    'total' => 100,
    'random_data' => $someData
]);
```

::: warning 注意事项
1. 模板中不要直接调用复杂的业务逻辑
2. 使用 `|noescape` 时要确保数据来源可信
3. 大量数据展示时考虑分页和性能
4. 多语言文本要使用翻译函数，不要硬编码
:::

::: tip 性能提示
- Latte 会自动编译和缓存模板
- 生产环境建议预编译常用模板
- 使用适当的模板继承避免重复渲染
- 条件性包含可以减少不必要的模板处理
:::