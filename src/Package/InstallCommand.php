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

class InstallCommand extends Command
{

    protected static $defaultName = 'install';
    protected static $defaultDescription = 'Installation application';

    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::REQUIRED,
            'please enter the app name'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        $auth = Package::getKey();

        if (!$auth) {
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
        } else {
            [$username, $password] = $auth;
        }

        try {
            Install::main($output, $username, $password, $name);
        } finally {
            FileSystem::delete(data_path('package'));
        }

        $application = $this->getApplication();
        Package::installOther($application, $output);

        return Command::SUCCESS;
    }

}