# 多语言支持

DuxLite 提供了完整的国际化（i18n）和本地化（l10n）解决方案，基于 Symfony Translation 组件，支持 TOML 格式的语言文件和动态语言切换。

## 设计理念

DuxLite 国际化系统采用**简单、高效、开发者友好的多语言支持**设计：

- **TOML 语言文件**：使用人类友好的 TOML 格式组织翻译内容
- **自动加载机制**：框架自动扫描和加载语言文件
- **多域支持**：支持按模块、应用分组管理翻译内容
- **动态语言切换**：支持基于请求头的动态语言检测
- **模板集成**：在 Latte 模板中直接使用翻译函数

### 核心组件

- **Translator**：基于 Symfony Translation 的翻译器
- **TomlFileLoader**：TOML 格式语言文件加载器
- **LangMiddleware**：语言检测和切换中间件
- **翻译函数**：`__()` 全局翻译函数和模板翻译函数
- **Trans 模型特质**：数据库字段多语言支持

## 语言文件管理

### 语言文件结构

```
project/
├── src/Langs/                    # 框架内置语言文件
│   ├── common.zh-CN.toml         # 中文通用翻译
│   └── common.en-US.toml         # 英文通用翻译
├── app/Web/Langs/                # Web 应用语言文件
│   ├── web.zh-CN.toml            # 中文 Web 翻译
│   ├── web.en-US.toml            # 英文 Web 翻译
│   ├── user.zh-CN.toml           # 中文用户模块翻译
│   └── user.en-US.toml           # 英文用户模块翻译
├── app/Admin/Langs/              # 管理后台语言文件
│   ├── admin.zh-CN.toml          # 中文管理后台翻译
│   ├── admin.en-US.toml          # 英文管理后台翻译
│   └── menu.zh-CN.toml           # 中文菜单翻译
└── app/Api/Langs/                # API 应用语言文件
    ├── api.zh-CN.toml            # 中文 API 翻译
    └── api.en-US.toml            # 英文 API 翻译
```

### TOML 语言文件格式

#### 基础格式

```toml
# app/Web/Langs/web.zh-CN.toml

[welcome]
title = "欢迎使用 DuxLite"
message = "这是一个现代化的 PHP 框架"
description = "构建高性能、可维护的 Web 应用程序"

[user]
login = "登录"
logout = "登出"
register = "注册"
profile = "个人资料"
settings = "设置"

[error]
not_found = "页面不存在"
server_error = "服务器内部错误"
permission_denied = "权限不足"

[form]
name = "姓名"
email = "邮箱"
password = "密码"
confirm_password = "确认密码"
submit = "提交"
cancel = "取消"
```

```toml
# app/Web/Langs/web.en-US.toml

[welcome]
title = "Welcome to DuxLite"
message = "A modern PHP framework"
description = "Build high-performance, maintainable web applications"

[user]
login = "Login"
logout = "Logout"
register = "Register"
profile = "Profile"
settings = "Settings"

[error]
not_found = "Page not found"
server_error = "Internal server error"
permission_denied = "Permission denied"

[form]
name = "Name"
email = "Email"
password = "Password"
confirm_password = "Confirm Password"
submit = "Submit"
cancel = "Cancel"
```

#### 带参数的翻译

```toml
# 参数翻译示例
[message]
welcome_user = "欢迎, {{name}}！"
items_count = "共有 {{count}} 个项目"
last_login = "上次登录时间：{{time}}"
file_uploaded = "文件 {{filename}} 上传成功，大小：{{size}}"

[validation]
required = "{{field}} 不能为空"
min_length = "{{field}} 至少需要 {{min}} 个字符"
max_length = "{{field}} 不能超过 {{max}} 个字符"
email_invalid = "{{email}} 不是有效的邮箱地址"

[notification]
user_created = "用户 {{username}} 创建成功"
post_published = "文章《{{title}}》已发布"
comment_approved = "{{author}} 的评论已通过审核"
```

#### 复杂数据结构

```toml
# 嵌套结构和复杂数据
[menu.main]
home = "首页"
about = "关于我们"
contact = "联系方式"

[menu.admin]
dashboard = "仪表板"
users = "用户管理"
posts = "文章管理"
settings = "系统设置"

[status]
published = "已发布"
draft = "草稿"
archived = "已归档"
deleted = "已删除"

[time]
just_now = "刚刚"
minutes_ago = "{{count}} 分钟前"
hours_ago = "{{count}} 小时前"
days_ago = "{{count}} 天前"
weeks_ago = "{{count}} 周前"
months_ago = "{{count}} 个月前"
years_ago = "{{count}} 年前"
```

## 翻译函数使用

### 全局翻译函数

```php
/**
 * 全局翻译函数
 * @param string $key 翻译键
 * @param array $parameters 参数数组
 * @param string $domain 翻译域（对应文件名）
 * @param string $locale 语言代码
 * @return string 翻译后的文本
 */
function __(string $key, array $parameters = [], string $domain = '', string $locale = ''): string
```

#### 基础翻译

```php
// 基础翻译（使用默认域）
echo __('welcome.title');           // "欢迎使用 DuxLite"
echo __('user.login');              // "登录"
echo __('error.not_found');         // "页面不存在"

// 指定翻译域
echo __('welcome.title', [], 'web');         // 从 web.zh-CN.toml 获取
echo __('dashboard.title', [], 'admin');     // 从 admin.zh-CN.toml 获取
echo __('menu.home', [], 'common');          // 从 common.zh-CN.toml 获取

// 多级键访问
echo __('menu.main.home');          // "首页"
echo __('menu.admin.dashboard');    // "仪表板"
```

#### 参数化翻译

```php
// 基础参数替换
echo __('message.welcome_user', ['name' => '张三']);
// 输出：欢迎, 张三！

echo __('message.items_count', ['count' => 25]);
// 输出：共有 25 个项目

// 复杂参数替换
echo __('message.file_uploaded', [
    'filename' => 'document.pdf',
    'size' => '1.2MB'
]);
// 输出：文件 document.pdf 上传成功，大小：1.2MB

// 验证错误消息
echo __('validation.required', ['field' => '用户名']);
// 输出：用户名 不能为空

echo __('validation.min_length', [
    'field' => '密码',
    'min' => 8
]);
// 输出：密码 至少需要 8 个字符
```

#### 在控制器中使用

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function create(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        try {
            // 创建用户逻辑
            $user = $this->createUser($request->getParsedBody());

            // 使用翻译的成功消息
            $message = __('notification.user_created', [
                'username' => $user->username
            ], 'admin');

            return send($response, $message, $user->toArray());

        } catch (\Exception $e) {
            // 使用翻译的错误消息
            $message = __('error.server_error', [], 'common');
            return send($response, $message, [], [], 500);
        }
    }

    public function list(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $users = User::paginate(10);

        // 翻译分页信息
        $meta = [
            'total' => $users->total(),
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'total_text' => __('message.items_count', [
                'count' => $users->total()
            ], 'common')
        ];

        return send($response, __('message.success'), $users->items(), $meta);
    }
}
```

### 模板中的翻译

#### Latte 模板翻译函数

```html
{* 基础翻译 *}
<h1>{__('welcome.title', [], 'web')}</h1>
<p>{__('welcome.message', [], 'web')}</p>

{* 导航菜单 *}
<nav>
    <a href="/">{__('menu.main.home', [], 'web')}</a>
    <a href="/about">{__('menu.main.about', [], 'web')}</a>
    <a href="/contact">{__('menu.main.contact', [], 'web')}</a>
</nav>

{* 表单标签 *}
<form>
    <label>{__('form.name', [], 'web')}</label>
    <input type="text" name="name" placeholder="{__('form.name', [], 'web')}">

    <label>{__('form.email', [], 'web')}</label>
    <input type="email" name="email" placeholder="{__('form.email', [], 'web')}">

    <button type="submit">{__('form.submit', [], 'web')}</button>
    <button type="button">{__('form.cancel', [], 'web')}</button>
</form>

{* 参数化翻译 *}
<div class="welcome">
    <h2>{__('message.welcome_user', ['name' => $user->name], 'web')}</h2>
    <p>{__('message.last_login', ['time' => $user->last_login_at], 'web')}</p>
</div>

{* 条件翻译 *}
{if $user->is_online}
    <span class="status online">{__('status.online', [], 'web')}</span>
{else}
    <span class="status offline">{__('status.offline', [], 'web')}</span>
{/if}

{* 循环中的翻译 *}
<ul class="status-list">
{foreach $posts as $post}
    <li>
        <h3>{$post->title}</h3>
        <span class="status">{__('status.' . $post->status, [], 'web')}</span>
        <time>{__('time.' . $post->time_ago_key, ['count' => $post->time_count], 'web')}</time>
    </li>
{/foreach}
</ul>
```

#### 错误页面翻译

```html
{* 404 错误页面 *}
<!DOCTYPE html>
<html lang="{App::$lang}">
<head>
    <title>{__('error.not_found', [], 'common')}</title>
</head>
<body>
    <main class="error-page">
        <div class="error-content">
            <h1>{$code}</h1>
            <h2>{__('error.not_found', [], 'common')}</h2>
            <p>{__('error.page_not_found_message', [], 'common')}</p>

            <div class="actions">
                <a href="/" class="btn btn-primary">
                    {__('action.back_home', [], 'common')}
                </a>
                <a href="#" onclick="history.back()" class="btn btn-secondary">
                    {__('action.go_back', [], 'common')}
                </a>
            </div>
        </div>
    </main>
</body>
</html>
```

## 语言检测和切换

### LangMiddleware 中间件

`LangMiddleware` 是框架自动注入的全局中间件，无需手动注册：

```php
// 框架自动注册（位置：src/Bootstrap.php）
public function loadRoute(): void
{
    // ...
    $this->web->addMiddleware(new LangMiddleware);
    // ...
}
```

**自动功能：**
- 自动解析 `Accept-Language` 请求头
- 自动将语言信息注入 DI 容器
- 自动设置到请求属性中
```

#### 语言检测逻辑

```php
// 语言检测优先级：
// 1. URL 参数：?lang=zh-CN
// 2. Accept-Language 请求头
// 3. 默认语言：en-US

// Accept-Language 解析示例：
// "zh-CN,zh;q=0.9,en;q=0.8" → "zh-CN"
// "en-US;q=0.9,en;q=0.8"     → "en-US"
// 空或无效时                  → "en-US"
```

#### 获取当前语言

```php
// 在控制器中获取当前语言
public function index(
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
): ResponseInterface {
    // 方式1：从请求属性获取
    $lang = $request->getAttribute('lang');

    // 方式2：从 DI 容器获取
    $lang = App::di()->get('lang');

    // 方式3：使用应用静态属性
    $lang = App::$lang;

    return send($response, '当前语言: ' . $lang);
}
```

### 手动语言切换

```php
// 语言切换 API 端点
class LanguageController
{
    public function switch(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = $request->getParsedBody();
        $locale = $data['locale'] ?? 'en-US';

        // 验证语言代码
        $supportedLocales = ['zh-CN', 'en-US', 'ja-JP', 'ko-KR'];
        if (!in_array($locale, $supportedLocales)) {
            throw new ExceptionBusiness(__('error.unsupported_locale', [], 'common'), 400);
        }

        // 设置语言到会话或 Cookie
        $_SESSION['locale'] = $locale;
        setcookie('locale', $locale, time() + 86400 * 30, '/');

        // 更新 DI 容器中的语言设置
        App::di()->set('lang', $locale);
        App::$lang = $locale;

        return send($response, __('message.language_switched', [
            'language' => $this->getLanguageName($locale)
        ], 'common'));
    }

    private function getLanguageName(string $locale): string
    {
        return match($locale) {
            'zh-CN' => '中文简体',
            'en-US' => 'English',
            'ja-JP' => '日本語',
            'ko-KR' => '한국어',
            default => $locale
        };
    }
}
```

## 数据库字段多语言

### TransTrait 模型特质

DuxLite 提供了数据库字段多语言支持：

```php
use Core\Model\TransTrait;
use Core\Model\TransSet;

class Article extends Model
{
    use TransTrait;

    protected $fillable = ['slug', 'status', 'translations'];
    protected $casts = [
        'translations' => 'array'
    ];

    public function migration(Blueprint $table): void
    {
        $table->id();
        $table->string('slug')->unique();
        $table->tinyInteger('status')->default(1);

        // 添加多语言字段
        TransSet::columns($table);

        $table->timestamps();
    }
}
```

#### 多语言数据操作

```php
// 创建多语言内容
$article = new Article();
$article->slug = 'hello-world';
$article->status = 1;
$article->translations = [
    'zh-CN' => [
        'title' => '你好世界',
        'content' => '这是中文内容',
        'excerpt' => '中文摘要'
    ],
    'en-US' => [
        'title' => 'Hello World',
        'content' => 'This is English content',
        'excerpt' => 'English excerpt'
    ],
    'ja-JP' => [
        'title' => 'こんにちは世界',
        'content' => 'これは日本語の内容です',
        'excerpt' => '日本語の要約'
    ]
];
$article->save();

// 获取翻译内容
$currentLang = App::$lang; // 当前语言

// 获取当前语言的翻译
$trans = $article->translate($currentLang);
echo $trans->title;   // 当前语言的标题
echo $trans->content; // 当前语言的内容

// 获取指定语言的翻译（带回退）
$trans = $article->translate('fr-FR', 'en-US'); // 法语不存在时使用英语
echo $trans->title;

// 获取所有翻译
$allTranslations = $article->translations;
```

#### 在资源控制器中使用

```php
use Core\Resources\Action\Resources;

class ArticleController extends Resources
{
    protected string $model = Article::class;

    /**
     * 数据转换 - 自动处理多语言字段
     */
    public function transform(object $item): array
    {
        $currentLang = App::di()->get('lang', 'zh-CN');
        $trans = $item->translate($currentLang, 'en-US');

        return [
            'id' => $item->id,
            'slug' => $item->slug,
            'status' => $item->status,
            // 当前语言的翻译内容
            'title' => $trans->title,
            'content' => $trans->content,
            'excerpt' => $trans->excerpt,
            // 所有翻译（用于编辑）
            'translations' => $item->translations,
            'created_at' => $item->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $item->updated_at->format('Y-m-d H:i:s')
        ];
    }

    /**
     * 验证多语言数据
     */
    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'slug' => [
                ['required', __('validation.required', ['field' => 'URL别名'])],
                ['regex', '/^[a-z0-9-]+$/', __('validation.slug_format')]
            ],
            'status' => [
                ['required', __('validation.required', ['field' => '状态'])],
                ['in', [0, 1], __('validation.invalid_status')]
            ],
            'translations' => [
                ['required', __('validation.required', ['field' => '翻译内容'])],
                ['array', __('validation.must_be_array', ['field' => '翻译内容'])]
            ]
        ];
    }

    /**
     * 格式化多语言数据
     */
    public function format(Data $data, ServerRequestInterface $request, array $args): array
    {
        $formatted = [
            'slug' => $data->slug,
            'status' => (int) $data->status,
            'translations' => $data->translations
        ];

        // 验证翻译内容结构
        foreach ($formatted['translations'] as $locale => $translation) {
            if (!isset($translation['title']) || empty(trim($translation['title']))) {
                throw new ExceptionValidator([
                    "translations.{$locale}.title" => [
                        __('validation.required', ['field' => "标题({$locale})"])
                    ]
                ]);
            }
        }

        return $formatted;
    }
}
```

## 实际应用场景

### 多语言电商网站

```php
// 产品模型
class Product extends Model
{
    use TransTrait;

    protected $fillable = ['sku', 'price', 'stock', 'status', 'translations'];
    protected $casts = [
        'translations' => 'array',
        'price' => 'decimal:2'
    ];
}

// 产品控制器
class ProductController extends Resources
{
    protected string $model = Product::class;

    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $id = $args['id'];
        $product = Product::findOrFail($id);

        $currentLang = App::di()->get('lang');
        $trans = $product->translate($currentLang, 'en-US');

        $result = [
            'id' => $product->id,
            'sku' => $product->sku,
            'price' => $product->price,
            'stock' => $product->stock,
            'status' => $product->status,
            // 本地化内容
            'name' => $trans->name,
            'description' => $trans->description,
            'features' => $trans->features ?? [],
            'specifications' => $trans->specifications ?? [],
            // 本地化价格显示
            'price_formatted' => $this->formatPrice($product->price, $currentLang),
            // 本地化状态
            'status_text' => __('product.status.' . $product->status, [], 'shop'),
            // 库存状态
            'stock_text' => $this->getStockText($product->stock, $currentLang)
        ];

        return send($response, __('message.success', [], 'common'), $result);
    }

    private function formatPrice(float $price, string $locale): string
    {
        return match($locale) {
            'zh-CN' => '¥' . number_format($price, 2),
            'en-US' => '$' . number_format($price, 2),
            'ja-JP' => '¥' . number_format($price, 0),
            'ko-KR' => '₩' . number_format($price, 0),
            default => number_format($price, 2)
        };
    }

    private function getStockText(int $stock, string $locale): string
    {
        if ($stock > 10) {
            return __('product.stock.in_stock', [], 'shop');
        } elseif ($stock > 0) {
            return __('product.stock.low_stock', ['count' => $stock], 'shop');
        } else {
            return __('product.stock.out_of_stock', [], 'shop');
        }
    }
}
```

### 多语言内容管理系统

```php
// CMS 文章管理
class CMSArticleController extends Resources
{
    protected string $model = Article::class;

    /**
     * 获取文章列表（带多语言支持）
     */
    public function list(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $queryParams = $request->getQueryParams();
        $locale = $queryParams['locale'] ?? App::di()->get('lang', 'zh-CN');

        $articles = Article::where('status', 1)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $result = format_data($articles, function($article) use ($locale) {
            $trans = $article->translate($locale, 'en-US');

            return [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $trans->title,
                'excerpt' => $trans->excerpt,
                'author' => $article->author->name,
                'category' => $trans->category_name ?? $article->category->name,
                'published_at' => $article->published_at?->format('Y-m-d H:i:s'),
                'locale' => $locale
            ];
        });

        return send($response, __('message.success'), $result['data'], $result['meta']);
    }

    /**
     * 多语言文章搜索
     */
    public function search(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $queryParams = $request->getQueryParams();
        $keyword = $queryParams['keyword'] ?? '';
        $locale = $queryParams['locale'] ?? App::di()->get('lang', 'zh-CN');

        if (empty($keyword)) {
            return send($response, __('error.search_keyword_required'), []);
        }

        // 在指定语言的翻译字段中搜索
        $articles = Article::where('status', 1)
            ->where(function($query) use ($keyword, $locale) {
                $query->whereRaw("JSON_EXTRACT(translations, '$.\"$locale\".title') LIKE ?", ["%$keyword%"])
                      ->orWhereRaw("JSON_EXTRACT(translations, '$.\"$locale\".content') LIKE ?", ["%$keyword%"]);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $result = format_data($articles, function($article) use ($locale, $keyword) {
            $trans = $article->translate($locale, 'en-US');

            return [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $this->highlightKeyword($trans->title, $keyword),
                'excerpt' => $this->highlightKeyword($trans->excerpt, $keyword),
                'match_score' => $this->calculateMatchScore($trans, $keyword),
                'locale' => $locale
            ];
        });

        return send($response, __('search.results_found', [
            'count' => $result['meta']['total'] ?? 0,
            'keyword' => $keyword
        ]), $result['data'], $result['meta']);
    }

    private function highlightKeyword(string $text, string $keyword): string
    {
        return str_ireplace($keyword, "<mark>$keyword</mark>", $text);
    }

    private function calculateMatchScore($trans, string $keyword): int
    {
        $score = 0;
        $score += substr_count(strtolower($trans->title), strtolower($keyword)) * 10;
        $score += substr_count(strtolower($trans->content), strtolower($keyword)) * 2;
        return $score;
    }
}
```

## 语言文件自动加载

### 自动扫描机制

DuxLite 会自动扫描以下目录的语言文件：

```php
// 框架会自动加载以下位置的语言文件：
// 1. 框架核心语言文件
App::loadTrans(__DIR__ . '/Langs', $translator);

// 2. 应用级语言文件
// 在应用扩展类中加载
class WebApp extends AppExtend
{
    public function init(Bootstrap $bootstrap): void
    {
        // 加载 Web 应用的语言文件
        App::loadTrans(__DIR__ . '/Langs', App::trans());
    }
}
```

### 自定义语言文件加载

```php
// 手动加载特定语言文件
class TranslationService
{
    public function loadCustomTranslations(): void
    {
        $translator = App::trans();

        // 加载自定义目录的语言文件
        App::loadTrans(base_path('resources/langs'), $translator);

        // 加载第三方包的语言文件
        App::loadTrans(base_path('vendor/duxweb/admin/langs'), $translator);

        // 加载数据库中的翻译（动态翻译）
        $this->loadDatabaseTranslations($translator);
    }

    private function loadDatabaseTranslations($translator): void
    {
        // 从数据库加载动态翻译内容
        $translations = Translation::where('status', 1)->get();

        foreach ($translations as $translation) {
            foreach (json_decode($translation->content, true) as $locale => $messages) {
                foreach ($messages as $key => $value) {
                    $translator->addResource(
                        'array',
                        [$key => $value],
                        $locale,
                        $translation->domain
                    );
                }
            }
        }
    }
}
```

## 最佳实践

### 1. 语言文件组织

```toml
# 按功能模块组织翻译
# admin.zh-CN.toml - 管理后台专用
[user]
list = "用户列表"
create = "创建用户"
edit = "编辑用户"
delete = "删除用户"

[post]
list = "文章列表"
create = "创建文章"
edit = "编辑文章"
publish = "发布文章"

# common.zh-CN.toml - 通用翻译
[action]
save = "保存"
cancel = "取消"
confirm = "确认"
submit = "提交"

[message]
success = "操作成功"
error = "操作失败"
loading = "加载中..."
```

### 2. 翻译键命名规范

```toml
# 推荐的命名规范：
# 1. 使用点号分隔层级
# 2. 使用下划线分隔单词
# 3. 保持语义化和一致性

[validation]
required = "此字段为必填项"
email_invalid = "邮箱格式无效"
password_too_short = "密码过短"
confirm_password_mismatch = "两次密码输入不一致"

[notification]
user_created = "用户创建成功"
post_published = "文章发布成功"
comment_approved = "评论审核通过"
email_sent = "邮件发送成功"

[button]
create_new = "新建"
edit_item = "编辑"
delete_item = "删除"
view_details = "查看详情"
```

### 3. 参数化翻译最佳实践

```php
// ✅ 推荐：使用描述性参数名
echo __('user.welcome_message', [
    'username' => $user->name,
    'last_login' => $user->last_login_at->format('Y-m-d H:i:s')
]);

// ✅ 推荐：使用一致的参数格式
echo __('file.upload_success', [
    'filename' => $file->name,
    'size' => human_filesize($file->size),
    'type' => $file->type
]);

// ❌ 避免：使用无意义的参数名
echo __('message.text', ['p1' => $name, 'p2' => $time]);
```

### 4. 多语言数据验证

```php
class MultiLanguageValidator
{
    public static function validateTranslations(array $translations, array $requiredFields): array
    {
        $errors = [];
        $supportedLocales = ['zh-CN', 'en-US', 'ja-JP'];

        foreach ($supportedLocales as $locale) {
            if (!isset($translations[$locale])) {
                $errors["translations.{$locale}"] = [
                    __('validation.translation_missing', ['locale' => $locale])
                ];
                continue;
            }

            foreach ($requiredFields as $field) {
                if (empty(trim($translations[$locale][$field] ?? ''))) {
                    $errors["translations.{$locale}.{$field}"] = [
                        __('validation.translation_field_required', [
                            'field' => $field,
                            'locale' => $locale
                        ])
                    ];
                }
            }
        }

        if (!empty($errors)) {
            throw new ExceptionValidator($errors);
        }

        return $translations;
    }
}

// 使用示例
MultiLanguageValidator::validateTranslations($data['translations'], [
    'title', 'content', 'excerpt'
]);
```

### 5. 性能优化

```php
// 缓存翻译内容
class TranslationCache
{
    public function getCachedTranslation(string $key, string $locale, string $domain): ?string
    {
        $cacheKey = "translation:{$domain}:{$locale}:{$key}";
        return App::cache()->get($cacheKey);
    }

    public function setCachedTranslation(string $key, string $locale, string $domain, string $value): void
    {
        $cacheKey = "translation:{$domain}:{$locale}:{$key}";
        App::cache()->set($cacheKey, $value, 3600); // 缓存1小时
    }
}
```

::: warning 注意事项
1. **字符编码**：确保所有语言文件使用 UTF-8 编码
2. **TOML 语法**：注意 TOML 格式的语法要求，特别是字符串转义
3. **翻译完整性**：确保所有支持的语言都有完整的翻译
4. **参数安全**：翻译参数要进行适当的转义，防止 XSS 攻击
5. **缓存策略**：生产环境建议缓存翻译内容以提高性能
:::

::: tip 开发建议
- 使用统一的翻译键命名规范
- 定期检查翻译文件的完整性
- 为动态内容提供合理的回退机制
- 考虑使用翻译管理工具辅助多语言内容管理
- 在模板中合理使用翻译函数，避免硬编码文本
:::

DuxLite 的多语言支持系统为您的应用提供了完整的国际化解决方案，通过合理使用语言文件、翻译函数和多语言数据模型，您可以轻松构建支持多语言的全球化应用。