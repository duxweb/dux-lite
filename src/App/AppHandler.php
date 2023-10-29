<?php
declare(strict_types=1);

namespace Dux\App;

use ErrorException;
use Nette\Utils\FileSystem;
use Noodlehaus\Config;

class AppHandler
{

    public static function install(string $name, bool $ignore = false): void
    {
        $dir = "./vendor/$name";
        if (!is_dir($dir)) {
            throw new ErrorException('The application already exists');
        }
        $composerFile = $dir . '/composer.json';
        if (!is_file($composerFile)) {
            throw new ErrorException('The application configuration does not exist');

        }
        $config = json_decode(file_get_contents($composerFile), true);
        $extra = $config['extra'];
        $duxExtra = $extra['dux'] ?: [];

        $apps = [];
        foreach ($duxExtra as $item) {

            $target = $item['target'];
            $source = $item['source'];
            $ignore = $item['ignore'] ?: $ignore;

            $list = glob("$dir/$source/*");
            foreach ($list as $vo) {

                $relativeDir = $target . "/" . basename($vo);
                $targetDir = "./$relativeDir";
                if (str_contains($targetDir, '/app')) {
                    $apps[] = basename($vo);
                }
                if ($ignore && (is_dir($targetDir) || is_file($targetDir))) {
                    continue;
                }
                FileSystem::copy($vo, $targetDir);
                echo "  - $targetDir Copy $relativeDir \n";
            }
        }

        // config
        $configFile = "./config/app.yaml";
        $conf = Config::load($configFile);
        $registers = $conf->get("registers", []);
        foreach ($apps as $app) {
            $name = "\\App\\$app\\App";
            if (in_array($name, (array)$registers)) {
                continue;
            }
            $registers[] = $name;
        }
        $conf->set("registers", $registers);
        $conf->toFile($configFile);
    }

    public static function uninstall(string $name): void
    {
        $dir = "./vendor/$name";
        if (!is_dir($dir)) {
            throw new ErrorException('The application already exists');
        }
        $composerFile = $dir . '/composer.json';
        if (!is_file($composerFile)) {
            throw new ErrorException('The application configuration does not exist');
        }
        $config = json_decode(file_get_contents($composerFile), true);
        $extra = $config['extra'];
        $duxExtra = $extra['dux'] ?: [];

        $apps = [];
        foreach ($duxExtra as $item) {
            $target = $item['target'];
            $source = $item['source'];
            $list = glob("$dir/$source/*");
            foreach ($list as $vo) {
                $apps[] = basename($vo);
                $relativeDir = $target . "/" . basename($vo);
                $targetDir = "./$relativeDir";
                FileSystem::delete($targetDir);
                echo "  - Delete $relativeDir \n";
            }
        }

        // config
        $configFile = "./config/app.yaml";
        $conf = Config::load($configFile);
        $registers = $conf->get("registers", []);

        foreach ($registers as $key => $vo) {
            $params = explode('\\', $vo);
            $app = $params[1];
            if (!in_array($app, $apps)) {
                continue;
            }
            unset($registers[$key]);
        }

        $conf->set("registers", array_values($registers));
        $conf->toFile($configFile);
    }

}