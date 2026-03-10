<?php

declare(strict_types=1);

use Core\Scheduler\Scheduler;
use Core\App;

function makeRuntimeScheduler(array $jobs): Scheduler
{
    return new class($jobs) extends Scheduler {
        public function __construct(private array $jobs)
        {
            parent::__construct();
        }

        public function loadJobs(): array
        {
            $this->data = $this->jobs;
            return $this->jobs;
        }
    };
}

function callSchedulerPrivate(Scheduler $scheduler, string $method, mixed ...$args): mixed
{
    $reflection = new ReflectionMethod($scheduler, $method);
    return $reflection->invoke($scheduler, ...$args);
}

function bootSchedulerTestApp(): void
{
    $basePath = sys_get_temp_dir() . '/dux-lite-scheduler-test';
    if (!is_dir($basePath)) {
        mkdir($basePath, 0777, true);
    }
    if (!is_dir($basePath . '/data')) {
        mkdir($basePath . '/data', 0777, true);
    }
    if (!is_file($basePath . '/.env')) {
        file_put_contents($basePath . '/.env', '');
    }
    App::create(basePath: $basePath, debug: true, timezone: 'UTC');
}

it('deduplicates five-field cron within the same minute', function (): void {
    $scheduler = makeRuntimeScheduler([
        [
            'name' => 'minute-job',
            'cron' => '* * * * *',
            'callback' => 'App\\Demo\\MinuteJob:handle',
            'params' => [],
            'desc' => '',
        ],
    ]);

    $first = $scheduler->pullRuntimeTasks('2026-03-10T13:42:01+08:00', 8);
    $second = $scheduler->pullRuntimeTasks('2026-03-10T13:42:59+08:00', 8);
    $third = $scheduler->pullRuntimeTasks('2026-03-10T13:43:00+08:00', 8);

    expect($first)->toHaveCount(1)
        ->and($second)->toBe([])
        ->and($third)->toHaveCount(1)
        ->and($first[0]['id'])->not->toBe($third[0]['id']);
});

it('keeps five-field cron deduplicated after report within the same minute', function (): void {
    bootSchedulerTestApp();

    $scheduler = makeRuntimeScheduler([
        [
            'name' => 'minute-job',
            'cron' => '* * * * *',
            'callback' => 'App\\Demo\\MinuteJob:handle',
            'params' => [],
            'desc' => '',
        ],
    ]);

    $first = $scheduler->pullRuntimeTasks('2026-03-10T13:42:01+08:00', 8);
    $scheduler->reportRuntimeTask($first[0]['id'], ['ok' => true], '');
    $second = $scheduler->pullRuntimeTasks('2026-03-10T13:42:30+08:00', 8);
    $third = $scheduler->pullRuntimeTasks('2026-03-10T13:43:00+08:00', 8);

    expect($first)->toHaveCount(1)
        ->and($second)->toBe([])
        ->and($third)->toHaveCount(1);
});

it('supports six-field cron with second-level deduplication', function (): void {
    $scheduler = makeRuntimeScheduler([
        [
            'name' => 'second-job',
            'cron' => '*/5 * * * * *',
            'callback' => 'App\\Demo\\SecondJob:handle',
            'params' => [],
            'desc' => '',
        ],
    ]);

    $first = $scheduler->pullRuntimeTasks('2026-03-10T13:42:05+08:00', 8);
    $second = $scheduler->pullRuntimeTasks('2026-03-10T13:42:05+08:00', 8);
    $third = $scheduler->pullRuntimeTasks('2026-03-10T13:42:06+08:00', 8);
    $fourth = $scheduler->pullRuntimeTasks('2026-03-10T13:42:10+08:00', 8);

    expect($first)->toHaveCount(1)
        ->and($second)->toBe([])
        ->and($third)->toBe([])
        ->and($fourth)->toHaveCount(1)
        ->and($first[0]['id'])->not->toBe($fourth[0]['id']);
});

it('keeps six-field cron disabled for native php scheduler loop', function (): void {
    $scheduler = makeRuntimeScheduler([
        [
            'name' => 'second-job',
            'cron' => '*/5 * * * * *',
            'callback' => 'App\\Demo\\SecondJob:handle',
            'params' => [],
            'desc' => '',
        ],
    ]);

    $due = callSchedulerPrivate($scheduler, 'isNativeCronDue', '*/5 * * * * *', new DateTimeImmutable('2026-03-10T13:42:05+08:00'));

    expect($due)->toBeFalse();
});
