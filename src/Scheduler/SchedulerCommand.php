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
        $this->setName("scheduler")->setDescription('Scheduler start service');
    }


    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = App::scheduler()->data ?: [['Not Scheduler Jobs']];
        $table = new Table($output);
        $table->setHeaders(['Core Scheduler Service', date('Y-m-d H:i:s')])
            ->setRows($data);
        $table->render();
        App::scheduler()->run();
        return Command::SUCCESS;
    }
}