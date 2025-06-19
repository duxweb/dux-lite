<?php

declare(strict_types=1);

namespace Core\Database;

use Core\App;
use Core\Database\Connection\MysqlConnection;
use Core\Database\Connection\SqliteConnection;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Events\Dispatcher;

class Db
{

    public static function init(array $configs): Manager
    {
        $capsule = new Manager;
        foreach ($configs as $key => $config) {
            $capsule->addConnection($config, $key);
        }
        $event = new Dispatcher(new Container);
        $capsule->setEventDispatcher($event);
        $capsule->bootEloquent();
        return $capsule;
    }
}
