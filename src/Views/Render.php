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
     * 支持参数：base, page, total, pageSize, pageParam, window
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
        $start = max(1, $page - $window);
        $end = min($pages, $page + $window);
        $sep = str_contains($base, '?') ? '&' : '?';

        $link = function (int $p) use ($base, $pageParam, $sep): string {
            $url = $base . $sep . $pageParam . '=' . $p;
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        };

        $html = '<nav class="pagination">';
        $html .= '<a class="page-btn' . ($page <= 1 ? ' is-disabled' : '') . '" href="' . $link(1) . '">«</a>';
        $html .= '<a class="page-btn' . ($page <= 1 ? ' is-disabled' : '') . '" href="' . $link($page - 1) . '">‹</a>';

        if ($start > 1) {
            $html .= '<a class="page-btn" href="' . $link(1) . '">1</a>';
            if ($start > 2) {
                $html .= '<span class="page-ellipsis">…</span>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $html .= '<a class="page-btn' . ($i === $page ? ' is-active' : '') . '" href="' . $link($i) . '">' . $i . '</a>';
        }

        if ($end < $pages) {
            if ($end < $pages - 1) {
                $html .= '<span class="page-ellipsis">…</span>';
            }
            $html .= '<a class="page-btn" href="' . $link($pages) . '">' . $pages . '</a>';
        }

        $html .= '<a class="page-btn' . ($page >= $pages ? ' is-disabled' : '') . '" href="' . $link($page + 1) . '">›</a>';
        $html .= '<a class="page-btn' . ($page >= $pages ? ' is-disabled' : '') . '" href="' . $link($pages) . '">»</a>';
        $html .= '</nav>';

        return $html;
    }
}
