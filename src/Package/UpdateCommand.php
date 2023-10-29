<?php
declare(strict_types=1);

namespace Dux\Package;

use Nette\Utils\FileSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateCommand extends Command
{

    protected static $defaultName = 'update';
    protected static $defaultDescription = 'Update the application';

    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::OPTIONAL,
            'please enter the app name'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $app = $input->getArgument('name');

        $helper = $this->getHelper('question');
        $question = new Question('Please enter username: ');
        $username = $helper->ask($input, $output, $question);
        if (!$username) {
            $io->error('Username not entered');
            return Command::FAILURE;
        }

        $question = new Question('Please enter password: ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $password = $helper->ask($input, $output, $question);
        if (!$password) {
            $io->error('password not entered');
            return Command::FAILURE;
        }

        try {
            Update::main($input, $output, $io, $username, $password, $app);
        } finally {
            FileSystem::delete(data_path('package'));
        }

        $application = $this->getApplication();
        Package::installOther($application, $output);

        return Command::SUCCESS;
    }

}