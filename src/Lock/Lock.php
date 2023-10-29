<?php
declare(strict_types=1);

namespace Dux\Lock;

use Dux\App;
use Dux\Handlers\Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Overtrue\Flysystem\Cos\CosAdapter;
use Overtrue\Flysystem\Qiniu\QiniuAdapter;
use Iidestiny\Flysystem\Oss\OssAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Lock\Store\SemaphoreStore;

class Lock {

    public static function init(string $type = 'semaphore'): LockFactory
    {
        $store = match ($type) {
            'flock' =>  new FlockStore(),
            'redis' => new RedisStore(App::redis()),
            'semaphore' => new SemaphoreStore(),
            default => throw new Exception('Lock type does not exist')
        };
        return new LockFactory($store);
    }
}