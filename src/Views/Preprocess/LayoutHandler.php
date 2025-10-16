<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class LayoutHandler
{
    public function __invoke(DOMElement $node, CustomTagPreprocessor $pp): string
    {
        // 将 <layout ...> 按原生 {layout ...} 语义处理：
        // 仅注册为文件头部宏。同时为了不强制闭合标签，
        // 若书写为 <layout ...>（未自闭合），其子节点（实际就是后续内容）继续正常渲染。
        $args = $pp->includeArgsFromEl($node);
        $pp->addLayoutFromArgs($args);
        return $pp->renderChildren($node);
    }
}
