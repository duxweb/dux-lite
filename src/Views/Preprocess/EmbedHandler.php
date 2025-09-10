<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class EmbedHandler
{
    public function __invoke(DOMElement $node, CustomTagPreprocessor $pp): string
    {
        $args = $pp->includeArgsFromEl($node);
        $inner = $pp->renderChildren($node);
        // 若内部没有显式定义任何 block，则将全部内容作为默认块 `content`
        if (!preg_match('/\{\s*block\s+[^}]+\}/', $inner)) {
            $inner = '{block content}' . $inner . '{/block}';
        }
        return '{embed ' . $args . '}' . $inner . '{/embed}';
    }
}
