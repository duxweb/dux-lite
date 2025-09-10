# 扩展模板预处理器（CustomTagPreprocessor）

本框架在 Latte 之上提供一层“XML 化标签”的预处理能力。目标是让“取数 + 渲染 + 结构控制”在模板中更直观、声明式。

## 能力概览

- 基于 HTML5 DOM 解析，稳定处理模板（非正则替换）。
- 自定义元素语法（处理器化）：
  - 结构与布局：`<layout>...</layout> <include/> <block>...</block>`
  - 控制流：`<if> <elseif> <else> <for> <foreach> <loop><empty>`
  - 数据拉取：`<list/> <info/>`（配合 `{xTag}`/`{xTagAssign}` 与 `ApiService`）
  - 空态：`<empty>`（配合 `<loop>`）
- 点语法占位：`<foo.bar/>` → `data_get($foo, 'bar')` 输出。
- 表达式属性：支持 `:attr="expr"`；也兼容未加 `:` 但值以 `$` 开头的表达式写法。
- 与 Latte 共存：原生 `n:*` 属性宏与块宏可直接混用，原样保留。
- 路径增强：`@` 前缀、绝对路径、URL（`phar://`、`file://` 等）。
- 缓存精准失效：按“级别/mtime/hash”判定，默认不全清。

> 说明：自定义元素（如 `<if>`、`<list>`）是本层的第一公民；Latte 原生语法（如 `n:if` 与 `{if}`）可同时使用。

## 基本用法

渲染引擎初始化（框架封装）：

```php
$engine = \Core\Views\Render::init('site');
$engine->setTemplateRoot(App::$appPath . '/Views'); // 可选：启用 '@' 路径
$engine->setTempDirectory(App::$dataPath . '/tpl/site');
// 精准失效作用域（同名视图隔离缓存）
$engine->setCacheScope('site');
// 可选：启用自动清理（默认关闭）
// $engine->enableAutoPurge(true);
```

### 布局与片段

```html
<layout src='@layouts/base.latte'>
  <block name="content">
    页面主体……
  </block>
</layout>

<include src='@inc/header.latte' title="Home" :user="$me" />
```

对应 Latte：`{embed ...}{/embed}`（`<layout>` 的内部实现）、`{include ...}`、`{block ...}{/block}`。

### 控制流

```html
<if condition="$loggedIn">
  欢迎回来
  <elseif condition="$isGuest">
    你好，游客
    <else>
      未知身份
    </else>
  </elseif>
</if>

<for from="0" to="10" step="2" as="i">第 {$i} 项</for>

<foreach of="$list" as="row" key="idx">
  {$idx}: {$row}
</foreach>

<loop data="$items" item="it" key="k">
  {$k} = {$it}
  <empty>
    无数据
  </empty>
</loop>
```

### 数据拉取标签（与 ApiService 配合）

`<list/>` 与 `<info/>` 声明数据源、变量名、参数、调试等，底层编译成 `{xTagAssign}` 或 `{xTag}+{foreach}` 调用，具体由 `CustomLatteExtension` 执行并使用 `ApiService` 拉取数据。

- 属性
  - `data`: 类方法路由，如 `App\Content\Api\Article::list` 或 `/path/to/Article.php::list`
  - `path`: 从 `params` 中挑选作为路径参数传入方法（逗号/空格分隔或数组）
  - 其他任意属性将聚合为 `params`（支持 `:key="expr"` 与 `key="$expr"`）
  - `as`: 变量名（`<list>` 为 item 变量，`<info>` 为赋值变量）
  - `meta`: 元数据变量名（可选）
  - `debug`: 开关，输出内联调试块

示例：

```html
<!-- 列表：生成变量 $list，内部遍历为 $listItem（或自定义 as） -->
<list data="App\News\Api\Article::list" category="tech" page="1" per="10" debug="true" as="row">
  <div>{$row.title}</div>
</list>

<!-- 单条信息：将结果赋给 $article，并可将 meta 赋给 $m -->
<info data="App\News\Api\Article::detail" id="$id" meta="m" as="article"/>
<h1>{$article.title}</h1>
```

### 点语法占位

```html
<!-- 输出 $user.profile.name，等价于 {= data_get($user, 'profile.name') } -->
<user.profile.name />
```

## 表达式属性与转义

- 表达式优先：`:attr="..."`。例如 `:user="$me"`、`:of="$list"`。
- 兼容：未写 `:` 但值以 `$` 开头也视为表达式（如 `num="$n"`）。
- 其他字面量会自动加引号并转义。
- 普通元素未识别的属性与内容会被“原样序列化”，但会保护 `{...}` 中的 Latte 表达式，避免被转义破坏。

## 路径与加载（AtFileLoader）

- `@` 前缀：相对模板根（`setTemplateRoot()`）解析，如 `@inc/header.latte`。
- 绝对/URL：直接读取（`/abs/path`、`phar://...`、`file://...`）。
- 相对路径：优先以引用文件所在目录解析，找不到回退到 Latte 默认规则。

### 标签前缀（可选）

- 所有内置标签均支持 `x-` 前缀别名，便于与原生 HTML 区分：
  - 例如：`<x-layout>` 等价于 `<layout>`，`<x-include>` 等价于 `<include>`，`<x-block>` 等价于 `<block>`，等等。
  - 当前别名覆盖：`layout, include, block, empty, if, elseif, else, for, foreach, loop, list, info`。

## 缓存与精准失效

引擎以“级别/mtime/hash”精确失效策略缓存预处理产物（`.latte` 文件）。

- 默认不清空缓存：`createTemplate(..., clearCache=false)`。
- 作用域：`setCacheScope('site')` 参与缓存 key，按视图/场景隔离。
- 自动清理（可选）：`enableAutoPurge(true)`。
- 主动预处理：`preprocessToCache($name)` 返回缓存文件，便于工具链集成。

缓存 key 组成：加载器唯一标识/源文件路径 + 模板根 + 预处理器版本 + 作用域；内容变化（hash）或源 mtime 更新会触发重写。

## 与 Latte 的关系

- 生成的模板仍是合法 Latte，后续由 Latte 负责编译与执行。
- 可以与 Latte 原生宏/过滤器/提供者一起使用（本层通过 `CustomLatteExtension` 暴露 `api` 服务、注册 `{xTag}` 与 `{xTagAssign}`）。
- 不干预 Latte `n:*` 属性宏；可与自定义元素语法并存。

## 最佳实践

- 页面结构：使用 `<layout>...</layout> <include/> <block>` 组织模板；
- 流程控制：使用 `<if>/<elseif>/<else>` 与 `<for>/<foreach>/<loop>`；
- 数据拉取：优先用 `<list>/<info>` 声明数据源与变量，复杂场景交给控制器；
- 表达式：推荐 `:attr="expr"`，也可用 `attr="$expr"`；
- 性能：生产环境关闭自动清理，依赖精准失效；按视图设置 `cacheScope`；
- 混用：需要时可直接使用 Latte 的块宏与 `n:*` 属性。

---

如需扩展新语法：实现一个处理器（`callable(DOMElement, CustomTagPreprocessor): string`），通过 `$pre->addElementHandler('tag', $callable)` 注册即可。框架已内置 `list/info/loop/empty/if/elseif/else/for/foreach/layout/include/embed/block` 等处理器。

## API 参考（简）

引擎与辅助类的常用方法与标签/属性清单，便于快速查阅。

### 引擎（CustomTagEngine）

- `setTempDirectory(string|null $path): static`：设置预处理产物缓存目录。
- `setTemplateRoot(string|null $path): static`：设置模板根目录，支持 `@` 路径前缀。
- `setCacheScope(string|null $scope): static`：设置缓存作用域（参与缓存键）。
- `enableAutoPurge(bool $on = true): static`：启用自动清理缓存（默认关闭）。
- `createTemplate(string $name, array $params = [], bool $clearCache = false): Template`：预处理后交由 Latte 创建模板。
- `preprocessToCache(string $name, bool $clearCache = false): string`：仅执行预处理并返回缓存文件路径。

### 渲染辅助（Render）

- `Render::init(string $name): CustomTagEngine`：根据名称初始化引擎与缓存目录，并设置 `cacheScope`。
- `Render::resolve(array $xt, ApiService $api): array{data,meta,target,args,error,debugFlag}`：解析 `{xTag}` 组合参数并拉取数据。
- `Render::debugBlock(array $pack, string $type = 'list'): string`：输出安全的内联 `<pre>` 调试块。

### 数据服务（ApiService）

- `setFetcher(callable $fetcher): void`：注入自定义获取器 `fn(string $route, array $query): iterable`。
- `fetch(string $route, array $query = [], array $pathArgs = []): iterable`：支持 `Class::method` 或 `/path/Class.php::method`。
- `call(string $methodSpec, array $query = []): iterable`：便捷直调 `Class::method`。
- `fetchAndUnpack(string $route, array $params = []): array{data,meta}`：拉取并拆分。
- `split(mixed $result): array{data,meta}`：标准化返回为 `[data, meta]`。
- `iterableOf(mixed $data): iterable`：将任意值转为安全可迭代。
- `getLastError(): ?array`：获取最近一次错误信息。

路由形式与签名适配：
- 路由：`App\Foo\Bar::list`、`/.../App/Foo/Bar.php::list`、也兼容 `@`/`->`/`#` 作为方法分隔符。
- 方法签名适配：`(ServerRequest)`、`(array $query)`、`(ServerRequest, Response)`、`(ServerRequest, array $args)`、`(Response, array $args)`、`(ServerRequest, Response, array $args)` 等常见形态。

### Latte 扩展（CustomLatteExtension）

- 宏：`{xTag ...}{/xTag}`、`{xTagAssign ...}`（由预处理器生成）。
- Provider：`api`（在模板中可通过 `$this->global->api` 使用）。

### 标签与属性速查

- `<layout src|:src ...>...</layout>` → `{embed ...}...{/embed}`（layout 内部实现）
  - 用途：页面级“包裹/主布局”。在当前位置嵌入布局模板，并通过内部 `<block name="...">` 将内容填充到布局中的同名块。
  - 行为：未显式编写任何 `<block>` 时，内部内容会作为默认块 `content` 注入布局的 `{block content}`。
  - 属性：`src|:src` 指定模板；其它属性变为命名参数传入布局。

- `<include src|:src .../>` → `{include ...}`
  - 用途：插入片段（无插槽）。
  - 行为：在当前位置内联渲染被包含模板。
  - 属性：`src|:src` 指定模板；其它属性作为命名参数传入。

- `<block name="...">...</block>` → `{block name}...{/block}`
  - 用途：定义命名片段；当放在 `<layout>` 内部时，覆盖布局中同名块。
  - 要求：`name` 必填，区分大小写。

- `<if condition>...</if>` / `<elseif condition>...</elseif>` / `<else>...</else>`
  - 用途：条件渲染。
  - 属性：`condition|:condition` 条件表达式。

- `<for from to step as>` → `{for $as = from; $as <= to; $as += step}...{/for}`
  - 用途：传统 for 循环。
  - 属性：`from|:from`、`to|:to`、`step|:step`、`as|:as`。

- `<foreach of as key>` → `{foreach of as [key =>] as}`
  - 用途：遍历集合。
  - 属性：`of|:of`（数据源）、`as|:as`（项变量）、`key|:key`（键名，可选）。

- `<loop data item key>` → `{foreach data as [key =>] item}{else}...{/foreach}`（支持 `<empty>`）
  - 用途：遍历并内置“空态”。
  - 属性：`data|:data`（数据源）、`item|:item`、`key|:key`。
  - 子元素：`<empty>...</empty>` 指定空态内容。

- `<list ...>`（结合 ApiService）
  - 用途：声明式拉取“列表数据”，并渲染或赋值。
  - 行为：无子节点 → 仅赋值 `{xTagAssign ...}`；有子节点 → 先赋值再循环渲染 `{xTagAssign ...}{foreach ...}{/foreach}`。
  - 属性：`data`（必填，目标方法）、`path|:path`（从 params 抽取为路径参数）、`as`（内部循环变量，默认 `$listItem`）、`meta`（元数据变量）、`debug|:debug`（调试开关）；其它属性聚合为 `params`（支持 `:key` 或 `key="$expr"`）。

- `<info ...>`（结合 ApiService）
  - 用途：拉取“单条数据”并赋值。
  - 属性：`data`（必填）、`path|:path`、`as`（赋值变量）、`meta`、`debug|:debug`、其它入 `params`。

- `<x-val data-var data-path .../>` → `{= data_get($var,'path') }`
  - 用途：值输出占位（由 `<foo.bar/>` 自动改写而来）。
  - 过滤器：支持追加，如 `<foo.bar escape|truncate="10"/>`。

表达式规则：
- 优先 `:attr="..."`；若未加 `:` 但值以 `$` 开头，同样视为表达式。

### 缓存行为（再次说明）

- Key 组成：`来源标识 + templateRoot + Preprocessor::VERSION + cacheScope`。
- 写入策略：内容 hash 变化或源 mtime 较新才落盘；`clearCache` 或 `enableAutoPurge(true)` 时才全清。
