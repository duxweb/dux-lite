<?php

declare(strict_types=1);

namespace Core\Resources;

use Core\App;
use Core\Resources\Attribute\Action;
use Core\Resources\Attribute\Resource;
use DI\DependencyException;
use DI\NotFoundException;
use Exception;

class Register
{


    /**
     * 资源列表
     * @var \Core\Resources\Resource[string]
     */
    public array $app = [];

    /**
     * 设置资源
     * @param string $name
     * @param \Core\Resources\Resource $resource
     * @return void
     */
    public function set(string $name, \Core\Resources\Resource $resource): void
    {
        $this->app[$name] = $resource;
    }

    /**
     * 获取资源
     * @param string $name
     * @return \Core\Resources\Resource
     */
    public function get(string $name): \Core\Resources\Resource
    {

        if (!isset($this->app[$name])) {
            throw new \Core\Handlers\Exception("The resources app [$name] is not registered");
        }
        return $this->app[$name];
    }


    /**
     * 注解路由注册
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     * @throws Exception
     */
    public function registerAttribute(): void
    {
        $attributes = App::attributes();


        foreach ($attributes as $item) {
            $resInfo = [];
            foreach ($item["annotations"] as $annotation) {
                if ($annotation["name"] != Resource::class) {
                    continue;
                }
                $resInfo = $annotation;
            }
            if (!$resInfo) {
                continue;
            }

            $appName = $resInfo["params"]["app"];
            $resName = $resInfo["params"]["name"];

            if (!$appName) {
                throw new \Exception("class [" . $item["name"] . "] resource attribute parameter missing \"app\" ");
            }

            // 设置路由组
            $routeData = App::route()->get($appName);
            $permissionData = App::permission()->get($appName);

            $routeGroup = $routeData->group($resInfo["params"]["route"], $resName);
            $permissionGroup = $permissionData->group($resName, 0);

            // 设置自定义方法
            foreach ($item["annotations"] as $annotation) {
                if ($annotation["name"] != Action::class) {
                    continue;
                }
                $params = $annotation["params"];
                $class = $annotation["class"];
                $name = $params["name"];
                if (!$name) {
                    $name = $this->getMethod($class);
                }

                $routeGroup->map($params["methods"], $params["route"], $class, $name);
                if ($permissionGroup) {
                    $permissionGroup->add($name);
                }
            }

            // 设置CURD方法
            $routeGroup->resources(
                class: $item["class"],
                actions: $resInfo["params"]["actions"] ?? [],
                softDelete: (bool)$resInfo["params"]["softDelete"]
            );

            // 设置权限名称
            $permissionGroup->resources(
                actions: $resInfo["params"]["actions"] ?? [],
                softDelete: (bool)$resInfo["params"]["softDelete"]
            );
        }
    }

    private function getMethod($class): string
    {
        $arr = explode(":", $class, 2);
        return lcfirst($arr[1]);
    }
}
