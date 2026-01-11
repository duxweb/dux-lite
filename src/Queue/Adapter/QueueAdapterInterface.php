<?php

declare(strict_types=1);

namespace Core\Queue\Adapter;

use Symfony\Component\Messenger\Transport\TransportInterface;

interface QueueAdapterInterface
{
    /**
     * 获取物理队列名（包含前缀等）。
     */
    public function queueKey(string $name): string;

    /**
     * 创建发送 transport（用于投递消息）。
     */
    public function createSendTransport(string $queueName): TransportInterface;

    /**
     * 创建消费 transport（用于 worker 消费）。
     */
    public function createConsumeTransport(string $queueName, string $consumerName): TransportInterface;

    /**
     * 获取队列统计。
     * @param string[] $names 物理队列名列表
     * @return array<int, array{name:string,type:string,pending:int|null,delayed:int|null,reserved:int|null}>
     */
    public function stats(array $names): array;
}
