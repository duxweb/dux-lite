<?php

declare(strict_types=1);

namespace Core\Views\Api;

class RouteResolver
{
    /**
     * 解析 "Class::method" 为 [class, method]。
     * 仅支持命名空间类名 + 双冒号方法，如 "\\App\\Content\\Api\\Article::list"。
     *
     * @return array{0:string,1:string}|null
     */
    public function parse(string $route): ?array
    {
        $route = trim($route);
        $route = trim($route, "\"' ");
        $route = str_replace('：', ':', $route);

        if (strpos($route, '::') === false) {
            return null;
        }
        [$classRaw, $method] = explode('::', $route, 2);
        $classRaw = trim($classRaw);
        $method = trim($method ?: 'list');

        if ($classRaw === '' || str_ends_with($classRaw, '.php')) {
            return null;
        }
        $cls = ltrim($classRaw, '\\');
        $cls = str_replace('/', '\\', $cls);
        if ($cls === '') {
            return null;
        }
        return [$cls, $method];
    }
}
