<?php
declare(strict_types=1);

namespace Core\Scheduler;

use Core\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SchedulerGenCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('scheduler:gen')->setDescription('Generate scheduler jobs file');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $scheduler = new Scheduler();
        $scheduler->registerAttribute();

        $data = $scheduler->gen();
        $file = $scheduler->jobsFilePath();

        $output->writeln('Generated: ' . $file);

        $table = new Table($output);
        $table
            ->setHeaders(['name', 'cron', 'callback', 'desc'])
            ->setRows($this->formatRows($data));
        $table->render();

        return Command::SUCCESS;
    }

    private function formatRows(array $jobs): array
    {
        if (!$jobs) {
            return [['Not Scheduler Jobs', '-', '-', '-']];
        }
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

