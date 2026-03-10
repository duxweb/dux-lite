<?php

declare(strict_types=1);

namespace Core\Plugin;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PluginRefreshCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin:refresh')->setDescription('Refresh plugin registry');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        unset($input);

        $plugins = PluginRegistry::rebuild();
        $output->writeln(sprintf('Plugin registry refreshed: %d plugin(s)', count($plugins)));

        foreach (array_keys($plugins) as $name) {
            $output->writeln(' - ' . $name);
        }

        return Command::SUCCESS;
    }
}
