<?php

declare(strict_types=1);

namespace Core\Storage\Drivers;

use Core\Storage\Contracts\StorageInterface;
use Core\Storage\Exceptions\StorageException;
use Nette\Utils\FileSystem;

class LocalDriver implements StorageInterface
{
    private string $root;
    private string $domain;
    private string $path;
    private $signCallback;

    public function __construct(array $config, ?callable $signCallback = null)
    {
        $this->root = $config['root'] ?? '';
        $this->domain = $config['domain'] ?? '';
        $this->path = $config['path'] ?? '';
        $this->signCallback = $signCallback;
    }

    public function write(string $path, string $contents, array $options = []): bool
    {
        $fullPath = $this->root . '/' . $this->getUploadPath($path);

        try {
            FileSystem::write($fullPath, $contents);
        } catch (\Throwable $e) {
            throw new StorageException("Failed to write file: {$path}");
        }
        return true;
    }

    public function writeStream(string $path, $resource, array $options = []): bool
    {
        $fullPath = $this->root . '/' . $this->getUploadPath($path);
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new StorageException("Failed to create directory: {$dir}");
            }
        }

        $dest = fopen($fullPath, 'wb');
        if ($dest === false) {
            throw new StorageException("Failed to create file: {$path}");
        }

        try {
            if (stream_copy_to_stream($resource, $dest) === false) {
                throw new StorageException("Failed to write stream: {$path}");
            }
        } finally {
            fclose($dest);
        }

        return true;
    }

    public function read(string $path): string
    {
        $fullPath = $this->root . '/' . $this->getUploadPath($path);
        if (!is_readable($fullPath)) {
            throw new StorageException("File not readable or not found: {$path}");
        }

        $contents = file_get_contents($fullPath);
        if ($contents === false) {
            throw new StorageException("Failed to read file: {$path}");
        }
        return $contents;
    }

    public function readStream(string $path)
    {
        $fullPath = $this->root . '/' . $this->getUploadPath($path);
        if (!is_readable($fullPath)) {
            throw new StorageException("File not readable or not found: {$path}");
        }

        try {
            $stream = fopen($fullPath, 'rb');
        } catch (\RuntimeException $e) {
            throw new StorageException("Failed to open stream: {$path}");
        }

        if ($stream === false) {
            throw new StorageException("Failed to open stream: {$path}");
        }

        return $stream;
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->root . '/' . $this->getUploadPath($path);
        if (!file_exists($fullPath)) {
            return true;
        }
        if (!is_writable($fullPath)) {
            throw new StorageException("Permission denied: {$path}");
        }
        if (!unlink($fullPath)) {
            throw new StorageException("Failed to delete file: {$path}");
        }
        return true;
    }

    public function size(string $path): int
    {
        $fullPath = $this->root . '/' . $this->getUploadPath($path);
        if (!file_exists($fullPath)) {
            throw new StorageException("File not found: {$path}");
        }

        $size = filesize($fullPath);
        if ($size === false) {
            throw new StorageException("Failed to get file size: {$path}");
        }
        return $size;
    }

    public function exists(string $path): bool
    {
        $fullPath = $this->root . '/' . $this->getUploadPath($path);
        return file_exists($fullPath);
    }

    public function publicUrl(string $path): string
    {
        $domain = rtrim($this->domain, '/');
        if ($this->path) {
            $domain = sprintf('%s/%s', $this->domain, $this->path);
        }
        return sprintf('%s/%s', $domain, $path);
    }

    public function privateUrl(string $path, int $expires = 3600): string
    {
        return $this->publicUrl($path);
    }

    public function signPostUrl(string $path): array
    {
        $url = $this->getUploadPath($path);
        $sign = $this->getSign($url);

        return [
            'url' => $url,
            'params' => [
                'sign' => $sign,
                'key' => $path,
            ]
        ];
    }

    public function signPutUrl(string $path): string
    {
        $url = $this->getUploadPath($path);
        $sign = $this->getSign($url);

        $params = http_build_query([
            'sign' => $sign,
            'key' => $path,
        ]);

        return str_contains($url, '?')
            ? $url . '&' . $params
            : $url . '?' . $params;
    }

    private function getUploadPath(string $path): string
    {
        if ($this->path) {
            $path = sprintf('%s/%s', $this->path, $path);
        }
        return $path;
    }

    private function getSign(string $path): string
    {
        if ($this->signCallback) {
            return call_user_func($this->signCallback, $path);
        }
        return '';
    }

    public function isLocal(): bool
    {
        return true;
    }
}
