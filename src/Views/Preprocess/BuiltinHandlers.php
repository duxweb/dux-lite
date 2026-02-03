<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class BuiltinHandlers
{
    public function register(BuiltinRegistry $registry): void
    {
        $registry->add('list', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new ListHandler();
            return $h($n, $pp);
        });
        $registry->add('info', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new InfoHandler();
            return $h($n, $pp);
        });
        $registry->add('loop', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new LoopHandler();
            return $h($n, $pp);
        });
        $registry->add('layout', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new LayoutHandler();
            return $h($n, $pp);
        });
        $registry->add('include', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new IncludeHandler();
            return $h($n, $pp);
        });
        $registry->add('embed', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new EmbedHandler();
            return $h($n, $pp);
        });
        $registry->add('block', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new BlockHandler();
            return $h($n, $pp);
        });
        $registry->add('empty', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new EmptyHandler();
            return $h($n, $pp);
        });
        $registry->add('if', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new IfHandler();
            return $h($n, $pp);
        });
        $registry->add('elseif', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new ElseIfHandler();
            return $h($n, $pp);
        });
        $registry->add('else', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new ElseHandler();
            return $h($n, $pp);
        });
        $registry->add('for', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new ForHandler();
            return $h($n, $pp);
        });
        $registry->add('foreach', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new ForeachHandler();
            return $h($n, $pp);
        });
        $registry->add('pagination', function (DOMElement $n, CustomTagPreprocessor $pp) {
            $h = new PaginationHandler();
            return $h($n, $pp);
        });
    }
}
