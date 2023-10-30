<?php

namespace Dux\Package;

use Dux\Handlers\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Update
{
    public static function main(InputInterface $input, OutputInterface $output, SymfonyStyle $io, string $username, string $password, ?string $app): void
    {
        $configFile = base_path('app.json');
        if (!is_file($configFile)) {
            throw new Exception('The app.json file does not exist');
        }
        $appJson = Package::getJson($configFile);
        $apps = array_keys($appJson['apps']);
        $info = Package::app($username, $password, $app ?: implode(',', $apps));
        $packages = $info['packages'];
        if (!$packages) {
            $output->writeln('<info>No updated applications</info>');
            return;
        }
        Add::main($input, $output, $io, $username, $password, $packages, true);
    }

}