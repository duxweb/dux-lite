<?php
declare(strict_types=1);

namespace Core\Docs\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ResultMessage
{
    /**
     * @param string $name 消息名称
     * @param string $desc 消息描述
     * @param mixed $example 示例消息
     */
    public function __construct(
        public string $name,
        public string $desc = '',
        public mixed $example = null,
    )
    {
    }
}