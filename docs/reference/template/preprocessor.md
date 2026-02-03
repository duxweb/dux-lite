# 模板引擎说明（独立的模板运行环境）

Dux 模板引擎不是“简单的模板渲染器”，而是一套 **独立的模板运行环境**：

- **模板 DSL 层**：HTML 超语法（`<layout>/<list>/<loop>` 等）
- **渲染内核**：Latte
- **内置运行时服务**：API 数据调用、分页输出、调试支持

可以把它理解为：**“模板自己的小型运行时”**，模板内声明式的数据调用会在渲染时自动执行，并把结果注入变量。

---

## 架构与执行流程

1. **加载模板**（`Render::init()->render()`）
2. **预处理 HTML 超语法**（`CustomTagPreprocessor`）
   - `<layout>/<include>/<list>/<info>/<loop>` 等被转换成 Latte 宏
3. **Latte 编译/执行**（`CustomTagEngine`）
4. **运行时服务注入**（`CustomLatteExtension`）
   - `{xTag}`、`{pagination}`、`$this->global->api` 等

`.latte` 文件会跳过预处理，直接进入 Latte。

---

## 运行时能力（模板内可直接使用）

### 1) 数据调用（模板即运行时）

`<list>` / `<info>` 会调用后端 `Class::method`，并把结果作为变量注入：

```html
<list data="App\Content\Api\Article::list" as="articles" :path="['category']" category="$tab">
  <h3>{$articlesItem.title}</h3>
  <empty>暂无数据</empty>
</list>
```

```html
<info data="App\Content\Api\Article::detail" id="$id" as="article" />
<h1>{$article.title}</h1>
```

这说明模板可以主动“发起数据调用”，而不是被动等待控制器传参。

### 2) 分页输出（运行时函数）

内置 `{pagination ...}` 宏或 `<pagination .../>` 语法：

```html
<pagination
  base="/articles?category={$category}"
  :page="$meta['page']"
  :total="$meta['total']"
  :pageSize="$pageSize"
  pageParam="page"
  window="2"
/>
```

等价：

```html
{pagination base => "/articles?category={$category}", page => $meta['page'], total => $meta['total'], pageSize => $pageSize, pageParam => 'page', window => 2}
```

### 3) 调试支持

```html
<list data="App\News\Api\Article::list" debug="true" />
```

会输出调试块（请求参数、数据类型、错误信息）。

---

## HTML 超语法（DSL）

### 布局

- `<layout src='@layouts/base.tpl'>` → `{layout ...}`
- `<include src='@inc/card.tpl' title="标题" />` → `{include ...}`
- `<embed ...>` → `{embed ...}`
- `<block name="content">...</block>` → `{block ...}`

### 控制流

- `<if condition="..."> ... </if>`
- `<elseif condition="..."> ... </elseif>`
- `<else> ... </else>`
- `<for from="0" to="10" step="1" as="i"> ... </for>`
- `<foreach of="$list" as="item" key="idx"> ... </foreach>`
- `<loop data="$rows" item="row" key="idx"> ... <empty>...</empty></loop>`

---

## 文件类型规则

- **`.latte` 文件**：不经过预处理，直接进入 Latte
- **其他扩展名**：先预处理 → Latte

---

## 路径解析规则

- `@` 前缀指向模板根目录（`Views`）
- 相对路径优先当前目录，其次模板根
- `layout/include/embed` 支持相对路径、绝对路径（以 `/` 开头）、`@` 根目录路径
- 预处理仅负责语法转换，不会改写路径，最终解析交由 Latte Loader

---

## 模板运行环境的关键对象

- `Render`：模板入口，负责加载与编译
- `CustomTagPreprocessor`：HTML 超语法解析器
- `CustomLatteExtension`：注册 `xTag` / `pagination` 等运行时宏
- `ApiService`：模板内数据调用引擎

---

## 预处理跟踪（Debug/Trace）

- 可开启 DSL → Latte 的跟踪输出（JSON），用于定位转换结果
- 可选包含源模板内容，便于对比差异
- 支持回调模式接入调试工具（内存收集或自定义日志）

### 使用示例

```php
use Core\Views\Render;

$engine = Render::init('views');
$engine->setTempDirectory($tmpDir);

// 开启 trace（包含源模板）
$engine->enablePreprocessTrace(true);

// 可选：设置回调收集 trace 数据
$engine->setPreprocessTraceCallback(function (array $payload) {
    // $payload 包含 name/cache/source/processed/updated_at 等字段
});
```

---

## 适用场景

适合：
- CMS/内容展示类页面
- 数据驱动的模板化页面
- 前后端分离时作为“视图层运行时”

不适合：
- 复杂 UI 状态管理（建议前端框架）
- 高交互 SPA（建议 React/Vue）

---

## 总结

Dux 模板引擎是一套独立运行环境：

- 模板可主动发起数据调用
- 内置运行时函数（分页/调试）
- HTML 超语法让模板更像“语义化程序”

它不是仅仅“渲染字符串”，而是**模板即程序**。
