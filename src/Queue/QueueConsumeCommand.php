<?php

declare(strict_types=1);

namespace Core\Queue;

use Core\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QueueConsumeCommand extends Command
{
    /**
     * 启动单个 worker 进程（常驻消费）。
     */
    protected function configure(): void
    {
        $this
            ->setName('queue:consume')
            ->setDescription('队列消费进程（常驻）')
            ->addArgument('work', InputArgument::OPTIONAL, 'worker 名（workers.<work>）', '')
            ->addArgument('priority', InputArgument::OPTIONAL, '优先级（high/medium/low），为空按权重混合消费', '');
    }

    /**
     * 执行消费进程（常驻）：消费指定 work + priority 的物理队列。
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        unset($output);
        $work = (string)$input->getArgument('work');
        $priority = (string)$input->getArgument('priority');
        App::queue()->process($priority, $work);
        return Command::SUCCESS;
    }
}
