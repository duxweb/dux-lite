<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use DOMElement;

class ArgsBuilder
{
    public function __construct(private AttrResolver $attrs)
    {
    }

    /** 供处理器构造 include/embed 参数串。 */
    public function includeArgsFromEl(DOMElement $node): string
    {
        $src = $this->attrs->getAttrRaw($node, 'src');
        $srcExpr = $this->attrs->getAttrRaw($node, ':src');
        $args = [];
        if ($srcExpr !== null) {
            $args[] = $srcExpr;
        } elseif ($src !== null) {
            $args[] = "'" . addslashes($src) . "'";
        }
        foreach ($node->attributes as $attr) {
            $name = $attr->nodeName;
            if ($name === 'src' || $name === ':src') {
                continue;
            }
            $isExpr = str_starts_with($name, ':');
            $key = $isExpr ? substr($name, 1) : $name;
            $val = (string) ($attr->nodeValue ?? '');
            // 兼容未声明 ':' 但值以 '$' 开头的表达式
            if (!$isExpr) {
                $trim = ltrim($val);
                if ($trim !== '' && $trim[0] === '$') {
                    $isExpr = true;
                }
            }
            $valCode = $isExpr ? $val : ("'" . addslashes($val) . "'");
            $args[] = "'" . addslashes($key) . "' => " . $valCode;
        }
        return implode(', ', $args);
    }

    /** 构造 {xTag}/{xTagAssign} 的参数数组代码。 */
    public function macroArrayArgs(string $canonicalName, DOMElement $node, bool $isBlock): string
    {
        $targetCode = $this->attrs->attrCode($node, 'data') ?? "''";
        $varName = $this->toCamelCase($canonicalName);
        if (!$isBlock) {
            $assignVar = $this->attrs->getAttrRaw($node, 'as');
            if ($assignVar !== null && $assignVar !== '') {
                $varName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $assignVar);
            }
        }
        $asItem = null;
        if ($isBlock) {
            $asItem = $this->attrs->getAttrRaw($node, 'as');
            if ($asItem === null || $asItem === '') {
                $asItem = $varName . 'Item';
            }
        }
        $params = $this->buildParamsCodeFromEl($node);

        $parts = [];
        $parts[] = "['type' => '" . addslashes($canonicalName) . "'";
        $parts[] = "'data' => " . $targetCode;
        $pathSpec = $this->attrs->attrCode($node, 'path');
        if ($pathSpec !== null) {
            $parts[] = "'path' => " . $pathSpec;
        }
        $argsSpec = $this->attrs->attrCode($node, 'args');
        if ($argsSpec !== null) {
            $parts[] = "'args' => " . $argsSpec;
        }
        $parts[] = "'params' => " . $params;
        $parts[] = "'var' => '" . addslashes($varName) . "'";
        if ($isBlock && $asItem !== null) {
            $parts[] = "'as' => '" . addslashes((string) $asItem) . "'";
        }
        $meta = $this->attrs->getAttrRaw($node, 'meta');
        if ($meta !== null && $meta !== '') {
            $parts[] = "'meta' => '" . addslashes((string) $meta) . "'";
        }
        $cls = $this->attrs->getAttrRaw($node, 'class');
        if ($cls !== null && $cls !== '') {
            $parts[] = "'class' => '" . addslashes((string) $cls) . "'";
        }
        $pth = $this->attrs->getAttrRaw($node, 'path');
        if ($pth !== null && $pth !== '') {
            $parts[] = "'path' => '" . addslashes((string) $pth) . "'";
        }
        $debugCode = $this->attrs->attrBool($node, 'debug');
        if ($debugCode !== null) {
            $parts[] = "'debug' => " . $debugCode;
        }
        return implode(', ', $parts) . ']';
    }

    /** 收集非控制属性为 params 数组代码。 */
    public function buildParamsCodeFromEl(DOMElement $node): string
    {
        $params = [];
        foreach ($node->attributes as $attr) {
            $name = $attr->nodeName;
            if (in_array($name, ['as', 'meta', 'data', ':data', 'path', ':path', 'args', ':args', 'debug'], true)) {
                continue;
            }
            $isExpr = str_starts_with($name, ':');
            $key = $isExpr ? substr($name, 1) : $name;
            $value = $attr->nodeValue ?? '';
            if (!$isExpr) {
                $trim = ltrim((string) $value);
                if ($trim !== '' && $trim[0] === '$') {
                    $isExpr = true;
                }
            }
            $valCode = $isExpr ? (string) $value : ('"' . addslashes((string) $value) . '"');
            $params[] = '"' . addslashes($key) . '" => ' . $valCode;
        }
        return '[' . implode(', ', $params) . ']';
    }

    /** 转换为 camelCase。 */
    public function toCamelCase(string $name): string
    {
        $name = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $name);
        $name = strtolower((string) $name);
        $parts = preg_split('/-+/', (string) $name) ?: [];
        $camel = '';
        foreach ($parts as $i => $p) {
            if ($p === '') {
                continue;
            }
            $camel .= $i === 0 ? $p : ucfirst($p);
        }
        return $camel;
    }
}
