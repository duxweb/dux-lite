<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class PaginationHandler
{
    public function __invoke(DOMElement $node, CustomTagPreprocessor $pp): string
    {
        $args = [];
        foreach ($node->attributes as $attr) {
            $name = $attr->nodeName;
            $isExpr = str_starts_with($name, ':');
            $key = $isExpr ? substr($name, 1) : $name;
            $val = (string)($attr->nodeValue ?? '');
            if (!$isExpr) {
                $trim = ltrim($val);
                if ($trim !== '' && $trim[0] === '$') {
                    $isExpr = true;
                }
            }
            $valCode = $isExpr ? $val : ("'" . addslashes($val) . "'");
            $args[] = $key . ' => ' . $valCode;
        }

        $payload = '[' . implode(', ', $args) . ']';
        return '{pagination ' . $payload . '}';
    }
}
