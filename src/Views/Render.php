<?php

declare(strict_types=1);

namespace Core\Views;

use Core\App;
use Latte\Engine;

class Render
{

    public static function init(string $name): Engine
    {
        $latte = new Engine;
        if (!is_dir(App::$dataPath . '/tpl/')) {
            mkdir(App::$dataPath . '/tpl/', 0777, true);
        }
        $latte->setTempDirectory(App::$dataPath . '/tpl/' . $name);
        return $latte;
    }
}
