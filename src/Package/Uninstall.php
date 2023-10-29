<?php

namespace Dux\Package;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Uninstall
{
    public static function main(InputInterface $input, OutputInterface $output, SymfonyStyle $io, string $username, string $password, string $app): void
    {
        $packages = Package::app($username, $password, $app);
        Del::main($input, $output, $io, $packages);

        $configFile = base_path('app.json');
        $appJson = [];
        if (is_file($configFile)) {
            $appJson = Package::getJson($configFile);
        }
        $apps = $appJson['apps'] ?: [];
        if (in_array($app, $apps)) {
            unset($apps[$app]);
        }
        $appJson['apps'] = array_values($apps);
        Package::saveJson($configFile, $appJson);
    }

}