# Storage 存储

DuxLite 存储系统的核心类定义和 API 规格说明。

## Storage 类

**命名空间：** `Core\Storage\Storage`

### 静态方法

```php
public static function disk(string $name = null): StorageInterface
```
- **参数：** `$name` - 存储驱动名称（可选）
- **返回：** `StorageInterface` - 存储驱动实例

```php
public static function init(array $config): StorageInterface
```
- **参数：** `$config` - 存储配置数组
- **返回：** `StorageInterface` - 存储驱动实例

## StorageInterface 接口

**命名空间：** `Core\Storage\Contracts\StorageInterface`

### 文件操作方法

```php
public function exists(string $path): bool
```
- **参数：** `$path` - 文件路径
- **返回：** `bool` - 文件是否存在

```php
public function get(string $path): string
```
- **参数：** `$path` - 文件路径
- **返回：** `string` - 文件内容
- **异常：** `StorageException` - 文件不存在或读取失败

```php
public function put(string $path, string $contents, array $options = []): bool
```
- **参数：**
  - `$path` - 文件路径
  - `$contents` - 文件内容
  - `$options` - 存储选项（可选）
- **返回：** `bool` - 是否存储成功
- **异常：** `StorageException` - 存储失败

```php
public function delete(string $path): bool
```
- **参数：** `$path` - 文件路径
- **返回：** `bool` - 是否删除成功

```php
public function copy(string $from, string $to): bool
```
- **参数：**
  - `$from` - 源文件路径
  - `$to` - 目标文件路径
- **返回：** `bool` - 是否复制成功

```php
public function move(string $from, string $to): bool
```
- **参数：**
  - `$from` - 源文件路径
  - `$to` - 目标文件路径
- **返回：** `bool` - 是否移动成功

```php
public function size(string $path): int
```
- **参数：** `$path` - 文件路径
- **返回：** `int` - 文件大小（字节）
- **异常：** `StorageException` - 文件不存在

```php
public function lastModified(string $path): int
```
- **参数：** `$path` - 文件路径
- **返回：** `int` - 最后修改时间戳
- **异常：** `StorageException` - 文件不存在

```php
public function mimeType(string $path): string
```
- **参数：** `$path` - 文件路径
- **返回：** `string` - MIME 类型
- **异常：** `StorageException` - 文件不存在

### 目录操作方法

```php
public function files(string $directory = '', bool $recursive = false): array
```
- **参数：**
  - `$directory` - 目录路径（可选）
  - `$recursive` - 是否递归查找（可选）
- **返回：** `array` - 文件路径数组

```php
public function directories(string $directory = '', bool $recursive = false): array
```
- **参数：**
  - `$directory` - 目录路径（可选）
  - `$recursive` - 是否递归查找（可选）
- **返回：** `array` - 目录路径数组

```php
public function makeDirectory(string $path): bool
```
- **参数：** `$path` - 目录路径
- **返回：** `bool` - 是否创建成功

```php
public function deleteDirectory(string $directory): bool
```
- **参数：** `$directory` - 目录路径
- **返回：** `bool` - 是否删除成功

### URL 生成方法

```php
public function url(string $path): string
```
- **参数：** `$path` - 文件路径
- **返回：** `string` - 访问URL

```php
public function temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
```
- **参数：**
  - `$path` - 文件路径
  - `$expiration` - 过期时间
  - `$options` - URL选项（可选）
- **返回：** `string` - 临时访问URL
- **异常：** `StorageException` - 不支持临时URL

## LocalDriver 类

**命名空间：** `Core\Storage\Drivers\LocalDriver`
**实现：** `StorageInterface`

### 构造方法

```php
public function __construct(string $root)
```
- **参数：** `$root` - 根目录路径

### 扩展方法

```php
public function path(string $path): string
```
- **参数：** `$path` - 相对路径
- **返回：** `string` - 绝对文件路径

```php
public function setPermissions(string $path, int $permissions): bool
```
- **参数：**
  - `$path` - 文件路径
  - `$permissions` - 权限值（八进制）
- **返回：** `bool` - 是否设置成功

## S3Driver 类

**命名空间：** `Core\Storage\Drivers\S3Driver`
**实现：** `StorageInterface`

### 构造方法

```php
public function __construct(array $config)
```
- **参数：** `$config` - S3配置数组

### S3 特有方法

```php
public function putFile(string $path, \SplFileInfo $file, array $options = []): bool
```
- **参数：**
  - `$path` - 存储路径
  - `$file` - 文件对象
  - `$options` - 上传选项（可选）
- **返回：** `bool` - 是否上传成功

```php
public function putFileAs(string $path, \SplFileInfo $file, string $name, array $options = []): bool
```
- **参数：**
  - `$path` - 存储目录
  - `$file` - 文件对象
  - `$name` - 文件名
  - `$options` - 上传选项（可选）
- **返回：** `bool` - 是否上传成功

```php
public function getPresignedUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
```
- **参数：**
  - `$path` - 文件路径
  - `$expiration` - 过期时间
  - `$options` - URL选项（可选）
- **返回：** `string` - 预签名URL

## StorageException 异常

**命名空间：** `Core\Storage\Exceptions\StorageException`
**继承：** `\Exception`

### 异常类型

| 异常代码 | 说明 |
|----------|------|
| `1001` | 文件不存在 |
| `1002` | 文件读取失败 |
| `1003` | 文件写入失败 |
| `1004` | 目录创建失败 |
| `1005` | 权限不足 |
| `1006` | 磁盘空间不足 |
| `1007` | 网络连接失败 |
| `1008` | 认证失败 |

## 存储配置结构

配置文件：`config/storage.toml`

### 本地存储配置

```toml
[local]
driver = "local"
root = "storage/app"
url = "/storage"
permissions = [
    file = 0644
    dir = 0755
]
```

### S3 存储配置

```toml
[s3]
driver = "s3"
key = "your-access-key"
secret = "your-secret-key"
region = "us-east-1"
bucket = "your-bucket-name"
url = "https://your-bucket.s3.amazonaws.com"
endpoint = ""
use_path_style_endpoint = false
```

### 阿里云 OSS 配置

```toml
[oss]
driver = "s3"
key = "your-access-key"
secret = "your-secret-key"
region = "oss-cn-hangzhou"
bucket = "your-bucket-name"
url = "https://your-bucket.oss-cn-hangzhou.aliyuncs.com"
endpoint = "https://oss-cn-hangzhou.aliyuncs.com"
use_path_style_endpoint = false
```

### 腾讯云 COS 配置

```toml
[cos]
driver = "s3"
key = "your-secret-id"
secret = "your-secret-key"
region = "ap-beijing"
bucket = "your-bucket-name"
url = "https://your-bucket.cos.ap-beijing.myqcloud.com"
endpoint = "https://cos.ap-beijing.myqcloud.com"
use_path_style_endpoint = false
```

## 支持的驱动类型

| 驱动类型 | 说明 | 必需配置 |
|----------|------|----------|
| `local` | 本地文件系统 | `root` - 根目录路径 |
| `s3` | Amazon S3 | `key`, `secret`, `region`, `bucket` |

## 文件操作选项

### 通用选项

| 选项 | 类型 | 说明 |
|------|------|------|
| `visibility` | `string` | 文件可见性（public/private） |
| `mimetype` | `string` | MIME 类型 |
| `metadata` | `array` | 文件元数据 |

### S3 特有选项

| 选项 | 类型 | 说明 |
|------|------|------|
| `CacheControl` | `string` | 缓存控制头 |
| `ContentDisposition` | `string` | 内容处置头 |
| `ContentEncoding` | `string` | 内容编码头 |
| `ContentLanguage` | `string` | 内容语言头 |
| `ContentType` | `string` | 内容类型头 |
| `Expires` | `string` | 过期时间 |
| `StorageClass` | `string` | 存储类别 |
| `ServerSideEncryption` | `string` | 服务端加密 |

## 文件可见性常量

| 常量 | 值 | 说明 |
|------|----|----- |
| `VISIBILITY_PUBLIC` | `public` | 公开可访问 |
| `VISIBILITY_PRIVATE` | `private` | 私有访问 |

## MIME 类型检测

### 支持的文件类型

| 扩展名 | MIME 类型 |
|--------|-----------|
| `.jpg`, `.jpeg` | `image/jpeg` |
| `.png` | `image/png` |
| `.gif` | `image/gif` |
| `.pdf` | `application/pdf` |
| `.txt` | `text/plain` |
| `.html` | `text/html` |
| `.css` | `text/css` |
| `.js` | `application/javascript` |
| `.json` | `application/json` |
| `.xml` | `application/xml` |

## 路径规范

### 路径格式

- 使用正斜杠 `/` 作为路径分隔符
- 不以斜杠开头：`path/to/file.txt`
- 目录路径不以斜杠结尾：`path/to/directory`

### 特殊字符处理

| 字符 | 编码后 | 说明 |
|------|--------|------|
| 空格 | `%20` | URL 编码 |
| 中文 | UTF-8编码 | 支持中文文件名 |
| 特殊符号 | 相应编码 | 根据存储驱动处理 |