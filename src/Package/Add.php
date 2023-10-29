<?php

namespace Dux\Package;

use Dux\Handlers\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Add
{
    public static function main(InputInterface $input, OutputInterface $output, SymfonyStyle $io, string $username, string $password, array $data = [], $update = false): void
    {
        $configFile = base_path('app.json');
        $configLockFile = base_path('app.lock');
        $appJson = [
            "name" => "project",
            "description" => "This is the dux application dependency configuration"
        ];
        $appLockJson = [
            "_readme" => [
                "This file relies on the Dux application to be in a locked state",
                "Read more about it at https://dux.plus"
            ]
        ];
        if (is_file($configFile)) {
            $appJson = Package::getJson($configFile);
        }
        if (is_file($configLockFile)) {
            $appLockJson = Package::getJson($configLockFile);
        }

        $apps = collect();
        $dependencies = collect($appJson['dependencies'] ?: []);
        $packages = collect($appLockJson['packages'] ?: []);
        $composers = collect();
        $node = collect();
        $files = collect();

        // 增加依赖
        if (!$update) {
            foreach ($data as $name => $verType) {
                if (!$dependencies->has($name)) {
                    $dependencies->put($name, $verType);
                }
            }
        } else {
            if ($dependencies->isEmpty()) {
                throw new Exception('Application not installed');
            }
        }

        // 查询未安装依赖
        $names = $dependencies->keys();
        $packageNames = $packages->map(function ($item) {
            return $item->name;
        });
        $diffNames = $names->diff($packageNames);
        $queryData = $dependencies->only($diffNames);

        // 增加更新依赖
        if ($update) {
            foreach ($data as $name) {
                if (!$dependencies->has($name)) {
                    throw new Exception('Application ' . $name . ' not installed');
                }
                if (!$queryData->has($name)) {
                    $queryData->put($name, $dependencies->get($name, 'release'));
                }
            }
        }

        // 获取云端包
        $cloudPackages = collect(Package::query($username, $password, $queryData->toArray(), $io));

        // 过滤未安装或更新包
        $cloudPackages = $cloudPackages->filter(function ($item) use ($packages, $update) {
            $package = $packages->where('name', $item['name'])->first();
            if (!$package) {
                return true;
            }
            if (version_compare($item['ver'], $package['version'], '>')) {
                return true;
            }
            return false;
        });

        if ($cloudPackages->isEmpty()) {
            $output->writeln('<info>No updated applications</info>');
            return;
        }

        $cloudPackages->map(function ($item) use ($output) {
            $output->writeln('<info>find: ' . $item['name'] . ' - ' . $item['ver'] . '</info>');
        });

        // download
        Package::downloadPackages($input, $output, $packages, $dependencies, $apps, $composers, $node, $files, $cloudPackages->toArray());

        // composer install
        Package::composer($output, $composers->toArray());

        // node install
        Package::node($output, $node->toArray());

        // copy files
        Package::copy($output, $files->toArray());

        // write config
        Package::saveConfig($output, $apps->toArray());

        // write lock
        $appJson['dependencies'] = $dependencies->toArray();
        $appLockJson['packages'] = $packages->values()->toArray();
        Package::saveJson($configFile, $appJson);
        Package::saveJson($configLockFile, $appLockJson);

        if (!$composers->isEmpty()) {
            $output->writeln('Run <info>composer install</info> manually');
        }
        if (!$node->isEmpty()) {
            $output->writeln('Run <info>yarn</info> in web path manually');
        }

        $io->success('Add Application Success');
    }

}