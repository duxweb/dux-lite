<?php
declare(strict_types=1);

namespace Core\Docs\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ResultStatus
{
    /**
     * @param int $code HTTP状态码
     * @param string $name 状态名称
     * @param string $desc 状态描述
     * @param mixed $example 示例响应
     */
    public function __construct(
        public int $code,
        public string $name,
        public string $desc = '',
        public mixed $example = null,
    )
    {
    }
}