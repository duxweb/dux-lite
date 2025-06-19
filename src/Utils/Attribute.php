<?php

declare(strict_types=1);

namespace Core\Utils;

use RuntimeException;
use ReflectionException;
use Slim\Psr7\Request;
use Slim\Routing\RouteContext;

class Attribute
{
    /**
     * 获取请求类或方法注解参数
     * 
     * @param Request $request 请求对象
     * @param string $name 参数名
     * @return mixed 参数值
     * @throws RuntimeException 运行时异常
     * @throws ReflectionException 反射异常
     */
    public static function getRequestParams(Request $request, string $name): mixed
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        if (!$route) {
            return null;
        }
        $callable = $route->getCallable();
        if (!is_array($callable)) {
            [$class, $method] = explode(':', $callable);
        } else {
            $class = $callable[0];
            $method = $callable[1] ?? null;
        }

        $reflectionClass = new \ReflectionClass($class);
        $attributes = $reflectionClass->getAttributes();

        if ($method) {
            $reflectionMethod = $reflectionClass->getMethod($method);
            $attributes = $reflectionMethod->getAttributes();
            foreach ($attributes as $attribute) {
                $args = $attribute->getArguments();
                if (isset($args[$name])) {
                    return $args[$name];
                }
            }
        }

        foreach ($attributes as $attribute) {
            $args = $attribute->getArguments();
            if (isset($args[$name])) {
                return $args[$name];
            }
        }

        return null;
    }
}
