<?php

declare(strict_types=1);

namespace Core\Plugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

abstract class ComposerPlugin implements PluginInterface
{
    protected Composer $composer;
    protected IOInterface $io;
    private ?array $composerData = null;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;

        $packageName = $this->getPackageName();
        $duxliteConfig = $this->getConfig();

        if ($packageName && $duxliteConfig) {
            PluginRegistry::register($packageName, $duxliteConfig);

            $this->io->write(sprintf(
                '<info>DuxLite plugin registered: %s</info>',
                $packageName
            ));
        }

        $this->onActivate();
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        $packageName = $this->getPackageName();

        if ($packageName && PluginRegistry::unregister($packageName)) {
            $this->io->write(sprintf(
                '<info>DuxLite plugin deactivated: %s</info>',
                $packageName
            ));
        }

        $this->onDeactivate();
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $packageName = $this->getPackageName();

        if ($packageName && PluginRegistry::unregister($packageName)) {
            $this->io->write(sprintf(
                '<info>DuxLite plugin unregistered: %s</info>',
                $packageName
            ));
        }

        $this->onUninstall();
    }

    private function getComposerData(): ?array
    {
        if ($this->composerData !== null) {
            return $this->composerData;
        }

        $reflection = new \ReflectionClass($this);
        $composerFile = dirname($reflection->getFileName(), 3) . '/composer.json';

        if (!file_exists($composerFile)) {
            return $this->composerData = null;
        }

        $data = json_decode(file_get_contents($composerFile), true);
        return $this->composerData = $data ?: null;
    }

    protected function getPackageName(): ?string
    {
        $data = $this->getComposerData();
        return $data['name'] ?? null;
    }

    protected function getConfig(): ?array
    {
        $data = $this->getComposerData();
        return $data['extra']['duxlite'] ?? null;
    }

    protected function onActivate(): void
    {
    }

    protected function onDeactivate(): void
    {
    }

    protected function onUninstall(): void
    {
    }
}