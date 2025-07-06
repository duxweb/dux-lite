<?php

declare(strict_types=1);

namespace Core\Worker;

use Core\App;
use Core\Command\Attribute\Command;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[Command]
class WorkerCommand extends BaseCommand
{
    protected static $defaultName = 'worker:start';
    protected static $defaultDescription = 'Start FrankenPHP Worker mode';

    protected function configure(): void
    {
        $serverConfig = App::config('server');

        $this
            ->setName('worker:start')
            ->setDescription('Start FrankenPHP Worker mode')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Listen port', $serverConfig->get('worker.port', 8080))
            ->addOption('max-requests', 'm', InputOption::VALUE_OPTIONAL, 'Max requests per worker', $serverConfig->get('worker.max_requests', 0))
            ->addOption('workers', 'w', InputOption::VALUE_OPTIONAL, 'Number of worker processes', $serverConfig->get('worker.workers', 0));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $frankenphpPath = $this->findFrankenPHP();
        if (!$frankenphpPath) {
            $io->error('FrankenPHP not found. Install: curl -fsSL https://frankenphp.dev/install.sh | sh');
            return BaseCommand::FAILURE;
        }

        $workingDir = base_path('public');
        if (!is_dir($workingDir)) {
            $io->error('Public directory not found: ' . $workingDir);
            return BaseCommand::FAILURE;
        }

        $port = (int)$input->getOption('port');
        $maxRequests = (int)$input->getOption('max-requests');
        $workers = (int)$input->getOption('workers');

        // 读取 [php] 配置，拼接 -d key=value
        $phpConfig = App::config('server')->get('php', []);
        $phpArgs = [];
        foreach ($phpConfig as $key => $value) {
            $phpArgs[] = "-d";
            $phpArgs[] = "$key=$value";
        }

        $cmd = array_merge([
            $frankenphpPath, 'php-server', '--listen', "0.0.0.0:{$port}", '--worker', 'index.php'
        ], $phpArgs);
        if ($workers > 0) {
            $cmd[] = (string)$workers;
        }

        $this->displayBanner($port, $maxRequests, $workers, $phpConfig);

        $env = array_merge($_ENV, [
            'MAX_REQUESTS' => (string)$maxRequests
        ]);

        $process = new Process($cmd, $workingDir, $env);
        $process->setTty(Process::isTtySupported());
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        return $process->getExitCode();
    }

    private function displayBanner(int $port, int $maxRequests, int $workers, array $phpConfig): void
    {
        $data = [
            'Port' => $port,
            'Workers' => $workers > 0 ? $workers : 'auto',
            'Max requests' => $maxRequests > 0 ? number_format($maxRequests) : 'unlimited',
        ];
        $extra = [];
        foreach ($phpConfig as $key => $value) {
            $extra[$key] = $value;
        }
        App::$host = '0.0.0.0:' . $port;
        App::banner($data, $extra);
    }

    private function findFrankenPHP(): ?string
    {
        $whichResult = trim(shell_exec('which frankenphp 2>/dev/null') ?: '');
        if (!empty($whichResult) && is_executable($whichResult)) {
            return $whichResult;
        }

        foreach (['frankenphp', '/opt/homebrew/bin/frankenphp', '/usr/local/bin/frankenphp', '/usr/bin/frankenphp', './frankenphp'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}