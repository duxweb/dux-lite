<?php

declare(strict_types=1);

namespace Core\Cache;

use Core\App;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

class Cache
{

    private Psr16Cache $adapter;

    public function __construct(?string $type, array $config = [])
    {

        switch ($type) {
            case 'redis':
                $cache = new RedisAdapter(
                    redis: App::redis(),
                    namespace: $config['prefix'] ?? '',
                    defaultLifetime: $config['defaultLifetime'] ?? 0
                );
                break;
            default:
            case 'file':
                $cache = new FilesystemAdapter(
                    namespace: $config['prefix'] ?? '',
                    defaultLifetime: $config['defaultLifetime'] ?? 0,
                    directory: base_path('data/cache')
                );
                break;
        }

        $this->adapter = new Psr16Cache($cache);
    }

    public function factory(): Psr16Cache
    {
        return $this->adapter;
    }
}
