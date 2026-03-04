<?php

declare(strict_types=1);

namespace Core\Queue;

use Core\App;
use Spatie\Async\Task;

class QueueConsumeTask extends Task
{
    public function __construct(
        private string $work,
        private string $priority,
        private string $runId,
        private string $basePath,
        private bool $debug,
        private string $timezone
    ) {
    }

    public function configure(): void
    {
    }

    public function run(): array
    {
        putenv('DUX_QUEUE_RUN_ID=' . $this->runId);
        putenv('DUX_QUEUE_WORK=' . $this->work);
        putenv('DUX_QUEUE_PRIORITY=' . $this->priority);
        App::create(
            basePath: $this->basePath,
            debug: $this->debug,
            timezone: $this->timezone
        );
        App::$bootstrap->loadApp();
        App::queue()->process($this->priority, $this->work);
        return [
            'exit_code' => 0,
        ];
    }
}
