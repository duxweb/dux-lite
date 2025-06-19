# Views 模板视图

DuxLite 模板视图系统的核心类定义和 API 规格说明。

## Render 类

**命名空间：** `Core\Views\Render`

### 方法

```php
public static function init(string $name): \Latte\Engine
```
- **参数：** `$name` - 视图实例名称
- **返回：** `\Latte\Engine` - Latte模板引擎实例
- **说明：** 创建并配置模板引擎实例，自动设置缓存目录

## Engine 类（Latte组件）

**命名空间：** `\Latte\Engine`

### 方法

```php
public function render(string $name, array $params = []): void
```
- **参数：**
  - `$name` - 模板文件名
  - `$params` - 模板参数数组（可选）
- **返回：** `void`
- **说明：** 渲染模板并输出

```php
public function renderToString(string $name, array $params = []): string
```
- **参数：**
  - `$name` - 模板文件名
  - `$params` - 模板参数数组（可选）
- **返回：** `string` - 渲染后的HTML字符串

```php
public function setTempDirectory(string $path): static
```
- **参数：** `$path` - 临时目录路径
- **返回：** `static` - 当前实例
- **说明：** 设置模板编译缓存目录

```php
public function setLoader(\Latte\Loader $loader): static
```
- **参数：** `$loader` - 模板加载器
- **返回：** `static` - 当前实例
- **说明：** 设置模板文件加载器

```php
public function addFilter(string $name, callable $callback): static
```
- **参数：**
  - `$name` - 过滤器名称
  - `$callback` - 过滤器回调函数
- **返回：** `static` - 当前实例
- **说明：** 添加自定义过滤器

```php
public function addFunction(string $name, callable $callback): static
```
- **参数：**
  - `$name` - 函数名称
  - `$callback` - 函数回调
- **返回：** `static` - 当前实例
- **说明：** 添加自定义函数

```php
public function addProvider(string $name, mixed $value): static
```
- **参数：**
  - `$name` - 提供者名称
  - `$value` - 提供者值
- **返回：** `static` - 当前实例
- **说明：** 添加全局变量提供者

```php
public function setPolicy(\Latte\Policy $policy): static
```
- **参数：** `$policy` - 安全策略
- **返回：** `static` - 当前实例
- **说明：** 设置模板安全策略

```php
public function setStrictTypes(bool $strictTypes = true): static
```
- **参数：** `$strictTypes` - 是否启用严格类型
- **返回：** `static` - 当前实例
- **说明：** 设置严格类型模式

```php
public function setStrictParsing(bool $strictParsing = true): static
```
- **参数：** `$strictParsing` - 是否启用严格解析
- **返回：** `static` - 当前实例
- **说明：** 设置严格解析模式