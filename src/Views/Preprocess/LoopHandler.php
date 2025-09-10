<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class LoopHandler
{
    public function __invoke(DOMElement $node, CustomTagPreprocessor $pp): string
    {
        $data = $pp->attrExprVal($node, 'data') ?? '';
        $item = ltrim($pp->attrExprVal($node, 'item') ?? 'item', '$');
        $key  = $pp->attrExprVal($node, 'key');
        $key  = $key ? ltrim($key, '$') : '';

        $main = '';
        $empty = '';
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'empty') {
                $empty .= $pp->renderChildren($child);
            } else {
                $main .= $pp->renderSingle($child);
            }
        }

        $asClause = $key !== '' ? ('$' . $key . ' => $' . $item) : ('$' . $item);
        $hdr = '{foreach ' . $data . ' as ' . $asClause . '}';
        if ($empty !== '') {
            $main .= '{else}' . $empty;
        }
        return $hdr . $main . '{/foreach}';
    }
}
