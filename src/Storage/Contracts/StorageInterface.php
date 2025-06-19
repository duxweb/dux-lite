<?php
declare(strict_types=1);

namespace Core\Storage\Contracts;

interface StorageInterface
{
    public function write(string $path, string $contents, array $options = []): bool;
    
    public function writeStream(string $path, $resource, array $options = []): bool;
    
    public function read(string $path): string;
    
    public function readStream(string $path);
    
    public function delete(string $path): bool;
    
    public function publicUrl(string $path): string;
    
    public function privateUrl(string $path, int $expires = 3600): string;
    
    public function signPostUrl(string $path): array;
    
    public function signPutUrl(string $path): string;
    
    public function size(string $path): int;
    
    public function exists(string $path): bool;

    public function isLocal(): bool;
} 