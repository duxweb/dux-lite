<?php
declare(strict_types=1);

namespace Core\Permission;

use Core\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PermissionCommand extends Command
{

    protected function configure(): void
    {
        $this->setName("permission:list")->setDescription('show permission list');
        $this->addArgument('group', InputArgument::OPTIONAL, 'show group permission');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {

        $group = $input->getArgument("group");
        if ($group) {
            $permissionList = [$group => App::permission()->get($group)];
        } else {
            $permissionList = App::permission()->app;
        }

        foreach ($permissionList as $key => $item) {
            $data = [];
            $permissions = $item->get();
            foreach ($permissions as $k => $permission) {
                if ($k) {
                    $data[] = new TableSeparator();
                }
                $data[] = [$permission["name"]];
                foreach ($permission["children"] as $vo) {
                    $data[] = [$vo["name"]];
                }
            }
            $table = new Table($output);
            $table
                ->setHeaders([
                    [new TableCell("permissions {$key}", ['colspan' => 1])],
                    ['Name']
                ])
                ->setRows($data);
            $table->render();
        }


        return Command::SUCCESS;
    }
}