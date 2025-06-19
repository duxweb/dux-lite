<?php

declare(strict_types=1);


namespace Core\Queue;

use Core\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class QueueCommand extends Command
{

    protected function configure(): void
    {
        $this->setName("queue:start")->setDescription('Queue service start')
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'please enter the queue name',
                'queue'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $table = new Table($output);
        $table->setHeaders(array('Queue Service'))
            ->setRows(array(
                array('Core Ver: ' . App::$version),
                array('Run Time: ' . date('Y-m-d H:i:s')),
            ));
        $table->render();
        App::queue()->process($name);
        return Command::SUCCESS;
    }
}
