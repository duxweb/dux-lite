<?php

declare(strict_types=1);

namespace Core\Resources\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Resource
{

    /**
     * @param string $app 路由注册名
     * @param string $route 路由
     * @param string $name 资源名
     * @param array|false $actions 方法
     * @param array $middleware 中间件
     * @param bool $softDelete 软删除
     */
    public function __construct(
        string      $app,
        string      $route,
        string      $name,
        array|false $actions = [],
        array       $middleware = [],
        bool        $softDelete = false,
    ) {}
}
