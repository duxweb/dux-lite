<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class IfHandler
{
    public function __invoke(DOMElement $node, CustomTagPreprocessor $pp): string
    {
        $cond = $pp->attrExprVal($node, 'condition') ?? 'true';
        return '{if ' . $cond . '}' . $pp->renderChildren($node) . '{/if}';
    }
}
