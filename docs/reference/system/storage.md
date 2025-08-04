# 文件存储

DuxLite 提供了统一的文件存储接口，支持本地存储和 S3 兼容存储（如 AWS S3、阿里云 OSS、腾讯云 COS 等）。存储系统提供了文件读写、URL 生成、签名上传等功能，可以轻松应对各种文件管理需求。

## 基本概念

### 存储架构

DuxLite 的存储系统采用统一接口、多驱动架构：

```
应用层 → StorageInterface → 存储驱动 → 存储后端（本地/S3）
```

### 核心组件

- **Storage**：存储管理器，统一存储接口
- **LocalDriver**：本地文件存储驱动
- **S3Driver**：S3 兼容存储驱动
- **StorageInterface**：标准化的存储操作接口

## 配置系统

### 配置文件设置

存储配置在 `config/storage.toml` 文件中：

#### 本地存储配置

```toml
# 本地存储驱动配置
[drivers.local]
type = "local"
# 存储根目录（相对于项目根目录）
root = "data/uploads"
# 公共访问域名
domain = "http://localhost:8000"
# URL 路径前缀
path = "uploads"
# 文件权限设置
permissions = [
    file = 0644
    dir = 0755
]
```

#### S3 兼容存储配置

```toml
# Amazon S3 配置
[drivers.s3]
type = "s3"
# S3 存储桶名称
bucket = "my-bucket"
# 自定义访问域名（可选）
domain = "https://cdn.example.com"
# API 端点
endpoint = "s3.amazonaws.com"
# 区域
region = "us-east-1"
# 是否使用 SSL
ssl = true
# API 版本
version = "latest"
# 访问密钥
access_key = "your-access-key"
# 密钥
secret_key = "your-secret-key"
# 是否为不可变存储
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

# 腾讯云 COS 配置示例
[drivers.cos]
type = "s3"
bucket = "my-cos-bucket"
domain = "https://my-cos-bucket.cos.ap-beijing.myqcloud.com"
endpoint = "cos.ap-beijing.myqcloud.com"
region = "ap-beijing"
ssl = true
version = "latest"
access_key = "your-secret-id"
secret_key = "your-secret-key"
```

#### 默认存储配置

```toml
# 默认存储类型
type = "local"
```

### 获取存储实例

```php
use Core\App;

// 获取默认存储（从 storage.toml 读取类型）
$storage = App::storage();

// 获取指定类型的存储
$localStorage = App::storage('local');
$s3Storage = App::storage('s3');
$ossStorage = App::storage('oss');
```

## 基础文件操作

### 文件写入

```php
use Core\App;

class FileService
{
    private $storage;

    public function __construct()
    {
        $this->storage = App::storage();
    }

    public function saveFile(string $path, string $content): bool
    {
        // 写入文件内容
        return $this->storage->write($path, $content);
    }

    public function saveFileFromUpload($uploadedFile, string $directory = 'uploads'): string
    {
        // 生成唯一文件名
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $path = $directory . '/' . date('Y/m/d') . '/' . $filename;

        // 获取文件流
        $stream = $uploadedFile->getStream();

        // 写入存储
        $this->storage->writeStream($path, $stream->detach());

        return $path;
    }

    public function saveFromStream(string $path, $resource): bool
    {
        // 从资源流写入文件
        return $this->storage->writeStream($path, $resource);
    }
}
```

### 文件读取

```php
class FileService
{
    public function getFileContent(string $path): string
    {
        // 读取文件内容
        return $this->storage->read($path);
    }

    public function getFileStream(string $path)
    {
        // 获取文件流
        return $this->storage->readStream($path);
    }

    public function downloadFile(string $path, ResponseInterface $response): ResponseInterface
    {
        if (!$this->storage->exists($path)) {
            throw new ExceptionNotFound('文件不存在');
        }

        // 获取文件流
        $stream = $this->storage->readStream($path);

        // 获取文件信息
        $size = $this->storage->size($path);
        $filename = basename($path);

        // 创建流响应
        $body = new \Slim\Psr7\Stream($stream);

        return $response
            ->withBody($body)
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Length', (string)$size)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
```

### 文件信息

```php
class FileService
{
    public function getFileInfo(string $path): array
    {
        if (!$this->storage->exists($path)) {
            throw new ExceptionNotFound('文件不存在');
        }

        return [
            'path' => $path,
            'size' => $this->storage->size($path),
            'exists' => true,
            'public_url' => $this->storage->publicUrl($path),
            'is_local' => $this->storage->isLocal(),
            'last_modified' => $this->storage->lastModified($path),
            'mime_type' => $this->storage->mimeType($path)
        ];
    }

    public function deleteFile(string $path): bool
    {
        return $this->storage->delete($path);
    }

    public function fileExists(string $path): bool
    {
        return $this->storage->exists($path);
    }

    public function copyFile(string $from, string $to): bool
    {
        return $this->storage->copy($from, $to);
    }

    public function moveFile(string $from, string $to): bool
    {
        return $this->storage->move($from, $to);
    }
}
```

## 文件上传处理

### 表单文件上传

```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class UploadController
{
    private $storage;

    public function __construct()
    {
        $this->storage = App::storage();
    }

    public function uploadAvatar(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $uploadedFiles = $request->getUploadedFiles();

        if (!isset($uploadedFiles['avatar'])) {
            throw new ExceptionBusiness('请选择头像文件', 400);
        }

        $uploadedFile = $uploadedFiles['avatar'];

        // 验证上传错误
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new ExceptionBusiness('文件上传失败', 400);
        }

        // 验证文件类型
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $mediaType = $uploadedFile->getClientMediaType();

        if (!in_array($mediaType, $allowedTypes)) {
            throw new ExceptionBusiness('不支持的文件类型', 400);
        }

        // 验证文件大小（5MB）
        $maxSize = 5 * 1024 * 1024;
        if ($uploadedFile->getSize() > $maxSize) {
            throw new ExceptionBusiness('文件大小超过限制', 400);
        }

        // 生成存储路径
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = uniqid('avatar_') . '.' . $extension;
        $path = 'avatars/' . date('Y/m') . '/' . $filename;

        // 保存文件
        $stream = $uploadedFile->getStream();
        $this->storage->writeStream($path, $stream->detach());

        // 返回文件信息
        return send($response, '上传成功', [
            'path' => $path,
            'url' => $this->storage->publicUrl($path),
            'size' => $uploadedFile->getSize(),
            'original_name' => $uploadedFile->getClientFilename()
        ]);
    }

    public function uploadMultiple(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $uploadedFiles = $request->getUploadedFiles();

        if (!isset($uploadedFiles['documents']) || !is_array($uploadedFiles['documents'])) {
            throw new ExceptionBusiness('请选择文件', 400);
        }

        $results = [];

        foreach ($uploadedFiles['documents'] as $uploadedFile) {
            if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
                // 验证和保存文件
                $path = $this->saveUploadedFile($uploadedFile, 'documents');

                $results[] = [
                    'path' => $path,
                    'url' => $this->storage->publicUrl($path),
                    'size' => $uploadedFile->getSize(),
                    'original_name' => $uploadedFile->getClientFilename()
                ];
            }
        }

        return send($response, '批量上传成功', $results);
    }

    private function saveUploadedFile($uploadedFile, string $directory): string
    {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $path = $directory . '/' . date('Y/m/d') . '/' . $filename;

        $stream = $uploadedFile->getStream();
        $this->storage->writeStream($path, $stream->detach());

        return $path;
    }
}
```

### 图片处理上传

```php
class ImageUploadService
{
    private $storage;
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct()
    {
        $this->storage = App::storage();
    }

    public function uploadAndResize($uploadedFile, int $maxWidth = 800, int $maxHeight = 600): array
    {
        // 验证图片
        $this->validateImage($uploadedFile);

        // 创建图片资源
        $imageData = $uploadedFile->getStream()->getContents();
        $image = imagecreatefromstring($imageData);

        if (!$image) {
            throw new ExceptionBusiness('无法处理图片文件', 400);
        }

        // 获取原始尺寸
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        // 计算新尺寸
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);

        // 创建新图片
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // 保持透明度（PNG/GIF）
        if (in_array($uploadedFile->getClientMediaType(), ['image/png', 'image/gif'])) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
        }

        // 缩放图片
        imagecopyresampled(
            $resizedImage, $image,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );

        // 生成存储路径
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = uniqid('img_') . '.' . $extension;
        $path = 'images/' . date('Y/m') . '/' . $filename;

        // 输出到缓冲区
        ob_start();
        switch ($uploadedFile->getClientMediaType()) {
            case 'image/jpeg':
                imagejpeg($resizedImage, null, 90);
                break;
            case 'image/png':
                imagepng($resizedImage);
                break;
            case 'image/gif':
                imagegif($resizedImage);
                break;
            case 'image/webp':
                imagewebp($resizedImage, null, 90);
                break;
        }
        $processedImageData = ob_get_clean();

        // 清理资源
        imagedestroy($image);
        imagedestroy($resizedImage);

        // 保存处理后的图片
        $this->storage->write($path, $processedImageData);

        return [
            'path' => $path,
            'url' => $this->storage->publicUrl($path),
            'width' => $newWidth,
            'height' => $newHeight,
            'size' => strlen($processedImageData),
            'original_size' => $uploadedFile->getSize()
        ];
    }

    public function generateThumbnails($uploadedFile, array $sizes = []): array
    {
        if (empty($sizes)) {
            $sizes = [
                'thumb' => [150, 150],
                'medium' => [300, 300],
                'large' => [800, 600]
            ];
        }

        $this->validateImage($uploadedFile);

        $imageData = $uploadedFile->getStream()->getContents();
        $image = imagecreatefromstring($imageData);

        $results = [];

        foreach ($sizes as $sizeKey => [$width, $height]) {
            $thumbnailData = $this->resizeImage($image, $width, $height, $uploadedFile->getClientMediaType());

            $filename = uniqid($sizeKey . '_') . '.jpg';
            $path = 'thumbnails/' . date('Y/m') . '/' . $filename;

            $this->storage->write($path, $thumbnailData);

            $results[$sizeKey] = [
                'path' => $path,
                'url' => $this->storage->publicUrl($path),
                'width' => $width,
                'height' => $height
            ];
        }

        imagedestroy($image);

        return $results;
    }

    private function validateImage($uploadedFile): void
    {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new ExceptionBusiness('文件上传失败', 400);
        }

        if (!in_array($uploadedFile->getClientMediaType(), $this->allowedTypes)) {
            throw new ExceptionBusiness('不支持的图片格式', 400);
        }

        if ($uploadedFile->getSize() > 10 * 1024 * 1024) { // 10MB
            throw new ExceptionBusiness('图片文件过大', 400);
        }
    }

    private function resizeImage($image, int $width, int $height, string $mimeType): string
    {
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        $ratio = min($width / $originalWidth, $height / $originalHeight);
        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);

        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled(
            $resizedImage, $image,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );

        ob_start();
        imagejpeg($resizedImage, null, 85);
        $imageData = ob_get_clean();

        imagedestroy($resizedImage);

        return $imageData;
    }
}
```

## URL 生成和访问

### 公共 URL

```php
class FileUrlService
{
    private $storage;

    public function __construct()
    {
        $this->storage = App::storage();
    }

    public function getPublicUrl(string $path): string
    {
        // 获取公共访问 URL
        return $this->storage->publicUrl($path);
    }

    public function getImageUrl(string $path, array $params = []): string
    {
        $url = $this->storage->publicUrl($path);

        // 添加图片处理参数（如果支持）
        if (!empty($params)) {
            $queryString = http_build_query($params);
            $separator = strpos($url, '?') !== false ? '&' : '?';
            $url .= $separator . $queryString;
        }

        return $url;
    }

    public function generateImageUrls(string $basePath): array
    {
        $baseUrl = $this->storage->publicUrl($basePath);

        return [
            'original' => $baseUrl,
            'thumb' => $this->getImageUrl($basePath, ['w' => 150, 'h' => 150]),
            'medium' => $this->getImageUrl($basePath, ['w' => 300, 'h' => 300]),
            'large' => $this->getImageUrl($basePath, ['w' => 800, 'h' => 600])
        ];
    }
}
```

### 私有 URL 和签名访问

```php
class SecureFileService
{
    private $storage;

    public function __construct()
    {
        $this->storage = App::storage();
    }

    public function getPrivateUrl(string $path, int $expires = 3600): string
    {
        // 生成有时效的私有访问 URL
        return $this->storage->privateUrl($path, $expires);
    }

    public function generateDownloadLink(string $path, string $filename = null): string
    {
        $filename = $filename ?: basename($path);

        // 生成 1 小时有效的下载链接
        $url = $this->storage->privateUrl($path, 3600);

        // 添加下载文件名参数
        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . 'response-content-disposition=attachment;filename=' . urlencode($filename);
    }

    public function getSignedUploadUrl(string $path): array
    {
        // 获取签名上传 URL（用于前端直传）
        return $this->storage->signPostUrl($path);
    }

    public function getSignedPutUrl(string $path): string
    {
        // 获取 PUT 方式的签名上传 URL
        return $this->storage->signPutUrl($path);
    }
}
```

## 前端直传

### POST 方式直传

```php
class DirectUploadController
{
    public function getUploadToken(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $storage = App::storage();

        // 生成上传路径
        $userId = $request->getAttribute('user_id');
        $path = 'uploads/' . $userId . '/' . uniqid() . '.tmp';

        // 获取签名上传信息
        $uploadInfo = $storage->signPostUrl($path);

        return send($response, '获取上传令牌成功', [
            'upload_url' => $uploadInfo['url'],
            'form_data' => $uploadInfo['params'],
            'path' => $path,
            'expires_in' => 3600
        ]);
    }

    public function confirmUpload(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = $request->getParsedBody();
        $path = $data['path'] ?? '';

        if (!$path) {
            throw new ExceptionBusiness('缺少文件路径', 400);
        }

        $storage = App::storage();

        // 验证文件是否上传成功
        if (!$storage->exists($path)) {
            throw new ExceptionBusiness('文件上传失败', 400);
        }

        // 移动到正式目录
        $content = $storage->read($path);
        $storage->delete($path); // 删除临时文件

        $finalPath = str_replace('.tmp', '.dat', $path);
        $storage->write($finalPath, $content);

        return send($response, '确认上传成功', [
            'path' => $finalPath,
            'url' => $storage->publicUrl($finalPath),
            'size' => strlen($content)
        ]);
    }
}
```

### PUT 方式直传

```php
class PutUploadController
{
    public function getPutUploadUrl(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $storage = App::storage();

        $data = $request->getParsedBody();
        $filename = $data['filename'] ?? 'upload';
        $contentType = $data['content_type'] ?? 'application/octet-stream';

        // 生成上传路径
        $path = 'uploads/' . date('Y/m/d') . '/' . uniqid() . '_' . $filename;

        // 获取 PUT 上传 URL
        $uploadUrl = $storage->signPutUrl($path);

        return send($response, '获取上传URL成功', [
            'upload_url' => $uploadUrl,
            'path' => $path,
            'method' => 'PUT',
            'headers' => [
                'Content-Type' => $contentType
            ]
        ]);
    }
}
```

## 文件管理功能

### 目录操作

```php
class DirectoryService
{
    private $storage;

    public function __construct()
    {
        $this->storage = App::storage();
    }

    public function listFiles(string $directory = '', bool $recursive = false): array
    {
        return $this->storage->files($directory, $recursive);
    }

    public function listDirectories(string $directory = '', bool $recursive = false): array
    {
        return $this->storage->directories($directory, $recursive);
    }

    public function makeDirectory(string $path): bool
    {
        return $this->storage->makeDirectory($path);
    }

    public function deleteDirectory(string $directory): bool
    {
        return $this->storage->deleteDirectory($directory);
    }
}
```

### 文件分类管理

```php
class FileManagerService
{
    private $storage;

    public function __construct()
    {
        $this->storage = App::storage();
    }

    public function createFileCategory(string $category): bool
    {
        $categoryPath = 'categories/' . $category . '/.gitkeep';
        return $this->storage->write($categoryPath, '');
    }

    public function moveFileToCategory(string $filePath, string $category): string
    {
        if (!$this->storage->exists($filePath)) {
            throw new ExceptionNotFound('文件不存在');
        }

        // 读取原文件
        $content = $this->storage->read($filePath);

        // 生成新路径
        $filename = basename($filePath);
        $newPath = 'categories/' . $category . '/' . $filename;

        // 写入新位置
        $this->storage->write($newPath, $content);

        // 删除原文件
        $this->storage->delete($filePath);

        return $newPath;
    }

    public function copyFile(string $sourcePath, string $destinationPath): bool
    {
        if (!$this->storage->exists($sourcePath)) {
            throw new ExceptionNotFound('源文件不存在');
        }

        return $this->storage->copy($sourcePath, $destinationPath);
    }

    public function getFilesByPattern(string $pattern): array
    {
        // 这个功能需要根据具体存储实现
        // 本地存储可以使用 glob，云存储需要使用 API 搜索
        if ($this->storage->isLocal()) {
            return $this->searchLocalFiles($pattern);
        } else {
            return $this->searchCloudFiles($pattern);
        }
    }

    private function searchLocalFiles(string $pattern): array
    {
        // 本地文件搜索实现
        return [];
    }

    private function searchCloudFiles(string $pattern): array
    {
        // 云存储文件搜索实现
        return [];
    }
}
```

### 文件清理和维护

```php
use Core\Scheduler\Attribute\Scheduler;

class FileMaintenanceTasks
{
    private $storage;

    public function __construct()
    {
        $this->storage = App::storage();
    }

    #[Scheduler('0 2 * * *')]  // 每天凌晨2点
    public function cleanTempFiles(): void
    {
        $tempPath = 'temp/';
        $cutoffTime = time() - (24 * 60 * 60); // 24小时前

        // 这里需要根据存储类型实现文件清理
        $this->cleanFilesByAge($tempPath, $cutoffTime);

        error_log("临时文件清理完成: " . date('Y-m-d H:i:s'));
    }

    #[Scheduler('0 3 * * 0')]  // 每周日凌晨3点
    public function optimizeStorage(): void
    {
        // 清理孤儿文件（数据库中不存在记录的文件）
        $this->cleanOrphanFiles();

        // 压缩旧文件
        $this->compressOldFiles();

        error_log("存储优化完成: " . date('Y-m-d H:i:s'));
    }

    private function cleanFilesByAge(string $path, int $cutoffTime): void
    {
        // 根据存储类型实现文件清理逻辑
        if ($this->storage->isLocal()) {
            $this->cleanLocalFilesByAge($path, $cutoffTime);
        } else {
            $this->cleanCloudFilesByAge($path, $cutoffTime);
        }
    }

    private function cleanLocalFilesByAge(string $path, int $cutoffTime): void
    {
        // 本地文件清理实现
        $fullPath = $this->getLocalStoragePath() . '/' . $path;

        if (!is_dir($fullPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoffTime) {
                unlink($file->getPathname());
            }
        }
    }

    private function cleanCloudFilesByAge(string $path, int $cutoffTime): void
    {
        // 云存储文件清理实现
        // 需要使用云存储 API 查询和删除文件
    }

    private function cleanOrphanFiles(): void
    {
        // 获取数据库中的文件记录
        $dbFiles = DB::table('files')->pluck('path')->toArray();

        // 与存储中的文件对比，删除孤儿文件
        // 具体实现需要根据存储类型和业务需求
    }

    private function compressOldFiles(): void
    {
        // 压缩30天前的文件
        $cutoffTime = time() - (30 * 24 * 60 * 60);

        // 实现文件压缩逻辑
    }

    private function getLocalStoragePath(): string
    {
        // 获取本地存储路径
        return base_path('data/uploads');
    }
}
```

## 与其他系统集成

### 与数据库模型集成

```php
trait HasFiles
{
    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function addFile(string $path, array $metadata = []): File
    {
        $storage = App::storage();

        if (!$storage->exists($path)) {
            throw new ExceptionNotFound('文件不存在');
        }

        return $this->files()->create([
            'path' => $path,
            'filename' => basename($path),
            'size' => $storage->size($path),
            'url' => $storage->publicUrl($path),
            'metadata' => $metadata
        ]);
    }

    public function getFileUrl(string $type = 'default'): ?string
    {
        $file = $this->files()->where('type', $type)->first();
        return $file ? $file->url : null;
    }

    public function deleteFiles(): void
    {
        $storage = App::storage();

        foreach ($this->files as $file) {
            if ($storage->exists($file->path)) {
                $storage->delete($file->path);
            }
            $file->delete();
        }
    }
}

class File extends Model
{
    protected $fillable = [
        'fileable_type',
        'fileable_id',
        'path',
        'filename',
        'size',
        'url',
        'type',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function fileable()
    {
        return $this->morphTo();
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}

// 使用示例
class User extends Model
{
    use HasFiles;

    public function setAvatar($uploadedFile): void
    {
        $service = new ImageUploadService();
        $result = $service->uploadAndResize($uploadedFile, 300, 300);

        // 删除旧头像
        $this->deleteFiles();

        // 添加新头像
        $this->addFile($result['path'], [
            'type' => 'avatar',
            'width' => $result['width'],
            'height' => $result['height']
        ]);
    }
}
```

### 与缓存系统集成

```php
class CachedFileService
{
    private $storage;
    private $cache;

    public function __construct()
    {
        $this->storage = App::storage();
        $this->cache = App::cache();
    }

    public function getCachedFileContent(string $path): string
    {
        $cacheKey = 'file_content:' . md5($path);

        $content = $this->cache->get($cacheKey);

        if ($content === null) {
            $content = $this->storage->read($path);
            $this->cache->set($cacheKey, $content, 3600); // 缓存1小时
        }

        return $content;
    }

    public function getCachedFileInfo(string $path): array
    {
        $cacheKey = 'file_info:' . md5($path);

        $info = $this->cache->get($cacheKey);

        if ($info === null) {
            $info = [
                'exists' => $this->storage->exists($path),
                'size' => $this->storage->exists($path) ? $this->storage->size($path) : 0,
                'url' => $this->storage->publicUrl($path)
            ];

            $this->cache->set($cacheKey, $info, 1800); // 缓存30分钟
        }

        return $info;
    }

    public function invalidateFileCache(string $path): void
    {
        $contentKey = 'file_content:' . md5($path);
        $infoKey = 'file_info:' . md5($path);

        $this->cache->deleteMultiple([$contentKey, $infoKey]);
    }
}
```

## API 参考

### Storage 类

**命名空间：** `Core\Storage\Storage`

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

### StorageInterface 接口

**命名空间：** `Core\Storage\Contracts\StorageInterface`

#### 文件操作方法

```php
public function exists(string $path): bool
```
- **参数：** `$path` - 文件路径
- **返回：** `bool` - 文件是否存在

```php
public function read(string $path): string
```
- **参数：** `$path` - 文件路径
- **返回：** `string` - 文件内容
- **异常：** `StorageException` - 文件不存在或读取失败

```php
public function write(string $path, string $contents, array $options = []): bool
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

#### 目录操作方法

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

#### URL 生成方法

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

## 最佳实践

### 文件命名策略

```php
class FileNamingService
{
    public function generateSecurePath(string $originalFilename, string $category = 'uploads'): string
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        // 生成安全的文件名
        $filename = uniqid() . '_' . time() . '.' . $extension;

        // 按日期分目录
        $datePath = date('Y/m/d');

        return $category . '/' . $datePath . '/' . $filename;
    }

    public function generateContentHashPath(string $content, string $extension): string
    {
        $hash = hash('sha256', $content);

        // 使用内容哈希避免重复存储
        return 'content/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash . '.' . $extension;
    }

    public function generateUserPath(int $userId, string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($filename, PATHINFO_FILENAME));

        return 'users/' . $userId . '/' . $safeFilename . '.' . $extension;
    }
}
```

### 文件安全检查

```php
class FileSecurityService
{
    private array $allowedExtensions = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
        'archive' => ['zip', 'rar', '7z'],
        'text' => ['txt', 'csv']
    ];

    private array $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml',
        'exe', 'com', 'bat', 'cmd',
        'js', 'vbs', 'jar'
    ];

    public function validateFile($uploadedFile, string $category = 'general'): void
    {
        // 检查上传错误
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new ExceptionBusiness('文件上传失败', 400);
        }

        // 检查文件大小
        $maxSize = $this->getMaxSizeForCategory($category);
        if ($uploadedFile->getSize() > $maxSize) {
            throw new ExceptionBusiness('文件大小超过限制', 400);
        }

        // 检查文件扩展名
        $filename = $uploadedFile->getClientFilename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($extension, $this->dangerousExtensions)) {
            throw new ExceptionBusiness('不允许上传此类型文件', 400);
        }

        if (isset($this->allowedExtensions[$category])) {
            if (!in_array($extension, $this->allowedExtensions[$category])) {
                throw new ExceptionBusiness('不支持的文件类型', 400);
            }
        }

        // 检查 MIME 类型
        $this->validateMimeType($uploadedFile, $extension);

        // 检查文件内容
        $this->scanFileContent($uploadedFile);
    }

    private function validateMimeType($uploadedFile, string $extension): void
    {
        $mimeType = $uploadedFile->getClientMediaType();
        $expectedMimes = $this->getExpectedMimeTypes($extension);

        if (!empty($expectedMimes) && !in_array($mimeType, $expectedMimes)) {
            throw new ExceptionBusiness('文件类型与扩展名不匹配', 400);
        }
    }

    private function scanFileContent($uploadedFile): void
    {
        $content = $uploadedFile->getStream()->getContents();

        // 检查是否包含恶意代码特征
        $patterns = [
            '/<\?php/',
            '/<script/',
            '/eval\s*\(/',
            '/exec\s*\(/',
            '/system\s*\(/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new ExceptionBusiness('文件包含恶意内容', 400);
            }
        }
    }

    private function getMaxSizeForCategory(string $category): int
    {
        return match ($category) {
            'image' => 10 * 1024 * 1024,      // 10MB
            'document' => 50 * 1024 * 1024,   // 50MB
            'archive' => 100 * 1024 * 1024,   // 100MB
            default => 20 * 1024 * 1024       // 20MB
        };
    }

    private function getExpectedMimeTypes(string $extension): array
    {
        return match ($extension) {
            'jpg', 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'pdf' => ['application/pdf'],
            'zip' => ['application/zip'],
            default => []
        };
    }
}
```

## 故障排除

### 常见问题诊断

#### 1. 存储连接问题

```bash
# 测试本地存储
ls -la data/uploads/

# 测试 S3 存储连接
aws s3 ls s3://your-bucket/ --region your-region

# 测试存储配置
php -r "
$storage = \Core\App::storage();
var_dump($storage->isLocal());
"
```

#### 2. 文件上传问题

```php
// 检查 PHP 上传配置
function checkUploadConfig(): array
{
    return [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit')
    ];
}
```

#### 3. 权限问题

```bash
# 检查本地存储目录权限
ls -la data/uploads/
chmod -R 755 data/uploads/
chown -R www-data:www-data data/uploads/
```

DuxLite 的文件存储系统为应用程序提供了强大的文件管理能力，支持多种存储后端，可以满足从简单文件上传到复杂文件处理的各种需求。