<?php
declare(strict_types=1);

namespace Core\Scheduler;

use Core\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SchedulerCommand extends Command
{
    protected function configure(): void
    {
        $this->setName("scheduler:run")->setDescription('Scheduler start service');
    }


    public function execute(InputInterface $input, OutputInterface $output): int
    {
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

        $code = $scheduler->run();
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

}
