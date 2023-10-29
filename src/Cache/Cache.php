<?php
declare(strict_types=1);

namespace Dux\Cache;

use Dux\App;
use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Helper\Psr16Adapter;

class Cache
{

    public static function init(string $type): Psr16Adapter
    {
        $driverConfig = [];
        if ($type != 'files') {
            $driver = App::config('cache')->get('driver', 'default');
            $driverConfig = App::config('database')->get($type . ".drivers." . $driver);
        }

        $config = match ($type) {
            'redis' => $driverConfig,
            'files' => [
                'path' => App::$dataPath . "/cache"
            ]
        };


        return new Psr16Adapter($type, new ConfigurationOption($config));
    }
}