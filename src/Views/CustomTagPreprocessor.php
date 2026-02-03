<?php

declare(strict_types=1);

namespace Core\Views;

use DOMElement;
use DOMNode;
use Core\Views\Preprocess\ArgsBuilder;
use Core\Views\Preprocess\AttrResolver;
use Core\Views\Preprocess\BuiltinHandlers;
use Core\Views\Preprocess\BuiltinRegistry;
use Core\Views\Preprocess\NodeEmitter;
use Core\Views\Preprocess\TemplateParser;

class CustomTagPreprocessor
{
    public const VERSION = '2025-09-10-5';
    /** @var string[] */
    private array $layoutMacros = [];
    private BuiltinRegistry $registry;
    private AttrResolver $attrs;
    private ArgsBuilder $args;
    private TemplateParser $parser;
    private NodeEmitter $emitter;
    /** @var string[] 允许的标签前缀（按需扩展）。默认支持 'x-' 前缀。*/
    private array $tagPrefixes = ['x-'];
    /**
     * 可前缀化的内置标签集合（包含 embed，用于支持 x-embed）。
     * @var string[]
     */
    private array $prefixableTags = [
        'layout','include','embed','block','empty','if','elseif','else','for','foreach','loop','list','info','pagination'
    ];

    /**
     * 构造函数。
     */
    public function __construct()
    {
        $this->registry = new BuiltinRegistry();
        $this->attrs = new AttrResolver();
        $this->args = new ArgsBuilder($this->attrs);
        $this->parser = new TemplateParser();
        (new BuiltinHandlers())->register($this->registry);
        $this->emitter = new NodeEmitter(
            $this,
            $this->registry,
            $this->attrs,
            $this->args,
            $this->tagPrefixes,
            $this->prefixableTags
        );
    }

    /**
     * 注册元素处理器：返回渲染后的字符串。
     */
    public function addElementHandler(string $tagName, callable $handler): void
    {
        $this->registry->add($tagName, $handler);
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
        $fragment = $this->parser->parseFragment($template);

        $this->layoutMacros = [];
        $out = $this->emitter->renderNodeList($fragment->childNodes);

        if ($this->layoutMacros) {
            $out = $this->layoutMacros[0] . "\n" . $out;
        }

        return $out;
    }


    // ===== 供处理器调用的公共轻量 API =====

    /** 渲染一个元素的所有子节点。 */
    public function renderChildren(DOMElement $node): string
    {
        return $this->emitter->renderNodeList($node->childNodes);
    }

    /** 渲染单个节点（委托私有渲染）。 */
    public function renderSingle(DOMNode $node): string
    {
        return $this->emitter->renderNode($node);
    }

    /** 读取原始属性值。 */
    public function getAttr(DOMElement $node, string $name): ?string
    {
        return $this->attrs->getAttrRaw($node, $name);
    }

    /** 表达式属性（优先 :name）。 */
    public function attrExprVal(DOMElement $node, string $name): ?string
    {
        return $this->attrs->attrExpr($node, $name);
    }

    /** 代码属性（字面量自动加引号）。 */
    public function attrCodeVal(DOMElement $node, string $name): ?string
    {
        return $this->attrs->attrCode($node, $name);
    }

    /** 构造 {xTag}/{xTagAssign} 的参数数组代码（供处理器使用）。 */
    public function buildMacroArgs(string $canonicalName, DOMElement $node, bool $isBlock): string
    {
        return $this->args->macroArrayArgs($canonicalName, $node, $isBlock);
    }

    /** 从语义名生成变量名。 */
    public function toVarName(string $name): string
    {
        return $this->args->toCamelCase($name);
    }

    /** 供处理器构造 include/embed 参数串。 */
    public function includeArgsFromEl(DOMElement $node): string
    {
        return $this->args->includeArgsFromEl($node);
    }

    /** 供处理器注册 layout 宏头部。 */
    public function addLayoutFromArgs(string $args): void
    {
        $this->layoutMacros[] = '{layout ' . $args . '}';
    }
}
