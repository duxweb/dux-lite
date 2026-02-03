<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;

class BuiltinRegistry
{
    /** @var array<string, callable(DOMElement, CustomTagPreprocessor): string> */
    private array $handlers = [];

    public function add(string $tagName, callable $handler): void
    {
        $this->handlers[strtolower($tagName)] = $handler;
    }

    public function get(string $tagName): ?callable
    {
        $key = strtolower($tagName);
        return $this->handlers[$key] ?? null;
    }

    public function has(string $tagName): bool
    {
        return array_key_exists(strtolower($tagName), $this->handlers);
    }
}
