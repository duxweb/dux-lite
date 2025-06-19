# 命令行工具

DuxLite 提供了强大的命令行工具系统，基于 Symfony Console 组件，支持自定义命令开发、自动发现和注册机制。通过命令行工具，您可以执行数据迁移、定时任务、维护脚本等各种操作。

## 设计理念

DuxLite 命令行系统采用**简单、高效、可扩展的命令行工具**设计：

- **自动发现**：通过注解自动发现和注册命令
- **标准化接口**：基于 Symfony Console 的标准化命令接口
- **依赖注入**：命令类支持依赖注入和服务容器
- **灵活配置**：支持丰富的参数选项和交互式输入
- **错误处理**：完整的异常处理和错误报告机制

### 核心组件

- **Command 类**：命令系统核心类，提供注册和发现功能
- **Console 应用**：基于 Symfony Console 的命令行应用程序
- **Command 注解**：用于标记自定义命令类的注解
- **属性扫描**：自动扫描带注解的命令类

## 命令注册和发现

### 自动命令发现

DuxLite 使用注解来自动发现和注册命令：

```php
use Core\Command\Command;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command]
class ExampleCommand extends BaseCommand
{
    protected static $defaultName = 'app:example';
    protected static $defaultDescription = '示例命令';

    protected function configure(): void
    {
        $this
            ->setName('app:example')
            ->setDescription('这是一个示例命令')
            ->setHelp('此命令演示如何创建自定义命令');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello from example command!');
        return Command::SUCCESS;
    }
}
```

### 手动命令注册

#### 1. 在 App.php 应用入口的 init 方法中注册

```php
// 在应用模块的 init() 方法中注册命令
use Core\Command\Command;

class App extends AppExtend
{
    public function init(Bootstrap $bootstrap): void
    {
        // 在 init 方法中注册自定义命令
        // 注意：这里是通过回调机制实现的
        $commands = [
            \App\Commands\DatabaseMigrateCommand::class,
            \App\Commands\CacheClearCommand::class,
            \App\Commands\QueueProcessCommand::class,
        ];

        // 将命令添加到全局配置中
        // 具体实现需要根据框架的回调机制
    }
}
```

#### 2. 通过 command.toml 文件配置

```toml
# config/command.toml
registers = [
    "App\\Commands\\DatabaseMigrateCommand",
    "App\\Commands\\CacheClearCommand",
    "App\\Commands\\QueueProcessCommand",
    "App\\Commands\\CustomCommand"
]
```

#### 3. 框架加载流程

```php
// 框架在 Bootstrap::loadCommand() 中自动加载
public function loadCommand(): void
{
    // 1. 从配置文件读取命令
    $commands = App::config("command")->get("registers", []);

    // 2. 添加框架内置命令
    $commands[] = BackupCommand::class;
    $commands[] = RestoreCommand::class;
    $commands[] = PermissionCommand::class;
    // ...

    // 3. 添加注解发现的命令
    $commands = [...$commands, ...Command::registerAttribute()];

    // 4. 初始化命令行应用
    $this->command = Command::init($commands);
}
```

## 基础命令开发

### 简单命令示例

```php
use Core\Command\Command;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class GreetCommand extends BaseCommand
{
    protected static $defaultName = 'app:greet';

    protected function configure(): void
    {
        $this
            ->setName('app:greet')
            ->setDescription('向用户打招呼')
            ->setHelp('此命令可以向指定用户打招呼')
            // 必需参数
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                '用户名称'
            )
            // 可选参数
            ->addArgument(
                'lastName',
                InputArgument::OPTIONAL,
                '用户姓氏'
            )
            // 选项
            ->addOption(
                'uppercase',
                'u',
                InputOption::VALUE_NONE,
                '输出大写字母'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                '输出格式',
                'default'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 获取参数
        $name = $input->getArgument('name');
        $lastName = $input->getArgument('lastName');

        // 获取选项
        $uppercase = $input->getOption('uppercase');
        $format = $input->getOption('format');

        // 构建问候语
        $fullName = $lastName ? "$name $lastName" : $name;
        $greeting = match($format) {
            'formal' => "尊敬的 $fullName 先生/女士，您好！",
            'casual' => "嗨，$fullName！",
            default => "您好，$fullName！"
        };

        // 处理大写选项
        if ($uppercase) {
            $greeting = strtoupper($greeting);
        }

        // 输出结果
        $output->writeln($greeting);

        return Command::SUCCESS;
    }
}
```

### 交互式命令

```php
use Core\Command\Command;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[Command]
class InteractiveCommand extends BaseCommand
{
    protected static $defaultName = 'app:interactive';

    protected function configure(): void
    {
        $this
            ->setName('app:interactive')
            ->setDescription('交互式示例命令')
            ->setHelp('演示各种交互式输入方式');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        // 文本输入
        $nameQuestion = new Question('请输入您的姓名: ');
        $nameQuestion->setValidator(function ($answer) {
            if (trim($answer) == '') {
                throw new \RuntimeException('姓名不能为空');
            }
            return $answer;
        });
        $name = $helper->ask($input, $output, $nameQuestion);

        // 密码输入（隐藏输入）
        $passwordQuestion = new Question('请输入密码: ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $password = $helper->ask($input, $output, $passwordQuestion);

        // 选择题
        $typeQuestion = new ChoiceQuestion(
            '请选择账户类型:',
            ['admin' => '管理员', 'user' => '普通用户', 'guest' => '访客'],
            'user'
        );
        $typeQuestion->setErrorMessage('选择 %s 无效');
        $userType = $helper->ask($input, $output, $typeQuestion);

        // 确认问题
        $confirmQuestion = new ConfirmationQuestion(
            "确认创建用户 $name (类型: $userType)? [y/N] ",
            false
        );
        $confirmed = $helper->ask($input, $output, $confirmQuestion);

        if ($confirmed) {
            $output->writeln("✅ 用户 $name 创建成功！");
            $output->writeln("类型: $userType");
            return Command::SUCCESS;
        } else {
            $output->writeln("❌ 操作已取消");
            return Command::FAILURE;
        }
    }
}
```

## 实用命令示例

### 数据库迁移命令

```php
use Core\Command\Command;
use Core\App;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

#[Command]
class DatabaseMigrateCommand extends BaseCommand
{
    protected static $defaultName = 'db:migrate';

    protected function configure(): void
    {
        $this
            ->setName('db:migrate')
            ->setDescription('运行数据库迁移')
            ->addOption(
                'fresh',
                null,
                InputOption::VALUE_NONE,
                '删除所有表并重新迁移'
            )
            ->addOption(
                'seed',
                null,
                InputOption::VALUE_NONE,
                '迁移后运行数据填充'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🚀 开始数据库迁移...');

        try {
            // 检查数据库连接
            App::db()->connection()->getPdo();
            $output->writeln('✅ 数据库连接成功');

            $fresh = $input->getOption('fresh');
            $seed = $input->getOption('seed');

            if ($fresh) {
                $output->writeln('🔄 删除所有表...');
                $this->dropAllTables();
                $output->writeln('✅ 所有表已删除');
            }

            // 创建迁移表
            $this->createMigrationTable();

            // 运行迁移
            $migrations = $this->getMigrations();
            $executed = $this->getExecutedMigrations();

            foreach ($migrations as $migration) {
                if (!in_array($migration, $executed)) {
                    $output->writeln("📦 运行迁移: $migration");
                    $this->runMigration($migration);
                    $this->recordMigration($migration);
                    $output->writeln("✅ 迁移完成: $migration");
                }
            }

            if ($seed) {
                $output->writeln('🌱 开始数据填充...');
                $this->runSeeders();
                $output->writeln('✅ 数据填充完成');
            }

            $output->writeln('🎉 数据库迁移成功完成！');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("❌ 迁移失败: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createMigrationTable(): void
    {
        if (!Schema::hasTable('migrations')) {
            Schema::create('migrations', function (Blueprint $table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    private function getMigrations(): array
    {
        $migrationPath = base_path('database/migrations');
        if (!is_dir($migrationPath)) {
            return [];
        }

        $files = scandir($migrationPath);
        $migrations = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $migrations[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        sort($migrations);
        return $migrations;
    }

    private function getExecutedMigrations(): array
    {
        try {
            return App::db()->table('migrations')
                ->pluck('migration')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function runMigration(string $migration): void
    {
        $migrationFile = base_path("database/migrations/{$migration}.php");
        if (file_exists($migrationFile)) {
            require_once $migrationFile;

            // 假设迁移文件包含 up() 函数
            if (function_exists('up')) {
                up();
            }
        }
    }

    private function recordMigration(string $migration): void
    {
        $batch = App::db()->table('migrations')->max('batch') + 1;
        App::db()->table('migrations')->insert([
            'migration' => $migration,
            'batch' => $batch,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function dropAllTables(): void
    {
        $tables = App::db()->select('SHOW TABLES');
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            Schema::dropIfExists($tableName);
        }
    }

    private function runSeeders(): void
    {
        $seederPath = base_path('database/seeders');
        if (!is_dir($seederPath)) {
            return;
        }

        $files = scandir($seederPath);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $seederFile = "$seederPath/$file";
                require_once $seederFile;
            }
        }
    }
}
```

### 缓存管理命令

```php
use Core\Command\Command;
use Core\App;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class CacheCommand extends BaseCommand
{
    protected static $defaultName = 'cache:clear';

    protected function configure(): void
    {
        $this
            ->setName('cache:clear')
            ->setDescription('清理缓存')
            ->addArgument(
                'type',
                InputArgument::OPTIONAL,
                '缓存类型 (all, app, views, config)',
                'all'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                '强制清理，不提示确认'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type');
        $force = $input->getOption('force');

        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new \Symfony\Component\Console\Question\ConfirmationQuestion(
                "确认清理 {$type} 缓存? [y/N] ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('❌ 操作已取消');
                return Command::SUCCESS;
            }
        }

        $output->writeln("🧹 开始清理 {$type} 缓存...");

        try {
            switch ($type) {
                case 'app':
                    $this->clearAppCache($output);
                    break;
                case 'views':
                    $this->clearViewCache($output);
                    break;
                case 'config':
                    $this->clearConfigCache($output);
                    break;
                case 'all':
                default:
                    $this->clearAppCache($output);
                    $this->clearViewCache($output);
                    $this->clearConfigCache($output);
                    break;
            }

            $output->writeln('✅ 缓存清理完成！');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("❌ 缓存清理失败: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function clearAppCache(OutputInterface $output): void
    {
        $cache = App::cache();
        $cache->clear();
        $output->writeln('📦 应用缓存已清理');
    }

    private function clearViewCache(OutputInterface $output): void
    {
        $viewCachePath = data_path('cache/views');
        if (is_dir($viewCachePath)) {
            $this->deleteDirectory($viewCachePath);
            mkdir($viewCachePath, 0755, true);
        }
        $output->writeln('🎨 视图缓存已清理');
    }

    private function clearConfigCache(OutputInterface $output): void
    {
        $configCachePath = data_path('cache/config');
        if (is_dir($configCachePath)) {
            $this->deleteDirectory($configCachePath);
            mkdir($configCachePath, 0755, true);
        }
        $output->writeln('⚙️ 配置缓存已清理');
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
```

### 队列处理命令

```php
use Core\Command\Command;
use Core\App;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class QueueWorkerCommand extends BaseCommand
{
    protected static $defaultName = 'queue:work';
    private bool $shouldStop = false;

    protected function configure(): void
    {
        $this
            ->setName('queue:work')
            ->setDescription('处理队列任务')
            ->addOption(
                'queue',
                'q',
                InputOption::VALUE_REQUIRED,
                '处理的队列名称',
                'default'
            )
            ->addOption(
                'delay',
                'd',
                InputOption::VALUE_REQUIRED,
                '失败任务重试延迟(秒)',
                '3'
            )
            ->addOption(
                'memory',
                'm',
                InputOption::VALUE_REQUIRED,
                '内存限制(MB)',
                '128'
            )
            ->addOption(
                'timeout',
                't',
                InputOption::VALUE_REQUIRED,
                '任务超时时间(秒)',
                '60'
            )
            ->addOption(
                'tries',
                'r',
                InputOption::VALUE_REQUIRED,
                '最大重试次数',
                '3'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueName = $input->getOption('queue');
        $delay = (int) $input->getOption('delay');
        $memoryLimit = (int) $input->getOption('memory');
        $timeout = (int) $input->getOption('timeout');
        $maxTries = (int) $input->getOption('tries');

        $output->writeln("🚀 队列工作进程启动");
        $output->writeln("📋 队列: {$queueName}");
        $output->writeln("⏱️ 超时: {$timeout}s");
        $output->writeln("💾 内存限制: {$memoryLimit}MB");
        $output->writeln("🔄 最大重试: {$maxTries}次");

        // 注册信号处理器
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }

        $startTime = time();
        $processedJobs = 0;

        while (!$this->shouldStop) {
            // 检查内存使用
            if ($this->exceedsMemoryLimit($memoryLimit)) {
                $output->writeln("⚠️ 内存使用超限，停止进程");
                break;
            }

            // 处理信号
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            try {
                // 获取队列任务（这里简化处理）
                $job = $this->getNextJob($queueName);

                if ($job) {
                    $output->writeln("📝 处理任务: {$job['id']}");

                    $jobStartTime = microtime(true);
                    $success = $this->processJob($job, $timeout);
                    $executionTime = round((microtime(true) - $jobStartTime) * 1000, 2);

                    if ($success) {
                        $this->markJobCompleted($job);
                        $processedJobs++;
                        $output->writeln("✅ 任务完成: {$job['id']} ({$executionTime}ms)");
                    } else {
                        $this->handleFailedJob($job, $maxTries, $delay);
                        $output->writeln("❌ 任务失败: {$job['id']}");
                    }
                } else {
                    // 没有任务，休眠1秒
                    sleep(1);
                }

            } catch (\Exception $e) {
                $output->writeln("💥 处理异常: " . $e->getMessage());
                sleep($delay);
            }
        }

        $runtime = time() - $startTime;
        $output->writeln("🛑 队列工作进程停止");
        $output->writeln("📊 统计: 运行时间 {$runtime}s，处理任务 {$processedJobs} 个");

        return Command::SUCCESS;
    }

    public function handleSignal(int $signal): void
    {
        $this->shouldStop = true;
    }

    private function exceedsMemoryLimit(int $limitMB): bool
    {
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        return $memoryUsage > $limitMB;
    }

    private function getNextJob(string $queueName): ?array
    {
        // 简化的队列任务获取逻辑
        try {
            $job = App::db()->table('jobs')
                ->where('queue', $queueName)
                ->where('reserved_at', null)
                ->where('available_at', '<=', time())
                ->orderBy('id')
                ->first();

            if ($job) {
                // 标记任务为已保留
                App::db()->table('jobs')
                    ->where('id', $job->id)
                    ->update(['reserved_at' => time()]);

                return (array) $job;
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    private function processJob(array $job, int $timeout): bool
    {
        try {
            // 设置超时
            set_time_limit($timeout);

            // 反序列化并执行任务
            $payload = json_decode($job['payload'], true);
            $jobClass = $payload['job'] ?? null;

            if ($jobClass && class_exists($jobClass)) {
                $jobInstance = new $jobClass();
                if (method_exists($jobInstance, 'handle')) {
                    $jobInstance->handle();
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            error_log("Job execution failed: " . $e->getMessage());
            return false;
        }
    }

    private function markJobCompleted(array $job): void
    {
        App::db()->table('jobs')->where('id', $job['id'])->delete();
    }

    private function handleFailedJob(array $job, int $maxTries, int $delay): void
    {
        $attempts = ($job['attempts'] ?? 0) + 1;

        if ($attempts >= $maxTries) {
            // 移到失败队列
            App::db()->table('failed_jobs')->insert([
                'uuid' => $job['uuid'] ?? uniqid(),
                'connection' => 'database',
                'queue' => $job['queue'],
                'payload' => $job['payload'],
                'exception' => 'Max attempts exceeded',
                'failed_at' => date('Y-m-d H:i:s')
            ]);

            App::db()->table('jobs')->where('id', $job['id'])->delete();
        } else {
            // 增加重试次数，设置延迟
            App::db()->table('jobs')
                ->where('id', $job['id'])
                ->update([
                    'attempts' => $attempts,
                    'reserved_at' => null,
                    'available_at' => time() + $delay
                ]);
        }
    }
}
```

## 定时任务命令

### 任务调度命令

```php
use Core\Command\Command;
use Core\App;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class ScheduleCommand extends BaseCommand
{
    protected static $defaultName = 'schedule:run';

    protected function configure(): void
    {
        $this
            ->setName('schedule:run')
            ->setDescription('运行定时任务')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                '强制运行所有任务'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption('force');

        $output->writeln('⏰ 开始检查定时任务...');

        try {
            $tasks = $this->getScheduledTasks();
            $executedCount = 0;

            foreach ($tasks as $task) {
                if ($force || $this->shouldRunTask($task)) {
                    $output->writeln("🚀 执行任务: {$task['name']}");

                    $startTime = microtime(true);
                    $success = $this->executeTask($task);
                    $duration = round((microtime(true) - $startTime) * 1000, 2);

                    if ($success) {
                        $output->writeln("✅ 任务完成: {$task['name']} ({$duration}ms)");
                        $this->recordTaskExecution($task, true, $duration);
                    } else {
                        $output->writeln("❌ 任务失败: {$task['name']}");
                        $this->recordTaskExecution($task, false, $duration);
                    }

                    $executedCount++;
                }
            }

            if ($executedCount === 0) {
                $output->writeln('💤 没有需要执行的任务');
            } else {
                $output->writeln("🎉 完成 {$executedCount} 个定时任务");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("❌ 定时任务执行失败: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getScheduledTasks(): array
    {
        return [
            [
                'name' => 'cache_cleanup',
                'command' => 'cache:clear',
                'schedule' => '0 2 * * *', // 每天凌晨2点
                'description' => '清理过期缓存'
            ],
            [
                'name' => 'log_rotation',
                'command' => 'log:rotate',
                'schedule' => '0 0 * * 0', // 每周日凌晨
                'description' => '日志轮转'
            ],
            [
                'name' => 'backup_database',
                'command' => 'db:backup',
                'schedule' => '0 3 * * *', // 每天凌晨3点
                'description' => '数据库备份'
            ]
        ];
    }

    private function shouldRunTask(array $task): bool
    {
        // 检查上次执行时间
        $lastRun = $this->getLastTaskRun($task['name']);
        $schedule = $task['schedule'];

        // 简化的cron表达式解析
        return $this->cronMatches($schedule, $lastRun);
    }

    private function executeTask(array $task): bool
    {
        try {
            // 执行命令
            $command = $task['command'];
            $application = Command::init([]);

            // 这里简化处理，实际应该调用相应的命令
            switch ($command) {
                case 'cache:clear':
                    App::cache()->clear();
                    break;
                case 'log:rotate':
                    $this->rotateLogFiles();
                    break;
                case 'db:backup':
                    $this->backupDatabase();
                    break;
                default:
                    return false;
            }

            return true;
        } catch (\Exception $e) {
            error_log("Scheduled task failed: " . $e->getMessage());
            return false;
        }
    }

    private function recordTaskExecution(array $task, bool $success, float $duration): void
    {
        App::db()->table('task_logs')->insert([
            'task_name' => $task['name'],
            'command' => $task['command'],
            'success' => $success,
            'duration_ms' => $duration,
            'executed_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function getLastTaskRun(string $taskName): ?string
    {
        $lastRun = App::db()->table('task_logs')
            ->where('task_name', $taskName)
            ->where('success', true)
            ->orderBy('executed_at', 'desc')
            ->first();

        return $lastRun ? $lastRun->executed_at : null;
    }

    private function cronMatches(string $schedule, ?string $lastRun): bool
    {
        // 简化的cron匹配逻辑
        // 实际应该使用专门的cron表达式解析库
        $now = time();
        $lastRunTime = $lastRun ? strtotime($lastRun) : 0;

        // 示例：每小时检查一次
        return ($now - $lastRunTime) >= 3600;
    }

    private function rotateLogFiles(): void
    {
        $logPath = data_path('logs');
        if (!is_dir($logPath)) {
            return;
        }

        $files = glob("$logPath/*.log");
        foreach ($files as $file) {
            if (filesize($file) > 10 * 1024 * 1024) { // 10MB
                $rotatedFile = $file . '.' . date('Y-m-d-H-i-s');
                rename($file, $rotatedFile);
                gzopen($rotatedFile . '.gz', 'w');
                unlink($rotatedFile);
            }
        }
    }

    private function backupDatabase(): void
    {
        $config = App::config('database');
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];
        $host = $config['host'];

        $backupFile = data_path("backups/database_" . date('Y-m-d_H-i-s') . '.sql');
        $backupDir = dirname($backupFile);

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $command = "mysqldump -h$host -u$username -p$password $database > $backupFile";
        exec($command, $output_lines, $return_code);
        if ($return_code !== 0) {
            throw new \RuntimeException('数据库备份失败');
        }

        exec("gzip $backupFile");
        $output->writeln('✅ 数据库备份完成');
    }
}
```

## 应用发布命令

### 部署和维护命令

```php
use Core\Command\Command;
use Core\App;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class DeployCommand extends BaseCommand
{
    protected static $defaultName = 'app:deploy';

    protected function configure(): void
    {
        $this
            ->setName('app:deploy')
            ->setDescription('应用部署')
            ->addOption(
                'env',
                'e',
                InputOption::VALUE_REQUIRED,
                '部署环境',
                'production'
            )
            ->addOption(
                'skip-backup',
                null,
                InputOption::VALUE_NONE,
                '跳过备份'
            )
            ->addOption(
                'skip-migrate',
                null,
                InputOption::VALUE_NONE,
                '跳过数据库迁移'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $input->getOption('env');
        $skipBackup = $input->getOption('skip-backup');
        $skipMigrate = $input->getOption('skip-migrate');

        $output->writeln("🚀 开始部署到 {$env} 环境...");

        try {
            // 1. 检查环境
            $this->checkEnvironment($output, $env);

            // 2. 备份数据库
            if (!$skipBackup) {
                $this->backupDatabase($output);
            }

            // 3. 清理缓存
            $this->clearCache($output);

            // 4. 数据库迁移
            if (!$skipMigrate) {
                $this->runMigrations($output);
            }

            // 5. 优化应用
            $this->optimizeApplication($output);

            // 6. 重启服务
            $this->restartServices($output, $env);

            $output->writeln('🎉 部署完成！');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("❌ 部署失败: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function checkEnvironment(OutputInterface $output, string $env): void
    {
        $output->writeln('🔍 检查运行环境...');

        // 检查PHP版本
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            throw new \RuntimeException('PHP版本需要8.1或更高');
        }

        // 检查必需扩展
        $requiredExtensions = ['pdo', 'mbstring', 'openssl', 'curl'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                throw new \RuntimeException("缺少必需的PHP扩展: {$ext}");
            }
        }

        // 检查目录权限
        $writableDirs = [data_path(), data_path('cache'), data_path('logs')];
        foreach ($writableDirs as $dir) {
            if (!is_writable($dir)) {
                throw new \RuntimeException("目录不可写: {$dir}");
            }
        }

        $output->writeln('✅ 环境检查通过');
    }

    private function backupDatabase(OutputInterface $output): void
    {
        $output->writeln('💾 备份数据库...');

        $config = App::config('database');
        $backupFile = data_path('backups/pre-deploy-' . date('Y-m-d-H-i-s') . '.sql');
        $backupDir = dirname($backupFile);

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s',
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $backupFile
        );

        exec($command, $output_lines, $return_code);
        if ($return_code !== 0) {
            throw new \RuntimeException('数据库备份失败');
        }

        exec("gzip {$backupFile}");
        $output->writeln('✅ 数据库备份完成');
    }

    private function clearCache(OutputInterface $output): void
    {
        $output->writeln('🧹 清理缓存...');

        App::cache()->clear();

        $cacheDirs = [
            data_path('cache/views'),
            data_path('cache/config'),
            data_path('cache/routes')
        ];

        foreach ($cacheDirs as $dir) {
            if (is_dir($dir)) {
                $this->deleteDirectory($dir);
                mkdir($dir, 0755, true);
            }
        }

        $output->writeln('✅ 缓存清理完成');
    }

    private function runMigrations(OutputInterface $output): void
    {
        $output->writeln('📦 运行数据库迁移...');

        // 这里应该调用迁移命令
        // 简化处理
        $migrations = $this->getPendingMigrations();
        foreach ($migrations as $migration) {
            $output->writeln("  📄 {$migration}");
            $this->runMigration($migration);
        }

        $output->writeln('✅ 数据库迁移完成');
    }

    private function optimizeApplication(OutputInterface $output): void
    {
        $output->writeln('⚡ 优化应用...');

        // 编译配置
        $this->compileConfig();

        // 编译路由
        $this->compileRoutes();

        // 优化自动加载
        exec('composer dump-autoload --optimize --no-dev');

        $output->writeln('✅ 应用优化完成');
    }

    private function restartServices(OutputInterface $output, string $env): void
    {
        $output->writeln('🔄 重启服务...');

        if ($env === 'production') {
            // 重启PHP-FPM
            exec('sudo systemctl reload php-fpm');

            // 重启Nginx
            exec('sudo systemctl reload nginx');

            // 重启队列工作进程
            exec('sudo supervisorctl restart laravel-worker:*');
        }

        $output->writeln('✅ 服务重启完成');
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function getPendingMigrations(): array
    {
        // 简化的迁移检查
        return [];
    }

    private function runMigration(string $migration): void
    {
        // 简化的迁移执行
    }

    private function compileConfig(): void
    {
        // 配置编译逻辑
    }

    private function compileRoutes(): void
    {
        // 路由编译逻辑
    }
}
```

## 最佳实践

### 1. 命令组织结构

```
app/
├── Commands/
│   ├── Database/
│   │   ├── MigrateCommand.php
│   │   ├── SeedCommand.php
│   │   └── BackupCommand.php
│   ├── Cache/
│   │   ├── ClearCommand.php
│   │   └── WarmupCommand.php
│   ├── Queue/
│   │   ├── WorkCommand.php
│   │   └── FailedCommand.php
│   └── Deploy/
│       ├── DeployCommand.php
│       └── RollbackCommand.php
```

### 2. 命令基类封装

```php
use Core\Command\Command;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AppCommand extends BaseCommand
{
    protected function info(OutputInterface $output, string $message): void
    {
        $output->writeln("ℹ️ {$message}");
    }

    protected function success(OutputInterface $output, string $message): void
    {
        $output->writeln("✅ {$message}");
    }

    protected function warning(OutputInterface $output, string $message): void
    {
        $output->writeln("⚠️ {$message}");
    }

    protected function error(OutputInterface $output, string $message): void
    {
        $output->writeln("❌ {$message}");
    }

    protected function progress(OutputInterface $output, string $message): void
    {
        $output->writeln("🚀 {$message}");
    }
}
```

### 3. 错误处理和日志

```php
use Core\Command\Command;
use Psr\Log\LoggerInterface;

#[Command]
class RobustCommand extends AppCommand
{
    protected static $defaultName = 'app:robust';

    public function __construct(private LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->progress($output, '开始执行命令...');

            // 命令逻辑
            $this->doWork($output);

            $this->success($output, '命令执行成功');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error($output, "命令执行失败: " . $e->getMessage());

            // 记录错误日志
            $this->logger->error('Command execution failed', [
                'command' => static::$defaultName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    private function doWork(OutputInterface $output): void
    {
        // 实际工作逻辑
    }
}
```

::: warning 注意事项
1. **信号处理**：长时间运行的命令应该正确处理SIGTERM和SIGINT信号
2. **内存管理**：避免内存泄漏，特别是在循环处理大量数据时
3. **错误处理**：提供详细的错误信息和适当的退出代码
4. **日志记录**：记录重要操作和错误信息
5. **权限检查**：执行文件操作前检查相应权限
:::

::: tip 开发建议
- 使用描述性的命令名称和参数
- 提供详细的帮助信息和使用示例
- 实现适当的进度显示和用户反馈
- 支持详细模式和静默模式
- 编写单元测试来验证命令逻辑
:::

DuxLite 的命令行工具系统为您提供了强大的自动化能力，通过编写自定义命令，您可以高效地执行各种维护、部署和管理任务。