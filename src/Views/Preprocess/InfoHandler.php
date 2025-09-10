<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class InfoHandler
{
    public function __invoke(DOMElement $node, CustomTagPreprocessor $pp): string
    {
        $tag = 'info';
        if ($node->childNodes->length === 0) {
            $args = $pp->buildMacroArgs($tag, $node, false);
            return '{xTagAssign ' . $args . '}';
        }
        $argsAssign = $pp->buildMacroArgs($tag, $node, true);
        return '{xTagAssign ' . $argsAssign . '}'
            . $pp->renderChildren($node);
    }
}
