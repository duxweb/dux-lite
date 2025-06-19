<?php

declare(strict_types=1);

namespace Core;

use Carbon\Carbon;
use Core\Command\Command;
use Core\Database\BackupCommand;
use Core\Database\ListCommand;
use Core\Database\MigrateCommand;
use Core\Database\RestoreCommand;
use Core\Handlers\ErrorHandler;
use Core\Handlers\ErrorHtmlRenderer;
use Core\Handlers\ErrorJsonRenderer;
use Core\Handlers\ErrorPlainRenderer;
use Core\Handlers\ErrorXmlRenderer;
use Core\Middleware\CorsMiddleware;
use Core\Middleware\LangMiddleware;
use Core\Permission\PermissionCommand;
use Core\Queue\QueueCommand;
use Core\Route\RouteCommand;
use Core\Scheduler\SchedulerCommand;
use DI\DependencyException;
use DI\NotFoundException;
use Latte\Engine;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Symfony\Component\Console\Application;

class Bootstrap
{
    public \Slim\App $web;
    public Engine $view;
    public Application $command;

    public function __construct()
    {
        error_reporting(E_ALL ^ E_DEPRECATED ^ E_WARNING);
    }

    /**
     * 注册公共函数
     * @return void
     */
    public function registerFunc(): void
    {
        require_once "Func/Response.php";
        require_once "Func/Common.php";
    }

    /**
     * 注册公共配置
     * @return void
     */
    public function registerConfig(): void
    {
        date_default_timezone_set(App::$timezone);
        Carbon::setLocale(App::$lang);
    }

    /**
     * 注册公共web服务
     * @return void
     */
    public function registerWeb(): void
    {
        // 注册web服务
        AppFactory::setContainer(App::di());
        $this->web = AppFactory::create();
        App::$debug = App::config("use")->get("app.debug", false);
    }

    /**
     * 加载路由
     * @return void
     */
    public function loadRoute(): void
    {
        // 解析内容中间件
        $this->web->addBodyParsingMiddleware();
        // 注册路由中间件
        $this->web->addRoutingMiddleware();
        // 注册请求中间件
        $this->web->addMiddleware(new LangMiddleware);
        // 注册授权异常
        $errorMiddleware = $this->web->addErrorMiddleware(App::$debug, true, true);
        // 注册异常
        $errorHandler = new ErrorHandler($this->web->getCallableResolver(), $this->web->getResponseFactory());
        $errorMiddleware->setDefaultErrorHandler($errorHandler);
        $errorHandler->registerErrorRenderer("application/json", ErrorJsonRenderer::class);
        $errorHandler->registerErrorRenderer("application/xml", ErrorXmlRenderer::class);
        $errorHandler->registerErrorRenderer("text/xml", ErrorXmlRenderer::class);
        $errorHandler->registerErrorRenderer("text/html", ErrorHtmlRenderer::class);
        $errorHandler->registerErrorRenderer("text/plain", ErrorPlainRenderer::class);
        // 注册OPTIONS请求
        $this->web->options('/{routes:.+}', function ($request, $response, $args) {
            return $response;
        });
        // 注册跨域中间件
        $this->web->addMiddleware(new CorsMiddleware);
    }


    /**
     * 加载应用
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function loadApp(): void
    {
        // 全局应用注册
        $appList = App::config("app")->get("registers", []);
        foreach ($appList as $vo) {
            App::$registerApp[] = $vo;
        }

        // 应用初始化触发
        foreach ($appList as $vo) {
            call_user_func([new $vo, "init"], $this);
        }

        // 资源注册触发
        foreach (App::resource()->app as $resource) {
            $resource->run($this);
        }

        // 应用注册触发
        foreach ($appList as $vo) {
            call_user_func([new $vo, "register"], $this);
        }

        // 注解资源注册
        App::resource()->registerAttribute();

        // 注解路由注册
        App::route()->registerAttribute();

        // 注册事件
        App::event()->registerAttribute();

        // 注册计划任务
        App::scheduler()->registerAttribute();

        // 普通路由注册
        foreach (App::route()->app as $route) {
            $route->run($this->web);
        }

        // 公共路由
        $this->web->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
            throw new HttpNotFoundException($request);
        });

        // 应用启动
        foreach ($appList as $vo) {
            call_user_func([new $vo, "boot"], $this);
        }
    }


    /**
     * 加载命令行
     * @return void
     */
    public function loadCommand(): void
    {
        $commands = App::config("command")->get("registers", []);

        $commands[] = BackupCommand::class;
        $commands[] = RestoreCommand::class;
        $commands[] = PermissionCommand::class;
        $commands[] = RouteCommand::class;
        $commands[] = ListCommand::class;
        $commands[] = MigrateCommand::class;
        $commands[] = QueueCommand::class;
        $commands[] = SchedulerCommand::class;

        $commands = [...$commands, ...Command::registerAttribute()];

        $this->command = Command::init($commands);

        // 注册模型迁移
        App::dbMigrate()->registerAttribute();
    }


    /**
     * 启动命令
     * @return void
     * @throws \Exception
     */
    public function run(): void
    {
        $this->command->run();
    }

    /**
     * 启动web服务
     * @return void
     */
    public function runWeb(): void
    {
        $this->web->run();
    }
}
