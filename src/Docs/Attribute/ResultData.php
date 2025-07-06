<?php
declare(strict_types=1);

namespace Core\Docs\Attribute;

use Attribute;
use Core\Docs\Enum\FieldEnum;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ResultData
{
    /**
     * @param string $field 参数名
     * @param FieldEnum $type 类型
     * @param string $name 参数名
     * @param bool $required 是否必填
     * @param string $desc 描述
     * @param mixed $example 示例
     * @param array $children 子字段
     */
    public function __construct(
        public string $field,
        public FieldEnum $type,
        public string $name,
        public bool $required = false,
        public string $desc = '',
        public mixed $example = null,
        public array $children = [],
        public bool $root = false,
    )
    {
    }

    /**
     * 获取字段信息数组
     * @return array
     */
    public function getField(): array
    {
        return [
            'field' => $this->field,
            'type' => $this->type,
            'name' => $this->name,
            'required' => $this->required,
            'desc' => $this->desc,
            'example' => $this->example,
            'children' => $this->children,
            'root' => $this->root,
        ];
    }
}