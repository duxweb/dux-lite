<?php

declare(strict_types=1);

namespace Core\Plugin;

class PluginRegistry
{
    private static array $registry = [];
    private static ?string $vendorDir = null;

    public static function register(string $packageName, array $config): void
    {
        self::$registry[$packageName] = [
            'name' => $packageName,
            'config' => $config,
            'registered_at' => date('c'),
        ];

        self::writeRegistry();
    }

    public static function unregister(string $packageName): bool
    {
        if (!isset(self::$registry[$packageName])) {
            return false;
        }

        unset(self::$registry[$packageName]);
        self::writeRegistry();

        return true;
    }

    public static function getAll(): array
    {
        return self::$registry;
    }

    public static function get(string $packageName): ?array
    {
        return self::$registry[$packageName] ?? null;
    }

    public static function getByConfigType(string $configType): array
    {
        $result = [];
        foreach (self::$registry as $pluginInfo) {
            if (!empty($pluginInfo[$configType])) {
                $result[$pluginInfo['name']] = $pluginInfo[$configType];
            }
        }
        return $result;
    }

    public static function setVendorDir(string $vendorDir): void
    {
        self::$vendorDir = $vendorDir;
    }

    private static function findVendorDir(): ?string
    {
        if (class_exists(\Core\App::class) && !empty(\Core\App::$basePath)) {
            $vendorDir = rtrim(\Core\App::$basePath, '/\\') . '/vendor';
            if (is_dir($vendorDir)) {
                self::$vendorDir = $vendorDir;
                return $vendorDir;
            }
        }

        if (self::$vendorDir) {
            return self::$vendorDir;
        }

        $currentDir = __DIR__;
        for ($i = 0; $i < 10; $i++) {
            $vendorDir = $currentDir . '/vendor';
            if (is_dir($vendorDir)) {
                self::$vendorDir = $vendorDir;
                return $vendorDir;
            }
            $currentDir = dirname($currentDir);
            if ($currentDir === '/') {
                break;
            }
        }

        return null;
    }

    private static function writeRegistry(): void
    {
        $vendorDir = self::findVendorDir();
        if (!$vendorDir) {
            return;
        }

        $registryFile = $vendorDir . '/duxlite-plugins.json';
        $registryData = [
            'generated' => date('c'),
            'plugins' => self::$registry,
        ];

        file_put_contents($registryFile, json_encode($registryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function loadFromFile(): array
    {
        $vendorDir = self::findVendorDir();
        if (!$vendorDir) {
            return [];
        }

        $registryFile = $vendorDir . '/duxlite-plugins.json';
        if (!file_exists($registryFile)) {
            return [];
        }

        $data = json_decode(file_get_contents($registryFile), true);
        $plugins = $data['plugins'] ?? [];

        self::$registry = $plugins;

        return $plugins;
    }

    public static function reset(): void
    {
        self::$registry = [];
    }

    public static function rebuild(): array
    {
        self::reset();

        $vendorDir = self::findVendorDir();
        if (!$vendorDir) {
            return [];
        }

        $installedFile = $vendorDir . '/composer/installed.php';
        if (!is_file($installedFile)) {
            self::writeRegistry();
            return [];
        }

        $installed = require $installedFile;
        $versions = is_array($installed['versions'] ?? null) ? $installed['versions'] : [];

        foreach ($versions as $packageName => $info) {
            $installPath = $info['install_path'] ?? '';
            if (!$installPath) {
                continue;
            }

            $composerFile = rtrim((string)$installPath, '/\\') . '/composer.json';
            if (!is_file($composerFile)) {
                continue;
            }

            $composer = json_decode(file_get_contents($composerFile), true);
            $config = $composer['extra']['duxlite'] ?? null;
            if (!$config) {
                continue;
            }

            self::$registry[$packageName] = [
                'name' => $packageName,
                'config' => $config,
                'registered_at' => date('c'),
            ];
        }

        self::writeRegistry();

        return self::$registry;
    }
}
