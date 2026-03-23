<?php

namespace Core\Scheduler;

use Cron\CronExpression;
use Core\App;
use Core\Handlers\Exception;
use Core\Scheduler\Attribute\Scheduler as AttributeScheduler;
use GO\Scheduler as GoScheduler;
use React\EventLoop\Loop;

class Scheduler
{
    public const int EXIT_OK = 0;
    public const int EXIT_RESTART = 100;

    public ?GoScheduler $scheduler = null;

    /**
     * @var array<int, array{name:string,desc:string,cron:?string,callback:string,params:array}>
     */
    public array $data = [];
    private array $executed = [];
    private array $runtimePulled = [];

    public function __construct()
    {
    }

    /**
     * 返回代码中收集到的临时计划数据
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 仅收集计划任务信息，不进行实际注册
     */
    public function add(
        string $callback,
        array $params = [],
        ?string $cron = null,
        string $name = '',
        string $desc = ''
    ): void {
        $this->data[] = [
            'name' => $name,
            'desc' => $desc,
            'cron' => $cron,
            'callback' => $callback,
            'params' => $params,
        ];
    }

    /**
     * 实际注册计划任务（从 data 文件读取后使用）
     */
    public function job(
        string $callback,
        array $params = [],
        ?string $cron = null,
        string $name = '',
        string $desc = ''
    ): ?\GO\Job {
        if (!is_string($cron) || trim($cron) === '') {
            return null;
        }
        [$class, $method] = $this->parseCallback($callback);

        $job = $this->goScheduler()->call(function () use ($class, $method, $params) {
            try {
                $object = new $class;
                call_user_func([$object, $method], $params);
            } catch (Exception $e) {
                App::log('scheduler')->error($e->getMessage(), [
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
        });
        $job->at($cron);
        return $job;
    }

    /**
     * 生成计划任务文件
     */
    public function gen(): array
    {
        $this->disableExecutionTimeout();
        $event = new SchedulerGenEvent($this->data);
        App::event()->dispatch($event, 'scheduler.gen');

        $data = $event->getData();

        $file = $this->jobsFilePath();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($file, "<?php\n\nreturn " . var_export($data, true) . ";\n");

        return $data;
    }

    /**
     * 优先从 data 文件读取计划任务，为空时生成并写入
     */
    public function loadJobs(): array
    {
        $this->disableExecutionTimeout();
        $data = [];
        if (!App::$debug) {
            $data = $this->readJobsFile($this->jobsFilePath());
        }
        if (!$data) {
            $data = $this->gen();
        }
        $this->data = $data;
        return $data;
    }

    public function run(): int
    {
        $this->disableExecutionTimeout();
        $loop = Loop::get();
        $loop->addPeriodicTimer(1, function () {
            $this->runDueJobs(new \DateTimeImmutable());
        });
        $loop->run();

        return self::EXIT_OK;
    }

    private function disableExecutionTimeout(): void
    {
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', '0');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }

    public function pullRuntimeTasks(string|\DateTimeInterface|null $time = null, int $limit = 1): array
    {
        $limit = max(1, $limit);
        $now = $time instanceof \DateTimeInterface ? $time : new \DateTimeImmutable($time ?: 'now');
        $this->cleanupRuntimePulled($now);
        $jobs = $this->loadJobs();
        $items = [];

        foreach ($jobs as $job) {
            if (count($items) >= $limit) {
                break;
            }

            $callback = $job['callback'] ?? '';
            $cron = $job['cron'] ?? '';
            if (!$callback || !$cron) {
                continue;
            }

            if (!$this->isCronDue($cron, $now)) {
                continue;
            }

            $key = $this->runtimeDueKey($callback, $cron, $now);
            if (isset($this->runtimePulled[$key])) {
                continue;
            }

            $items[] = [
                'id' => $key,
                'type' => 'schedule',
                'name' => $callback,
                'payload' => [
                    'callback' => $callback,
                    'params' => is_array($job['params'] ?? null) ? $job['params'] : [],
                    'cron' => $cron,
                    'desc' => $job['desc'] ?? '',
                ],
                'attempt' => 1,
                'timeout' => $this->runtimeTaskTimeout(),
                'meta' => [
                    'cron' => $cron,
                    'name' => $job['name'] ?? '',
                    'desc' => $job['desc'] ?? '',
                    'due_at' => $now->format(DATE_ATOM),
                ],
            ];

            $this->runtimePulled[$key] = [
                'callback' => $callback,
                'time' => $now->format(DATE_ATOM),
                'expires_at' => $this->runtimeDueExpiresAt($cron, $now)->format(DATE_ATOM),
            ];
        }

        return $items;
    }

    private function runtimeTaskTimeout(): int
    {
        $timeout = (int)App::config('use')->get('runtime.task_timeout', 30);
        return $timeout > 0 ? $timeout : 30;
    }

    public function reportRuntimeTask(string $taskId, array $result = [], string $error = ''): void
    {
        $job = $this->runtimePulled[$taskId] ?? null;
        if (!$job) {
            return;
        }

        if ($error) {
            App::log('scheduler')->error('runtime scheduler task failed', [
                'task_id' => $taskId,
                'callback' => $job['callback'] ?? '',
                'error' => $error,
            ]);
        } else {
            App::log('scheduler')->info('runtime scheduler task done', [
                'task_id' => $taskId,
                'callback' => $job['callback'] ?? '',
                'result' => $result,
            ]);
        }
    }

    public function registerAttribute(): void
    {
        $attributes = App::attributes();

        foreach ($attributes as $item) {
            foreach ($item['annotations'] as $annotation) {
                if ($annotation['name'] != AttributeScheduler::class) {
                    continue;
                }
                $params = $annotation['params'] ?? [];
                $cron = $params['cron'] ?? $params[0] ?? null;
                $name = $params['name'] ?? $params[1] ?? '';
                $desc = $params['desc'] ?? $params[2] ?? '';
                $this->add(
                    $annotation['class'],
                    [],
                    $cron,
                    $name,
                    $desc
                );
            }
        }
    }

    public function jobsFilePath(): string
    {
        $relative = 'scheduler/jobs.php';
        if (function_exists('data_path')) {
            return data_path($relative);
        }
        return rtrim(str_replace('\\', '/', App::$dataPath), '/') . '/' . $relative;
    }

    private function parseCallback(string $callback): array
    {
        if (str_contains($callback, ':')) {
            [$class, $method] = explode(':', $callback, 2);
        } else {
            $class = $callback;
            $method = '__invoke';
        }

        if (!class_exists($class)) {
            throw new Exception($class . ' does not exist');
        }
        if (!method_exists($class, $method)) {
            throw new Exception($class . ':' . $method . ' does not exist');
        }

        return [$class, $method];
    }

    public function executeCallback(string $callback, array $params = []): void
    {
        [$class, $method] = $this->parseCallback($callback);
        $object = new $class;
        call_user_func([$object, $method], $params);
    }

    private function readJobsFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $data = include $path;
        return is_array($data) ? $data : [];
    }

    private function registerJobs(array $jobs): void
    {
        foreach ($jobs as $job) {
            $callback = (string)($job['callback'] ?? '');
            if (!$callback) {
                continue;
            }
            $cron = $job['cron'] ?? null;
            if (!is_string($cron) || trim($cron) === '') {
                continue;
            }

            try {
                $this->job(
                    $callback,
                    is_array($job['params'] ?? null) ? $job['params'] : [],
                    $cron,
                    (string)($job['name'] ?? ''),
                    (string)($job['desc'] ?? '')
                );
            } catch (Exception $e) {
                App::log('scheduler')->error($e->getMessage(), [
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'job' => $job,
                ]);
            }
        }
    }

    private function goScheduler(): GoScheduler
    {
        if (!$this->scheduler) {
            $this->scheduler = new GoScheduler();
        }
        return $this->scheduler;
    }

    private function runDueJobs(\DateTimeImmutable $now): void
    {
        foreach ($this->loadJobs() as $job) {
            $callback = $job['callback'] ?? '';
            $cron = $job['cron'] ?? '';
            if (!$callback || !$cron) {
                continue;
            }
            if (!$this->isNativeCronDue($cron, $now)) {
                continue;
            }

            $key = $this->runtimeDueKey($callback, $cron, $now);
            if (isset($this->executed[$key])) {
                continue;
            }

            try {
                $this->executeCallback($callback, is_array($job['params'] ?? null) ? $job['params'] : []);
                $this->executed[$key] = $now->format(DATE_ATOM);
            } catch (\Throwable $e) {
                App::log('scheduler')->error($e->getMessage(), [
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'job' => $job,
                ]);
            }
        }
    }

    private function runtimeDueKey(string $callback, string $cron, \DateTimeInterface $now): string
    {
        $suffix = $this->cronFieldCount($cron) === 6 ? $now->format('YmdHis') : $now->format('YmdHi');
        return sha1($callback . '|' . $cron . '|' . $suffix);
    }

    private function runtimeDueExpiresAt(string $cron, \DateTimeInterface $now): \DateTimeImmutable
    {
        $time = $now instanceof \DateTimeImmutable ? $now : \DateTimeImmutable::createFromInterface($now);
        if ($this->cronFieldCount($cron) === 6) {
            return $time->modify('+1 second');
        }
        return $time->modify('+1 minute');
    }

    private function cleanupRuntimePulled(\DateTimeInterface $now): void
    {
        $time = $now instanceof \DateTimeImmutable ? $now : \DateTimeImmutable::createFromInterface($now);
        foreach ($this->runtimePulled as $key => $item) {
            $expiresAt = $item['expires_at'] ?? null;
            if (!$expiresAt) {
                continue;
            }
            $expires = new \DateTimeImmutable((string)$expiresAt);
            if ($expires <= $time) {
                unset($this->runtimePulled[$key]);
            }
        }
    }

    private function isCronDue(string $cron, \DateTimeInterface $now): bool
    {
        $parts = $this->cronParts($cron);
        if (count($parts) === 5) {
            return CronExpression::factory($cron)->isDue($now);
        }
        if (count($parts) !== 6) {
            throw new Exception('scheduler cron format not supported: ' . $cron);
        }

        $values = [
            (int)$now->format('s'),
            (int)$now->format('i'),
            (int)$now->format('G'),
            (int)$now->format('j'),
            (int)$now->format('n'),
            (int)$now->format('w'),
        ];

        foreach ($parts as $index => $part) {
            if (!$this->cronPartMatches($part, $values[$index], $index === 5)) {
                return false;
            }
        }
        return true;
    }

    private function isNativeCronDue(string $cron, \DateTimeInterface $now): bool
    {
        if ($this->cronFieldCount($cron) !== 5) {
            return false;
        }
        return CronExpression::factory($cron)->isDue($now);
    }

    private function cronFieldCount(string $cron): int
    {
        return count($this->cronParts($cron));
    }

    private function cronParts(string $cron): array
    {
        return preg_split('/\s+/', trim($cron), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function cronPartMatches(string $expression, int $value, bool $weekday = false): bool
    {
        foreach (explode(',', strtoupper($expression)) as $segment) {
            if ($this->cronSegmentMatches(trim($segment), $value, $weekday)) {
                return true;
            }
        }
        return false;
    }

    private function cronSegmentMatches(string $segment, int $value, bool $weekday = false): bool
    {
        if ($segment === '*' || $segment === '?') {
            return true;
        }

        $step = 1;
        $base = $segment;
        if (str_contains($segment, '/')) {
            [$base, $stepValue] = explode('/', $segment, 2);
            $step = max(1, (int)$stepValue);
        }

        if ($base === '*') {
            return $value % $step === 0;
        }

        if (str_contains($base, '-')) {
            [$start, $end] = explode('-', $base, 2);
            $startValue = $this->cronValue($start, $weekday);
            $endValue = $this->cronValue($end, $weekday);
            if ($value < $startValue || $value > $endValue) {
                return false;
            }
            return (($value - $startValue) % $step) === 0;
        }

        $target = $this->cronValue($base, $weekday);
        if ($step > 1) {
            return $value >= $target && (($value - $target) % $step) === 0;
        }
        return $value === $target;
    }

    private function cronValue(string $value, bool $weekday = false): int
    {
        $value = strtoupper(trim($value));
        if ($weekday) {
            $map = [
                'SUN' => 0,
                'MON' => 1,
                'TUE' => 2,
                'WED' => 3,
                'THU' => 4,
                'FRI' => 5,
                'SAT' => 6,
                '7' => 0,
            ];
            if (isset($map[$value])) {
                return $map[$value];
            }
        } else {
            $map = [
                'JAN' => 1,
                'FEB' => 2,
                'MAR' => 3,
                'APR' => 4,
                'MAY' => 5,
                'JUN' => 6,
                'JUL' => 7,
                'AUG' => 8,
                'SEP' => 9,
                'OCT' => 10,
                'NOV' => 11,
                'DEC' => 12,
            ];
            if (isset($map[$value])) {
                return $map[$value];
            }
        }

        return (int)$value;
    }

}
