# Model 模型扩展

DuxLite 模型扩展系统的核心类定义和 API 规格说明。

## Nestedset 类

**命名空间：** `Core\Model\Nestedset`

**说明：** 嵌套集合模型，用于处理树形结构数据。

### 方法

```php
public static function sort(string|Builder $model, int $id, int $beforeId, int $parentId): void
```
- **参数：**
  - `$model` - 模型类名或查询构造器
  - `$id` - 要排序的节点ID
  - `$beforeId` - 前一个节点ID（0表示移到最前面）
  - `$parentId` - 父节点ID（0表示根节点）
- **返回：** `void`
- **异常：** `ExceptionBusiness` - 节点不存在时抛出
- **说明：** 对嵌套集合节点进行排序

## Trans 类

**命名空间：** `Core\Model\Trans`

**说明：** 翻译数据访问器，用于处理多语言字段。

### 属性

```php
private string $locale
```
- **说明：** 当前语言代码

```php
private array $translations
```
- **说明：** 翻译数据数组

```php
private object $parent
```
- **说明：** 父模型实例

```php
private string $fallbackLocale
```
- **说明：** 回退语言代码

### 方法

```php
public function __construct(array $translations, string $locale, object $parent, string $fallbackLocale = 'en-US')
```
- **参数：**
  - `$translations` - 翻译数据数组
  - `$locale` - 当前语言代码
  - `$parent` - 父模型实例
  - `$fallbackLocale` - 回退语言代码（可选）
- **说明：** 构造翻译访问器

```php
public function __get(string $name): mixed
```
- **参数：** `$name` - 字段名称
- **返回：** `mixed` - 字段值
- **说明：** 获取翻译字段值，支持回退语言

```php
public function __set(string $name, mixed $value): void
```
- **参数：**
  - `$name` - 字段名称
  - `$value` - 字段值
- **返回：** `void`
- **说明：** 设置翻译字段值

## TransTrait 特性

**命名空间：** `Core\Model\TransTrait`

**说明：** 为模型提供翻译功能的特性。

### 方法

```php
public function setTranslationsAttribute(mixed $value): void
```
- **参数：** `$value` - 翻译数据
- **返回：** `void`
- **说明：** 设置翻译字段，自动转换为JSON格式

```php
public function getTranslationsAttribute(mixed $value): array
```
- **参数：** `$value` - 原始数据
- **返回：** `array` - 解析后的翻译数组
- **说明：** 获取翻译字段，自动从JSON解析

```php
public function translate(string $locale, ?string $fallbackLocale = null): Trans
```
- **参数：**
  - `$locale` - 目标语言代码
  - `$fallbackLocale` - 回退语言代码（可选）
- **返回：** `Trans` - 翻译访问器实例
- **说明：** 获取指定语言的翻译访问器

## TransSet 类

**命名空间：** `Core\Model\TransSet`

**说明：** 翻译数据集合管理器。

### 使用示例

```php
// 使用嵌套集合特性
class Category extends Model
{
    use \Kalnoy\Nestedset\NodeTrait;

    // 排序节点
    Nestedset::sort(Category::class, $nodeId, $beforeId, $parentId);
}

// 使用翻译特性
class Product extends Model
{
    use TransTrait;

    protected $fillable = ['name', 'description', 'translations'];

    // 获取中文翻译
    $product = Product::find(1);
    $zhTrans = $product->translate('zh-CN');
    echo $zhTrans->name; // 中文名称

    // 设置翻译数据
    $product->translations = [
        'zh-CN' => ['name' => '产品名称', 'description' => '产品描述'],
        'en-US' => ['name' => 'Product Name', 'description' => 'Product Description']
    ];
    $product->save();
}
```

## 模型扩展特性

### 嵌套集合特性

| 特性 | 说明 |
|------|------|
| 树形结构 | 支持无限层级的树形数据结构 |
| 高效查询 | 基于左右值的高效树形查询 |
| 节点排序 | 支持节点位置调整和排序 |
| 路径查询 | 支持祖先、后代、兄弟节点查询 |

### 翻译特性

| 特性 | 说明 |
|------|------|
| 多语言支持 | 同一模型支持多种语言 |
| 回退机制 | 当前语言不存在时回退到默认语言 |
| JSON存储 | 翻译数据以JSON格式存储在数据库中 |
| 动态访问 | 通过魔术方法动态访问翻译字段 |

### 支持的嵌套集合操作

| 操作 | 方法 | 说明 |
|------|------|------|
| 移动到根节点 | `saveAsRoot()` | 将节点移动为根节点 |
| 移动到节点前 | `beforeNode()` | 移动到指定节点前面 |
| 移动到节点后 | `afterNode()` | 移动到指定节点后面 |
| 添加为子节点 | `prependToNode()` | 添加为指定节点的第一个子节点 |
| 添加为最后子节点 | `appendToNode()` | 添加为指定节点的最后一个子节点 |