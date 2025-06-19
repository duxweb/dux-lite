<?php

declare(strict_types=1);

namespace Core\Storage\Drivers;

use Aws\S3\S3Client;
use Core\Storage\Contracts\StorageInterface;
use Core\Storage\Exceptions\StorageException;

class S3Driver implements StorageInterface
{
    private S3Client $client;
    private string $bucket;
    private string $domain;
    private string $endpoint;
    private string $region;
    private bool $ssl;
    private string $version;
    private bool $immutable;

    public function __construct(array $config, ?S3Client $client = null)
    {
        $this->bucket = $config['bucket'];
        $this->domain = $config['domain'] ?? '';
        $this->endpoint = $config['endpoint'] ?? '';
        $this->region = $config['region'] ?? '';
        $this->version = $config['version'] ?? 'latest';
        $this->ssl = $config['ssl'] ?? true;
        $this->immutable = $config['immutable'] ?? false;

        if ($client) {
            $this->client = $client;
        } else {
            $this->client = new S3Client([
                'version' => $this->version,
                'region'  => $this->region,
                'bucket' => $this->bucket,
                'endpoint' => ($this->ssl ? 'https' : 'http') . '://' . $this->endpoint,
                'credentials' => [
                    'key'    => $config['access_key'],
                    'secret' => $config['secret_key'],
                ],
                //'use_path_style_endpoint' => false,
            ]);
        }
    }

    public function write(string $path, string $contents, array $options = []): bool
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
                'Body'   => $contents,
                'ContentType' => $this->getContentType($contents),
            ]);
            return true;
        } catch (\Exception $e) {
            throw new StorageException("Failed to write file: {$path}, " . $e->getMessage());
        }
    }

    public function writeStream(string $path, $resource, array $options = []): bool
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
                'Body'   => $resource,
                'ContentType' => $this->getContentType($resource),

            ]);
            return true;
        } catch (\Exception $e) {
            throw new StorageException("Failed to write stream: {$path}, " . $e->getMessage());
        }
    }

    private function getContentType($resource): string
    {
        if (is_string($resource)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            return $finfo->buffer($resource) ?: 'application/octet-stream';
        } else if (is_resource($resource)) {
            $position = ftell($resource);
            $content = stream_get_contents($resource, 8192, 0);
            fseek($resource, $position);
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            return $finfo->buffer($content) ?: 'application/octet-stream';
        }
        return 'application/octet-stream';
    }

    public function read(string $path): string
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
            ]);
            return (string) $result['Body'];
        } catch (\Exception $e) {
            throw new StorageException("Failed to read file: {$path}, " . $e->getMessage());
        }
    }

    public function readStream(string $path)
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
            ]);
            return $result['Body']->detach();
        } catch (\Exception $e) {
            throw new StorageException("Failed to open stream: {$path}, " . $e->getMessage());
        }
    }

    public function delete(string $path): bool
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
            ]);
            return true;
        } catch (\Exception $e) {
            throw new StorageException("Failed to delete file: {$path}, " . $e->getMessage());
        }
    }

    public function exists(string $path): bool
    {
        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
            ]);
            return true;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            if (in_array($e->getAwsErrorCode(), ['NotFound', 'NoSuchKey'])) {
                return false;
            }
            throw new StorageException("Failed to check file existence: {$path}, " . $e->getMessage());
        }
    }

    public function size(string $path): int
    {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
            ]);
            return $result['ContentLength'];
        } catch (\Exception $e) {
            throw new StorageException("Failed to get file size: {$path}, " . $e->getMessage());
        }
    }

    public function publicUrl(string $path): string
    {
        if ($this->domain) {
            return sprintf('%s/%s', rtrim($this->domain, '/'), $path);
        }
        return $this->client->getObjectUrl($this->bucket, $path);
    }

    public function privateUrl(string $path, int $expires = 1200): string
    {
        $command = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key'    => $path,
        ]);

        return (string) $this->client->createPresignedRequest($command, "+{$expires} seconds")->getUri();
    }

    public function signPostUrl(string $path): array
    {
        $formInputs = [
            'key' => $path,
        ];

        $options = [
            ['bucket' => $this->bucket],
        ];

        $postObject = new \Aws\S3\PostObjectV4(
            $this->client,
            $this->bucket,
            $formInputs,
            $options,
        );

        $formAttributes = $postObject->getFormAttributes();
        $formInputs = $postObject->getFormInputs();

        return [
            'url' => $formAttributes['action'],
            'params' => $formInputs
        ];
    }

    public function signPutUrl(string $path): string
    {
        $command = $this->client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key'    => $path,
        ]);

        return (string) $this->client->createPresignedRequest($command, '+20 minutes')->getUri();
    }

    public function isLocal(): bool
    {
        return false;
    }
}
