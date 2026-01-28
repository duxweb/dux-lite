<?php

declare(strict_types=1);

namespace Core\Queue;

use Core\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Process\Process;

class QueueCommand extends Command
{
    /**
     * manager 进程是否需要退出（跨平台）。
     */
    private bool $stop = false;
    private ?ConsoleSectionOutput $statusSection = null;

    /**
     * 启动管理进程：按 workers 配置拉起多个 queue:consume 子进程，并周期输出队列状态。
     */
    protected function configure(): void
    {
        $this
            ->setName('queue:start')
            ->setDescription('队列管理进程（按配置启动并发 worker）')
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
        $processes = [];
        register_shutdown_function(function () use (&$processes) {
            // 跨平台兜底：manager 退出时尽量停止全部子进程，避免 orphan。
            $this->stopWorkers($processes);
        });

        $runId = date('YmdHis') . '-' . getmypid();
        putenv('DUX_QUEUE_RUN_ID=' . $runId);

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

        $this->registerSignalHandlers();

        $entry = $this->resolveConsoleEntry();
        if ($entry === null) {
            $output->writeln('Unable to resolve console entry script path.');
            $output->writeln('Try running this command via a script file (e.g. `php dux queue:start`).');
            return Command::FAILURE;
        }

        $output->writeln('Start time: ' . date('Y-m-d H:i:s'));
        $output->writeln('Worker command: ' . PHP_BINARY . ' ' . $entry . ' queue:consume <work> [priority]');
        if (method_exists($output, 'section')) {
            $this->statusSection = $output->section();
        }

        $processes = $this->startWorkers($workers, $entry);

        while (true) {
            if ($this->shouldStop()) {
                $this->stopWorkers($processes);
                return Command::SUCCESS;
            }

            $this->restartDeadWorkers($processes, $entry);

            if ($showStatus) {
                $this->renderStatus($output, $workers, $processes);
            }

            sleep($statusInterval);
        }
    }

    /**
     * @param array<string, array{type:string,driver:string,num:int,weights:array{high:int,medium:int,low:int}}> $workers
     * @return array<string, array<string, array<int, Process>>>
     */
    private function startWorkers(array $workers, string $entry): array
    {
        $processes = [];
        foreach ($workers as $work => $cfg) {
            $processes[$work] = ['all' => []];
            $concurrency = (int)($cfg['num'] ?? 0);
            for ($i = 0; $i < $concurrency; $i++) {
                $processes[$work]['all'][] = $this->startWorkerProcess($entry, (string)$work, '');
            }
        }
        return $processes;
    }

    /**
     * 启动一个消费子进程。
     */
    private function startWorkerProcess(string $entry, string $work, string $priority = ''): Process
    {
        $cmd = [PHP_BINARY, $entry, 'queue:consume', $work];
        if ($priority !== '') {
            $cmd[] = $priority;
        }
        $process = new Process($cmd);
        $process->setEnv([
            ...($_ENV ?? []),
            'DUX_QUEUE_RUN_ID' => (string)(getenv('DUX_QUEUE_RUN_ID') ?: ''),
            'DUX_QUEUE_WORK' => $work,
            'DUX_QUEUE_PRIORITY' => $priority,
        ]);
        $process->setTimeout(null);
        $process->disableOutput();
        $process->start();
        return $process;
    }

    /**
     * @param array<string, array<string, array<int, Process>>> $processes
     */
    private function stopWorkers(array $processes): void
    {
        foreach ($processes as $work) {
            foreach ($work as $list) {
                foreach ($list as $process) {
                    if ($process->isRunning()) {
                        $this->terminateProcess($process);
                    }
                }
            }
        }
    }

    /**
     * @param array<string, array<string, array<int, Process>>> $processes
     */
    private function restartDeadWorkers(array &$processes, string $entry): void
    {
        foreach ($processes as $work => &$queues) {
            foreach ($queues as $priority => &$list) {
                foreach ($list as $idx => $process) {
                    if ($process->isRunning()) {
                        continue;
                    }
                    $priorityName = (string)$priority;
                    if ($priorityName === 'all') {
                        $priorityName = '';
                    }
                    $list[$idx] = $this->startWorkerProcess($entry, (string)$work, $priorityName);
                }
            }
            unset($list);
        }
        unset($queues);
    }

    /**
     * @param array<string, array{type:string,driver:string,num:int,weights:array{high:int,medium:int,low:int}}> $workers
     * @param array<string, array<string, array<int, Process>>> $processes
     */
    private function renderStatus(OutputInterface $output, array $workers, array $processes): void
    {
        $rows = [];
        $stats = App::queue()->stats();
        $byWork = [];
        foreach ($stats as $row) {
            $byWork[$row['name']] = $row;
        }

        foreach ($workers as $work => $cfg) {
            $running = 0;
            foreach (($processes[$work] ?? []) as $priority => $list) {
                foreach ($list as $p) {
                    if ($p->isRunning()) {
                        $running++;
                    }
                }
            }

            $row = $byWork[$work] ?? null;
            $rows[] = [
                (string)$work,
                (string)($cfg['num'] ?? 0),
                (string)($cfg['weights']['high'] ?? 0) . '/' . (string)($cfg['weights']['medium'] ?? 0) . '/' . (string)($cfg['weights']['low'] ?? 0),
                (string)$running,
                $row ? (string)$row['pending'] : '-',
                $row ? (string)$row['running'] : '-',
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
     * 解析当前命令入口脚本路径（用于拉起子进程）。
     */
    private function resolveConsoleEntry(): ?string
    {
        $argv0 = $_SERVER['argv'][0] ?? null;
        if (is_string($argv0) && $argv0 !== '' && is_file($argv0)) {
            return $argv0;
        }

        $phpSelf = $_SERVER['PHP_SELF'] ?? null;
        if (is_string($phpSelf) && $phpSelf !== '' && is_file($phpSelf)) {
            return $phpSelf;
        }

        return null;
    }

    /**
     * 注册退出信号（Linux/macOS 可用；Windows 下自动降级为无信号模式）。
     */
    private function registerSignalHandlers(): void
    {
        // manager 模式下，收到信号后退出 while 循环并 stop 子进程
        if ($this->isWindows() || !function_exists('pcntl_async_signals')) {
            return;
        }
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function () {
            $this->stop = true;
        });
        pcntl_signal(SIGTERM, function () {
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
     * 终止子进程（跨平台）。
     * - Linux/macOS：尽量发送 SIGTERM
     * - Windows：使用 Process::stop() 终止
     */
    private function terminateProcess(Process $process): void
    {
        if (!$process->isRunning()) {
            return;
        }

        if ($this->isWindows()) {
            // stop() 在 Windows 下会走 proc_terminate，避免 signal() 行为不一致。
            $process->stop(3);
            return;
        }

        // POSIX 下优先用信号，失败再 stop()
        try {
            $process->signal(SIGTERM);
        } catch (\Throwable) {
            $process->stop(3);
        }
    }

    /**
     * 判断是否为 Windows。
     */
    private function isWindows(): bool
    {
        return \DIRECTORY_SEPARATOR === '\\';
    }

}
