<?php

declare(strict_types=1);

namespace Core\Plugin;

use Core\App;
use Core\Bootstrap;
use Core\App\AppExtend;

class Plugin
{
    public static function init(): void
    {
        $packages = \Composer\InstalledVersions::getAllRawData();

        foreach ($packages as $installed) {
            if (!isset($installed['versions'])) {
                continue;
            }

            foreach ($installed['versions'] as $packageName => $packageData) {
                if (!isset($packageData['type']) || $packageData['type'] !== 'duxlite-plugin') {
                    continue;
                }

                $providers = $packageData['extra']['providers'];
                if (!$providers || !is_array($providers)) {
                    continue;
                }

                foreach ($providers as $provider) {
                    if (class_exists($provider) && !in_array($provider, App::$registerPlugin)) {
                        App::$registerPlugin[] = new $provider();
                    }
                }
            }
        }
    }

    public static function register(Bootstrap $bootstrap): void
    {
        foreach (App::$registerPlugin as $plugin) {
            if (!method_exists($plugin, 'register')) {
                continue;
            }
            $plugin->register($bootstrap);
        }
    }

    public static function boot(Bootstrap $bootstrap): void
    {
        foreach (App::$registerPlugin as $plugin) {
            if (!method_exists($plugin, 'boot')) {
                continue;
            }
            $plugin->boot($bootstrap);
        }
    }

    /**
     * 获取应用入口
     * @return AppExtend[]
     */
    public static function apps(): array
    {
        $apps = [];
        foreach (App::$registerPlugin as $plugin) {
            if (!method_exists($plugin, 'apps')) {
                continue;
            }
            $apps[] = $plugin->apps();
        }
        return $apps;
    }

}