<?php

declare(strict_types=1);

namespace Core\Views;

use Core\Views\Api\Invoker;
use Core\Views\Api\Normalizer;
use Core\Views\Api\RouteResolver;

/**
 * 模板数据调用器：执行 Class::method 并标准化返回结构。
 */
class ApiService
{
    /** @var null|callable(string, array): iterable */
    private $fetcher = null;
    private ?array $lastError = null;
    private RouteResolver $routes;
    private Invoker $invoker;
    private Normalizer $normalizer;

    public function __construct(?RouteResolver $routes = null, ?Invoker $invoker = null, ?Normalizer $normalizer = null)
    {
        $this->routes = $routes ?? new RouteResolver();
        $this->invoker = $invoker ?? new Invoker();
        $this->normalizer = $normalizer ?? new Normalizer();
    }

    /**
     * 注入自定义获取器：fn(string $route, array $query): iterable
     */
    public function setFetcher(callable $fetcher): void
    {
        $this->fetcher = $fetcher;
    }

    /**
     * @param string $route 传入 data 指定的“Class::method”（例如 "\\App\\Content\\Api\\Article::list"）
     * @param array $query  查询参数
     * @param array $pathArgs 路径参数（方法第三参 $args）
     */
    /**
     * 调用 "Class::method"，传入查询参数与可选路径参数。
     *
     * @return iterable
     */
    public function fetch(string $route, array $query = [], array $pathArgs = []): iterable
    {
        $this->lastError = null;
        if ($this->fetcher) {
            return ($this->fetcher)($route, $query);
        }
        $cm = $this->routes->parse($route);
        if ($cm !== null) {
            [$cls, $method] = $cm;
            try {
                $res = $this->invoker->invoke($cls, $method, $query, $pathArgs);
                return $this->normalizer->normalizeFetchResult($res);
            } catch (\Throwable $e) {
                $this->lastError = [
                    'exception' => get_debug_type($e),
                    'message' => $e->getMessage(),
                    'class' => $cls,
                    'method' => $method,
                    'query' => $query,
                ];
                return [];
            }
        }

        $this->lastError = [
            'exception' => 'InvalidRoute',
            'message' => 'data must be "Class::method" (e.g. \\App\\Content\\Api\\Article::list)',
            'route' => $route,
            'query' => $query,
        ];
        return [];
    }

    /** 仅按 data 传入的“Class::method”直接调用。 */
    /**
     * 便捷直调 "Class::method"。
     *
     * @return iterable
     */
    public function call(string $methodSpec, array $query = []): iterable
    {
        $this->lastError = null;
        $cm = $this->routes->parse($methodSpec);
        if ($cm === null) {
            $this->lastError = [
                'exception' => 'InvalidTarget',
                'message' => 'data must be "Class::method" (e.g. \\App\\Content\\Api\\Article::list)',
                'data' => $methodSpec,
                'query' => $query,
            ];
            return [];
        }
        [$cls, $method] = $cm;
        try {
            $res = $this->invoker->invokeDirect($cls, $method, $query);
            return $this->normalizer->normalizeDirectResult($res);
        } catch (\Throwable $e) {
            $this->lastError = [
                'exception' => get_debug_type($e),
                'message' => $e->getMessage(),
                'class' => $cls,
                'method' => $method,
                'query' => $query,
            ];
            
            return [];
        }
    }

    /**
     * 获取最近一次错误信息。
     *
     * @return array<string,mixed>|null
     */
    public function getLastError(): ?array
    {
        return $this->lastError;
    }

    /**
     * 标准化返回结构为 [data, meta]。
     *
     * @return array{0:mixed,1:mixed}
     */
    public function split(mixed $result): array
    {
        return $this->normalizer->split($result);
    }

    /**
     * 拉取并标准化为 [data, meta]。
     *
     * @return array{0:mixed,1:mixed}
     */
    public function fetchAndUnpack(string $route, array $params = []): array
    {
        return $this->split($this->fetch($route, $params));
    }

    /**
     * 为 foreach 提供安全的可迭代对象。
     */
    public function iterableOf(mixed $data): iterable
    {
        return $this->normalizer->iterableOf($data);
    }
}
