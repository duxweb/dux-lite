<?php
declare(strict_types=1);

namespace Core\Storage;

use Core\Storage\Contracts\StorageInterface;
use Core\Storage\Drivers\LocalDriver;
use Core\Storage\Drivers\S3Driver;
use Core\Storage\Exceptions\StorageException;

class Storage
{
    private StorageInterface $driver;

    public function __construct(string $driver, array $config, ?callable $signCallback = null)
    {
        $this->driver = match ($driver) {
            'local' => new LocalDriver($config, $signCallback),
            's3' => new S3Driver($config),
            default => throw new StorageException('Driver not supported'),
        };
    }

    public function getInstance(): StorageInterface
    {
        return $this->driver;
    }

    public function __call(string $name, array $arguments)
    {
        if (method_exists($this->driver, $name)) {
            return $this->driver->$name(...$arguments);
        }
        throw new StorageException("Method {$name} not found");
    }
} 