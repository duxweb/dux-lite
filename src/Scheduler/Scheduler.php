<?php

namespace Core\Scheduler;

use Core\App;
use Core\Handlers\Exception;
use Core\Scheduler\Attribute\Scheduler as AttributeScheduler;
use GO\Scheduler as GoScheduler;
use React\EventLoop\Loop;

class Scheduler
{
    public const int EXIT_OK = 0;
    public const int EXIT_RESTART = 100;
    public const string DEFAULT_CRON = '* * * * *';

    public GoScheduler $scheduler;

    /**
     * @var array<int, array{name:string,desc:string,cron:string,callback:string,params:array}>
     */
    public array $data = [];

    public function __construct()
    {
        $this->scheduler = new GoScheduler();
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
        string $cron = self::DEFAULT_CRON,
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
        string $cron = self::DEFAULT_CRON,
        string $name = '',
        string $desc = ''
    ): \GO\Job {
        [$class, $method] = $this->parseCallback($callback);

        $job = $this->scheduler->call(function () use ($class, $method, $params) {
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
        $event = new SchedulerGenEvent($this->data);
        App::event()->dispatch($event, 'scheduler.gen');

        $data = $event->getData();
        if (!$data) {
            $data = $event->getFallbackData();
        }

        $file = $this->jobsFilePath();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($file, "<?php\n\nreturn " . var_export($data, true) . ";\n");

        return $data;
    }

    /**
     * 优先从 data 文件读取计划任务，否则回退到当前内存数据
     */
    public function loadJobs(): array
    {
        $data = $this->readJobsFile($this->jobsFilePath());
        if (!$data) {
            $data = $this->data;
        }
        $this->data = $data;
        return $data;
    }

    public function run(bool $watch = false, int $watchInterval = 3): int
    {
        $jobs = $this->loadJobs();
        $this->registerJobs($jobs);

        $this->scheduler->work();
        $loop = Loop::get();

        $restart = false;
        if ($watch) {
            $watchFiles = [$this->jobsFilePath()];
            $hashes = [];
            foreach ($watchFiles as $path) {
                $hashes[$path] = $this->hashFile($path);
            }
            $loop->addPeriodicTimer(max(1, $watchInterval), function () use (&$restart, $watchFiles, &$hashes) {
                foreach ($watchFiles as $path) {
                    $hash = $this->hashFile($path);
                    if (($hashes[$path] ?? null) !== $hash) {
                        $restart = true;
                        App::log('scheduler')->info('Scheduler jobs file changed, restarting', [
                            'file' => $path,
                        ]);
                        Loop::stop();
                        return;
                    }
                }
            });
        }

        // 定时检查任务
        $loop->addPeriodicTimer(1, function () {
            $seconds = [0];
            if (in_array((int)date('s'), $seconds)) {
                $this->scheduler->run();
            }
        });
        $loop->run();

        if ($restart) {
            return self::EXIT_RESTART;
        }
        return self::EXIT_OK;
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
                $cron = $params['cron'] ?? $params[0] ?? self::DEFAULT_CRON;
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

            try {
                $this->job(
                    $callback,
                    is_array($job['params'] ?? null) ? $job['params'] : [],
                    (string)($job['cron'] ?? self::DEFAULT_CRON),
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

    private function hashFile(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        return hash_file('sha256', $path) ?: null;
    }
}
