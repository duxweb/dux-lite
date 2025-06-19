<?php
declare(strict_types=1);

namespace Core\Command;

use Core\App;
use Symfony\Component\Console\Application;

class Command {

    public static function init(array $commands = []): Application {
        $application = new Application();
        foreach ($commands as $command) {
            if (!$command) {
                continue;
            }
            $application->add(new $command);
        }
        return $application;
    }

    public static function registerAttribute(): array
    {
        $attributes = App::attributes();
        $commands = [];
        foreach ($attributes as $item) {
            foreach ($item["annotations"] as $annotation) {
                if ($annotation["name"] != Command::class) {
                    continue;
                }
                $commands[] = $annotation["class"];
            }
        }
        return $commands;
    }

}