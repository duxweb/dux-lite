<?php

declare(strict_types=1);

namespace Core\Plugin;

use Core\Bootstrap;

interface PluginProvider
{
    /**
     * 注册插件
     * @param Bootstrap $bootstrap
     * @return void
     */
    public static function register(Bootstrap $bootstrap): void;

    /**
     * 启动插件
     * @param Bootstrap $bootstrap
     * @return void
     */
    public static function boot(Bootstrap $bootstrap): void;

    /**
     * 应用入口
     *
     * @return AppExtend[]
     */
    public static function apps(): array;


}