<?php

namespace Dux\Package;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Install
{
    public static function main(OutputInterface $output, string $username, string $password, string $app): void
    {
        $info = Package::app($username, $password, $app);
        $packages = $info['packages'];
        Add::main($output, $username, $password, $packages);

        $configFile = base_path('app.json');
        $appJson = [];
        if (is_file($configFile)) {
            $appJson = Package::getJson($configFile);
        }
        $apps = $appJson['apps'] ?: [];
        $apps[$app] = $info['apps'][0]['time'];
        $appJson['apps'] = $apps;

        Package::saveJson($configFile, $appJson);
    }

}