<?php

declare(strict_types=1);

namespace Core\Views\Preprocess;

use DOMElement;

class AttrResolver
{
    /**
     * 统一属性读取：
     * - type=expr 返回表达式优先（:name 或字面值字符串）
     * - type=code 返回表达式或已加引号的字面量
     * - type=bool 返回 'true'/'false'（表达式原样返回）
     */
    public function readAttr(DOMElement $node, string $name, string $type = 'expr'): ?string
    {
        $hasExpr = $node->hasAttribute(':' . $name);
        $hasRaw = $node->hasAttribute($name);
        if (!$hasExpr && !$hasRaw) {
            return null;
        }

        if ($type === 'expr') {
            if ($hasExpr) {
                return (string) $node->getAttribute(':' . $name);
            }
            return (string) $node->getAttribute($name);
        }
        if ($type === 'code') {
            if ($hasExpr) {
                return (string) $node->getAttribute(':' . $name);
            }
            return "'" . addslashes((string) $node->getAttribute($name)) . "'";
        }
        if ($type === 'bool') {
            if ($hasExpr) {
                return (string) $node->getAttribute(':' . $name);
            }
            $v = strtolower((string) $node->getAttribute($name));
            if ($v === '' || $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on') {
                return 'true';
            }
            return 'false';
        }
        return null;
    }

    /** 读取原始属性值。 */
    public function getAttrRaw(DOMElement $node, string $name): ?string
    {
        if ($node->hasAttribute($name)) {
            return (string) $node->getAttribute($name);
        }
        return null;
    }

    /** 解析表达式属性（优先 :name）。 */
    public function attrExpr(DOMElement $node, string $name): ?string
    {
        return $this->readAttr($node, $name, 'expr');
    }

    /** 解析属性为代码（字面量加引号）。 */
    public function attrCode(DOMElement $node, string $name): ?string
    {
        return $this->readAttr($node, $name, 'code');
    }

    /** 解析布尔属性为 'true'/'false' 字符串。 */
    public function attrBool(DOMElement $node, string $name): ?string
    {
        return $this->readAttr($node, $name, 'bool');
    }

    /**
     * 从元素属性构建 Latte 过滤器链。
     * @param array $skipNames 需要跳过的属性名
     */
    public function buildFilterChainFromAttributes(DOMElement $node, array $skipNames = []): string
    {
        if (!$node->hasAttributes()) {
            return '';
        }
        $filters = '';
        foreach ($node->attributes as $attr) {
            $nameRaw = $attr->nodeName;
            if (in_array($nameRaw, $skipNames, true)) {
                continue;
            }
            $isExpr = str_starts_with($nameRaw, ':');
            $name = $isExpr ? substr($nameRaw, 1) : $nameRaw;
            $val = (string) ($attr->nodeValue ?? '');
            if ($val === '' && !$isExpr) {
                $filters .= '|' . $name;
                continue;
            }
            $asExpr = $isExpr || (strlen(ltrim($val)) > 0 && ltrim($val)[0] === '$');
            $arg = $asExpr ? $val : ("'" . addslashes($val) . "'");
            $filters .= '|' . $name . ':' . $arg;
        }
        return $filters;
    }

    /**
     * 转义属性值但保留花括号内的 Latte 表达式。
     */
    public function escapeAttrPreservingLatte(string $value): string
    {
        $buf = '';
        $offset = 0;
        $len = strlen($value);
        while (true) {
            if (!preg_match('/\{[^}]*\}/u', $value, $m, PREG_OFFSET_CAPTURE, $offset)) {
                $rest = substr($value, $offset);
                $buf .= htmlspecialchars($rest, ENT_QUOTES);
                break;
            }
            $start = (int) $m[0][1];
            $match = (string) $m[0][0];
            $before = substr($value, $offset, $start - $offset);
            $buf .= htmlspecialchars($before, ENT_QUOTES);
            $buf .= $match;
            $offset = $start + strlen($match);
            if ($offset >= $len) {
                break;
            }
        }
        return $buf;
    }

    /**
     * 选择属性值外层引号：尽量避开 Latte 宏中已存在的引号。
     * - 宏内仅有双引号 -> 用单引号包裹; 仅有单引号 -> 用双引号包裹; 都有或都无 -> 用双引号。
     */
    public function chooseAttrQuoteWrapper(string $raw): string
    {
        $hasD = false;
        $hasS = false;
        if (preg_match_all('/\{[^}]*\}/u', $raw, $mm)) {
            foreach ($mm[0] as $seg) {
                if (strpos($seg, '"') !== false) {
                    $hasD = true;
                }
                if (strpos($seg, "'") !== false) {
                    $hasS = true;
                }
                if ($hasD && $hasS) {
                    break;
                }
            }
        }
        if ($hasD && !$hasS) {
            return "'";
        }
        if ($hasS && !$hasD) {
            return '"';
        }
        return '"';
    }
}
