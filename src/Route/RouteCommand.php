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
                $middleware = "NULL";
                if (!empty($route["middleware"])) {
                    $middleware = implode("\n", array_map(static function ($item): string {
                        if (is_object($item)) {
                            return $item::class;
                        }
                        if (is_string($item)) {
                            return $item;
                        }
                        return (string)json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }, (array)$route["middleware"]));
                }
                $methods = is_array($route["methods"]) ? implode("|", $route["methods"]) : $route["methods"];
                $data[] = [$route["pattern"], $route["name"], $methods, $middleware];
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
