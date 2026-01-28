<?php
declare(strict_types=1);

namespace Core\Scheduler;

use Core\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SchedulerCommand extends Command
{
    protected function configure(): void
    {
        $this->setName("scheduler:run")->setDescription('Scheduler start service');
        $this
            ->addOption('watch-interval', null, InputOption::VALUE_REQUIRED, 'Watch interval (seconds)', '3');
    }


    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $watchInterval = max(1, (int)$input->getOption('watch-interval'));

        $scheduler = App::scheduler();
        $jobs = $scheduler->loadJobs();
        $output->writeln('<info>Watching jobs file: ' . $scheduler->jobsFilePath() . '</info>');

        $rows = $this->formatRows($jobs);
        if (!$rows) {
            $rows = [['Not Scheduler Jobs', '-', '-', '-']];
        }

        $table = new Table($output);
        $table
            ->setHeaders(['name', 'cron', 'callback', 'desc'])
            ->setRows($rows);
        $table->render();

        if (function_exists('pcntl_fork') && function_exists('posix_kill')) {
            $jobsPath = $scheduler->jobsFilePath();
            $hash = $this->hashFile($jobsPath);
            $pid = pcntl_fork();
            if ($pid === -1) {
                $output->writeln('<error>Unable to fork watcher process, running without watcher.</error>');
            } elseif ($pid === 0) {
                $code = $scheduler->run();
                exit($code === Scheduler::EXIT_OK ? Command::SUCCESS : $code);
            } else {
                $interval = max(1, $watchInterval);
                while (true) {
                    $status = null;
                    $res = pcntl_waitpid($pid, $status, WNOHANG);
                    if ($res === $pid) {
                        $exitCode = pcntl_wexitstatus($status);
                        return $exitCode === 0 ? Command::SUCCESS : $exitCode;
                    }

                    sleep($interval);
                    $newHash = $this->hashFile($jobsPath);
                    if ($hash !== $newHash) {
                        $output->writeln('<comment>Jobs file changed, stopping scheduler...</comment>');
                        App::log('scheduler')->info('Scheduler jobs file changed, stopping', [
                            'file' => $jobsPath,
                        ]);
                        posix_kill($pid, SIGTERM);
                        sleep(1);
                        posix_kill($pid, SIGKILL);
                        return Scheduler::EXIT_RESTART;
                    }
                }
            }
        }

        $code = $scheduler->run();
        if ($code === Scheduler::EXIT_RESTART) {
            return Scheduler::EXIT_RESTART;
        }
        return $code === Scheduler::EXIT_OK ? Command::SUCCESS : $code;
    }

    private function formatRows(array $jobs): array
    {
        $rows = [];
        foreach ($jobs as $job) {
            $rows[] = [
                (string)($job['name'] ?? ''),
                (string)($job['cron'] ?? ''),
                (string)($job['callback'] ?? ''),
                (string)($job['desc'] ?? ''),
            ];
        }
        return $rows;
    }

    private function hashFile(string $path): ?string
    {
        clearstatcache(true, $path);
        if (!is_file($path)) {
            return null;
        }
        return hash_file('sha256', $path) ?: null;
    }
}
