<?php

declare(strict_types=1);

namespace Core\Database;

use Core\App;
use Core\Database\Attribute\AutoMigrate;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column as DbalColumn;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\DecimalType;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Symfony\Component\Console\Output\OutputInterface;

class Migrate
{
    public array $migrate = [];
    private array $doctrineConnections = [];

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
            $output?->writeln("sync seed <info>$seedName</info> {$time}s");
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
        $tableExists = $connect->getSchemaBuilder()->hasTable($modelTable);
        $connectionSettings = $connect->getConfig();
        $driver = (string)($connectionSettings['driver'] ?? '');
        $isSqlite = $driver === 'sqlite';
        // SQLite index names are global; use a unique temp table name to avoid
        // conflicts when target table indexes were previously created with temp names.
        $tempTable = 'table_' . $modelTable;
        if ($isSqlite && $tableExists) {
            $tempTable .= '_' . substr(md5($modelTable . microtime(true) . random_int(1000, 9999)), 0, 8);
        }
        $createdTemp = false;
        try {
            $connect->getSchemaBuilder()->dropIfExists($tempTable);
            try {
                $connect->getSchemaBuilder()->create($tableExists ? $tempTable : $modelTable, function (Blueprint $table) use ($model, $connectionSettings) {
                    if (!empty($connectionSettings['charset'])) {
                        $table->charset($connectionSettings['charset']);
                    }
                    if (!empty($connectionSettings['collation'])) {
                        $table->collation($connectionSettings['collation']);
                    }
                    if ($model->getTableComment()) {
                        $table->comment($model->getTableComment());
                    }
                    $model->migration($table);
                    $model->migrationGlobal($table);
                });
            } catch (\Throwable $e) {
                if ($isSqlite && $tableExists && $this->isSqliteIndexCreateConflict($e)) {
                    return;
                }
                throw $e;
            }
            $createdTemp = $tableExists;

            if (!$tableExists) {
                $seed[] = $model;
                return;
            }

            // 更新表字段
            $connection = $this->getDoctrineConnection($connect);
            $schemaManager = $connection->createSchemaManager();
            $currentTable = $schemaManager->introspectTableByUnquotedName($pre . $modelTable);
            $targetTable = $schemaManager->introspectTableByUnquotedName($pre . $tempTable);
            if ($isSqlite) {
                $this->normalizeSqliteDecimalColumns($currentTable, $targetTable);
            }
            $tableDiff = $schemaManager->createComparator()->compareTables(
                $currentTable,
                $targetTable
            );
            if (!$tableDiff->isEmpty()) {
                // SQLite indexes are database-global. When comparing with a temp table, keep
                // the diff but drop temp table first to avoid duplicate index name conflicts.
                if ($isSqlite && $createdTemp) {
                    $connect->getSchemaBuilder()->drop($tempTable);
                    $createdTemp = false;
                }
                $schemaManager->alterTable($tableDiff);
            }
            $this->forceTableCharset($connect, $modelTable);
        } finally {
            if ($createdTemp && $connect->getSchemaBuilder()->hasTable($tempTable)) {
                $connect->getSchemaBuilder()->drop($tempTable);
            }
        }
    }

    public function getDoctrineConnection(Connection $modelConnection): \Doctrine\DBAL\Connection
    {
        $connectionKey = spl_object_hash($modelConnection);
        if (isset($this->doctrineConnections[$connectionKey])) {
            return $this->doctrineConnections[$connectionKey];
        }

        $connectionSettings = $modelConnection->getConfig();
        $driver = (string)($connectionSettings['driver'] ?? 'mysql');
        if ($driver === 'sqlite') {
            $database = (string)($connectionSettings['database'] ?? '');
            $path = $this->resolveSqlitePath($database);
            $options = [
                'driver' => 'pdo_sqlite',
                'path' => $path,
            ];
            $this->doctrineConnections[$connectionKey] = DriverManager::getConnection($options);
            return $this->doctrineConnections[$connectionKey];
        }

        $options = [
            'dbname' => $connectionSettings['database'],
            'user' => $connectionSettings['username'],
            'password' => $connectionSettings['password'],
            'host' => $connectionSettings['host'],
            'port' => $connectionSettings['port'] ?? 3306,
            'driver' => 'pdo_' . $driver,
            'charset' => $connectionSettings['charset'] ?? 'utf8mb4',
        ];
        if (!empty($connectionSettings['collation'])) {
            $options['defaultTableOptions'] = [
                'charset' => $options['charset'],
                'collation' => $connectionSettings['collation'],
            ];
        }
        $this->doctrineConnections[$connectionKey] = DriverManager::getConnection($options);
        return $this->doctrineConnections[$connectionKey];
    }

    private function resolveSqlitePath(string $database): string
    {
        if ($database === ':memory:' || str_starts_with($database, 'file:')) {
            return $database;
        }
        if (preg_match('/^[a-zA-Z]:[\\\\\\/]/', $database) || str_starts_with($database, '/') || str_starts_with($database, '\\\\')) {
            return $database;
        }
        return base_path($database);
    }

    private function forceTableCharset(Connection $connect, string $table): void
    {
        $connectionSettings = $connect->getConfig();
        if (($connectionSettings['driver'] ?? '') !== 'mysql') {
            return;
        }
        if (empty($connectionSettings['charset']) || empty($connectionSettings['collation'])) {
            return;
        }
        $charset = $connectionSettings['charset'];
        $collation = $connectionSettings['collation'];
        $tableName = $connect->getTablePrefix() . $table;
        if (!$this->needsCharsetConversion($connect, $tableName, $charset, $collation, $connectionSettings['database'] ?? null)) {
            return;
        }
        $connect->statement(
            "ALTER TABLE `{$tableName}` CONVERT TO CHARACTER SET {$charset} COLLATE {$collation}"
        );
    }

    private function needsCharsetConversion(
        Connection $connect,
        string $tableName,
        string $charset,
        string $collation,
        ?string $database
    ): bool {
        if (!$database) {
            return false;
        }
        $tableInfo = $connect->selectOne(
            'SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$database, $tableName]
        );
        if ($tableInfo && isset($tableInfo->TABLE_COLLATION) && $tableInfo->TABLE_COLLATION !== $collation) {
            return true;
        }
        $columns = $connect->select(
            'SELECT COLLATION_NAME, CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLLATION_NAME IS NOT NULL',
            [$database, $tableName]
        );
        foreach ($columns as $column) {
            if (($column->COLLATION_NAME ?? null) !== $collation || ($column->CHARACTER_SET_NAME ?? null) !== $charset) {
                return true;
            }
        }
        return false;
    }

    private function normalizeSqliteDecimalColumns(Table $currentTable, Table $targetTable): void
    {
        $columns = [];
        foreach ($currentTable->getColumns() as $column) {
            $columns[] = $column->getObjectName()->toString();
        }
        foreach ($targetTable->getColumns() as $column) {
            $columns[] = $column->getObjectName()->toString();
        }
        $columns = array_unique($columns);
        foreach ($columns as $columnName) {
            $currentColumn = $currentTable->hasColumn($columnName) ? $currentTable->getColumn($columnName) : null;
            $targetColumn = $targetTable->hasColumn($columnName) ? $targetTable->getColumn($columnName) : null;
            if (!$this->isDecimalColumn($currentColumn) && !$this->isDecimalColumn($targetColumn)) {
                continue;
            }
            $precision = $currentColumn?->getPrecision() ?? $targetColumn?->getPrecision() ?? 10;
            $scale = $currentColumn?->getScale() ?? $targetColumn?->getScale() ?? 0;
            if ($this->isDecimalColumn($currentColumn) && $currentColumn->getPrecision() === null) {
                $currentColumn->setPrecision($precision);
                $currentColumn->setScale($scale);
            }
            if ($this->isDecimalColumn($targetColumn) && $targetColumn->getPrecision() === null) {
                $targetColumn->setPrecision($precision);
                $targetColumn->setScale($scale);
            }
        }
    }

    private function isDecimalColumn(?DbalColumn $column): bool
    {
        return $column && $column->getType() instanceof DecimalType;
    }

    private function isSqliteIndexCreateConflict(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'index') && str_contains($message, 'already exists');
    }

    // 注册迁移模型
    public function registerAttribute(): void
    {
        $attributes = App::attributes();
        foreach ($attributes as $item) {
            foreach ($item["annotations"] as $annotation) {
                if ($annotation["name"] !== AutoMigrate::class) {
                    continue;
                }
                $this->register($annotation["class"]);
            }
        }
    }
}
