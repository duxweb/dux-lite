# Translation 翻译国际化

DuxLite 翻译国际化系统的核心类定义和 API 规格说明。

## TomlFileLoader 类

**命名空间：** `Core\Translation\TomlFileLoader`

### 方法

```php
protected function loadResource(string $resource): array
```
- **参数：** `$resource` - TOML文件路径
- **返回：** `array` - 解析后的翻译数组
- **异常：** `InvalidResourceException` - 文件解析失败时抛出

## 翻译函数

### __() 函数

```php
function __(string $value, mixed ...$params): string
```
- **参数：**
  - `$value` - 翻译键名
  - `...$params` - 参数数组或域名
- **返回：** `string` - 翻译后的文本
- **说明：** 全局翻译函数，支持参数替换和多域翻译

## 语言文件格式

### TOML 文件结构

```toml
# common.zh-CN.toml
[welcome]
message = "欢迎使用DuxLite"
greeting = "你好，%name%！"

[user]
profile = "用户资料"
not_found = "用户不存在"

[error]
validation_failed = "验证失败"
```

### 支持的语言代码

| 语言 | 代码 | 文件示例 |
|------|------|----------|
| 中文 | `zh-CN` | `common.zh-CN.toml` |
| 英文 | `en-US` | `common.en-US.toml` |
| 日文 | `ja-JP` | `common.ja-JP.toml` |

### 翻译域

| 域名 | 说明 | 文件格式 |
|------|------|----------|
| `common` | 通用翻译 | `common.{locale}.toml` |
| `validation` | 验证消息 | `validation.{locale}.toml` |
| `email` | 邮件模板 | `email.{locale}.toml` |
| `admin` | 管理后台 | `admin.{locale}.toml` |