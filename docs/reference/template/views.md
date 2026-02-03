# 视图模板（Latte 语法层）

本章只讲 Latte 语法和模板组织方式。  
模板引擎作为“独立运行环境”的机制与执行流程，已在 `preprocessor.md` 中说明。

## 定位与职责

- **模板引擎文档**：讲运行环境、超语法、数据调用、内建运行时能力  
- **本章节**：只讲 Latte 语法、模板结构和组织规范

## 基础语法

### 变量输出

```html
<!-- 基本变量输出 -->
<h1>{$title}</h1>
<p>{$user->name}</p>

<!-- 带默认值的输出 -->
<img src="{$user->avatar|default:'/images/default-avatar.png'}" alt="头像">
<p>{$post->excerpt|default:'暂无简介'}</p>

<!-- 原始HTML输出（不转义） -->
<div class="content">{$post->content|noescape}</div>

<!-- 条件输出 -->
<span class="status">{$user->isVip ? 'VIP用户' : '普通用户'}</span>
```

### 注释

```html
{* 这是单行注释 *}

{* 
  这是多行注释
  可以跨越多行
*}
```

### 条件语句

```html
<!-- 简单条件 -->
{if $user}
    <p>欢迎，{$user->name}！</p>
{else}
    <p>请先登录</p>
{/if}

<!-- 复杂条件 -->
{if $user && $user->isActive}
    <div class="user-panel">
        <h3>{$user->name}</h3>
        {if $user->isVip}
            <span class="vip-badge">VIP</span>
        {/if}
    </div>
{elseif $user && !$user->isActive}
    <div class="alert alert-warning">
        您的账户已被暂停，请联系管理员
    </div>
{else}
    <div class="login-prompt">
        <a href="/login">立即登录</a>
    </div>
{/if}

<!-- 状态判断 -->
{if $status === 'published'}
    <span class="badge badge-success">已发布</span>
{elseif $status === 'draft'}
    <span class="badge badge-secondary">草稿</span>
{elseif $status === 'pending'}
    <span class="badge badge-warning">待审核</span>
{else}
    <span class="badge badge-danger">已删除</span>
{/if}
```

### 循环语句

```html
<!-- 简单循环 -->
<ul>
    {foreach $posts as $post}
        <li>{$post->title}</li>
    {/foreach}
</ul>

<!-- 带索引的循环 -->
<ol>
    {foreach $posts as $index => $post}
        <li data-index="{$index}">{$post->title}</li>
    {/foreach}
</ol>

<!-- 复杂循环，使用 $iterator 变量 -->
<div class="post-list">
    {foreach $posts as $post}
        <article class="post-item{if $iterator->first} first{/if}{if $iterator->last} last{/if}">
            <h3>{$post->title}</h3>
            <p>{$post->excerpt}</p>
            <div class="meta">
                <span>第 {$iterator->counter} / {$iterator->count} 篇</span>
                <span>{$post->created_at|date:'Y-m-d'}</span>
            </div>
        </article>
    {else}
        <p class="no-posts">暂无文章</p>
    {/foreach}
</div>

<!-- 嵌套循环 -->
{foreach $categories as $category}
    <div class="category">
        <h2>{$category->name}</h2>
        <div class="posts">
            {foreach $category->posts as $post}
                <div class="post">
                    <h3>{$post->title}</h3>
                    <p>{$post->excerpt}</p>
                </div>
            {else}
                <p>该分类下暂无文章</p>
            {/foreach}
        </div>
    </div>
{/foreach}
```

## 模板继承和布局（Latte）

### 基础布局模板

**基础布局模板（layouts/base.latte）：**

```html
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{block title}{$title|default:'DuxLite'}{/block}</title>
    
    {block meta}
        <meta name="description" content="{block description}DuxLite 框架{/block}">
        <meta name="keywords" content="{block keywords}DuxLite,PHP,Framework{/block}">
    {/block}
    
    {block styles}
        <link rel="stylesheet" href="/assets/css/app.css">
    {/block}
</head>
<body class="{block bodyClass}{/block}">
    <header class="header">
        {block header}
            <nav class="navbar">
                <div class="container">
                    <a href="/" class="navbar-brand">
                        {$appName|default:'DuxLite'}
                    </a>
                    
                    <ul class="navbar-nav">
                        {block navigation}
                            <li><a href="/">首页</a></li>
                            <li><a href="/about">关于</a></li>
                            <li><a href="/contact">联系</a></li>
                        {/block}
                    </ul>
                    
                    <div class="navbar-user">
                        {if $user}
                            <span>欢迎，{$user->name}</span>
                            <a href="/logout">退出</a>
                        {else}
                            <a href="/login">登录</a>
                        {/if}
                    </div>
                </div>
            </nav>
        {/block}
    </header>

    <main class="main">
        {block breadcrumb}{/block}
        
        {block flashMessages}
            {if $flash->success}
                <div class="alert alert-success">{$flash->success}</div>
            {/if}
            {if $flash->error}
                <div class="alert alert-error">{$flash->error}</div>
            {/if}
        {/block}
        
        <div class="container">
            {block content}{/block}
        </div>
    </main>

    <footer class="footer">
        {block footer}
            <div class="container">
                <p>&copy; {date('Y')} {$appName|default:'DuxLite'}. All rights reserved.</p>
            </div>
        {/block}
    </footer>

    {block scripts}
        <script src="/assets/js/app.js"></script>
    {/block}
</body>
</html>
```

**子模板（index.latte）：**

```html
{layout 'layouts/base.latte'}

{block title}首页 - {include parent}{/block}

{block description}DuxLite 框架首页，展示最新功能和内容{/block}

{block bodyClass}home-page{/block}

{block content}
    <div class="hero-section">
        <h1>欢迎来到 {$appName|default:'DuxLite'}</h1>
        <p>一个现代化的 PHP 开发框架</p>
        <a href="/docs" class="btn btn-primary">查看文档</a>
    </div>

    <div class="features-section">
        <h2>主要特性</h2>
        <div class="features-grid">
            {foreach $features as $feature}
                <div class="feature-card">
                    <h3>{$feature->title}</h3>
                    <p>{$feature->description}</p>
                </div>
            {/foreach}
        </div>
    </div>

    {if $posts}
        <div class="posts-section">
            <h2>最新文章</h2>
            {include 'partials/post-list.latte', posts: $posts}
        </div>
    {/if}
{/block}

{block scripts}
    {include parent}
{/block}
```

### 组件化开发

**可复用的组件（partials/post-card.latte）：**

```html
<!-- 文章卡片组件 -->
<article class="post-card">
    <div class="post-header">
        {if $post->featured_image}
            <img src="{$post->featured_image}" alt="{$post->title}" class="post-image">
        {/if}
        
        <div class="post-meta">
            <time datetime="{$post->created_at|date:'c'}">{$post->created_at|date:'Y-m-d'}</time>
            <span class="post-category">{$post->category->name}</span>
        </div>
    </div>
    
    <div class="post-content">
        <h3 class="post-title">
            <a href="/blog/{$post->id}">{$post->title}</a>
        </h3>
        
        <p class="post-excerpt">{$post->excerpt|truncate:120}</p>
        
        <div class="post-tags">
            {foreach $post->tags as $tag}
                <span class="tag">{$tag->name}</span>
            {/foreach}
        </div>
    </div>
    
    <div class="post-footer">
        <div class="post-author">
            <img src="{$post->author->avatar|default:'/images/default-avatar.png'}" alt="{$post->author->name}">
            <span>{$post->author->name}</span>
        </div>
        
        <div class="post-stats">
            <span class="views">{$post->views} 次浏览</span>
            <span class="comments">{$post->comments_count} 条评论</span>
        </div>
    </div>
</article>
```

**在模板中使用组件：**

```html
<!-- 文章列表页 -->
{layout 'layouts/base.latte'}

{block content}
    <div class="posts-container">
        {foreach $posts as $post}
            {include 'partials/post-card.latte', post: $post}
        {/foreach}
        
        {if empty($posts)}
            <div class="empty-state">
                <h3>暂无文章</h3>
                <p>还没有发布任何文章</p>
            </div>
        {/if}
        
    </div>
{/block}
```

## 内置过滤器

### 常用过滤器

```html
<!-- 日期格式化 -->
<time>{$post->created_at|date:'Y-m-d H:i:s'}</time>
<span>{$user->birthday|date:'Y年m月d日'}</span>

<!-- 字符串处理 -->
<h1>{$post->title|upper}</h1>        <!-- 转大写 -->
<p>{$post->content|truncate:100}</p>  <!-- 截断字符串 -->
<span>{$post->slug|capitalize}</span> <!-- 首字母大写 -->

<!-- 默认值 -->
<img src="{$user->avatar|default:'/images/default-avatar.png'}" alt="头像">
<p>{$post->excerpt|default:'暂无简介'}</p>

<!-- HTML 转义控制 -->
<div class="content">{$post->content|noescape}</div>  <!-- 不转义，输出原始HTML -->
<p>{$user->bio}</p>                                   <!-- 默认自动转义 -->

<!-- 数组处理 -->
<span>共 {$tags|length} 个标签</span>  <!-- 数组长度 -->
<span>{$categories|first}</span>       <!-- 第一个元素 -->
<span>{$posts|last}</span>            <!-- 最后一个元素 -->
```

### 自定义过滤器

在控制器应用初始化中添加自定义过滤器：

```php
// 价格格式化过滤器
$latte->addFilter('price', function ($price, $currency = '¥') {
    return $currency . number_format((float)$price, 2);
});

// 人性化时间过滤器
$latte->addFilter('timeAgo', function ($datetime) {
    $time = is_string($datetime) ? strtotime($datetime) : $datetime->getTimestamp();
    $diff = time() - $time;
    
    if ($diff < 60) return '刚刚';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    if ($diff < 2592000) return floor($diff / 86400) . '天前';
    
    return date('Y-m-d', $time);
});
```

在模板中使用：

```html
<!-- 价格格式化 -->
<span class="price">{$product->price|price:'$'}</span>

<!-- 人性化时间 -->
<time>{$post->created_at|timeAgo}</time>
```

## 错误页面处理

### 自定义错误模板

**404 错误页面（errors/404.latte）：**

```html
{layout 'layouts/base.latte'}

{block title}页面不存在 - {include parent}{/block}

{block content}
    <div class="error-page error-404">
        <div class="error-content">
            <div class="error-code">404</div>
            <h1 class="error-title">{$title|default:'页面不存在'}</h1>
            <p class="error-message">{$message|default:'您访问的页面不存在或已被删除'}</p>
            
            <div class="error-actions">
                <a href="/" class="btn btn-primary">返回首页</a>
                <a href="javascript:history.back()" class="btn btn-secondary">返回上页</a>
            </div>
        </div>
    </div>
{/block}
```

**500 错误页面（errors/500.latte）：**

```html
{layout 'layouts/base.latte'}

{block title}服务器错误 - {include parent}{/block}

{block content}
    <div class="error-page error-500">
        <div class="error-content">
            <div class="error-code">500</div>
            <h1 class="error-title">服务器错误</h1>
            <p class="error-message">抱歉，服务器遇到了一些问题，请稍后再试。</p>
            
            <div class="error-actions">
                <a href="/" class="btn btn-primary">返回首页</a>
                <a href="javascript:location.reload()" class="btn btn-secondary">重新加载</a>
            </div>
        </div>
    </div>
{/block}
```

## 最佳实践

### 1. 模板组织结构

```
templates/
├── layouts/               # 布局模板
│   ├── base.latte         # 基础布局
│   └── admin.latte        # 管理后台布局
├── partials/              # 可复用组件
│   ├── header.latte
│   └── footer.latte
├── errors/                # 错误页面
│   ├── 404.latte
│   └── 500.latte
└── pages/                 # 具体页面
    ├── index.latte
    └── blog/
        ├── index.latte
        └── show.latte
```

### 2. 路径写法

- 相对路径：`{include 'partials/user-card.latte'}`
- 绝对路径：`{include '/views/partials/user-card.latte'}`
- 根目录前缀：`{include '@partials/user-card.latte'}`
- 同样适用于 `{layout ...}` 与 `{embed ...}`

### 3. 安全考虑

```html
<!-- ✅ 推荐：默认自动转义 -->
<p>{$user->bio}</p>

<!-- ✅ 推荐：明确不转义可信内容 -->
<div class="content">{$post->htmlContent|noescape}</div>

<!-- ❌ 避免：盲目使用 noescape -->
<p>{$userInput|noescape}</p>
```

### 4. 组件化开发

```html
<!-- ✅ 推荐：创建可复用组件 -->
{include 'partials/user-card.latte', user: $user, showActions: true}

<!-- ✅ 推荐：使用块定义组件接口 -->
{define userCard}
    <div class="user-card">
        <img src="{$user->avatar}" alt="{$user->name}">
        <h3>{$user->name}</h3>
        <p>{$user->bio}</p>
    </div>
{/define}
```

通过遵循这些最佳实践，可以构建结构清晰、安全可靠的模板系统。
