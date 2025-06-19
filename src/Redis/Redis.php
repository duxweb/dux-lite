<?php

declare(strict_types=1);

namespace Core\Redis;

use Core\Redis\Adapter\PhpRedisAdapter;
use Core\Redis\Adapter\PredisAdapter;

class Redis
{
    private PhpRedisAdapter|PredisAdapter $client;

    public function __construct(public array $config)
    {
        if (extension_loaded('redis')) {
            $this->client = new PhpRedisAdapter($config);
        } else {
            $this->client = new PredisAdapter($config);
        }
    }

    public function factory(): \Predis\ClientInterface|\Redis
    {
        return $this->client->getClient();
    }
}
