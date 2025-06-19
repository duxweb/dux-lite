<?php

declare(strict_types=1);

namespace Core\Route;

use DI\DependencyException;
use DI\NotFoundException;
use Core\App;
use Core\Bootstrap;
use Core\Handlers\Exception;
use Core\Route\Attribute\RouteGroup;

class Register
{

    public array $app = [];
    public array $path = [];

    /**
     * 设置路由应用
     * @param string $name
     * @param Route $route
     * @return void
     */
    public function set(string $name, Route $route): void
    {
        $route->setApp($name);
        $this->app[$name] = $route;
    }

    /**
     * 获取路由应用
     * @param string $name
     * @return Route
     */
    public function get(string $name): Route
    {

        if (!isset($this->app[$name])) {
            throw new Exception("The routing app [$name] is not registered");
        }
        return $this->app[$name];
    }

    /**
     * 注解路由注册
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function registerAttribute(): void
    {
        $attributes = App::attributes();

        foreach ($attributes as $item) {
            $groupInfo = [];
            foreach ($item["annotations"] as $annotation) {
                if ($annotation["name"] != RouteGroup::class) {
                    continue;
                }
                $groupInfo = $annotation;
            }

            $routeGroup = null;
            if ($groupInfo) {
                $groupClass = $item["class"];
                $groupParams = $groupInfo["params"];
                $appName = $groupParams["app"];
                $groupName = $groupParams["name"];

                if (!$appName) {
                    throw new \Exception("class [" . $groupClass . "] route attribute parameter missing \"app\" ");
                }

                if (!$groupName) {
                    // 获取当前类和应用目录
                    [$_, $_, $name] = $this->parseClass($groupClass);
                    $groupName = $name;
                }

                $routeGroup = $this->get($appName)->group($groupParams["route"], $groupName, ...($groupParams["middleware"] ?? []));
            }


            foreach ($item["annotations"] as $annotation) {
                if ($annotation["name"] != \Core\Route\Attribute\Route::class) {
                    continue;
                }

                $params = $annotation["params"];
                $class = $annotation["class"];
                $name = $params["name"];
                $appName = $params["app"];

                if (!$name) {
                    [$className, $methodName, $name] = $this->parseClass($class);
                }

                if ($routeGroup) {
                    $routeGroup->map(
                        methods: is_array($params["methods"]) ? $params["methods"] : [$params["methods"]],
                        pattern: $params["route"] ?? "",
                        callable: $class,
                        name: lcfirst($methodName),
                        middleware: $params["middleware"] ?? []
                    );
                } else {
                    if (!$appName) {
                        throw new \Exception("class [" . $class . "] route attribute parameter missing \"app\" ");
                    }

                    $this->get($appName)->map(
                        methods: is_array($params["methods"]) ? $params["methods"] : [$params["methods"]],
                        pattern: $params["route"] ?? "",
                        callable: $class,
                        name: lcfirst($methodName),
                        middleware: $params["middleware"] ?? []
                    );
                }
            }
        }
    }

    private function parseClass(string $class): array
    {
        [$className, $methodName] = explode(":", $class, 2);
        $classArr = explode("\\", $className);
        $layout = array_slice($classArr, -3, 1)[0];
        $name = lcfirst($layout) . "." . lcfirst(end($classArr));
        return [$className, $methodName, $name];
    }
}
