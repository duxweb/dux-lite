<?php

declare(strict_types=1);

namespace Core\Plugin;

use Core\App;
use Core\Bootstrap;
use Core\App\AppExtend;

class Plugin
{
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        PluginRegistry::loadFromFile();

        $plugins = PluginRegistry::getAll();

        foreach ($plugins as $pluginInfo) {
            $providers = $pluginInfo['config']['providers'] ?? [];
            if (is_array($providers)) {
                foreach ($providers as $provider) {
                    if (class_exists($provider) && !self::isProviderRegistered($provider)) {
                        App::$registerPlugin[] = new $provider();
                    }
                }
            }
        }

        self::$initialized = true;
    }

    private static function isProviderRegistered(string $providerClass): bool
    {
        foreach (App::$registerPlugin as $plugin) {
            if (get_class($plugin) === $providerClass) {
                return true;
            }
        }
        return false;
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