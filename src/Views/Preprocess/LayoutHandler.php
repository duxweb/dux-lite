<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class LayoutHandler
{
    public function __invoke(DOMElement $node, CustomTagPreprocessor $pp): string
    {
        // 将 <layout ...> 视为 embed：在当前位置嵌入模板并允许块覆盖
        $args = $pp->includeArgsFromEl($node);
        $inner = $pp->renderChildren($node);
        // 复用与 Embed 一致的容错：若未声明任何 block，则默认包到 content 块
        if (!preg_match('/\{\s*block\s+[^}]+\}/', $inner)) {
            $inner = '{block content}' . $inner . '{/block}';
        }
        return '{embed ' . $args . '}' . $inner . '{/embed}';
    }
}
