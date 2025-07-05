<?php
declare(strict_types=1);

namespace Core\App;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppCommand extends Command
{
    protected function configure(): void
    {
        $this->setName("app:cache")->setDescription('Clear app cache');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheData = [
            data_path("/cache/attributes.cache"),
            data_path("/cache/route.cache"),
        ];

        foreach ($cacheData as $cache) {
            if (file_exists($cache)) {
                unlink($cache);
            }
        }

        $output->writeln("Cache cleared successfully");
        return Command::SUCCESS;
    }
}