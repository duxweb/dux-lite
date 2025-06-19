# Validator 验证

DuxLite 验证系统的核心类定义和 API 规格说明。

## Validator 类

**命名空间：** `Core\Validator\Validator`

### 静态方法

```php
public static function make(array $data, array $rules, array $messages = []): self
```
- **参数：**
  - `$data` - 待验证数据
  - `$rules` - 验证规则
  - `$messages` - 自定义错误消息（可选）
- **返回：** `Validator` - 验证器实例

```php
public static function validate(array $data, array $rules, array $messages = []): array
```
- **参数：**
  - `$data` - 待验证数据
  - `$rules` - 验证规则
  - `$messages` - 自定义错误消息（可选）
- **返回：** `array` - 验证通过的数据
- **异常：** `ExceptionValidator` - 验证失败

### 实例方法

```php
public function passes(): bool
```
- **返回：** `bool` - 验证是否通过

```php
public function fails(): bool
```
- **返回：** `bool` - 验证是否失败

```php
public function validated(): array
```
- **返回：** `array` - 验证通过的数据
- **异常：** `ExceptionValidator` - 验证失败

```php
public function errors(): MessageBag
```
- **返回：** `MessageBag` - 错误消息集合

```php
public function getMessageBag(): MessageBag
```
- **返回：** `MessageBag` - 错误消息集合

```php
public function after(callable $callback): self
```
- **参数：** `$callback` - 验证后执行的回调函数
- **返回：** `self` - 验证器实例

```php
public function sometimes(string $attribute, string|array $rules, callable $callback): self
```
- **参数：**
  - `$attribute` - 字段名
  - `$rules` - 验证规则
  - `$callback` - 条件回调函数
- **返回：** `self` - 验证器实例

```php
public function addRules(array $rules): self
```
- **参数：** `$rules` - 要添加的验证规则
- **返回：** `self` - 验证器实例

```php
public function setRules(array $rules): self
```
- **参数：** `$rules` - 验证规则
- **返回：** `self` - 验证器实例

```php
public function setData(array $data): self
```
- **参数：** `$data` - 验证数据
- **返回：** `self` - 验证器实例

```php
public function setCustomMessages(array $messages): self
```
- **参数：** `$messages` - 自定义错误消息
- **返回：** `self` - 验证器实例

```php
public function setAttributeNames(array $attributes): self
```
- **参数：** `$attributes` - 字段名称映射
- **返回：** `self` - 验证器实例

## Data 类

**命名空间：** `Core\Validator\Data`

### 构造方法

```php
public function __construct(array $data = [])
```
- **参数：** `$data` - 初始数据（可选）

### 数据操作方法

```php
public function set(string $key, mixed $value): self
```
- **参数：**
  - `$key` - 数据键名
  - `$value` - 数据值
- **返回：** `self` - Data实例

```php
public function get(string $key, mixed $default = null): mixed
```
- **参数：**
  - `$key` - 数据键名
  - `$default` - 默认值（可选）
- **返回：** `mixed` - 数据值

```php
public function has(string $key): bool
```
- **参数：** `$key` - 数据键名
- **返回：** `bool` - 是否存在该键

```php
public function forget(string $key): self
```
- **参数：** `$key` - 要删除的键名
- **返回：** `self` - Data实例

```php
public function only(array $keys): array
```
- **参数：** `$keys` - 要获取的键名数组
- **返回：** `array` - 指定键的数据

```php
public function except(array $keys): array
```
- **参数：** `$keys` - 要排除的键名数组
- **返回：** `array` - 排除指定键后的数据

```php
public function all(): array
```
- **返回：** `array` - 所有数据

```php
public function toArray(): array
```
- **返回：** `array` - 转换为数组

```php
public function isEmpty(): bool
```
- **返回：** `bool` - 是否为空

```php
public function count(): int
```
- **返回：** `int` - 数据项数量

## 验证规则

### 基础验证规则

| 规则 | 说明 | 参数 |
|------|------|------|
| `required` | 必填字段 | 无 |
| `nullable` | 允许为空 | 无 |
| `optional` | 可选字段 | 无 |
| `filled` | 不为空（存在时） | 无 |
| `present` | 必须存在 | 无 |

### 类型验证规则

| 规则 | 说明 | 参数 |
|------|------|------|
| `string` | 字符串类型 | 无 |
| `integer` | 整数类型 | 无 |
| `numeric` | 数字类型 | 无 |
| `boolean` | 布尔类型 | 无 |
| `array` | 数组类型 | 无 |
| `object` | 对象类型 | 无 |
| `json` | JSON字符串 | 无 |

### 长度验证规则

| 规则 | 说明 | 参数 |
|------|------|------|
| `min:value` | 最小长度/值 | `value` - 最小值 |
| `max:value` | 最大长度/值 | `value` - 最大值 |
| `between:min,max` | 长度/值范围 | `min,max` - 最小值,最大值 |
| `size:value` | 固定长度/值 | `value` - 固定值 |

### 格式验证规则

| 规则 | 说明 | 参数 |
|------|------|------|
| `email` | 邮箱格式 | 无 |
| `url` | URL格式 | 无 |
| `ip` | IP地址格式 | 无 |
| `ipv4` | IPv4地址格式 | 无 |
| `ipv6` | IPv6地址格式 | 无 |
| `mac_address` | MAC地址格式 | 无 |
| `uuid` | UUID格式 | 无 |

### 日期验证规则

| 规则 | 说明 | 参数 |
|------|------|------|
| `date` | 日期格式 | 无 |
| `date_format:format` | 指定日期格式 | `format` - 日期格式 |
| `before:date` | 早于指定日期 | `date` - 对比日期 |
| `after:date` | 晚于指定日期 | `date` - 对比日期 |
| `before_or_equal:date` | 早于或等于指定日期 | `date` - 对比日期 |
| `after_or_equal:date` | 晚于或等于指定日期 | `date` - 对比日期 |

### 比较验证规则

| 规则 | 说明 | 参数 |
|------|------|------|
| `same:field` | 与指定字段相同 | `field` - 对比字段名 |
| `different:field` | 与指定字段不同 | `field` - 对比字段名 |
| `confirmed` | 确认字段（需要field_confirmation） | 无 |
| `gt:field` | 大于指定字段 | `field` - 对比字段名 |
| `gte:field` | 大于等于指定字段 | `field` - 对比字段名 |
| `lt:field` | 小于指定字段 | `field` - 对比字段名 |
| `lte:field` | 小于等于指定字段 | `field` - 对比字段名 |

### 选择验证规则

| 规则 | 说明 | 参数 |
|------|------|------|
| `in:value1,value2,...` | 在指定值列表中 | `values` - 允许的值列表 |
| `not_in:value1,value2,...` | 不在指定值列表中 | `values` - 禁止的值列表 |
| `in_array:field` | 在指定数组字段中 | `field` - 数组字段名 |

### 正则验证规则

| 规则 | 说明 | 参数 |
|------|------|------|
| `regex:pattern` | 正则表达式匹配 | `pattern` - 正则表达式 |
| `alpha` | 只包含字母 | 无 |
| `alpha_dash` | 只包含字母、数字、破折号、下划线 | 无 |
| `alpha_num` | 只包含字母和数字 | 无 |

### 数据库验证规则

| 规则 | 说明 | 参数 |
|------|------|------|
| `exists:table,column` | 数据库中存在 | `table,column` - 表名,字段名 |
| `unique:table,column,except` | 数据库中唯一 | `table,column,except` - 表名,字段名,排除值 |

### 文件验证规则

| 规则 | 说明 | 参数 |
|------|------|------|
| `file` | 文件类型 | 无 |
| `image` | 图片文件 | 无 |
| `mimes:ext1,ext2,...` | 指定MIME类型 | `extensions` - 允许的扩展名 |
| `mimetypes:type1,type2,...` | 指定MIME类型 | `types` - 允许的MIME类型 |
| `dimensions:min_width=100,min_height=200` | 图片尺寸限制 | 尺寸参数 |

## 错误消息

### MessageBag 类

```php
public function all(): array
```
- **返回：** `array` - 所有错误消息

```php
public function first(string $key = null): string|null
```
- **参数：** `$key` - 字段名（可选）
- **返回：** `string|null` - 第一个错误消息

```php
public function get(string $key): array
```
- **参数：** `$key` - 字段名
- **返回：** `array` - 指定字段的所有错误消息

```php
public function has(string $key): bool
```
- **参数：** `$key` - 字段名
- **返回：** `bool` - 是否有错误消息

```php
public function isEmpty(): bool
```
- **返回：** `bool` - 是否为空

```php
public function isNotEmpty(): bool
```
- **返回：** `bool` - 是否不为空

```php
public function count(): int
```
- **返回：** `int` - 错误消息数量

```php
public function toArray(): array
```
- **返回：** `array` - 转换为数组

### 默认错误消息

| 规则 | 默认消息 |
|------|----------|
| `required` | `:attribute 字段是必填的` |
| `string` | `:attribute 必须是字符串` |
| `integer` | `:attribute 必须是整数` |
| `email` | `:attribute 必须是有效的邮箱地址` |
| `min` | `:attribute 最少需要 :min 个字符` |
| `max` | `:attribute 最多只能有 :max 个字符` |
| `unique` | `:attribute 已经存在` |
| `exists` | `:attribute 不存在` |

### 自定义错误消息格式

```php
[
    'field.rule' => '自定义错误消息',
    'field.required' => '该字段必须填写',
    'email.email' => '邮箱格式不正确',
    'password.min' => '密码至少需要8位字符'
]
```

### 字段名称映射

```php
[
    'email' => '邮箱地址',
    'password' => '密码',
    'name' => '姓名',
    'phone' => '手机号码'
]
```

## 验证规则语法

### 单个规则

```php
'field' => 'required'
```

### 多个规则（字符串）

```php
'field' => 'required|string|min:3|max:255'
```

### 多个规则（数组）

```php
'field' => ['required', 'string', 'min:3', 'max:255']
```

### 带参数的规则

```php
'field' => 'min:8|max:20|regex:/^[a-zA-Z0-9]+$/'
```

### 条件规则

```php
'field' => 'required_if:other_field,value'
'field' => 'required_unless:other_field,value'
'field' => 'required_with:field1,field2'
'field' => 'required_without:field1,field2'
```

### 数组验证

```php
'items' => 'array|min:1'
'items.*' => 'required|string'
'items.*.name' => 'required|string|max:100'
'items.*.price' => 'required|numeric|min:0'
```

### 嵌套对象验证

```php
'user' => 'required|array'
'user.name' => 'required|string|max:100'
'user.email' => 'required|email|unique:users,email'
'user.profile' => 'array'
'user.profile.bio' => 'string|max:500'
```

## 自定义验证规则

### 扩展验证器

```php
Validator::extend('foo', function ($attribute, $value, $parameters, $validator) {
    return $value === 'foo';
});
```

### 隐式规则

```php
Validator::extendImplicit('foo', function ($attribute, $value, $parameters, $validator) {
    return $value === 'foo';
});
```

### 可替换规则

```php
Validator::replacer('foo', function ($message, $attribute, $rule, $parameters) {
    return str_replace(':foo', $parameters[0], $message);
});
```

## 验证异常

### ExceptionValidator

**命名空间：** `Core\Handlers\ExceptionValidator`
**继承：** `Exception`

### 异常属性

```php
public function getErrors(): MessageBag
```
- **返回：** `MessageBag` - 验证错误消息

```php
public function getFirstError(): string|null
```
- **返回：** `string|null` - 第一个错误消息

```php
public function getErrorsArray(): array
```
- **返回：** `array` - 错误消息数组

## 条件验证规则

### 条件必填规则

| 规则 | 说明 |
|------|------|
| `required_if:field,value` | 当指定字段等于某值时必填 |
| `required_unless:field,value` | 当指定字段不等于某值时必填 |
| `required_with:field1,field2` | 当指定字段存在时必填 |
| `required_with_all:field1,field2` | 当所有指定字段都存在时必填 |
| `required_without:field1,field2` | 当指定字段不存在时必填 |
| `required_without_all:field1,field2` | 当所有指定字段都不存在时必填 |

### 条件规则参数

```php
'field' => 'required_if:type,premium|string|max:100'
'field' => 'required_unless:status,draft|email'
'field' => 'required_with:first_name,last_name|string'
```

## 验证数据转换

### 自动类型转换

| 类型 | 转换规则 |
|------|----------|
| `integer` | 转换为整数 |
| `boolean` | 转换为布尔值 |
| `array` | 确保为数组 |
| `string` | 转换为字符串 |

### 数据清理

验证通过后，数据会根据验证规则进行清理：
- 移除未定义的字段
- 类型转换
- 格式标准化

## 性能优化

### 验证缓存

- 验证规则解析结果缓存
- 自定义规则缓存
- 错误消息模板缓存

### 批量验证

- 支持批量数据验证
- 并行验证处理
- 早期失败机制

### 内存管理

- 大数据集分批验证
- 及时释放验证器实例
- 错误消息内存优化