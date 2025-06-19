<?php

namespace Dux\Scheduler;

use Dux\App;
use Dux\Handlers\Exception;
use GO\Scheduler as GoScheduler;
use React\EventLoop\Loop;

class Scheduler
{

    public GoScheduler $scheduler;

    public array $data = [];

    public function __construct()
    {
        $this->scheduler = new GoScheduler();
    }

    public function add(string $cron, callable|array $callback, $params = []): void
    {
        $this->job($callback, $params)->at($cron);
    }

    public function job($callback, $params = []): \GO\Job
    {
        if ($callback instanceof \Closure) {
            $this->data[] = ['func', '-'];
            return $this->scheduler->call($callback, $params);
        } else {
            [$class, $method] = $callback;
            if (!class_exists($class)) {
                throw new Exception($class . ' does not exist');
            }
            if (!method_exists($class, $method)) {
                throw new Exception($class . ':' . $method . ' does not exist');
            }
            $this->data[] = [$class, $method];
            return $this->scheduler->call(function () use ($class, $method, $params) {
                try {
                    $object = new $class;
                    call_user_func([$object, $method], $params);
                } catch (Exception $e) {
                    App::log('scheduler')->error($e->getMessage(), [
                        'file' => $e->getFile() . ':' . $e->getLine(),
                    ]);
                }
            });
        }
    }

    public function run(): void
    {
        $loop = Loop::get();
        // 定时检查任务
        $loop->addPeriodicTimer(1, function () {
            $seconds = [0];
            if (in_array((int)date('s'), $seconds)) {
                $this->scheduler->run();
            }
        });
        $loop->run();
    }
}