<?php

declare(strict_types=1);

namespace Core\Logs;

use Core\App;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;

class LogHandler
{

    public static function init(string $name, Level $level): Logger
    {
        $fileHandle = new RotatingFileHandler(App::$dataPath . '/logs/' . $name . '.log', 15, $level, true, 0777);
        $logger = new Logger($name);
        $logger->useLoggingLoopDetection(false);
        $logger->pushHandler($fileHandle);
        return $logger;
    }
}
