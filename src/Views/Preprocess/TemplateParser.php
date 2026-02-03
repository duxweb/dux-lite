<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Masterminds\HTML5;

class TemplateParser
{
    private HTML5 $html5;

    public function __construct(?HTML5 $html5 = null)
    {
        $this->html5 = $html5 ?? new HTML5(['disable_html_ns' => true]);
    }

    /**
     * 解析模板片段为 DOMDocumentFragment。
     */
    public function parseFragment(string $template): \DOMDocumentFragment
    {
        $template = $this->rewriteDottedPlaceholders($template);
        return $this->html5->loadHTMLFragment($template);
    }

    /**
     * 将 <foo.bar.baz /> 改写为 <x-val data-var="foo" data-path="bar.baz" />。
     * 只处理自闭合标签。
     */
    private function rewriteDottedPlaceholders(string $tpl): string
    {
        $re = '/<\s*([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)+)\s*([^>]*)\/>/u';
        return (string) preg_replace_callback($re, function (array $m) {
            $full = $m[1];
            $attrs = $m[2] ?? '';
            $parts = explode('.', $full);
            if (count($parts) < 2) {
                return $m[0];
            }
            $first = array_shift($parts);
            $rest = implode('.', $parts);
            return '<x-val data-var="' . htmlspecialchars($first, ENT_QUOTES) . '" data-path="' . htmlspecialchars($rest, ENT_QUOTES) . '"' . ($attrs ? ' ' . trim($attrs) : '') . '/>';
        }, $tpl);
    }
}
