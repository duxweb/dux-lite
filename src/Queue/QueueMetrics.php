<?php

declare(strict_types=1);

namespace Core\Queue;

use RuntimeException;

class QueueMetrics
{
    public const string KEY_EXECUTED = 'executed';
    public const string KEY_FAILED = 'failed';

    /**
     * 增加统计计数（按本次启动 runId + work 维度累计）。
     */
    public static function incr(string $work, string $key, int $delta = 1): void
    {
        $path = self::metricsPath($work);
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $fp = fopen($path, 'c+');
        if (!$fp) {
            throw new RuntimeException('Unable to open metrics file: ' . $path);
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException('Unable to lock metrics file: ' . $path);
            }

            $raw = stream_get_contents($fp);
            $data = [];
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }

            $data[self::KEY_EXECUTED] = (int)($data[self::KEY_EXECUTED] ?? 0);
            $data[self::KEY_FAILED] = (int)($data[self::KEY_FAILED] ?? 0);

            $data[$key] = (int)($data[$key] ?? 0) + $delta;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * 获取统计计数（按本次启动 runId + work）。
     * @return array{executed:int,failed:int}
     */
    public static function get(string $work): array
    {
        $path = self::metricsPath($work);
        if (!is_file($path)) {
            return [
                self::KEY_EXECUTED => 0,
                self::KEY_FAILED => 0,
            ];
        }

        $raw = file_get_contents($path);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return [
                self::KEY_EXECUTED => 0,
                self::KEY_FAILED => 0,
            ];
        }

        return [
            self::KEY_EXECUTED => (int)($data[self::KEY_EXECUTED] ?? 0),
            self::KEY_FAILED => (int)($data[self::KEY_FAILED] ?? 0),
        ];
    }

    /**
     * 获取当前启动的 runId（用于区分不同的队列管理进程启动周期）。
     */
    private static function runId(): string
    {
        return (string)(getenv('DUX_QUEUE_RUN_ID') ?: 'default');
    }

    /**
     * 获取统计文件路径。
     */
    private static function metricsPath(string $work): string
    {
        $file = 'queue/metrics/' . self::runId() . '/' . $work . '.json';
        if (function_exists('data_path')) {
            return data_path($file);
        }
        return rtrim(\Core\App::$dataPath, '/') . '/' . $file;
    }
}
