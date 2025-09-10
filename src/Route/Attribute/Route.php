<?php
declare(strict_types=1);

namespace Core\Route\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{

    /**
     * @param array|string $methods 请求方法
     * @param string $route 路由匹配
     * @param string $name 路由名称
     * @param string|null $app 路由注册名
     * @param bool $auth 是否需要登录
     */
    public function __construct(
        public array|string $methods,
        public string       $route,
        public string       $name = '',
        public ?string      $app = null,
        public bool         $auth = true,
        public int          $priority = 0,
    )
    {
    }

}
