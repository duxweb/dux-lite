<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class IncludeHandler
{
    public function __invoke(DOMElement $node, CustomTagPreprocessor $pp): string
    {
        $args = $pp->includeArgsFromEl($node);
        return '{include ' . $args . '}';
    }
}
