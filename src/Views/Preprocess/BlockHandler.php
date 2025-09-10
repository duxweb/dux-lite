<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class BlockHandler
{
    public function __invoke(DOMElement $node, CustomTagPreprocessor $pp): string
    {
        // 仅支持标准写法：<block name="content">...</block>
        $name = (string)($node->getAttribute('name') ?: '');
        return '{block ' . $name . '}'
            . $pp->renderChildren($node)
            . '{/block}';
    }
}
