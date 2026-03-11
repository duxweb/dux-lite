<?php

declare(strict_types=1);

namespace Core\Queue;

use Core\App;

class QueueMetrics
{
    public const string KEY_EXECUTED = 'executed';
    public const string KEY_FAILED = 'failed';
    private const int TTL = 86400;

    public static function incr(string $work, string $key, int $delta = 1): void
    {
        if (!self::enabled() || $delta === 0) {
            return;
        }

        $lock = App::lock()->createLock(self::lockKey($work), 5);
        $lock->acquire(true);

        try {
            $data = self::get($work);
            $data[$key] = (int)($data[$key] ?? 0) + $delta;
            App::cache()->set(self::cacheKey($work), $data, self::TTL);
        } finally {
            $lock->release();
        }
    }

    public static function get(string $work): array
    {
        if (!self::enabled()) {
            return self::defaults();
        }

        $data = App::cache()->get(self::cacheKey($work), self::defaults());
        if (!is_array($data)) {
            return self::defaults();
        }

        return [
            self::KEY_EXECUTED => (int)($data[self::KEY_EXECUTED] ?? 0),
            self::KEY_FAILED => (int)($data[self::KEY_FAILED] ?? 0),
        ];
    }

    private static function cacheKey(string $work): string
    {
        return 'queue.metrics.' . self::runId() . '.' . ($work ?: 'default');
    }

    private static function lockKey(string $work): string
    {
        return 'queue-metrics-' . md5(self::cacheKey($work));
    }

    private static function runId(): string
    {
        return (string)(getenv('DUX_QUEUE_RUN_ID') ?: 'default');
    }

    private static function defaults(): array
    {
        return [
            self::KEY_EXECUTED => 0,
            self::KEY_FAILED => 0,
        ];
    }

    private static function enabled(): bool
    {
        return (bool)App::config('use')->get('runtime.queue_metrics', false);
    }
}
