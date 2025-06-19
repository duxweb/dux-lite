<?php

declare(strict_types=1);

namespace Core\Database;

use Core\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName("db:sync")->setDescription('Synchronize model data tables and fields');
        $this->addArgument('app', InputArgument::OPTIONAL, 'please enter the app name');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = $input->getArgument('app') ?: '';
        App::dbMigrate()->migrate($output, $app);
        $output->writeln("<info>Sync database successfully</info>");
        return Command::SUCCESS;
    }
}
