<?php
declare(strict_types=1);

namespace Core\App;

use Core\Bootstrap;

abstract class AppExtend
{
    /**
     * @param Bootstrap $app
     * @return void
     */
    public function init(Bootstrap $app): void
    {
    }


    /**
     * @param Bootstrap $app
     * @return void
     */
    public function register(Bootstrap $app): void
    {
    }

    /**
     * @param Bootstrap $app
     * @return void
     */
    public function boot(Bootstrap $app): void
    {
    }

}