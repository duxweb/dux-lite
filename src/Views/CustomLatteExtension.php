<?php

declare(strict_types=1);

namespace Core\Views;

use Latte\Compiler\Nodes\AuxiliaryNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Extension;

/**
 * Latte 扩展：注册 {xTag}/{xTagAssign} 标签并提供 'api' 服务。
 */
class CustomLatteExtension extends Extension
{
    private ApiService $api;

    /** 安装默认的 ApiService。 */
    public function __construct()
    {
        $this->api = new ApiService();
    }

    /** 注册自定义标签。 */
    public function getTags(): array
    {
        return [
            'xTag' => function (Tag $tag) {
                $tag->expectArguments();
                $args = $tag->parser->parseArguments();
                if ($tag->void) {
                    return new AuxiliaryNode(function (PrintContext $context) use ($args) {
                        return $this->printXTag($context, $args, null);
                    });
                }
                return function (...$cbArgs) use ($args) {
                    $content = $cbArgs[1] ?? $cbArgs[0] ?? null;
                    return new AuxiliaryNode(function (PrintContext $context) use ($args, $content) {
                        return $this->printXTag($context, $args, $content);
                    });
                };
            },

            'xTagAssign' => function (Tag $tag) {
                $tag->expectArguments();
                $args = $tag->parser->parseArguments();
                return new AuxiliaryNode(function (PrintContext $context) use ($args) {
                    return $this->printAssign($context, $args);
                });
            },
        ];
    }

    /** 提供模板可用的服务。 */
    public function getProviders(): array
    {
        return [
            'api' => $this->api,
        ];
    }

    // 不再对外暴露 setApiFetcher；数据源通过 data="Class::method" 调用

    /** 生成 {xTag} 的运行时代码。 */
    private function printXTag(PrintContext $context, ExpressionNode $args, ?\Latte\Compiler\Nodes\AreaNode $content): string
    {
        $open = $context->format(
            <<<'PHP'
            $__xt = (function($x){ return (is_array($x) && array_key_exists(0,$x) && count($x)===1) ? $x[0] : $x; })(%node);
            $__pack = \Core\Views\Render::resolve($__xt, $this->global->api);
             
            $__var = $__xt['var'] ?? 'data';
            ${$__var} = $__pack[0];
            $__meta = $__pack[1];
            if (!empty($__xt['meta']) && $__meta !== null) { ${$__xt['meta']} = $__meta; }
            if (($__pack[5] ?? false)) { echo \Core\Views\Render::debugBlock($__pack, 'list'); }
            $__as = $__xt['as'] ?? ($__var . 'Item');
            foreach ($this->global->api->iterableOf(${$__var}) as ${$__as}) {
            PHP,
            $args,
        );
        $inner = $content ? $content->print($context) : '';
        $close = $context->format("\n}\n");
        return $open . $inner . $close;
    }

    /** 生成 {xTagAssign} 的运行时代码。 */
    private function printAssign(PrintContext $context, ExpressionNode $args): string
    {
        return $context->format(
            <<<'PHP'
            $__xt = (function($x){ return (is_array($x) && array_key_exists(0,$x) && count($x)===1) ? $x[0] : $x; })(%node);
            $__pack = \Core\Views\Render::resolve($__xt, $this->global->api);
            $__var = $__xt['var'] ?? 'data';
            ${$__var} = $__pack[0];
            $__meta = $__pack[1];
            if (!empty($__xt['meta']) && $__meta !== null) { ${$__xt['meta']} = $__meta; }
            if (($__pack[5] ?? false)) { echo \Core\Views\Render::debugBlock($__pack, 'info'); }
            PHP,
            $args,
        );
    }
}
