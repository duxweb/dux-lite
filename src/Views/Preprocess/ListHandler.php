<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class ListHandler
{
    public function __invoke(DOMElement $node, CustomTagPreprocessor $pp): string
    {
        $tag = 'list';
        if ($node->childNodes->length === 0) {
            $args = $pp->buildMacroArgs($tag, $node, false);
            return '{xTagAssign ' . $args . '}';
        }
        $argsAssign = $pp->buildMacroArgs($tag, $node, true);
        $varName = $pp->toVarName($tag);
        $asItem = $pp->getAttr($node, 'as');
        if ($asItem === null || $asItem === '') {
            $asItem = $varName . 'Item';
        }
        return '{xTagAssign ' . $argsAssign . '}'
            . '{foreach $' . $varName . ' as $' . $asItem . '}'
            . $pp->renderChildren($node)
            . '{/foreach}';
    }
}
