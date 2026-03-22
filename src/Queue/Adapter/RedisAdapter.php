<?php

declare(strict_types=1);

namespace Core\Queue\Adapter;

use RuntimeException;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportInterface;

class RedisAdapter implements QueueAdapterInterface
{
    private array $config;
    private string $prefix;
    private string $group;

    /**
     * @param array $config Redis 驱动配置（来自 config/database.toml）
     * @param string $group worker 名（用于 Redis streams group）
     */
    public function __construct(string $driver, array $config, string $group)
    {
        unset($driver);
        $this->config = $config;
        $this->prefix = (string)($this->config['prefix'] ?? ($this->config['optPrefix'] ?? ''));
        $this->group = $group;
    }

    /**
     * 获取物理队列名（包含前缀）。
     */
    public function queueKey(string $name): string
    {
        return $this->prefix . $name;
    }

    /**
     * 创建发送 transport（投递消息）。
     */
    public function createSendTransport(string $queueName): TransportInterface
    {
        if (!extension_loaded('redis')) {
            throw new RuntimeException('Redis queue transport requires ext-redis.');
        }
        $factory = 'Symfony\\Component\\Messenger\\Bridge\\Redis\\Transport\\RedisTransportFactory';
        if (!class_exists($factory)) {
            throw new RuntimeException('Redis queue transport requires symfony/redis-messenger. Run: composer require symfony/redis-messenger');
        }

        $serializer = new PhpSerializer();
        return (new $factory())->createTransport(
            $this->buildDsn($queueName, $this->group, 'producer'),
            [],
            $serializer
        );
    }

    /**
     * 创建消费 transport（worker 消费）。
     */
    public function createConsumeTransport(string $queueName, string $consumerName): TransportInterface
    {
        if (!extension_loaded('redis')) {
            throw new RuntimeException('Redis queue transport requires ext-redis.');
        }
        $factory = 'Symfony\\Component\\Messenger\\Bridge\\Redis\\Transport\\RedisTransportFactory';
        if (!class_exists($factory)) {
            throw new RuntimeException('Redis queue transport requires symfony/redis-messenger. Run: composer require symfony/redis-messenger');
        }

        $serializer = new PhpSerializer();
        return (new $factory())->createTransport(
            $this->buildDsn($queueName, $this->group, $consumerName),
            [],
            $serializer
        );
    }

    /**
     * 获取队列状态：
     * - pending：可立即消费数量（stream 总数 - reserved）
     * - reserved：XPENDING 数量（执行中/未 ack）
     * - delayed：延迟队列 ZSET 数量
     */
    public function stats(array $names): array
    {
        $rows = [];
        foreach ($names as $name) {
            $rows[] = [
                'name' => $name,
                'type' => 'redis',
                'pending' => null,
                'delayed' => null,
                'reserved' => null,
            ];
        }

        if (!extension_loaded('redis')) {
            return $rows;
        }

        $redis = $this->createRedisClient();
        foreach ($rows as &$row) {
            $stream = $this->streamKey($row['name']);
            $row['reserved'] = $this->redisXpendingCount($redis, $stream, $this->group);
            $total = $this->redisXlen($redis, $stream);
            $row['pending'] = max(0, $total - (int)$row['reserved']);
            $row['delayed'] = $this->redisZcard($redis, $stream . '__queue');
        }
        unset($row);

        return $rows;
    }

    /**
     * 构建 Redis DSN（stream/group/consumer 写入 path）。
     */
    private function buildDsn(string $queueName, string $group, string $consumer): string
    {
        $host = (string)($this->config['host'] ?? '127.0.0.1');
        $port = (int)($this->config['port'] ?? 6379);
        $dbindex = (int)($this->config['database'] ?? 0);
        $auth = (string)($this->config['auth'] ?? ($this->config['password'] ?? ''));

        $path = '/' . $this->streamKey($queueName) . '/' . rawurlencode($group) . '/' . rawurlencode($consumer);
        $query = $dbindex ? ('?dbindex=' . $dbindex) : '';

        if ($auth !== '') {
            return 'redis://:' . rawurlencode($auth) . '@' . $host . ':' . $port . $path . $query;
        }

        return 'redis://' . $host . ':' . $port . $path . $query;
    }

    /**
     * 创建 Redis 客户端（ext-redis）。
     */
    private function createRedisClient(): \Redis
    {
        $host = (string)($this->config['host'] ?? '127.0.0.1');
        $port = (int)($this->config['port'] ?? 6379);
        $auth = (string)($this->config['auth'] ?? ($this->config['password'] ?? ''));
        $database = (int)($this->config['database'] ?? 0);
        $timeout = (float)($this->config['timeout'] ?? 1.0);

        $redis = new \Redis();
        $redis->connect($host, $port, $timeout);
        if ($auth !== '') {
            $redis->auth($auth);
        }
        if ($database) {
            $redis->select($database);
        }

        return $redis;
    }

    private function streamKey(string $queueName): string
    {
        return rawurlencode($this->queueKey($queueName));
    }

    /**
     * 获取 stream 长度（近似 pending 总数）。
     */
    private function redisXlen(\Redis $redis, string $stream): int
    {
        if (method_exists($redis, 'xLen')) {
            return (int)$redis->xLen($stream);
        }
        $len = $redis->rawCommand('XLEN', $stream);
        return (int)($len ?: 0);
    }

    /**
     * 获取 group 的 pending 数量。
     */
    private function redisXpendingCount(\Redis $redis, string $stream, string $group): int
    {
        if (method_exists($redis, 'xPending')) {
            $info = $redis->xPending($stream, $group);
            if (is_array($info) && isset($info[0])) {
                return (int)$info[0];
            }
        }
        $info = $redis->rawCommand('XPENDING', $stream, $group);
        if (is_array($info) && isset($info[0])) {
            return (int)$info[0];
        }
        return 0;
    }

    /**
     * 获取 ZSET 的元素数量（延迟队列）。
     */
    private function redisZcard(\Redis $redis, string $key): int
    {
        if (method_exists($redis, 'zCard')) {
            return (int)$redis->zCard($key);
        }
        $count = $redis->rawCommand('ZCARD', $key);
        return (int)($count ?: 0);
    }
}
