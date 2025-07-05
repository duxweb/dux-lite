<?php
declare(strict_types=1);

namespace Core\Docs\Attribute;

use Attribute;
use Core\Docs\Enum\FieldEnum;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Query
{
    /**
     * @param string $field 参数名
     * @param FieldEnum $type 类型
     * @param string $name 参数名
     * @param bool $required 是否必填
     * @param string $desc 描述
     * @param mixed $example 示例
     */
    public function __construct(
        public string $field,
        public FieldEnum $type,
        public string $name,
        public bool $required = false,
        public string $desc = '',
        public mixed $example = null,
    )
    {
    }
}