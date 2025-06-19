<?php
declare(strict_types=1);

namespace Core\Route;

use Core\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RouteCommand extends Command
{

    protected function configure(): void
    {
        $this->setName("route:list")->setDescription('show route list');
        $this->addArgument('group', InputArgument::OPTIONAL, 'please enter the route group name');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {

        $group = $input->getArgument("group");
        if ($group) {
            $routeList = [$group => App::route()->get($group)];
        } else {
            $routeList = App::route()->app;
        }

        foreach ($routeList as $key => $item) {
            $data = [];
            $routes = $item->parseData();
            foreach ($routes as $k => $route) {
                if ($k) {
                    $data[] = new TableSeparator();
                }
                $data[] = [$route["pattern"], $route["name"], is_array($route["methods"]) ? implode("|", $route["methods"]) : $route["methods"], $route["middleware"] ? implode("\n", $route["middleware"]) : "NULL"];
            }
            $table = new Table($output);
            $table
                ->setHeaders([
                    [new TableCell("routes {$key}", ['colspan' => 3])],
                    ['Pattern', 'Name', 'Methods', 'middleware']
                ])
                ->setRows($data);
            $table->render();
        }


        return Command::SUCCESS;
    }
}