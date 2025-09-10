<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class EmptyHandler
{
    public function __invoke(DOMElement $node, CustomTagPreprocessor $pp): string
    {
        return $pp->renderChildren($node);
    }
}
