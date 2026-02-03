<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use Core\Views\CustomTagPreprocessor;
use DOMElement;
use DOMNode;
use DOMText;

class NodeEmitter
{
    /** @var string[] */
    private array $tagPrefixes;
    /** @var string[] */
    private array $prefixableTags;

    public function __construct(
        private CustomTagPreprocessor $pp,
        private BuiltinRegistry $registry,
        private AttrResolver $attrs,
        private ArgsBuilder $args,
        array $tagPrefixes,
        array $prefixableTags
    ) {
        $this->tagPrefixes = $tagPrefixes;
        $this->prefixableTags = $prefixableTags;
    }

    /**
     * 渲染节点列表。
     * @param iterable $nodeList
     */
    public function renderNodeList($nodeList): string
    {
        $buf = '';
        foreach ($nodeList as $node) {
            $buf .= $this->renderNode($node);
        }
        return $buf;
    }

    /**
     * 渲染单个节点。
     */
    public function renderNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            $text = $node->wholeText;
            if ($this->isInCodeLike($node)) {
                $text = str_replace(['{', '}'], ['&#123;', '&#125;'], $text);
            }
            return $text;
        }

        if ($node instanceof DOMElement) {
            $tag = (string) $node->tagName;
            $tagLower = strtolower($tag);
            $tagKey = $this->canonicalTagName($tagLower);

            $handler = $this->registry->get($tagKey);
            if ($handler !== null) {
                return (string) $handler($node, $this->pp);
            }

            if ($tagLower === 'x-val') {
                $first = (string) ($this->attrs->getAttrRaw($node, 'data-var') ?? '');
                $rest = (string) ($this->attrs->getAttrRaw($node, 'data-path') ?? '');
                if ($first !== '' && $rest !== '') {
                    $expr = '{= data_get($' . $first . ", '" . addslashes($rest) . "')";
                    $filters = $this->attrs->buildFilterChainFromAttributes($node, ['data-var', 'data-path']);
                    return $expr . $filters . ' }';
                }
            }

            switch ($tagKey) {
                case 'layout':
                    $args = $this->args->includeArgsFromEl($node);
                    $this->pp->addLayoutFromArgs($args);
                    return '';

                case 'include':
                    return '{include ' . $this->args->includeArgsFromEl($node) . '}';

                case 'embed':
                    $args = $this->args->includeArgsFromEl($node);
                    return '{embed ' . $args . '}'
                        . $this->renderNodeList($node->childNodes)
                        . '{/embed}';

                case 'block':
                    $name = (string) ($node->getAttribute('name') ?: '');
                    return '{block ' . $name . '}'
                        . $this->renderNodeList($node->childNodes)
                        . '{/block}';

                case 'list':
                case 'info':
                case 'if':
                case 'elseif':
                case 'else':
                case 'for':
                case 'foreach':
                case 'loop':
                case 'empty':
                    return '';

                default:
                    return $this->serializeElementVerbatim($node);
            }
        }

        return '';
    }

    /**
     * 归一化可前缀标签名，如 'x-layout' -> 'layout'。
     */
    private function canonicalTagName(string $tagLower): string
    {
        foreach ($this->tagPrefixes as $px) {
            if (str_starts_with($tagLower, $px)) {
                $base = substr($tagLower, strlen($px));
                if (in_array($base, $this->prefixableTags, true)) {
                    return $base;
                }
            }
        }
        return $tagLower;
    }

    /**
     * 判断节点是否位于 code/pre/samp/kbd 等代码类标签中。
     */
    private function isInCodeLike(DOMNode $node): bool
    {
        $n = $node->parentNode;
        while ($n && $n instanceof DOMNode) {
            if ($n instanceof DOMElement) {
                $tag = strtolower($n->tagName);
                if (in_array($tag, ['code', 'pre', 'samp', 'kbd'], true)) {
                    return true;
                }
            }
            $n = $n->parentNode;
        }
        return false;
    }

    /**
     * 原样序列化未知元素，保留属性中的 Latte 表达式。
     */
    private function serializeElementVerbatim(DOMElement $el): string
    {
        $tag = $el->tagName;
        $attrs = '';
        if ($el->hasAttributes()) {
            foreach ($el->attributes as $attr) {
                $name = $attr->nodeName;
                $raw = (string) $attr->nodeValue;
                $val = $this->attrs->escapeAttrPreservingLatte($raw);
                $q = $this->attrs->chooseAttrQuoteWrapper($raw);
                $attrs .= ' ' . $name . '=' . $q . $val . $q;
            }
        }
        if ($el->childNodes->length === 0) {
            return '<' . $tag . $attrs . '/>';
        }
        return '<' . $tag . $attrs . '>'
            . $this->renderNodeList($el->childNodes)
            . '</' . $tag . '>';
    }
}
