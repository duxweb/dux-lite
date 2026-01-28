<?php

declare(strict_types=1);

namespace Core\Queue;

class QueueMessage
{
    private int $delayMs = 0;
    private string $id = '';

    /**
     * @param string $name worker 名（workers.<name>），为空则使用 default
     * @param string $priority 优先级（high/medium/low），为空则默认 medium
     */
    public function __construct(
        private Queue $queue,
        public string $class,
        public string $method = '',
        public array $params = [],
        public string $name = '',
        public string $priority = '',
    ) {
    }

    /**
     * 设置优先级（high/medium/low）。
     */
    public function priority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * 设置延迟时间（秒）。
     */
    public function delay(int|float $second = 0): self
    {
        $this->delayMs = max(0, (int)round($second * 1000));
        return $this;
    }

    /**
     * 设置任务 ID（用于日志跟踪）。
     */
    public function id(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * 投递到队列。
     */
    public function send(): void
    {
        $this->queue->dispatch(
            $this->name,
            $this->priority,
            new QueueJobMessage($this->class, $this->method, $this->params, $this->priority, $this->id),
            $this->delayMs
        );
    }
}
