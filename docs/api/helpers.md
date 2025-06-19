# Helpers 辅助函数

DuxLite 框架提供的全局辅助函数定义和 API 规格说明。

## 调试辅助函数

### dd()
```php
function dd(mixed ...$vars): void
```
- **参数：** `...$vars` - 要调试的变量
- **返回：** `void`
- **说明：** 打印变量并终止执行

## 路径辅助函数

### base_path()
```php
function base_path(string $path = ""): string
```
- **参数：** `$path` - 相对路径（可选）
- **返回：** `string` - 绝对路径
- **说明：** 获取应用根目录路径

### app_path()
```php
function app_path(string $path = ""): string
```
- **参数：** `$path` - 相对路径（可选）
- **返回：** `string` - 绝对路径
- **说明：** 获取应用代码目录路径

### data_path()
```php
function data_path(string $path = ""): string
```
- **参数：** `$path` - 相对路径（可选）
- **返回：** `string` - 绝对路径
- **说明：** 获取数据存储目录路径

### public_path()
```php
function public_path(string $path = ""): string
```
- **参数：** `$path` - 相对路径（可选）
- **返回：** `string` - 绝对路径
- **说明：** 获取公共访问目录路径

### config_path()
```php
function config_path(string $path = ""): string
```
- **参数：** `$path` - 相对路径（可选）
- **返回：** `string` - 绝对路径
- **说明：** 获取配置文件目录路径

### sys_path()
```php
function sys_path(string $base = "", string $path = ""): string
```
- **参数：**
  - `$base` - 基础路径（可选）
  - `$path` - 子路径（可选）
- **返回：** `string` - 组合后的路径
- **说明：** 组合路径并处理分隔符

## 时间辅助函数

### now()
```php
function now(DateTimeZone|string|int|null $timezone = null): \Carbon\Carbon
```
- **参数：** `$timezone` - 时区（可选）
- **返回：** `\Carbon\Carbon` - Carbon时间对象
- **说明：** 获取当前时间

## 网络辅助函数

### get_ip()
```php
function get_ip(): string
```
- **返回：** `string` - 客户端IP地址
- **说明：** 获取客户端真实IP地址

## 数学运算辅助函数

### bc_format()
```php
function bc_format(int|float|string $value = 0, int $decimals = 2): string
```
- **参数：**
  - `$value` - 要格式化的数值
  - `$decimals` - 小数位数（可选）
- **返回：** `string` - 格式化后的数字字符串
- **说明：** 格式化数字，保留指定小数位

### bc_math()
```php
function bc_math(int|float|string $left = 0, string $symbol = '+', int|float|string $right = 0, int $default = 2): string
```
- **参数：**
  - `$left` - 左操作数
  - `$symbol` - 运算符（'+', '-', '*', '/', '%'）
  - `$right` - 右操作数
  - `$default` - 精度（可选）
- **返回：** `string` - 运算结果
- **说明：** 高精度数学运算

### bc_comp()
```php
function bc_comp(int|float|string $left = 0, int|float|string $right = 0, int $scale = 2): int
```
- **参数：**
  - `$left` - 左操作数
  - `$right` - 右操作数
  - `$scale` - 精度（可选）
- **返回：** `int` - 比较结果（-1, 0, 1）
- **说明：** 高精度数字比较

## 加密辅助函数

### encryption()
```php
function encryption(string $str, string $key = '', string $iv = '', string $method = 'AES-256-CBC'): string
```
- **参数：**
  - `$str` - 要加密的字符串
  - `$key` - 加密密钥（可选）
  - `$iv` - 初始化向量（可选）
  - `$method` - 加密方法（可选）
- **返回：** `string` - 加密后的字符串
- **异常：** `ExceptionBusiness` - 加密失败
- **说明：** 加密字符串

### decryption()
```php
function decryption(string $str, string $key = '', string $iv = '', string $method = 'AES-256-CBC'): string|false
```
- **参数：**
  - `$str` - 要解密的字符串
  - `$key` - 解密密钥（可选）
  - `$iv` - 初始化向量（可选）
  - `$method` - 解密方法（可选）
- **返回：** `string|false` - 解密后的字符串或失败
- **说明：** 解密字符串

## 服务检测辅助函数

### is_service()
```php
function is_service(): bool
```
- **返回：** `bool` - 是否为服务环境
- **说明：** 检测是否运行在服务环境中

## 翻译辅助函数

### __()
```php
function __(string $value, mixed ...$params): string
```
- **参数：**
  - `$value` - 翻译键名
  - `...$params` - 参数数组或域名
- **返回：** `string` - 翻译后的文本
- **说明：** 获取翻译文本

## 文件大小辅助函数

### human_filesize()
```php
function human_filesize(int $bytes, int $decimals = 2): string
```
- **参数：**
  - `$bytes` - 字节数
  - `$decimals` - 小数位数（可选）
- **返回：** `string` - 人类可读的文件大小
- **说明：** 将字节数转换为可读格式

## 字符串隐藏辅助函数

### str_hidden()
```php
function str_hidden(string $str, int $percent = 50, string $hide = '*', string $explode = ''): string
```
- **参数：**
  - `$str` - 原始字符串
  - `$percent` - 隐藏百分比（可选）
  - `$hide` - 隐藏字符（可选）
  - `$explode` - 分隔符（可选）
- **返回：** `string` - 隐藏后的字符串
- **说明：** 隐藏字符串中的敏感部分

## 响应辅助函数

### send()
```php
function send(ResponseInterface $response, string $message, array|object|null $data = null, array $meta = [], int $code = 200): ResponseInterface
```
- **参数：**
  - `$response` - 响应对象
  - `$message` - 响应消息
  - `$data` - 响应数据（可选）
  - `$meta` - 元数据（可选）
  - `$code` - 状态码（可选）
- **返回：** `ResponseInterface` - JSON响应对象
- **说明：** 创建标准JSON响应

### sendText()
```php
function sendText(ResponseInterface $response, string $message, int $code = 200): ResponseInterface
```
- **参数：**
  - `$response` - 响应对象
  - `$message` - 响应内容
  - `$code` - 状态码（可选）
- **返回：** `ResponseInterface` - HTML响应对象
- **说明：** 创建HTML文本响应

### sendTpl()
```php
function sendTpl(ResponseInterface $response, string $tpl, array $data = [], string $name = 'web', int $code = 200): ResponseInterface
```
- **参数：**
  - `$response` - 响应对象
  - `$tpl` - 模板名称
  - `$data` - 模板数据（可选）
  - `$name` - 视图名称（可选）
  - `$code` - 状态码（可选）
- **返回：** `ResponseInterface` - 模板响应对象
- **说明：** 渲染模板并创建响应

## URL辅助函数

### url()
```php
function url(string $name, array $params): string
```
- **参数：**
  - `$name` - 路由名称
  - `$params` - 路由参数
- **返回：** `string` - 生成的URL
- **说明：** 根据路由名称生成URL

## 数据格式化辅助函数

### format_data()
```php
function format_data(Collection|LengthAwarePaginator|Model|null $data, callable $callback): array
```
- **参数：**
  - `$data` - 要格式化的数据
  - `$callback` - 格式化回调函数
- **返回：** `array` - 格式化后的数据数组
- **说明：** 格式化数据集合，支持分页和模型