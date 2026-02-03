<?php

declare(strict_types=1);

namespace Core\Views;

use Latte\Compiler\Nodes\AuxiliaryNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Extension;
use Carbon\Carbon;

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
            'pagination' => function (Tag $tag) {
                $tag->expectArguments();
                $args = $tag->parser->parseArguments();
                return new AuxiliaryNode(function (PrintContext $context) use ($args) {
                    return $this->printPagination($context, $args);
                });
            },
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

    /** 注册自定义过滤器。 */
    public function getFilters(): array
    {
        $dateFilter = function ($value, string $format = 'Y-m-d', ?string $tz = null): string {
            $zone = $tz ?: date_default_timezone_get();
            try {
                if ($value === null || $value === '') {
                    // 空值直接返回空串，避免误输出当前时间
                    return '';
                }
                if (is_string($value) && strtolower(trim($value)) === 'now') {
                    $dt = now($zone);
                    return $dt->format($format);
                }
                if ($value instanceof \DateTimeInterface) {
                    $dt = $value instanceof Carbon ? $value : Carbon::instance($value);
                    if ($tz) { $dt = $dt->setTimezone($zone); }
                    return $dt->format($format);
                }
                if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                    $dt = Carbon::createFromTimestamp((int)$value, $zone);
                    return $dt->format($format);
                }
                // 其它情况按字符串解析（如 '2025-10-12 08:00'、ISO8601 等）
                $dt = Carbon::parse((string)$value, $zone);
                return $dt->format($format);
            } catch (\Throwable $e) {
                return (string)$value;
            }
        };

        $cutFilter = function ($value, int $length = 50, string $suffix = '…', bool $preserveWords = false, bool $stripTags = false): string {
            if ($value === null) return '';
            if (is_object($value) && !method_exists($value, '__toString')) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $s = (string)$value;
            if ($stripTags) {
                $s = strip_tags($s);
            }
            if ($length <= 0) return '';
            $enc = function_exists('mb_internal_encoding') ? (mb_internal_encoding() ?: 'UTF-8') : 'UTF-8';
            $strlen = function_exists('mb_strlen') ? fn($x) => mb_strlen($x, $enc) : fn($x) => strlen($x);
            $substr = function_exists('mb_substr') ? fn($x,$a,$b) => mb_substr($x,$a,$b,$enc) : fn($x,$a,$b) => substr($x,$a,$b);
            if ($strlen($s) <= $length) return $s;
            $slice = $substr($s, 0, $length);
            if ($preserveWords) {
                $pos = strrpos($slice, ' ');
                if ($pos !== false && $pos > 0) {
                    $slice = $substr($slice, 0, $pos);
                }
            }
            return $slice . $suffix;
        };

        return [
            'date' => $dateFilter,
            // 字符串截取：{$text|cut:50,'…',true,true} 或 {$text|substr:80}
            'cut' => $cutFilter,
            'substr' => $cutFilter,
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

    /** 生成 {pagination} 的运行时代码。 */
    private function printPagination(PrintContext $context, ExpressionNode $args): string
    {
        return $context->format(
            <<<'PHP'
            $__pg = (function($x){ return (is_array($x) && array_key_exists(0,$x) && count($x)===1) ? $x[0] : $x; })(%node);
            echo \Core\Views\Render::pagination(is_array($__pg) ? $__pg : []);
            PHP,
            $args,
        );
    }
}
