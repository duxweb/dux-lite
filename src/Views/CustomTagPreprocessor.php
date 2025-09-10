<?php

declare(strict_types=1);

namespace Core\Views;

use DOMElement;
use DOMNode;
use DOMText;
use Masterminds\HTML5;

class CustomTagPreprocessor
{
    public const VERSION = '2025-09-10-5';
    /** @var string[] */
    private array $layoutMacros = [];
    /** @var array<string, callable(DOMElement, self): string> */
    private array $elementHandlers = [];
    /** @var string[] 允许的标签前缀（按需扩展）。默认支持 'x-' 前缀。*/
    private array $tagPrefixes = ['x-'];
    /**
     * 可前缀化的内置标签集合（不包含 embed，避免隐式恢复 x-embed）。
     * @var string[]
     */
    private array $prefixableTags = [
        'layout','include','block','empty','if','elseif','else','for','foreach','loop','list','info'
    ];

    /**
     * 构造函数。
     */
    public function __construct()
    {
        // 在构造时注册内置处理器（可被外部覆盖）
        $this->registerBuiltins();
    }

    /**
     * 注册元素处理器：返回渲染后的字符串。
     */
    public function addElementHandler(string $tagName, callable $handler): void
    {
        $this->elementHandlers[strtolower($tagName)] = $handler;
    }

    private function registerBuiltins(): void
    {
        // 延迟加载，避免在无需要时引入类
        $this->addElementHandler('list', function(\DOMElement $n, self $pp) {
            $h = new \Core\Views\Preprocess\ListHandler();
            return $h($n, $pp);
        });
        $this->addElementHandler('info', function(\DOMElement $n, self $pp) {
            $h = new \Core\Views\Preprocess\InfoHandler();
            return $h($n, $pp);
        });
        $this->addElementHandler('loop', function(\DOMElement $n, self $pp) {
            $h = new \Core\Views\Preprocess\LoopHandler();
            return $h($n, $pp);
        });
        $this->addElementHandler('layout', function(\DOMElement $n, self $pp) {
            $h = new \Core\Views\Preprocess\LayoutHandler();
            return $h($n, $pp);
        });
        $this->addElementHandler('include', function(\DOMElement $n, self $pp) {
            $h = new \Core\Views\Preprocess\IncludeHandler();
            return $h($n, $pp);
        });
        $this->addElementHandler('embed', function(\DOMElement $n, self $pp) {
            $h = new \Core\Views\Preprocess\EmbedHandler();
            return $h($n, $pp);
        });
        $this->addElementHandler('block', function(\DOMElement $n, self $pp) {
            $h = new \Core\Views\Preprocess\BlockHandler();
            return $h($n, $pp);
        });
        $this->addElementHandler('empty', function(\DOMElement $n, self $pp) {
            $h = new \Core\Views\Preprocess\EmptyHandler();
            return $h($n, $pp);
        });
        $this->addElementHandler('if', function(\DOMElement $n, self $pp) {
            $h = new \Core\Views\Preprocess\IfHandler();
            return $h($n, $pp);
        });
        $this->addElementHandler('elseif', function(\DOMElement $n, self $pp) {
            $h = new \Core\Views\Preprocess\ElseIfHandler();
            return $h($n, $pp);
        });
        $this->addElementHandler('else', function(\DOMElement $n, self $pp) {
            $h = new \Core\Views\Preprocess\ElseHandler();
            return $h($n, $pp);
        });
        $this->addElementHandler('for', function(\DOMElement $n, self $pp) {
            $h = new \Core\Views\Preprocess\ForHandler();
            return $h($n, $pp);
        });
        $this->addElementHandler('foreach', function(\DOMElement $n, self $pp) {
            $h = new \Core\Views\Preprocess\ForeachHandler();
            return $h($n, $pp);
        });
    }

    /**
     * 统一属性读取：
     * - type=expr 返回表达式优先（:name 或字面值字符串）
     * - type=code 返回表达式或已加引号的字面量
     * - type=bool 返回 'true'/'false'（表达式原样返回）
     */
    private function readAttr(DOMElement $node, string $name, string $type = 'expr'): ?string
    {
        $hasExpr = $node->hasAttribute(':' . $name);
        $hasRaw  = $node->hasAttribute($name);
        if (!$hasExpr && !$hasRaw) return null;

        if ($type === 'expr') {
            if ($hasExpr) return (string)$node->getAttribute(':' . $name);
            return (string)$node->getAttribute($name);
        }
        if ($type === 'code') {
            if ($hasExpr) return (string)$node->getAttribute(':' . $name);
            return "'" . addslashes((string)$node->getAttribute($name)) . "'";
        }
        if ($type === 'bool') {
            if ($hasExpr) return (string)$node->getAttribute(':' . $name);
            $v = strtolower((string)$node->getAttribute($name));
            if ($v === '' || $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on') {
                return 'true';
            }
            return 'false';
        }
        return null;
    }

    /**
     * 预处理 HTML 片段为 Latte 宏。
     * - 支持点语法占位符重写
     * - 保护属性中的 Latte 表达式
     *
     * @param string $template 原始模板
     * @return string 处理后的模板
     */
    public function preprocess(string $template): string
    {
        $template = $this->rewriteDottedPlaceholders($template);

        $html5 = new HTML5(['disable_html_ns' => true]);
        $fragment = $html5->loadHTMLFragment($template);

        $this->layoutMacros = [];
        $out = $this->renderNodeList($fragment->childNodes);

        if ($this->layoutMacros) {
            $out = $this->layoutMacros[0] . "\n" . $out;
        }

        return $out;
    }

    /**
     * 渲染节点列表。
     * @param iterable $nodeList
     * @return string
     */
    private function renderNodeList($nodeList): string
    {
        $buf = '';
        foreach ($nodeList as $node) {
            $buf .= $this->renderNode($node);
        }
        return $buf;
    }

    /**
     * 渲染单个节点。
     * @param DOMNode $node
     * @return string
     */
    private function renderNode(DOMNode $node): string
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

            // 可插拔：优先命中自定义处理器（含前缀归一化）
            if (isset($this->elementHandlers[$tagKey])) {
                return (string) ($this->elementHandlers[$tagKey])($node, $this);
            }

            if ($tagLower === 'x-val') {
                $first = (string)($this->getAttrRaw($node, 'data-var') ?? '');
                $rest  = (string)($this->getAttrRaw($node, 'data-path') ?? '');
                if ($first !== '' && $rest !== '') {
                    $expr = '{= data_get($' . $first . ", '" . addslashes($rest) . "')";
                    $filters = $this->buildFilterChainFromAttributes($node, ['data-var', 'data-path']);
                    return $expr . $filters . ' }';
                }
            }

            switch ($tagKey) {
                case 'layout':
                    $args = $this->latteArgsFromIncludeLike($node);
                    $this->layoutMacros[] = '{layout ' . $args . '}';
                    return '';

                case 'include':
                    return '{include ' . $this->latteArgsFromIncludeLike($node) . '}';

                case 'embed':
                    $args = $this->latteArgsFromIncludeLike($node);
                    return '{embed ' . $args . '}'
                        . $this->renderNodeList($node->childNodes)
                        . '{/embed}';

                case 'block':
                    $name = (string)($node->getAttribute('name') ?: '');
                    return '{block ' . $name . '}'
                        . $this->renderNodeList($node->childNodes)
                        . '{/block}';

                case 'list':
                case 'info':
                    // 由外部处理器处理
                    return '';

                case 'if':
                case 'elseif':
                case 'else':
                case 'for':
                case 'foreach':
                    // 由外部处理器处理
                    return '';

                case 'loop':
                    // 由外部处理器处理
                    return '';

                case 'empty':
                    // 由外部处理器处理
                    return '';

                default:
                    return $this->serializeElementVerbatim($node);
            }
        }

        return '';
    }

    // ===== 供处理器调用的公共轻量 API =====

    /** 渲染一个元素的所有子节点。 */
    public function renderChildren(DOMElement $node): string
    {
        return $this->renderNodeList($node->childNodes);
    }

    /**
     * 归一化可前缀标签名，如 'x-layout' → 'layout'。
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

    /** 渲染单个节点（委托私有渲染）。 */
    public function renderSingle(DOMNode $node): string
    {
        return $this->renderNode($node);
    }

    /** 读取原始属性值。 */
    public function getAttr(DOMElement $node, string $name): ?string
    {
        return $this->getAttrRaw($node, $name);
    }

    /** 表达式属性（优先 :name）。 */
    public function attrExprVal(DOMElement $node, string $name): ?string
    {
        return $this->readAttr($node, $name, 'expr');
    }

    /** 代码属性（字面量自动加引号）。 */
    public function attrCodeVal(DOMElement $node, string $name): ?string
    {
        return $this->readAttr($node, $name, 'code');
    }

    /** 构造 {xTag}/{xTagAssign} 的参数数组代码（供处理器使用）。 */
    public function buildMacroArgs(string $canonicalName, DOMElement $node, bool $isBlock): string
    {
        return $this->macroArrayArgs($canonicalName, $node, $isBlock);
    }

    /** 从语义名生成变量名。 */
    public function toVarName(string $name): string
    {
        return $this->toCamelCase($name);
    }

    /** 供处理器构造 include/embed 参数串。 */
    public function includeArgsFromEl(DOMElement $node): string
    {
        return $this->latteArgsFromIncludeLike($node);
    }

    /** 供处理器注册 layout 宏头部。 */
    public function addLayoutFromArgs(string $args): void
    {
        $this->layoutMacros[] = '{layout ' . $args . '}';
    }

    /**
     * 将 <foo.bar.baz /> 改写为 <x-val data-var="foo" data-path="bar.baz" />。
     * 只处理自闭合标签。
     *
     * @param string $tpl
     * @return string
     */
    private function rewriteDottedPlaceholders(string $tpl): string
    {
        $re = '/<\s*([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)+)\s*([^>]*)\/>/u';
        return (string)preg_replace_callback($re, function (array $m) {
            $full = $m[1];
            $attrs = $m[2] ?? '';
            $parts = explode('.', $full);
            if (count($parts) < 2) return $m[0];
            $first = array_shift($parts);
            $rest = implode('.', $parts);
            return '<x-val data-var="' . htmlspecialchars($first, ENT_QUOTES) . '" data-path="' . htmlspecialchars($rest, ENT_QUOTES) . '"' . ($attrs ? ' ' . trim($attrs) : '') . '/>';
        }, $tpl);
    }

    

    /**
     * 从元素属性构建 Latte 过滤器链。
     * @param DOMElement $node
     * @param array $skipNames 需要跳过的属性名
     * @return string 以 | 开头的过滤器串或空字符串
     */
    private function buildFilterChainFromAttributes(DOMElement $node, array $skipNames = []): string
    {
        if (!$node->hasAttributes()) return '';
        $filters = '';
        foreach ($node->attributes as $attr) {
            $nameRaw = $attr->nodeName;
            if (in_array($nameRaw, $skipNames, true)) continue;
            $isExpr = str_starts_with($nameRaw, ':');
            $name = $isExpr ? substr($nameRaw, 1) : $nameRaw;
            $val = (string)($attr->nodeValue ?? '');
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
     * 判断节点是否位于 code/pre/samp/kbd 等代码类标签中。
     */
    private function isInCodeLike(DOMNode $node): bool
    {
        $n = $node->parentNode;
        while ($n && $n instanceof \DOMNode) {
            if ($n instanceof DOMElement) {
                $tag = strtolower($n->tagName);
                if (in_array($tag, ['code','pre','samp','kbd'], true)) {
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
                $raw  = (string)$attr->nodeValue;
                $val  = $this->escapeAttrPreservingLatte($raw);
                $q    = $this->chooseAttrQuoteWrapper($raw);
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

    /**
     * 从元素属性构造 include/embed 的参数串。
     */
    private function latteArgsFromIncludeLike(DOMElement $node): string
    {
        $src = $this->getAttrRaw($node, 'src');
        $srcExpr = $this->getAttrRaw($node, ':src');
        $args = [];
        if ($srcExpr !== null) {
            $args[] = $srcExpr;
        } elseif ($src !== null) {
            $args[] = "'" . addslashes($src) . "'";
        }
        foreach ($node->attributes as $attr) {
            $name = $attr->nodeName;
            if ($name === 'src' || $name === ':src') continue;
            $isExpr = str_starts_with($name, ':');
            $key = $isExpr ? substr($name, 1) : $name;
            $val = (string)($attr->nodeValue ?? '');
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

    /**
     * 构造 {xTag}/{xTagAssign} 的参数数组代码。
     */
    private function macroArrayArgs(string $canonicalName, DOMElement $node, bool $isBlock): string
    {
        $targetCode = $this->attrCode($node, 'data') ?? "''";
        $varName = $this->toCamelCase($canonicalName);
        if (!$isBlock) {
            $assignVar = $this->getAttrRaw($node, 'as');
            if ($assignVar !== null && $assignVar !== '') {
                $varName = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$assignVar);
            }
        }
        $asItem = null;
        if ($isBlock) {
            $asItem = $this->getAttrRaw($node, 'as');
            if ($asItem === null || $asItem === '') {
                $asItem = $varName . 'Item';
            }
        }
        $params = $this->buildParamsCodeFromEl($node);

        $parts = [];
        $parts[] = "['type' => '" . addslashes($canonicalName) . "'";
        $parts[] = "'data' => " . $targetCode;
        $pathSpec = $this->attrCode($node, 'path');
        if ($pathSpec !== null) {
            $parts[] = "'path' => " . $pathSpec;
        }
        $parts[] = "'params' => " . $params;
        $parts[] = "'var' => '" . addslashes($varName) . "'";
        if ($isBlock && $asItem !== null) {
            $parts[] = "'as' => '" . addslashes((string)$asItem) . "'";
        }
        $meta = $this->getAttrRaw($node, 'meta');
        if ($meta !== null && $meta !== '') {
            $parts[] = "'meta' => '" . addslashes((string)$meta) . "'";
        }
        $cls = $this->getAttrRaw($node, 'class');
        if ($cls !== null && $cls !== '') {
            $parts[] = "'class' => '" . addslashes((string)$cls) . "'";
        }
        $pth = $this->getAttrRaw($node, 'path');
        if ($pth !== null && $pth !== '') {
            $parts[] = "'path' => '" . addslashes((string)$pth) . "'";
        }
        $debugCode = $this->attrBool($node, 'debug');
        if ($debugCode !== null) {
            $parts[] = "'debug' => " . $debugCode;
        }
        return implode(', ', $parts) . ']';
    }

    /**
     * 收集非控制属性为 params 数组代码。
     */
    private function buildParamsCodeFromEl(DOMElement $node): string
    {
        $params = [];
        foreach ($node->attributes as $attr) {
            $name = $attr->nodeName;
            if (in_array($name, ['as', 'meta', 'data', ':data', 'path', ':path', 'debug'], true)) continue;
            $isExpr = str_starts_with($name, ':');
            $key = $isExpr ? substr($name, 1) : $name;
            $value = $attr->nodeValue ?? '';
            if (!$isExpr) {
                $trim = ltrim((string)$value);
                if ($trim !== '' && $trim[0] === '$') {
                    $isExpr = true;
                }
            }
            $valCode = $isExpr ? (string)$value : ('"' . addslashes((string)$value) . '"');
            $params[] = '"' . addslashes($key) . '" => ' . $valCode;
        }
        return '[' . implode(', ', $params) . ']';
    }

    /**
     * 转义属性值但保留花括号内的 Latte 表达式。
     */
    private function escapeAttrPreservingLatte(string $value): string
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
            $start = (int)$m[0][1];
            $match = (string)$m[0][0];
            $before = substr($value, $offset, $start - $offset);
            $buf .= htmlspecialchars($before, ENT_QUOTES);
            $buf .= $match;
            $offset = $start + strlen($match);
            if ($offset >= $len) break;
        }
        return $buf;
    }

    /**
     * 选择属性值外层引号：尽量避开 Latte 宏中已存在的引号。
     * - 宏内仅有双引号 → 用单引号包裹；仅有单引号 → 用双引号包裹；都有或都无 → 用双引号。
     */
    private function chooseAttrQuoteWrapper(string $raw): string
    {
        $hasD = false; $hasS = false;
        if (preg_match_all('/\{[^}]*\}/u', $raw, $mm)) {
            foreach ($mm[0] as $seg) {
                if (strpos($seg, '"') !== false) $hasD = true;
                if (strpos($seg, "'") !== false) $hasS = true;
                if ($hasD && $hasS) break;
            }
        }
        if ($hasD && !$hasS) return "'";
        if ($hasS && !$hasD) return '"';
        return '"';
    }

    /**
     * 获取原始属性值。
     */
    private function getAttrRaw(DOMElement $node, string $name): ?string
    {
        if ($node->hasAttribute($name)) {
            return (string)$node->getAttribute($name);
        }
        return null;
    }

    /**
     * 解析表达式属性（优先 :name）。
     */
    private function attrExpr(DOMElement $node, string $name): ?string
    { return $this->readAttr($node, $name, 'expr'); }

    /**
     * 解析属性为代码（字面量加引号）。
     */
    private function attrCode(DOMElement $node, string $name): ?string
    { return $this->readAttr($node, $name, 'code'); }

    /**
     * 解析布尔属性为 'true'/'false' 字符串。
     */
    private function attrBool(DOMElement $node, string $name): ?string
    { return $this->readAttr($node, $name, 'bool'); }

    /**
     * 转换为 camelCase。
     */
    private function toCamelCase(string $name): string
    {
        $name = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $name);
        $name = strtolower((string)$name);
        $parts = preg_split('/-+/', (string)$name) ?: [];
        $camel = '';
        foreach ($parts as $i => $p) {
            if ($p === '') continue;
            $camel .= $i === 0 ? $p : ucfirst($p);
        }
        return $camel;
    }
}
