<?php
declare(strict_types=1);

namespace Core\Docs\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Docs {

    /**
     * @param string $name 分组名称
     * @param string $desc 文档描述
     * @param string $category 类目名称
     */
    public function __construct(
        public string $name,
        public string $desc = '',
        public string $category = '',
    ) {}


}
