<?php

declare(strict_types=1);

namespace Core;

use Core\App\Attribute;
use Core\Cache\Cache;
use Core\Config\TomlLoader;
use Core\Database\Migrate;
use Core\Event\Event;
use Core\Lock\Lock;
use Core\Logs\LogHandler;
use Core\Queue\Queue;
use Core\Redis\Redis;
use Core\Scheduler\Scheduler;
use Core\Storage\Contracts\StorageInterface;
use Core\Storage\Storage;
use Core\Translation\TomlFileLoader;
use Core\Views\Render;
use DI\Container;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager;
use Latte\Engine;
use Monolog\Level;
use Monolog\Logger;
use Noodlehaus\Config;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Translation\Translator;
use XdbSearcher;

use function Termwind\render;

class App
{

    public static string $basePath;
    public static string $configPath;
    public static string $dataPath;
    public static string $publicPath;
    public static string $appPath;
    public static Bootstrap $bootstrap;
    public static Container $di;
    public static array $config;
    public static string $version = '0.0.1';
    public static array $registerApp = [];
    public static bool $debug = true;
    public static string $logo = '';
    public static string $lang = '';
    public static string $timezone = 'UTC';
    public static ?string $host = null;

    public static function create(string $basePath, string $lang = 'en-US', string $timezone = 'UTC', ?string $host = "0.0.0.0", ?bool $debug = true, ?string $logo = '')
    {
        self::$logo = $logo;
        self::$lang = $lang;
        self::$debug = $debug;
        self::$timezone = $timezone;
        self::$host = $host;

        self::$basePath = $basePath;
        self::$configPath = $basePath . '/config';
        self::$dataPath = $basePath . '/data';
        self::$publicPath = $basePath . '/public';
        self::$appPath = $basePath . '/app';
        self::init();
    }

    public static function init()
    {
        $dotenv = Dotenv::createImmutable(self::$basePath);
        $dotenv->safeLoad();

        self::$di = new Container();
        self::$bootstrap = new \Core\Bootstrap();
        self::$bootstrap->registerFunc();
        self::$bootstrap->registerConfig();
        self::$bootstrap->registerWeb();
    }

    public static function run()
    {
        self::$bootstrap->loadApp();
        self::$bootstrap->loadRoute();
        self::$bootstrap->loadCommand();
        self::$bootstrap->run();
    }

    public static function runWeb()
    {
        self::$bootstrap->loadApp();
        self::$bootstrap->loadRoute();
        self::$bootstrap->runWeb();
    }

    public static function web(): \Slim\App
    {
        return self::$bootstrap->web;
    }

    public static function di(): Container
    {
        return self::$di;
    }

    public static function db(): Manager
    {
        if (!self::$di->has("db")) {
            self::di()->set(
                "db",
                \Core\Database\Db::init(\Core\App::config("database")->get("db.drivers"))
            );
        }
        return self::$di->get("db");
    }

    public static function dbMigrate(): Migrate
    {
        if (!self::$di->has("db.migrate")) {
            self::$di->set(
                "db.migrate",
                new Migrate()
            );
        }
        return self::$di->get("db.migrate");
    }

    public static function lock(?string $type = null): LockFactory
    {
        $config = self::config("use");
        $type = $type ?? $config->get("lock.type", "semaphore");

        if (!self::$di->has("lock." . $type)) {
            self::$di->set(
                "lock." . $type,
                Lock::init($type)
            );
        }
        return self::$di->get("lock." . $type);
    }

    public static function cache(?string $type = null): Psr16Cache
    {
        $config = self::config("use");
        $type = $type ?? $config->get("cache.type", "file");

        if (!self::$di->has("cache." . $type)) {
            self::$di->set(
                "cache." . $type,
                new Cache($type, $config->get("cache", []))
            );
        }
        return self::$di->get("cache." . $type)->factory();
    }

    public static function redis(string $name = "default", int $database = 0): \Predis\ClientInterface|\Redis
    {
        if (!self::$di->has("redis." . $name)) {
            $config = self::config("database")->get("redis.drivers." . $name);
            $redis = new Redis($config);
            self::$di->set(
                "redis." . $name,
                $redis
            );
        }
        $redis = self::$di->get("redis." . $name)->factory();
        $redis->select($database);
        return $redis;
    }

    public static function view(string $name): Engine
    {
        if (!self::$di->has("view." . $name)) {
            self::$di->set(
                "view." . $name,
                Render::init($name)
            );
        }
        return self::$di->get("view." . $name);
    }

    public static function event(): Event
    {
        if (self::$di->has("events")) {
            return self::$di->get("events");
        }

        $event = new Event();
        self::di()->set(
            "events",
            $event
        );
        return $event;
    }

    public static function attributes(): array
    {
        if (self::$di->has("attributes")) {
            return self::$di->get("attributes");
        }

        $attributes = Attribute::load(self::$registerApp);

        self::$di->set("attributes", $attributes);
        return $attributes;
    }

    public static function permission(): Permission\Register
    {
        if (self::$di->has("permission")) {
            return self::$di->get("permission");
        }

        $permission = new Permission\Register();
        self::$di->set("permission", $permission);
        return $permission;
    }

    public static function resource(): Resources\Register
    {
        if (self::$di->has("resource")) {
            return self::$di->get("resource");
        }

        $resource = new Resources\Register();
        self::$di->set("resource", $resource);
        return $resource;
    }

    public static function route(): Route\Register
    {
        if (self::$di->has("route")) {
            return self::$di->get("route");
        }

        $route = new Route\Register();
        self::$di->set("route", $route);
        return $route;
    }

    public static function trans(): Translator
    {
        if (!self::$di->has("trans")) {
            $translator = new Translator(self::$lang);
            $translator->addLoader('toml', new TomlFileLoader());
            self::loadTrans(__DIR__ . '/Langs', $translator);
            self::$di->set(
                "trans",
                $translator
            );
        }
        return self::$di->get("trans");
    }

    public static function loadTrans(string $dirPath, Translator $trans): void
    {
        $files = glob($dirPath . "/*.*.toml");
        if (!$files) {
            return;
        }
        foreach ($files as $file) {
            $names = explode('.', basename($file, '.toml'), 2);
            [$name, $lang] = $names;
            $trans->addResource('toml', $file, $lang, $name);
        }
    }

    public static function log(string $app = "app", Level $level = Level::Debug): Logger
    {
        if (!self::$di->has("logger." . $app)) {
            self::$di->set(
                "logger." . $app,
                LogHandler::init($app, $level)
            );
        }
        return self::$di->get("logger." . $app);
    }

    public static function config(string $name): Config
    {
        if (self::$di->has("config." . $name)) {
            return self::$di->get("config." . $name);
        }

        $file = App::$configPath . "/$name.dev.toml";
        if (!is_file($file)) {
            $file = App::$configPath . "/$name.toml";
        }
        $string = false;

        if (!is_file($file)) {
            $file = '';
            $string = true;
        }
        $config = new Config($file, new TomlLoader(), $string);
        self::$di->set("config." . $name, $config);
        return $config;
    }

    public static function queue(string $type = ""): Queue
    {
        if (!$type) {
            $type = self::config("queue")->get("type", 'redis');
        }
        if (!self::$di->has("queue." . $type)) {
            self::$di->set(
                "queue." . $type,
                new Queue($type)
            );
        }
        return self::$di->get("queue." . $type);
    }

    public static function storage(string $type = ""): StorageInterface
    {
        if (!$type) {
            $type = self::config("storage")->get("type");
        }
        if (!self::$di->has("storage." . $type)) {
            $config = self::config("storage")->get("drivers." . $type);
            $storageType = $config["type"];
            unset($config["type"]);
            self::$di->set(
                "storage." . $type,
                new Storage($storageType, $config)
            );
        }
        return self::$di->get("storage." . $type)->getInstance();
    }

    public static function scheduler(): Scheduler
    {
        if (!self::$di->has("scheduler")) {
            self::$di->set(
                "scheduler",
                new Scheduler()
            );
        }
        return self::$di->get("scheduler");
    }

    public static function geo(): XdbSearcher|null
    {
        if (!self::$di->has("geo")) {
            $db = self::config("geo")->get("db");

            $dbFile = config_path($db ?: '');
            if ($db && is_file($dbFile)) {
                $ip2region = XdbSearcher::newWithFileOnly($dbFile);
            } else {
                $ip2region = null;
            }
            self::$di->set(
                "geo",
                $ip2region
            );
        }
        return self::$di->get("geo");
    }

    public static function banner(array $data = [], array $extra = [])
    {
        $logo = self::$logo ?: null;
        $time = date('Y-m-d H:i:s');


        $banner = $logo ? <<<HTML
        <div class="mb-1">
            <pre>{$logo}</pre>
        </div>
        HTML : null;

        $dataHtml = '';
        foreach ($data as $key => $value) {
            $dataHtml .= <<<HTML
            <span class="mr-1">{$key}</span>
            <span class="text-green mr-2">{$value}</span>
            HTML;
        }

        $footerHtml = '';
        foreach ($extra as $key => $value) {
            $footerHtml .= <<<HTML
            <div>
                <span class="mr-1">⇨ </span>
                <span>{$key}</span>
                <span class="text-cyan ml-1">{$value}</span>
            </div>
            HTML;
        }

        render(<<<HTML
            <div class="">
                {$banner}
                <div class="flex">
                    <span class="mr-1">⇨ </span>
                    {$dataHtml}
                </div>
                <div>
                    <span class="mr-1">⇨ </span>
                    <span>Start time</span>
                    <span class="text-cyan ml-1">{$time}</span>
                </div>
                {$footerHtml}
            </div>
        HTML);
    }
}
