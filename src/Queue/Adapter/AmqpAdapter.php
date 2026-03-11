<?php

declare(strict_types=1);

namespace Core\Queue\Adapter;

use RuntimeException;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportInterface;

class AmqpAdapter implements QueueAdapterInterface
{
    private array $config;
    private string $prefix;

    /**
     * @param array $config AMQP 驱动配置（来自 config/database.toml）
     */
    public function __construct(string $driver, array $config)
    {
        unset($driver);
        $this->config = $config;
        $this->prefix = (string)($this->config['prefix'] ?? '');
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
        if (!extension_loaded('amqp')) {
            throw new RuntimeException('AMQP queue transport requires ext-amqp.');
        }
        if (!class_exists(AmqpTransportFactory::class)) {
            throw new RuntimeException('AMQP queue transport requires symfony/amqp-messenger. Run: composer require symfony/amqp-messenger');
        }

        $serializer = new PhpSerializer();
        return (new AmqpTransportFactory())->createTransport($this->buildDsn($queueName), [], $serializer);
    }

    /**
     * 创建消费 transport（worker 消费）。
     */
    public function createConsumeTransport(string $queueName, string $consumerName): TransportInterface
    {
        unset($consumerName);
        if (!extension_loaded('amqp')) {
            throw new RuntimeException('AMQP queue transport requires ext-amqp.');
        }
        if (!class_exists(AmqpTransportFactory::class)) {
            throw new RuntimeException('AMQP queue transport requires symfony/amqp-messenger. Run: composer require symfony/amqp-messenger');
        }

        $serializer = new PhpSerializer();
        return (new AmqpTransportFactory())->createTransport($this->buildDsn($queueName), [], $serializer);
    }

    /**
     * 获取队列状态（需要 RabbitMQ Management API 才能拿到堆积/消费者等，这里返回 null）。
     */
    public function stats(array $names): array
    {
        $rows = [];
        foreach ($names as $name) {
            $rows[] = [
                'name' => $name,
                'type' => 'amqp',
                'pending' => null,
                'delayed' => null,
                'reserved' => null,
            ];
        }
        return $rows;
    }

    /**
     * 构建 AMQP DSN（使用 ext-amqp transport）。
     */
    private function buildDsn(string $queueName): string
    {
        $host = (string)($this->config['host'] ?? '127.0.0.1');
        $port = (int)($this->config['port'] ?? 5672);
        $vhost = (string)($this->config['vhost'] ?? '/');
        $user = (string)($this->config['username'] ?? 'guest');
        $pass = (string)($this->config['password'] ?? 'guest');

        return 'amqp://'
            . rawurlencode($user) . ':' . rawurlencode($pass)
            . '@' . $host . ':' . $port
            . '/' . rawurlencode($vhost)
            . '/' . rawurlencode($this->queueKey($queueName));
    }
}
