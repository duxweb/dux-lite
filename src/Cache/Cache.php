<?php
declare(strict_types=1);

namespace Dux\Cache;

use Dux\App;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Helper\Psr16Adapter;

class Cache
{

    public static function init(string $type): Psr16Adapter
    {
        if ($type === "files") {
            $config = [];
            $config["path"] = App::$dataPath . "/cache";
        }
        if ($type === "redis") {
            $driver = App::config('cache')->get('driver', 'default');
            $config = App::config('database')->get($type . ".drivers." . $driver);
        }
        return new Psr16Adapter($type, new ConfigurationOption($config));
    }
}