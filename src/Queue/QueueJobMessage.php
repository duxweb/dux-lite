<?php

declare(strict_types=1);

namespace Core\Queue;

class QueueJobMessage
{
    /**
     * @param string $class 任务类名
     * @param string $method 方法名（为空则调用 __invoke）
     * @param array $params 方法参数列表（按顺序展开）
     */
    public function __construct(
        public string $class,
        public string $method = '',
        public array $params = [],
        public string $priority = '',
        public string $id = '',
    ) {
    }
}
