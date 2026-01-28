<?php

declare(strict_types=1);

namespace Core\Queue;

use Core\App;
use Core\Handlers\Exception;

class QueueJobMessageHandler
{
    /**
     * 消费端处理入口：实例化任务类并调用方法。
     * 抛异常会让 Messenger 按 transport 规则处理（重试/失败等）。
     */
    public function __invoke(QueueJobMessage $message): void
    {
        if (!class_exists($message->class)) {
            throw new Exception($message->class . ' does not exist');
        }

        $method = $message->method ?: '__invoke';
        if (!method_exists($message->class, $method)) {
            throw new Exception($message->class . ':' . $method . ' does not exist');
        }

        $work = (string)(getenv('DUX_QUEUE_WORK') ?: '');
        $priority = $message->priority !== '' ? $message->priority : (string)(getenv('DUX_QUEUE_PRIORITY') ?: 'medium');
        App::event()->dispatch(new QueueEvent($work, $priority, $message), QueueEvent::EXECUTE);

        try {
            $object = new $message->class;
            call_user_func([$object, $method], ...$message->params);
        } catch (\Throwable $e) {
            App::log('queue')->error($e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'job_id' => $message->id,
            ]);
            QueueMetrics::incr($work, QueueMetrics::KEY_FAILED, 1);
            App::event()->dispatch(new QueueEvent($work, $priority, $message, 0, $e), QueueEvent::FAILED);
            throw $e;
        }

        QueueMetrics::incr($work, QueueMetrics::KEY_EXECUTED, 1);
        App::event()->dispatch(new QueueEvent($work, $priority, $message), QueueEvent::DONE);
    }
}
