<?php

declare(strict_types=1);

namespace Core\Queue;

use Core\App;
use Spatie\Async\Pool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class QueueCommand extends Command
{
    /**
     * manager 进程是否需要退出（跨平台）。
     */
    private bool $stop = false;
    private ?ConsoleSectionOutput $statusSection = null;
    private ?Pool $pool = null;

    /**
     * @var array<int, array{work:string,priority:string,started_at:int}>
     */
    private array $slots = [];

    private string $runId = '';

    /**
     * 启动管理进程：按 workers 配置拉起消费子进程，并周期输出队列状态。
     */
    protected function configure(): void
    {
        $this
            ->setName('queue:start')
            ->setDescription('Queue manager process (starts concurrent workers by config)')
            ->addArgument('works', InputArgument::IS_ARRAY, '指定要启动的 worker 名（默认启动全部）')
            ->addOption('status-interval', null, InputOption::VALUE_REQUIRED, '状态刷新间隔（秒）', '5')
            ->addOption('no-status', null, InputOption::VALUE_NONE, '关闭状态输出');
    }

    /**
     * 执行队列管理进程（常驻）：启动子进程 + 状态输出 + 守护重启。
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', '0');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $this->runId = date('YmdHis') . '-' . getmypid();
        putenv('DUX_QUEUE_RUN_ID=' . $this->runId);

        $worksFilter = array_values(array_filter(array_map('strval', (array)$input->getArgument('works'))));
        $workers = App::queue()->getWorkersConfig();
        if ($worksFilter) {
            $workers = array_intersect_key($workers, array_flip($worksFilter));
        }
        if (!$workers) {
            $output->writeln('No queue workers configured.');
            return Command::FAILURE;
        }

        $statusInterval = max(1, (int)$input->getOption('status-interval'));
        $showStatus = !$input->getOption('no-status');
        $lastStatusAt = 0;

        $this->registerSignalHandlers();

        if (!Pool::isSupported()) {
            $output->writeln('spatie/async is not supported in current environment.');
            $output->writeln('Required: pcntl_async_signals, posix_kill, proc_open.');
            return Command::FAILURE;
        }

        $output->writeln('Start time: ' . date('Y-m-d H:i:s'));
        $output->writeln('Worker mode: spatie/async managed queue workers');
        if ($output instanceof ConsoleOutputInterface) {
            $this->statusSection = $output->section();
        }

        $totalConcurrency = 0;
        foreach ($workers as $cfg) {
            $totalConcurrency += max(0, (int)($cfg['num'] ?? 0));
        }
        $this->pool = Pool::create()
            ->concurrency(max(1, $totalConcurrency))
            ->timeout(315360000)
            ->sleepTime(100000);

        register_shutdown_function(function (): void {
            $this->stopWorkers();
        });

        $this->startWorkers($workers);

        while (true) {
            if ($this->shouldStop()) {
                $this->stopWorkers();
                return Command::SUCCESS;
            }

            if ($showStatus && (time() - $lastStatusAt) >= $statusInterval) {
                $this->renderStatus($output, $workers);
                $lastStatusAt = time();
            }

            usleep(200000);
        }
    }

    /**
     * @param array<string, array{type:string,driver:string,num:int,weights:array{high:int,medium:int,low:int}}> $workers
     */
    private function startWorkers(array $workers): void
    {
        foreach ($workers as $work => $cfg) {
            $concurrency = (int)($cfg['num'] ?? 0);
            for ($i = 0; $i < $concurrency; $i++) {
                $this->spawnWorker((string)$work, '');
            }
        }
    }

    /**
     * 启动一个消费子进程（由 spatie/async 托管）。
     */
    private function spawnWorker(string $work, string $priority): void
    {
        if (!$this->pool) {
            return;
        }

        $runnable = $this->pool->add(new QueueConsumeTask(
            $work,
            $priority,
            $this->runId,
            App::$basePath,
            App::$debug,
            App::$timezone
        ));

        $taskId = $runnable->getId();
        $this->slots[$taskId] = [
            'work' => $work,
            'priority' => $priority,
            'started_at' => time(),
        ];

        $runnable
            ->then(function (array $result = []) use ($taskId): void {
                $this->handleWorkerExit($taskId, null, (int)($result['exit_code'] ?? 0));
            })
            ->catch(function (Throwable $exception) use ($taskId): void {
                $this->handleWorkerExit($taskId, $exception, -1);
            })
            ->timeout(function () use ($taskId): void {
                $this->handleWorkerExit($taskId, new \RuntimeException('worker timeout'), -2);
            });
    }

    private function handleWorkerExit(int $taskId, ?Throwable $exception, int $exitCode): void
    {
        $slot = $this->slots[$taskId] ?? null;
        unset($this->slots[$taskId]);
        if (!$slot) {
            return;
        }

        $runtime = time() - (int)$slot['started_at'];
        if ($exception) {
            App::log('queue')->error('worker.exit.error', [
                'work' => (string)$slot['work'],
                'priority' => (string)$slot['priority'],
                'runtime_sec' => $runtime,
                'exit_code' => $exitCode,
                'reason' => $exception->getMessage(),
                'type' => $exception::class,
            ]);
        } elseif ($exitCode !== 0) {
            App::log('queue')->warning('worker.exit.non_zero', [
                'work' => (string)$slot['work'],
                'priority' => (string)$slot['priority'],
                'runtime_sec' => $runtime,
                'exit_code' => $exitCode,
            ]);
        }

        if ($this->shouldStop()) {
            return;
        }

        if ($runtime < 3) {
            usleep(500000);
        }
        $this->spawnWorker((string)$slot['work'], (string)$slot['priority']);
    }

    /**
     * 停止全部 worker。
     */
    private function stopWorkers(): void
    {
        if (!$this->pool) {
            return;
        }
        $this->pool->stop();
        foreach ($this->pool->getInProgress() as $process) {
            try {
                $process->stop(1);
            } catch (Throwable) {
            }
        }
    }

    /**
     * @param array<string, array{type:string,driver:string,num:int,weights:array{high:int,medium:int,low:int}}> $workers
     */
    private function renderStatus(OutputInterface $output, array $workers): void
    {
        $rows = [];
        $stats = App::queue()->stats();
        $byWork = [];
        foreach ($stats as $row) {
            $byWork[$row['name']] = $row;
        }

        $runningByWork = [];
        foreach ($workers as $work => $cfg) {
            $runningByWork[(string)$work] = 0;
        }
        if ($this->pool) {
            foreach ($this->pool->getInProgress() as $process) {
                $taskId = (int)$process->getId();
                $slot = $this->slots[$taskId] ?? null;
                if (!$slot) {
                    continue;
                }
                $work = (string)$slot['work'];
                $runningByWork[$work] = (int)($runningByWork[$work] ?? 0) + 1;
            }
        }

        foreach ($workers as $work => $cfg) {
            $row = $byWork[$work] ?? null;
            $rows[] = [
                (string)$work,
                (string)($cfg['num'] ?? 0),
                (string)($cfg['weights']['high'] ?? 0) . '/' . (string)($cfg['weights']['medium'] ?? 0) . '/' . (string)($cfg['weights']['low'] ?? 0),
                (string)($runningByWork[(string)$work] ?? 0),
                ($row && $row['pending'] !== null) ? (string)$row['pending'] : '-',
                ($row && $row['running'] !== null) ? (string)$row['running'] : '-',
                $row ? (string)$row['executed'] : '-',
                $row ? (string)$row['failed'] : '-',
                date('Y-m-d H:i:s'),
            ];
        }

        $tableOutput = $this->statusSection ?: $output;
        if ($this->statusSection) {
            $this->statusSection->clear();
        }
        $table = new Table($tableOutput);
        $table
            ->setHeaders(['work', 'num', 'weight(h/m/l)', 'procs', 'pending', 'running', 'executed', 'failed', 'time'])
            ->setRows($rows);
        $table->render();
    }

    /**
     * 注册退出信号（Linux/macOS 可用；Windows 下自动降级为无信号模式）。
     */
    private function registerSignalHandlers(): void
    {
        if ($this->isWindows() || !function_exists('pcntl_async_signals')) {
            return;
        }
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function (): void {
            $this->stop = true;
        });
        pcntl_signal(SIGTERM, function (): void {
            $this->stop = true;
        });
    }

    /**
     * 是否需要退出。
     */
    private function shouldStop(): bool
    {
        return $this->stop;
    }

    /**
     * 判断是否为 Windows。
     */
    private function isWindows(): bool
    {
        return \DIRECTORY_SEPARATOR === '\\';
    }
}
