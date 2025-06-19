<?php
declare(strict_types=1);

namespace Core\Route\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class RouteGroup {

    /**
     * @param string $app 路由注册名
     * @param string $route 路由前缀
     * @param array $middleware 中间件
     */
    public function __construct(
        public string $app,
        public string $route,
        public string $name = '',
        public array $middleware = [],
        public bool $auth = true,
    ) {}


}