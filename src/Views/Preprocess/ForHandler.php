<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class ForHandler
{
    public function __invoke(DOMElement $node, CustomTagPreprocessor $pp): string
    {
        $from = $pp->attrExprVal($node, 'from') ?? '0';
        $to   = $pp->attrExprVal($node, 'to') ?? '0';
        $step = $pp->attrExprVal($node, 'step') ?? '1';
        $var  = $pp->attrExprVal($node, 'as') ?? 'i';
        $hdr  = '{for $' . $var . ' = ' . $from . '; $' . $var . ' <= ' . $to . '; $' . $var . ' += ' . $step . '}';
        return $hdr . $pp->renderChildren($node) . '{/for}';
    }
}
