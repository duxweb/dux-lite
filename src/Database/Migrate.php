<?php

declare(strict_types=1);

namespace Core\Database;

use Core\App;
use Core\Database\Attribute\AutoMigrate;
use Doctrine\DBAL\DriverManager;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Symfony\Component\Console\Output\OutputInterface;

class Migrate
{
    public array $migrate = [];

    public function register(string $model): void
    {
        $this->migrate[] = $model;
    }

    public function migrate(OutputInterface $output, string $appName = ''): void
    {
        $appName = ucfirst($appName);


        $seeds = [];
        $syncModels = [];

        foreach ($this->migrate as $model) {
            if ($appName && !str_contains($model, "\\$appName\\Models\\")) {
                continue;
            }

            if (!method_exists($model, 'migration')) {
                continue;
            }
            $startTime = microtime(true);
            $modelObj = new $model;

            $connect = $modelObj->getConnection();
            $this->migrateTable($connect, $modelObj, $seeds);

            if (method_exists($model, 'migrationAfter')) {
                $modelObj->migrationAfter($connect);
            }


            $time = round(microtime(true) - $startTime, 3);
            $output?->writeln("sync model <info>$model</info> {$time}s");

            $syncModels[] = $modelObj;
        }

        foreach ($seeds as $seed) {
            $startTime = microtime(true);
            $seed->seed($seed->getConnection());
            $time = round(microtime(true) - $startTime, 3);
            $seedName = $seed::class;
            $output?->writeln("sync send <info>$seedName</info> {$time}s");
        }

        foreach ($syncModels as $seed) {
            if (!method_exists($seed, 'install')) {
                continue;
            }

            $startTime = microtime(true);
            $seed->install($seed->getConnection());
            $time = round(microtime(true) - $startTime, 3);
            $seedName = $seed::class;
            $output?->writeln("sync install <info>$seedName</info> {$time}s");
        }


    }

    private function migrateTable(Connection $connect, Model $model, &$seed): void
    {
        $pre = $connect->getTablePrefix();
        $modelTable = $model->getTable();
        $tempTable = 'table_' . $modelTable;
        $tableExists = $connect->getSchemaBuilder()->hasTable($modelTable);
        $connect->getSchemaBuilder()->dropIfExists($tempTable);
        $connect->getSchemaBuilder()->create($tableExists ? $tempTable : $modelTable, function (Blueprint $table) use ($model) {
            if ($model->getTableComment()) {
                $table->comment($model->getTableComment());
            }
            $model->migration($table);
            $model->migrationGlobal($table);
        });
        if (!$tableExists) {
            if (method_exists($model, 'seed')) {
                $seed[] = $model;
            }
            return;
        }

        // 更新表字段
        $connection = $this->getDoctrineConnection($connect);
        $schemaManager = $connection->createSchemaManager();
        $tableDiff = $schemaManager->createComparator()->compareTables(
            $schemaManager->introspectTable($pre . $modelTable),
            $schemaManager->introspectTable($pre . $tempTable)
        );
        if (!$tableDiff->isEmpty()) {
            $schemaManager->alterTable($tableDiff);
        }
        $connect->getSchemaBuilder()->drop($tempTable);
    }

    public function getDoctrineConnection(Connection $modelConnection): \Doctrine\DBAL\Connection
    {
        $connectionSettings = $modelConnection->getConfig();
        return DriverManager::getConnection([
            'dbname' => $connectionSettings['database'],
            'user' => $connectionSettings['username'],
            'password' => $connectionSettings['password'],
            'host' => $connectionSettings['host'],
            'driver' => 'pdo_' . $connectionSettings['driver'],
        ]);
    }

    // 注册迁移模型
    public function registerAttribute(): void
    {
        $attributes = App::attributes();
        foreach ($attributes as $item) {
            foreach ($item["annotations"] as $annotation) {
                if ($annotation["name"] != AutoMigrate::class) {
                    continue;
                }
                $this->register($annotation["class"]);
            }
        }
    }
}

