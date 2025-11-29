# 模板预处理器新手指南

这份说明书面向负责编写模板的前端/全栈同学：在 `Views/` 目录里写 `<layout>`、`<list>`、`<loop>` 等标签即可完成布局、控制流与数据拉取，无须了解底层 PHP 类。以下内容覆盖如何启用预处理器、常见标签的详细语法、路径/调试规则以及扩展方法。

## 启用方式（后端一次性配置）

```php
use Core\Views\Render;
use Core\App;

$engine = Render::init('site');                 // scope 可自定义
$engine->setTemplateRoot(App::$appPath . '/Views');
$engine->setTempDirectory(App::$dataPath . '/tpl/site'); // 可选
// $engine->enableAutoPurge(true);              // 开发期可打开自动清理

echo $engine->render('home.tpl', ['me' => $user]);
```

配置好之后，模板作者只需提交 `Views/home.tpl` 等文件；文件扩展名随项目约定（`.tpl`、`.html`、`.latte` 等），只有 `.latte` 会跳过预处理直接交给 Latte。

## 写模板之前的约定

- 页面文件建议放在 `Views/` 根下，碎片/组件放在 `Views/inc/`，布局放在 `Views/layouts/`。
- 使用 `@` 前缀引用模板根，例如 `src='@inc/banner.tpl'`、`src='@layouts/base.tpl'`。
- 同级引用可直接写 `src='partial.tpl'`，系统会按当前文件所在目录查找。
- 模板里可以自由混写 Latte 原生语法（`{block}`、`n:if` 等），预处理器只会把特定标签转换成对应宏。

## 标签参考

### layout
- **作用**：指定要继承的布局文件（每个模板文件仅允许出现一次，通常放在最顶部）。
- **属性**：`src`（必填，表达式用 `:src`），其他属性会原样传给布局。
- **示例**：
  ```html
  <layout src='@layouts/base.tpl'>
    <block name="content">内容</block>
  </layout>
  ```

### block
- **作用**：定义或覆盖 Latte block。
- **属性**：`name`（必填），`append`/`prepend` 等布尔属性可用表达式。
- **示例**：
  ```html
  <block name="content" append="true">
    新增内容
  </block>
  ```

### include
- **作用**：引入一个片段或局部模板。
- **属性**：`src` / `:src` 指定文件，其他任意属性会作为参数传入。
- **示例**：
  ```html
  <include src='@inc/card.tpl' title="最新动态" :items="$list" />
  ```

### embed
- **作用**：与 Latte `{embed}` 相同，可在子模板里覆盖 block。
- **属性**：同 `include`，额外可以在模板内部写 `<block>` 覆盖 slot。
- **示例**：
  ```html
  <embed src='@inc/card.tpl' title="推荐">
    <block name="content">默认插槽内容</block>
  </embed>
  ```

### list
- **作用**：声明式地获取列表数据，可选择只赋值或直接遍历。
- **关键属性**：
  - `data`/`:data`：`命名空间\\Class::method`，指定要调用的后端方法。
  - `as`：存放结果的变量名（默认 `$list`）。若标签有子节点，循环项变量默认为 `${as}Item`。
  - `path`：数组或字符串，表示要从 `params` 提取哪些键传入 `$args`。
  - `args`：补充或覆盖的参数。写 `"*"` 表示把剩余 `params` 全量推进去。
  - `meta`：可选变量名，用于接收返回结果里的 `meta`。
  - `debug`：布尔值，开启后会在模板中输出 `<pre>` 调试信息。
- **示例（遍历）**：
  ```html
  <list data="App\News\Api\Article::list" as="articles" per="10" :path="['category']" category="$tab">
    <div class="card">
      <h3>{$articlesItem.title}</h3>
    </div>
    <empty>
      <p class="empty">暂无文章</p>
    </empty>
  </list>
  ```
- **示例（只赋值）**：
  ```html
  <list data="App\News\Api\Article::list" as="articles" per="10" />
  <p>共 {$articles|length} 条</p>
  ```

### info
- **作用**：获取单条数据并赋值，不负责遍历。
- **常用属性**：同 `list`，但没有 `empty` 子标签。
- **示例**：
  ```html
  <info data="App\News\Api\Article::detail" id="$id" as="article" meta="m" debug="true" />
  <h1>{$article.title}</h1>
  <pre>{$m|json_encode}</pre>
  ```

### loop
- **作用**：语义化的 `{foreach}`，带 `<empty>` 分支。
- **属性**：`data`/`:data`（必填），`item`、`key`、`as` 等。
- **示例**：
  ```html
  <loop data="$rows" item="row" key="idx">
    <div>{$idx + 1}. {$row.title}</div>
    <empty><p>暂无数据</p></empty>
  </loop>
  ```

### empty
- **作用**：`<loop>` 内的空状态替代 `{else}`，不可单独使用。
- **示例**：见上方 `<loop>`。

### for
- **作用**：等价于 `{for $i = ... }`。
- **属性**：`from`、`to`、`step`、`as`，可写表达式。
- **示例**：
  ```html
  <for from="0" to="3" step="1" as="i">
    <li>第 {$i} 次</li>
  </for>
  ```

### foreach
- **作用**：映射到 `{foreach $list as $key => $item}`。
- **属性**：`of`（必填）、`as`、`key`。
- **示例**：
  ```html
  <foreach of="$list" as="item" key="idx">
    <p>{$idx}: {$item}</p>
  </foreach>
  ```

### if / elseif / else
- **作用**：条件渲染，顺序必须保持 `if -> elseif -> else`。
- **属性**：`condition`/`:condition`，值为任意表达式。
- **示例**：
  ```html
  <if condition="$user">
    欢迎 {$user.name}
    <elseif condition="$pending">
      请等待审核
      <else>
        请先登录
      </else>
    </elseif>
  </if>
  ```

### x- 前缀
任意标签都可以加 `x-` 前缀（如 `<x-list>`、`<x-if>`），语义不变，适合在 HTML 中与原生元素做区分。

## 属性语法速记

- `:attr="..."` 表示表达式；未加冒号但以 `$` 开头的值也会当作表达式。
- 字面字符串会自动加引号与转义，可直接写 `title="最新动态"`.
- 布尔属性接受 `true/false/1/0/yes/on`，也可写表达式。
- 可以在元素后追加过滤器：`<user.profile |upper />` 会在预处理阶段转换成 `data_get($user, 'profile')|upper`。

## 路径解析规则

1. `@` 前缀始终指向模板根：`@inc/footer.tpl` → `Views/inc/footer.tpl`。
2. 普通相对路径先以当前模板所在目录为基准，若命中则直接使用；找不到才回退到模板根。
3. 绝对路径或 `file://`、`phar://` 会原样读取，但上线代码建议避免使用以提升可移植性。

## 调试与缓存

- 在 `<list>` 或 `<info>` 上加 `debug="true"` 可输出 `<pre>` 调试块（包含 target、请求参数、错误信息）。
- 模板内可以输出 `{$this->global->api->getLastError()|dump}` 查看最近一次调用的异常。
- 预处理后的模板会缓存到 `data/tpl/{scope}`。若修改后未生效，可请后端开启 `enableAutoPurge(true)` 或手动删除对应目录。

## 扩展新的标签

若你需要 `<tabs>`、`<timeline>` 之类的专用标签，可以在启动阶段注册自定义处理器：

```php
$pre = new \Core\Views\CustomTagPreprocessor();
$pre->addElementHandler('tabs', function (\DOMElement $node, $pp) {
    $active = $pp->attrExprVal($node, 'active') ?? '0';
    return '{var $active = ' . $active . '}'; // 返回任意 Latte 片段
});
```

然后把这个自定义预处理器注入 `CustomTagEngine`。模板作者只需按照约定好的标签/属性书写即可。若你只负责模板编写，把需求描述给维护者，由他们在预处理器里完成注册即可。

---

掌握以上内容后，你就能像写 HTML 一样搭建页面结构、声明数据源，并借助 `<loop><empty>` 等标签快速输出列表。记得充分使用 `debug` 属性与 `@inc/*` 片段拆分机制，维护大型站点也会变得更轻松。
