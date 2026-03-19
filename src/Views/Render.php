<?php

declare(strict_types=1);

namespace Core\Views;

use Core\App;

/**
 * 渲染辅助：引擎初始化、数据解析与内联调试块。
 */
class Render
{

    /**
     * 初始化引擎并设置 data/tpl/{name} 作为临时目录。
     */
    public static function init(string $name): CustomTagEngine
    {
        $engine = new CustomTagEngine();
        if (!is_dir(App::$dataPath . '/tpl/')) {
            mkdir(App::$dataPath . '/tpl/', 0777, true);
        }
        $engine->setTempDirectory(App::$dataPath . '/tpl/' . $name);
        $engine->setCacheScope($name);
        return $engine;
    }

    /**
     * 解析并调用数据源，返回 [data, meta, target, args, error, debugFlag]。
     * 兼容 Latte 包裹的单元素数组形式。
     */
    public static function resolve(array $xt, ApiService $api): array
    {
        if (isset($xt[0]) && is_array($xt[0]) && count($xt) === 1) {
            $xt = $xt[0];
        }
        $target = (string)($xt['data'] ?? '');
        $params = is_array($xt['params'] ?? null) ? $xt['params'] : [];
        $args = [];
        if (!empty($xt['path'])) {
            $spec = $xt['path'];
            $keys = is_array($spec) ? $spec : preg_split('/[\s,]+/', (string)$spec, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($keys)) {
                foreach ($keys as $k) {
                    if (is_string($k) && array_key_exists($k, $params)) {
                        $args[$k] = $params[$k];
                        unset($params[$k]);
                    }
                }
            }
        }
        // 合并显式 args 属性传入的参数（显式 > path 提取）
        if (isset($xt['args'])) {
            $manual = $xt['args'];
            if ($manual === '*') {
                // 特殊值：'*' 表示将当前 params 全量合并到 args（显式覆盖 path）
                $args = array_replace($args, is_array($params) ? $params : []);
            } elseif (is_array($manual)) {
                $args = array_replace($args, $manual);
            } elseif (is_string($manual)) {
                $dec = json_decode($manual, true);
                if (is_array($dec)) {
                    $args = array_replace($args, $dec);
                }
            }
        }

        // 最省事：始终把其余 params 补充到 args（不覆盖已存在键：显式/Path 优先）
        if (is_array($params)) {
            $args = $args + $params;
        }
        $result = $api->fetch($target, $params, $args);
        [$data, $meta] = $api->split($result);
        $error = $api->getLastError();
        $debugFlag = !empty($xt['debug']);
        return [$data, $meta, $target, $args, $error, $debugFlag];
    }
    
    /**
     * 输出安全的内联调试 <pre> 块，不触发额外请求。
     */
    public static function debugBlock(array $pack, string $type = 'list'): string
    {
        [$data, $meta, $target, $args, $error] = $pack;
        $dbg = [
            'type' => $type,
            'target' => $target,
            'data_type' => get_debug_type($data),
            'count' => is_countable($data) ? count($data) : null,
            'meta_type' => get_debug_type($meta),
            'meta_keys' => is_array($meta) ? array_keys($meta) : null,
            'args' => $args,
            'error' => $error,
        ];
        $json = json_encode($dbg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $safe = htmlspecialchars($json ?: 'null', ENT_QUOTES, 'UTF-8');
        return '<pre class="xtag-debug" style="background:#f6f8fa;padding:8px;border-radius:6px;white-space:pre-wrap;word-break:break-word;">' . $safe . '</pre>';
    }

    /**
     * 生成分页 HTML。
     * 支持参数：
     * - base: 基础路径，例如 market 或 market.html
     * - page, total, pageSize, pageParam, window
     * - style/mode: query | path | segment
     * - query: 额外 query 参数数组
     * - separator: path 模式页码分隔符，默认 '-'
     * - omitFirstPage: 首页是否省略页码，默认 true
     * - class/navClass: 分页容器 class
     * - itemClass: 基础按钮 class
     * - activeClass: 当前页额外 class
     * - disabledClass: 禁用按钮额外 class
     * - ellipsisClass: 省略号 class
     */
    public static function pagination(array $opts = []): string
    {
        $page = max(1, (int)($opts['page'] ?? 1));
        $total = max(0, (int)($opts['total'] ?? 0));
        $pageSize = max(1, (int)($opts['pageSize'] ?? 10));
        $pages = (int)ceil($total / $pageSize);
        if ($pages <= 1) {
            return '';
        }

        $base = (string)($opts['base'] ?? '');
        $pageParam = (string)($opts['pageParam'] ?? 'page');
        $window = max(1, (int)($opts['window'] ?? 2));
        $mode = (string)($opts['style'] ?? $opts['mode'] ?? 'query');
        $separator = (string)($opts['separator'] ?? ($mode === 'segment' ? '/page/' : '-'));
        $omitFirstPage = array_key_exists('omitFirstPage', $opts) ? (bool)$opts['omitFirstPage'] : true;
        $navClass = trim((string)($opts['navClass'] ?? $opts['class'] ?? 'pagination'));
        $itemClass = trim((string)($opts['itemClass'] ?? 'page-btn'));
        $activeClass = trim((string)($opts['activeClass'] ?? 'is-active'));
        $disabledClass = trim((string)($opts['disabledClass'] ?? 'is-disabled'));
        $ellipsisClass = trim((string)($opts['ellipsisClass'] ?? 'page-ellipsis'));
        $start = max(1, $page - $window);
        $end = min($pages, $page + $window);

        $query = $opts['query'] ?? [];
        if ($query instanceof \ArrayObject) {
            $query = $query->getArrayCopy();
        } elseif (is_object($query)) {
            $query = (array) $query;
        } elseif (!is_array($query)) {
            $query = [];
        }

        [$basePath, $baseQueryString] = array_pad(explode('?', $base, 2), 2, '');
        $base = $basePath;
        $baseQuery = [];
        if ($baseQueryString !== '') {
            parse_str($baseQueryString, $baseQuery);
        }

        $query = array_merge($baseQuery, $query);
        unset($query[$pageParam]);
        $query = array_filter($query, function ($value) {
            return $value !== '' && $value !== null && $value !== false;
        });

        $link = function (int $p) use ($base, $pageParam, $pages, $mode, $separator, $omitFirstPage, $query): string {
            $p = max(1, min($pages, $p));

            if ($mode === 'path' || $mode === 'segment') {
                $url = $base;
                if (!($omitFirstPage && $p === 1)) {
                    $url .= $separator . $p;
                }
                if (!empty($query)) {
                    $url .= '?' . http_build_query($query);
                }
                return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            }

            $url = $base;
            $params = $query;
            if (!($omitFirstPage && $p === 1)) {
                $params[$pageParam] = $p;
            }
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        };

        $btnClass = fn (bool $active = false, bool $disabled = false): string => trim($itemClass
            . ($active && $activeClass !== '' ? ' ' . $activeClass : '')
            . ($disabled && $disabledClass !== '' ? ' ' . $disabledClass : ''));

        $html = '<nav class="' . htmlspecialchars($navClass, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<a class="' . htmlspecialchars($btnClass(false, $page <= 1), ENT_QUOTES, 'UTF-8') . '" href="' . $link(1) . '">«</a>';
        $html .= '<a class="' . htmlspecialchars($btnClass(false, $page <= 1), ENT_QUOTES, 'UTF-8') . '" href="' . $link($page <= 1 ? 1 : $page - 1) . '">‹</a>';

        if ($start > 1) {
            $html .= '<a class="' . htmlspecialchars($btnClass(), ENT_QUOTES, 'UTF-8') . '" href="' . $link(1) . '">1</a>';
            if ($start > 2) {
                $html .= '<span class="' . htmlspecialchars($ellipsisClass, ENT_QUOTES, 'UTF-8') . '">…</span>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $html .= '<a class="' . htmlspecialchars($btnClass($i === $page), ENT_QUOTES, 'UTF-8') . '" href="' . $link($i) . '">' . $i . '</a>';
        }

        if ($end < $pages) {
            if ($end < $pages - 1) {
                $html .= '<span class="' . htmlspecialchars($ellipsisClass, ENT_QUOTES, 'UTF-8') . '">…</span>';
            }
            $html .= '<a class="' . htmlspecialchars($btnClass(), ENT_QUOTES, 'UTF-8') . '" href="' . $link($pages) . '">' . $pages . '</a>';
        }

        $html .= '<a class="' . htmlspecialchars($btnClass(false, $page >= $pages), ENT_QUOTES, 'UTF-8') . '" href="' . $link($page >= $pages ? $pages : $page + 1) . '">›</a>';
        $html .= '<a class="' . htmlspecialchars($btnClass(false, $page >= $pages), ENT_QUOTES, 'UTF-8') . '" href="' . $link($pages) . '">»</a>';
        $html .= '</nav>';

        return $html;
    }
}
