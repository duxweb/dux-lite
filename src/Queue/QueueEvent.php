<?php

declare(strict_types=1);

namespace Core\Queue;

use Symfony\Contracts\EventDispatcher\Event;

class QueueEvent extends Event
{
    public const string ENQUEUE = 'queue.enqueue';
    public const string EXECUTE = 'queue.execute';
    public const string DONE = 'queue.done';
    public const string FAILED = 'queue.failed';

    /**
     * @param string $work worker 名（config/queue.toml 的 workers.<work>）
     * @param string $priority 优先级（high/medium/low）
     * @param QueueJobMessage $message 任务消息
     * @param int $delayMs 延迟毫秒（仅 enqueue 时有意义）
     * @param \Throwable|null $exception 异常（仅 failed 时有意义）
     */
    public function __construct(
        public string $work,
        public string $priority,
        public QueueJobMessage $message,
        public int $delayMs = 0,
        public ?\Throwable $exception = null,
    ) {
    }
}
