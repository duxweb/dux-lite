<?php

declare(strict_types=1);

namespace Core\Route;

use Slim\Routing\RouteCollectorProxy;

class Route
{

    private array $middleware = [];
    private array $group = [];
    private array $data = [];
    private string $app = "";

    /**
     * @param string $pattern
     * @param string|null $name
     * @param object ...$middleware
     */
    public function __construct(public string $pattern = "", public ?string $name = "", object ...$middleware)
    {
        $this->middleware = $middleware;
    }

    public function setApp(string $app): void
    {
        $this->app = $app;
    }

    /**
     * 分组
     * @param string $pattern
     * @param object ...$middleware
     * @return Route
     */
    public function group(string $pattern, ?string $name = "", object ...$middleware): Route
    {
        $group = new Route($pattern, $name ? ($this->name ? $this->name . "." . $name : $name) : '', ...$middleware);
        $group->setApp($this->app);
        $this->group[] = $group;
        return $group;
    }

    /**
     * get
     * @param string $pattern
     * @param callable|object|string $callable
     * @param string $name
     * @param int $priority
     * @return void
     */
    public function get(string $pattern, callable|object|string $callable, string $name, int $priority = 0): void
    {
        $this->map(["GET"], $pattern, $callable, $name, [], $priority);
    }

    /**
     * post
     * @param string $pattern
     * @param callable|object|string $callable
     * @param string $name
     * @param int $priority
     * @return void
     */
    public function post(string $pattern, callable|object|string $callable, string $name, int $priority = 0): void
    {
        $this->map(["POST"], $pattern, $callable, $name, [], $priority);
    }

    /**
     * put
     * @param string $pattern
     * @param callable|object|string $callable
     * @param string $name
     * @param int $priority
     * @return void
     */
    public function put(string $pattern, callable|object|string $callable, string $name, int $priority = 0): void
    {
        $this->map(["PUT"], $pattern, $callable, $name, [], $priority);
    }

    /**
     * delete
     * @param string $pattern
     * @param callable|object|string $callable
     * @param string $name
     * @param int $priority
     * @return void
     */
    public function delete(string $pattern, callable|object|string $callable, string $name, int $priority = 0): void
    {
        $this->map(["DELETE"], $pattern, $callable, $name, [], $priority);
    }

    /**
     * options
     * @param string $pattern
     * @param callable|object|string $callable
     * @param string $name
     * @param int $priority
     * @return void
     */
    public function options(string $pattern, callable|object|string $callable, string $name, int $priority = 0): void
    {
        $this->map(["OPTIONS"], $pattern, $callable, $name, [], $priority);
    }

    /**
     * patch
     * @param string $pattern
     * @param callable|object|string $callable
     * @param string $name
     * @param int $priority
     * @return void
     */
    public function patch(string $pattern, callable|object|string $callable, string $name, int $priority = 0): void
    {
        $this->map(["PATCH"], $pattern, $callable, $name, [], $priority);
    }

    /**
     * any
     * @param string $pattern
     * @param callable|object|string $callable
     * @param string $name
     * @param int $priority
     * @return void
     */
    public function any(string $pattern, callable|object|string $callable, string $name, int $priority = 0): void
    {
        $this->map(["ANY"], $pattern, $callable, $name, [], $priority);
    }


    public static array $actions = ['list', 'show', 'create', 'edit', 'store', 'delete'];

    /**
     * resources
     * @param string $class
     * @param array|false $actions
     * @param bool $softDelete
     * @return Route
     */
    public function resources(string $class, array|false $actions = [], bool $softDelete = false): self
    {
        if ($actions === false) {
            return $this;
        }

        if (!$actions) {
            $actions = self::$actions;
        }

        $actions = array_intersect(self::$actions, $actions);

        if ($softDelete) {
            $actions = [...$actions, 'trash', 'restore'];
        }

        if (in_array("list", $actions)) {
            $this->get('', "$class:list", "list", 100);
        }
        if (in_array("show", $actions)) {
            $this->get("/{id:[0-9]+}", "$class:show", "show", 100);
        }
        if (in_array("create", $actions)) {
            $this->post("", "$class:create", "create", 100);
        }
        if (in_array("edit", $actions)) {
            $this->put("/{id:[0-9]+}", "$class:edit", "edit", 100);
        }
        if (in_array("store", $actions)) {
            $this->patch("/{id:[0-9]+}", "$class:store", "store", 100);
        }
        if (in_array("delete", $actions)) {
            $this->delete("/{id:[0-9]+}", "$class:delete", "delete", 100);
            $this->delete("", "$class:deleteMany", "deleteMany", 100);
        }
        if (in_array("trash", $actions)) {
            $this->delete("/{id:[0-9]+}/trash", "$class:trash", "trash", 100);
        }
        if (in_array("restore", $actions)) {
            $this->put("/{id:[0-9]+}/restore", "$class:restore", "restore", 100);
        }
        return $this;
    }

    /**
     * map
     * @param string|array $methods [GET, POST, PUT, DELETE, OPTIONS, PATCH]
     * @param string $pattern
     * @param string|callable $callable function(Request $request, Response $response)
     * @param string|null $name
     * @param array $middleware
     * @param int $priority
     * @return void
     */
    public function map(string|array $methods, string $pattern, string|callable $callable, ?string $name, array $middleware = [], int $priority = 0): void
    {
        $this->data[] = [
            "methods" => is_array($methods) ? $methods : [$methods],
            "pattern" => $pattern,
            "callable" => $callable,
            "name" => $name ? ($this->name ? $this->name . "." . $name : $name) : '',
            "middleware" => $middleware ?: [],
            "priority" => $priority,
            "score" => $this->computeScore($pattern),
        ];
    }

    private function computeScore(string $pattern): int
    {
        $len = strlen($pattern);
        $dynamic = 0;
        $greedyPenalty = 0;
        // count dynamic tokens: {...} or regex parentheses, wildcards
        $dynamic += substr_count($pattern, '{');
        $dynamic += substr_count($pattern, '(');
        $dynamic += substr_count($pattern, '*');
        if (str_contains($pattern, '.*')) {
            $greedyPenalty += 1000;
        }
        // higher is better: prefer longer static, fewer dynamics, less greedy
        return max(0, $len - $dynamic * 10 - $greedyPenalty);
    }

    /**
     * 解析树形路由
     * @param string $pattern
     * @param array $middleware
     * @return array
     */
    public function parseTree(string $pattern = "", array $middleware = []): array
    {
        $pattern = $pattern ?: $this->pattern;
        foreach ($this->middleware as $vo) {
            // 保留中间件实例或类名本身，不再转换为类名字符串
            $middleware[] = $vo;
        }
        $data = [];
        foreach ($this->data as $route) {
            $route["pattern"] = $pattern . $route["pattern"];
            // 保留路由自身中间件的原始形式（对象或类名）
            $routeMiddleware = $route['middleware'];

            $data[] = [
                "name" => $route["name"],
                "pattern" => $route["pattern"],
                "methods" => $route["methods"],
                "middleware" => array_filter([...$middleware, ...$routeMiddleware])
            ];
        }
        foreach ($this->group as $group) {
            $data[] = $group->parseTree($pattern . $group->pattern, $middleware);
        }

        return [
            "pattern" => $pattern,
            "data" => $data
        ];
    }


    /**
     * 解析路由列表
     * @param string $pattern
     * @param array $middleware
     * @return array
     */
    public function parseData(string $pattern = "", array $middleware = []): array
    {
        $pattern = $pattern ?: $this->pattern;
        foreach ($this->middleware as $vo) {
            $middleware[] = $vo;
        }
        $data = [];
        foreach ($this->data as $route) {
            $route["pattern"] = $pattern . $route["pattern"];
            $routeMiddleware = $route['middleware'];

            $data[] = [
                "name" => $route["name"],
                "pattern" => $route["pattern"],
                "methods" => $route["methods"],
                "middleware" => array_filter([...$middleware, ...$routeMiddleware])
            ];
        }
        foreach ($this->group as $group) {
            $data = [...$data, ...$group->parseData($pattern . $group->pattern, $middleware)];
        }
        return $data;
    }


    /**
     * 运行路由注册
     * @param RouteCollectorProxy $route
     * @return void
     */
    public function run(RouteCollectorProxy $route): void
    {
        $app = $this->app;
        $all = $this->collectAll($this->pattern, []);
        // Sort globally within this tree: priority DESC, score DESC
        usort($all, function ($a, $b) {
            $pa = $a['priority'] ?? 0; $pb = $b['priority'] ?? 0;
            if ($pa !== $pb) return $pb <=> $pa;
            $sa = $a['score'] ?? 0; $sb = $b['score'] ?? 0;
            if ($sa !== $sb) return $sb <=> $sa;
            return 0;
        });
        foreach ($all as $item) {
            $r = $route->map($item['methods'], $item['pattern'], $item['callable'])
                ->setName($item['name'])
                ->setArgument('app', $app);
            foreach ($item['middleware'] as $mw) {
                $r->add($mw);
            }
        }
    }

    /**
     * 扁平导出当前路由树的全部路由，包含完整 pattern 与聚合后的中间件。
     * 用于全局排序后一次性注册，避免通配路由遮蔽静态路由。
     *
     * @return array<int, array{
     *   app:string, methods:array, pattern:string, callable:mixed, name:string,
     *   middleware:array, priority:int, score:int
     * }>
     */
    public function exportFlat(): array
    {
        return $this->collectAll($this->pattern, []);
    }

    private function collectAll(string $prefix, array $mwPrefix): array
    {
        $out = [];
        $mwHere = [...$mwPrefix];
        foreach ($this->middleware as $vo) {
            $mwHere[] = $vo;
        }
        foreach ($this->data as $route) {
            $pattern = $prefix . $route['pattern'];
            $routeMw = [...$mwHere];
            foreach ($route['middleware'] as $m) {
                $routeMw[] = $m;
            }
            $out[] = [
                'app' => $this->app,
                'methods' => $route['methods'],
                'pattern' => $pattern,
                'callable' => $route['callable'],
                'name' => $route['name'],
                'middleware' => $routeMw,
                'priority' => $route['priority'] ?? 0,
                'score' => $this->computeScore($pattern),
            ];
        }
        foreach ($this->group as $group) {
            $out = [...$out, ...$group->collectAll($prefix . $group->pattern, $mwHere)];
        }
        return $out;
    }

    
}
