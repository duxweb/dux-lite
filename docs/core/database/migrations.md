# 数据库迁移

DuxLite 提供了强大而智能的数据库迁移系统，通过 **自动迁移注解** 和 **增量更新机制**，让数据库结构管理变得简单高效。系统基于 Laravel 的 Schema Builder，支持自动对比表结构差异并进行安全的增量更新。

## 核心概念

### 迁移系统特点

DuxLite 的迁移系统具有以下特色：

- **基于注解** - 使用 `#[AutoMigrate]` 注解自动注册迁移模型
- **增量更新** - 智能对比表结构，只更新变化的字段
- **数据安全** - 不会删除现有数据，只进行结构调整
- **自动发现** - 自动扫描所有带注解的模型类
- **多应用支持** - 可以单独迁移指定应用的模型
- **事件驱动** - 支持迁移前后的钩子处理

### 系统架构

```php
// 迁移系统核心组件
Core\Database\Migrate          // 迁移管理器
Core\Database\Attribute\AutoMigrate  // 自动迁移注解
Core\Database\MigrateCommand    // 迁移命令
Core\Database\ListCommand       // 列表命令
```

## 自动迁移注解

### 基础用法

使用 `#[AutoMigrate]` 注解将模型标记为自动迁移：

```php
<?php
namespace App\Models;

use Core\Database\Model;
use Core\Database\Attribute\AutoMigrate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Connection;

#[AutoMigrate]
class User extends Model
{
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'status'];
    protected $hidden = ['password'];
    protected $tableComment = '用户数据表';

    /**
     * 定义数据表结构
     */
    public function migration(Blueprint $table): void
    {
        $table->id();
        $table->string('name')->comment('用户名');
        $table->string('email')->unique()->comment('邮箱地址');
        $table->timestamp('email_verified_at')->nullable()->comment('邮箱验证时间');
        $table->string('password')->comment('密码');
        $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
        $table->timestamps();
    }
}
```

### 迁移方法详解

#### migration() 方法

`migration()` 方法定义完整的表结构，这是必须实现的方法：

```php
public function migration(Blueprint $table): void
{
    // 主键
    $table->id(); // 等同于 $table->bigIncrements('id')

    // 字符串字段
    $table->string('name', 100)->comment('用户名');
    $table->string('email')->unique()->comment('邮箱');
    $table->text('description')->nullable()->comment('描述');

    // 数字字段
    $table->integer('age')->default(0)->comment('年龄');
    $table->decimal('price', 8, 2)->comment('价格');
    $table->boolean('is_active')->default(true)->comment('是否激活');

    // 时间字段
    $table->timestamp('created_at')->useCurrent()->comment('创建时间');
    $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新时间');
    $table->datetime('login_at')->nullable()->comment('登录时间');
    $table->date('birthday')->nullable()->comment('生日');

    // JSON 字段
    $table->json('metadata')->nullable()->comment('元数据');

    // 外键字段
    $table->foreignId('category_id')->constrained()->comment('分类ID');

    // 索引
    $table->index(['status', 'created_at']);
    $table->unique(['email']);
}
```

#### migrationAfter() 方法

在表结构创建完成后执行的钩子方法：

```php
public function migrationAfter(Connection $db): void
{
    // 创建额外的索引
    $db->statement('CREATE INDEX idx_user_status_name ON users (status, name)');

    // 创建触发器
    $db->statement('
        CREATE TRIGGER user_updated_trigger
        BEFORE UPDATE ON users
        FOR EACH ROW
        SET NEW.updated_at = CURRENT_TIMESTAMP
    ');

    // 执行其他 SQL 操作
    $db->statement('ALTER TABLE users AUTO_INCREMENT = 1000');
}
```

#### seed() 方法

数据填充方法，只在表首次创建时执行：

```php
public function seed(Connection $db): void
{
    // 插入管理员账户
    $this->create([
        'name' => 'Administrator',
        'email' => 'admin@example.com',
        'password' => password_hash('123456', PASSWORD_DEFAULT),
        'status' => 1,
        'email_verified_at' => now()
    ]);

    // 批量插入数据
    $this->insert([
        [
            'name' => 'Test User 1',
            'email' => 'test1@example.com',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]
    ]);
}
```

## 字段类型详解

### 数字类型

```php
public function migration(Blueprint $table): void
{
    // 整数类型
    $table->tinyInteger('status')->comment('状态（-128 到 127）');
    $table->smallInteger('sort')->comment('排序（-32768 到 32767）');
    $table->mediumInteger('views')->comment('查看数（-8388608 到 8388607）');
    $table->integer('count')->comment('计数（-2147483648 到 2147483647）');
    $table->bigInteger('big_number')->comment('大整数');

    // 无符号整数
    $table->unsignedTinyInteger('type')->comment('类型（0 到 255）');
    $table->unsignedSmallInteger('port')->comment('端口（0 到 65535）');
    $table->unsignedMediumInteger('amount')->comment('数量');
    $table->unsignedInteger('user_id')->comment('用户ID');
    $table->unsignedBigInteger('transaction_id')->comment('交易ID');

    // 浮点数
    $table->float('rate', 8, 2)->comment('比率');
    $table->double('latitude', 15, 8)->comment('纬度');
    $table->decimal('price', 10, 2)->comment('价格');

    // 自增主键
    $table->increments('id')->comment('主键');
    $table->bigIncrements('id')->comment('大整数主键');
}
```

### 字符串类型

```php
public function migration(Blueprint $table): void
{
    // 字符串类型
    $table->char('code', 6)->comment('固定长度字符（6位验证码）');
    $table->string('name', 100)->comment('变长字符串（最大100字符）');
    $table->string('email')->comment('邮箱（默认255字符）');

    // 文本类型
    $table->tinyText('summary')->comment('简介（最大255字符）');
    $table->text('content')->comment('内容（最大65535字符）');
    $table->mediumText('article')->comment('文章（最大16MB）');
    $table->longText('data')->comment('大文本（最大4GB）');

    // 二进制类型
    $table->binary('file_data')->comment('二进制数据');
}
```

### 时间类型

```php
public function migration(Blueprint $table): void
{
    // 时间戳
    $table->timestamp('created_at')->useCurrent()->comment('创建时间');
    $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新时间');
    $table->timestampTz('published_at')->nullable()->comment('发布时间（带时区）');

    // 时间类型
    $table->datetime('login_at')->nullable()->comment('登录时间');
    $table->datetimeTz('event_time')->comment('事件时间（带时区）');
    $table->date('birthday')->nullable()->comment('生日');
    $table->time('work_time')->comment('工作时间');
    $table->timeTz('meeting_time')->comment('会议时间（带时区）');
    $table->year('graduate_year')->comment('毕业年份');

    // 自动时间戳
    $table->timestamps(); // 创建 created_at 和 updated_at
    $table->nullableTimestamps(); // 可空的时间戳
    $table->timestampsTz(); // 带时区的时间戳
}
```

### 特殊类型

```php
public function migration(Blueprint $table): void
{
    // JSON 类型
    $table->json('settings')->nullable()->comment('设置信息');
    $table->jsonb('metadata')->comment('元数据（PostgreSQL）');

    // 布尔类型
    $table->boolean('is_active')->default(true)->comment('是否激活');

    // 枚举类型
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->comment('状态');

    // UUID 类型
    $table->uuid('uuid')->comment('UUID');
    $table->uuidMorphs('taggable'); // 创建 taggable_type 和 taggable_id

    // IP 地址
    $table->ipAddress('ip')->comment('IP地址');
    $table->macAddress('mac')->comment('MAC地址');

    // 地理位置
    $table->point('coordinates')->comment('坐标点');
    $table->geometry('area')->comment('几何区域');
}
```

## 索引和约束

### 索引类型

```php
public function migration(Blueprint $table): void
{
    // 字段定义
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->integer('category_id');
    $table->tinyInteger('status');
    $table->timestamp('created_at');

    // 主键（通常在 id() 中已定义）
    $table->primary('id');

    // 唯一索引
    $table->unique('email');
    $table->unique(['name', 'category_id'], 'unique_name_category');

    // 普通索引
    $table->index('status');
    $table->index(['status', 'created_at'], 'idx_status_created');

    // 全文索引
    $table->fullText(['name', 'description']);

    // 空间索引
    $table->spatialIndex('coordinates');
}
```

### 外键约束

```php
public function migration(Blueprint $table): void
{
    // 用户表
    $table->id();
    $table->string('name');
    $table->foreignId('department_id')->constrained()->comment('部门ID');
    $table->timestamps();

    // 手动定义外键
    $table->unsignedBigInteger('manager_id')->nullable();
    $table->foreign('manager_id')->references('id')->on('users')->onDelete('set null');

    // 级联操作
    $table->foreignId('company_id')
        ->constrained()
        ->onUpdate('cascade')
        ->onDelete('cascade');
}
```

### 约束条件

```php
public function migration(Blueprint $table): void
{
    // 字段约束
    $table->string('email')->unique()->comment('邮箱');
    $table->integer('age')->unsigned()->comment('年龄');
    $table->decimal('price', 8, 2)->default(0.00)->comment('价格');
    $table->string('status')->default('active')->comment('状态');
    $table->text('description')->nullable()->comment('描述');

    // 检查约束（MySQL 8.0.16+）
    $table->integer('age');
    $table->check('age >= 18', 'check_adult_age');
}
```

## 多语言字段支持

DuxLite 提供了便捷的多语言字段支持：

```php
use Core\Model\TransSet;

#[AutoMigrate]
class Article extends Model
{
    protected $table = 'articles';
    protected $tableComment = '文章表';

    public function migration(Blueprint $table): void
    {
        $table->id();
        $table->string('slug')->unique()->comment('URL别名');
        $table->tinyInteger('status')->default(1)->comment('状态');

        // 添加多语言字段（translations 列）
        TransSet::columns($table);

        $table->timestamps();
    }
}
```

使用多语言字段：

```php
$article = new Article();
$article->slug = 'hello-world';
$article->translations = [
    'zh-CN' => [
        'title' => '你好世界',
        'content' => '这是中文内容'
    ],
    'en-US' => [
        'title' => 'Hello World',
        'content' => 'This is English content'
    ]
];
$article->save();
```

## 迁移命令

### 同步所有模型

```bash
# 同步所有带有 #[AutoMigrate] 注解的模型
php dux db:sync
```

输出示例：
```
sync model App\Models\User 0.023s
sync model App\Models\Post 0.015s
sync model App\Models\Category 0.008s
sync send App\Models\User 0.001s
Sync database successfully
```

### 同步指定应用

```bash
# 仅同步 Admin 应用的模型
php dux db:sync admin

# 仅同步 Web 应用的模型
php dux db:sync web
```

### 查看注册的模型

```bash
# 查看所有已注册的自动迁移模型
php dux db:list
```

输出示例：
```
+---------------------------+
| Auto Migrate Models       |
+---------------------------+
| App\Models\User          |
| App\Models\Post          |
| App\Models\Category      |
| App\Admin\Models\Config  |
+---------------------------+
```

## 迁移原理解析

### 增量更新机制

DuxLite 的迁移系统使用 Doctrine DBAL 进行表结构对比：

1. **创建临时表** - 根据模型的 `migration()` 方法创建临时表
2. **结构对比** - 对比现有表和临时表的结构差异
3. **生成 DDL** - 生成需要执行的数据库变更语句
4. **安全更新** - 执行结构变更，不影响现有数据
5. **清理临时表** - 删除临时表

```php
// 迁移核心逻辑（简化版）
private function migrateTable(Connection $connect, Model $model, &$seed): void
{
    $modelTable = $model->getTable();
    $tempTable = 'table_' . $modelTable;
    $tableExists = $connect->getSchemaBuilder()->hasTable($modelTable);

    // 创建临时表
    $connect->getSchemaBuilder()->create($tableExists ? $tempTable : $modelTable, function (Blueprint $table) use ($model) {
        if ($model->getTableComment()) {
            $table->comment($model->getTableComment());
        }
        $model->migration($table);
        $model->migrationGlobal($table);
    });

    if (!$tableExists) {
        // 新表，执行数据填充
        if (method_exists($model, 'seed')) {
            $seed[] = $model;
        }
        return;
    }

    // 对比表结构差异
    $connection = $this->getDoctrineConnection($model->getConnection());
    $schemaManager = $connection->createSchemaManager();
    $tableDiff = $schemaManager->createComparator()->compareTables(
        $schemaManager->introspectTable($pre . $modelTable),
        $schemaManager->introspectTable($pre . $tempTable)
    );

    // 执行结构更新
    if (!$tableDiff->isEmpty()) {
        $schemaManager->alterTable($tableDiff);
    }

    // 清理临时表
    $connect->getSchemaBuilder()->drop($tempTable);
}
```

### 全局迁移事件

通过 `migrationGlobal()` 方法，可以监听模型的迁移事件：

```php
// 在事件监听器中
use Core\Event\Attribute\Listener;

class DatabaseEventListener
{
    #[Listener('model.App\Models\User')]
    public function handleUserMigration(DatabaseEvent $event): void
    {
        $event->migration(function (Blueprint $table) {
            // 为所有用户模型添加全局字段
            $table->ipAddress('created_ip')->nullable()->comment('创建IP');
            $table->ipAddress('updated_ip')->nullable()->comment('更新IP');
        });
    }
}
```

## 数据库备份与恢复

### 自动备份

```bash
# 备份数据库（支持 MySQL）
php dux db:backup
```

备份文件保存在 `data/backup/` 目录，文件名格式：`YYYY-MM-DD-HHMMSS.sql`

### 恢复数据库

```bash
# 恢复最新的备份文件
php dux db:restore
```

自动选择 `data/backup/` 目录中最新的 `.sql` 文件进行恢复。

### 备份策略建议

```bash
# 在生产环境建议设置定时备份
# 添加到 crontab
0 2 * * * cd /path/to/duxlite && php dux db:backup

# 结合迁移的安全操作
php dux db:backup && php dux db:sync
```

## 实际应用示例

### 用户系统模型

```php
<?php
namespace App\Models;

use Core\Database\Model;
use Core\Database\Attribute\AutoMigrate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Connection;

#[AutoMigrate]
class User extends Model
{
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'phone', 'status', 'role_id'];
    protected $hidden = ['password', 'remember_token'];
    protected $tableComment = '用户数据表';

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'settings' => 'array'
    ];

    public function migration(Blueprint $table): void
    {
        // 基础字段
        $table->id();
        $table->string('name', 100)->comment('用户名');
        $table->string('email')->unique()->comment('邮箱地址');
        $table->string('phone', 20)->nullable()->unique()->comment('手机号');
        $table->timestamp('email_verified_at')->nullable()->comment('邮箱验证时间');
        $table->string('password')->comment('密码');
        $table->string('remember_token', 100)->nullable()->comment('记住密码令牌');

        // 状态和角色
        $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用，2=待审核');
        $table->foreignId('role_id')->default(1)->comment('角色ID');

        // 扩展字段
        $table->string('avatar', 255)->nullable()->comment('头像路径');
        $table->json('settings')->nullable()->comment('个人设置');
        $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
        $table->ipAddress('last_login_ip')->nullable()->comment('最后登录IP');

        // 时间戳
        $table->timestamps();

        // 索引
        $table->index(['status', 'created_at']);
        $table->index('role_id');
        $table->fullText(['name', 'email']);
    }

    public function migrationAfter(Connection $db): void
    {
        // 创建复合索引
        $db->statement('CREATE INDEX idx_user_status_role ON users (status, role_id)');

        // 为高频查询创建覆盖索引
        $db->statement('CREATE INDEX idx_user_login ON users (email, password, status)');
    }

    public function seed(Connection $db): void
    {
        // 创建超级管理员
        $this->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => password_hash('123456', PASSWORD_DEFAULT),
            'status' => 1,
            'role_id' => 1,
            'email_verified_at' => now(),
            'settings' => [
                'theme' => 'light',
                'language' => 'zh-CN',
                'timezone' => 'Asia/Shanghai'
            ]
        ]);

        // 创建测试用户
        for ($i = 1; $i <= 10; $i++) {
            $this->create([
                'name' => "Test User {$i}",
                'email' => "test{$i}@example.com",
                'password' => password_hash('123456', PASSWORD_DEFAULT),
                'status' => 1,
                'role_id' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
```

### 内容管理系统

```php
#[AutoMigrate]
class Article extends Model
{
    use TransTrait; // 多语言支持

    protected $table = 'articles';
    protected $tableComment = '文章内容表';

    public function migration(Blueprint $table): void
    {
        $table->id();
        $table->string('title')->comment('标题');
        $table->string('slug')->unique()->comment('URL别名');
        $table->text('excerpt')->nullable()->comment('摘要');
        $table->longText('content')->comment('内容');
        $table->string('featured_image')->nullable()->comment('特色图片');

        // 分类和标签
        $table->foreignId('category_id')->constrained()->comment('分类ID');
        $table->json('tags')->nullable()->comment('标签');

        // 作者和状态
        $table->foreignId('author_id')->constrained('users')->comment('作者ID');
        $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->comment('状态');

        // SEO 字段
        $table->string('meta_title')->nullable()->comment('SEO标题');
        $table->text('meta_description')->nullable()->comment('SEO描述');
        $table->json('meta_keywords')->nullable()->comment('SEO关键词');

        // 统计字段
        $table->unsignedInteger('view_count')->default(0)->comment('查看次数');
        $table->unsignedInteger('like_count')->default(0)->comment('点赞次数');
        $table->unsignedInteger('comment_count')->default(0)->comment('评论次数');

        // 发布时间
        $table->timestamp('published_at')->nullable()->comment('发布时间');
        $table->timestamps();

        // 多语言支持
        TransSet::columns($table);

        // 索引优化
        $table->index(['status', 'published_at']);
        $table->index(['category_id', 'status']);
        $table->index(['author_id', 'status']);
        $table->fullText(['title', 'content']);
    }
}

#[AutoMigrate]
class Category extends Model
{
    protected $table = 'categories';
    protected $tableComment = '文章分类表';

    public function migration(Blueprint $table): void
    {
        $table->id();
        $table->string('name')->comment('分类名称');
        $table->string('slug')->unique()->comment('分类别名');
        $table->text('description')->nullable()->comment('分类描述');
        $table->string('image')->nullable()->comment('分类图片');
        $table->unsignedInteger('parent_id')->default(0)->comment('父分类ID');
        $table->unsignedInteger('sort_order')->default(0)->comment('排序');
        $table->boolean('is_active')->default(true)->comment('是否激活');
        $table->timestamps();

        // 树形结构索引
        $table->index(['parent_id', 'sort_order']);
        $table->index(['is_active', 'sort_order']);
    }
}
```

## 最佳实践

### 迁移设计原则

```php
#[AutoMigrate]
class OptimizedModel extends Model
{
    public function migration(Blueprint $table): void
    {
        // 1. 合理选择字段类型和长度
        $table->string('username', 50)->comment('用户名（限制50字符）');
        $table->tinyInteger('status')->comment('状态（-128到127足够）');
        $table->unsignedMediumInteger('sort_order')->comment('排序（0到16M足够）');

        // 2. 为高频查询字段添加索引
        $table->index(['status', 'created_at']);
        $table->index('category_id'); // 外键字段

        // 3. 合理使用默认值
        $table->timestamp('created_at')->useCurrent();
        $table->boolean('is_active')->default(true);

        // 4. 添加详细的字段注释
        $table->decimal('price', 10, 2)->comment('价格（最大99999999.99）');

        // 5. 考虑字段的空值策略
        $table->string('optional_field')->nullable()->comment('可选字段');
        $table->string('required_field')->comment('必填字段');
    }
}
```

### 性能优化策略

```php
#[AutoMigrate]
class PerformanceOptimizedModel extends Model
{
    public function migration(Blueprint $table): void
    {
        $table->id();

        // 复合索引设计（最常用的查询条件在前）
        $table->tinyInteger('status');
        $table->unsignedBigInteger('user_id');
        $table->timestamp('created_at');

        // 为常见查询模式创建复合索引
        $table->index(['status', 'user_id', 'created_at']); // 覆盖索引

        // 分区字段（如果使用表分区）
        $table->date('partition_date')->comment('分区字段');
        $table->index('partition_date');
    }

    public function migrationAfter(Connection $db): void
    {
        // 创建分区表（MySQL）
        $db->statement('
            ALTER TABLE ' . $this->getTable() . '
            PARTITION BY RANGE (TO_DAYS(partition_date)) (
                PARTITION p2024 VALUES LESS THAN (TO_DAYS("2025-01-01")),
                PARTITION p2025 VALUES LESS THAN (TO_DAYS("2026-01-01")),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )
        ');
    }
}
```

### 数据完整性保证

```php
#[AutoMigrate]
class DataIntegrityModel extends Model
{
    public function migration(Blueprint $table): void
    {
        $table->id();

        // 外键约束保证引用完整性
        $table->foreignId('user_id')
            ->constrained()
            ->onUpdate('cascade')
            ->onDelete('restrict'); // 防止误删除

        // 唯一约束防止重复
        $table->string('email')->unique();
        $table->unique(['user_id', 'category_id']); // 复合唯一约束

        // 检查约束保证数据有效性（MySQL 8.0.16+）
        $table->integer('age');
        $table->decimal('price', 8, 2);

        // 使用 migrationAfter 添加复杂约束
    }

    public function migrationAfter(Connection $db): void
    {
        // 添加检查约束
        $db->statement('ALTER TABLE ' . $this->getTable() . ' ADD CONSTRAINT chk_age CHECK (age >= 0 AND age <= 150)');
        $db->statement('ALTER TABLE ' . $this->getTable() . ' ADD CONSTRAINT chk_price CHECK (price >= 0)');
    }
}
```

### 版本升级策略

```php
#[AutoMigrate]
class VersionedModel extends Model
{
    public function migration(Blueprint $table): void
    {
        $table->id();
        $table->string('name');

        // v1.0 字段
        $table->string('email');
        $table->timestamps();

        // v1.1 新增字段（通过增量更新自动添加）
        $table->string('phone')->nullable()->comment('v1.1新增：手机号');

        // v1.2 新增字段
        $table->json('settings')->nullable()->comment('v1.2新增：用户设置');

        // 注意：不要删除或重命名已有字段，这会导致数据丢失
        // 如需废弃字段，保留字段但停止使用，通过应用层控制
    }
}
```

## 注意事项

### ⚠️ 安全提醒

1. **生产环境备份**：执行迁移前务必备份数据库
2. **字段兼容性**：不要删除或重命名现有字段
3. **约束添加**：为已有数据添加约束时要确保数据符合约束条件
4. **索引影响**：大表添加索引可能影响性能，建议在低峰期操作

### 🚫 避免的操作

```php
// ❌ 错误：删除字段会导致数据丢失
public function migration(Blueprint $table): void
{
    $table->id();
    $table->string('name');
    // 移除了 'old_field' - 这会导致该字段被删除！
}

// ❌ 错误：重命名字段会导致数据丢失
public function migration(Blueprint $table): void
{
    $table->id();
    $table->string('new_name'); // 原来是 'old_name'
}

// ✅ 正确：添加新字段，保留旧字段
public function migration(Blueprint $table): void
{
    $table->id();
    $table->string('name');
    $table->string('old_field')->nullable(); // 保留旧字段
    $table->string('new_field')->nullable(); // 添加新字段
}
```

::: tip 最佳实践总结
1. **增量设计**：只添加新字段，不删除旧字段
2. **合理索引**：为高频查询字段创建索引
3. **字段注释**：详细的字段注释有助于维护
4. **类型选择**：选择合适的字段类型和长度
5. **约束完整**：合理使用外键和唯一约束
6. **备份习惯**：重要操作前先备份数据
:::

通过 DuxLite 的自动迁移系统，您可以高效地管理数据库结构变更，确保开发和生产环境的数据库结构保持同步，同时保证数据安全和系统稳定。