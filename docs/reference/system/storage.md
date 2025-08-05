# 文件存储

DuxLite 提供统一的文件存储接口，支持本地存储和 S3 兼容存储（如 AWS S3、阿里云 OSS、腾讯云 COS 等）。

## 核心组件

- **Storage**：存储管理器 (`Core\Storage\Storage`)
- **StorageInterface**：标准化的存储操作接口
- **LocalDriver**：本地文件存储驱动
- **S3Driver**：S3 兼容存储驱动

## 配置系统

### 本地存储配置

```toml
[drivers.local]
type = "local"
# 存储根目录（相对于项目根目录）
root = "data/uploads"
# 公共访问域名
domain = "http://localhost:8000"
# URL 路径前缀
path = "uploads"
```

### S3 兼容存储配置

```toml
# Amazon S3 配置
[drivers.s3]
type = "s3"
bucket = "my-bucket"
domain = "https://cdn.example.com"
endpoint = "s3.amazonaws.com"
region = "us-east-1"
ssl = true
version = "latest"
access_key = "your-access-key"
secret_key = "your-secret-key"
immutable = false

# 阿里云 OSS 配置示例
[drivers.oss]
type = "s3"
bucket = "my-oss-bucket"
domain = "https://my-oss-bucket.oss-cn-hangzhou.aliyuncs.com"
endpoint = "oss-cn-hangzhou.aliyuncs.com"
region = "oss-cn-hangzhou"
ssl = true
version = "latest"
access_key = "your-access-key-id"
secret_key = "your-access-key-secret"
```

### 默认存储配置

```toml
# 默认存储类型
type = "local"
```

## 基础文件操作

### Storage 类

```php
use Core\Storage\Storage;

// 创建存储实例
$storage = new Storage('local', $config);

// 获取存储驱动接口
$driver = $storage->getInstance();
```

### StorageInterface 接口方法

```php
// 文件写入
// $path: 文件路径, $contents: 文件内容, $options: 额外选项
$storage->write(string $path, string $contents, array $options = []): bool

// 文件流写入
// $path: 文件路径, $resource: 文件资源流, $options: 额外选项
$storage->writeStream(string $path, $resource, array $options = []): bool

// 文件读取
// $path: 文件路径，返回文件内容字符串
$storage->read(string $path): string

// 文件流读取
// $path: 文件路径，返回文件资源流
$storage->readStream(string $path)

// 文件删除
// $path: 文件路径
$storage->delete(string $path): bool

// 文件是否存在
// $path: 文件路径
$storage->exists(string $path): bool

// 获取文件大小
// $path: 文件路径，返回文件大小（字节）
$storage->size(string $path): int

// 获取公共访问URL
// $path: 文件路径，返回可直接访问的URL
$storage->publicUrl(string $path): string

// 获取私有访问URL（带过期时间）
// $path: 文件路径, $expires: 过期时间（秒，默认3600）
$storage->privateUrl(string $path, int $expires = 3600): string

// 获取POST方式签名上传信息
// $path: 文件路径，返回包含url和params的数组
$storage->signPostUrl(string $path): array

// 获取PUT方式签名上传URL
// $path: 文件路径，返回签名上传URL
$storage->signPutUrl(string $path): string

// 判断是否为本地存储
$storage->isLocal(): bool
```

DuxLite 存储系统提供统一接口，支持本地和云存储，满足各种文件管理需求。