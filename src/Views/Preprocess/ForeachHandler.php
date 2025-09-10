<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class ForeachHandler
{
    public function __invoke(DOMElement $node, CustomTagPreprocessor $pp): string
    {
        $of   = $pp->attrExprVal($node, 'of') ?? '$items';
        $item = $pp->attrExprVal($node, 'as') ?? 'item';
        $key  = $pp->attrExprVal($node, 'key');
        $asClause = $key ? ('$' . $key . ' => $' . $item) : ('$' . $item);
        $hdr = '{foreach ' . $of . ' as ' . $asClause . '}';
        return $hdr . $pp->renderChildren($node) . '{/foreach}';
    }
}
