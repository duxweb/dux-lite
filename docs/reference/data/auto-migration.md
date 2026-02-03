# 自动同步

DuxLite 提供了自动同步功能，通过 `#[AutoMigrate]` 注解和模型方法自动同步数据表结构，比传统迁移更方便。

## 基本使用

### 启用自动同步

```php
<?php
namespace App\Models;

use Core\Database\Attribute\AutoMigrate;
use Core\Database\Model;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

#[AutoMigrate]
class User extends Model
{
    protected $table = 'users';
    
    /**
     * 数据表结构定义
     */
    public function migration(Blueprint $table): void
    {
        $table->id();
        $table->string('name', 100)->index();
        $table->string('email', 191)->unique();
        $table->string('password');
        $table->tinyInteger('status')->default(1)->index();
        $table->timestamps();
    }

    /**
     * 数据种子方法
     * 仅在表首次创建时执行，用于初始化基础数据
     */
    public function seed(Connection $db): void
    {
        // 检查是否已有数据，避免重复插入
        if ($this->query()->exists()) {
            return;
        }
        
        // 创建初始管理员用户
        $this->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'status' => 1
        ]);
    }

    /**
     * 安装后处理方法（可选）
     * 在所有模型同步完成后执行，用于处理关联数据
     */
    public function install(Connection $db): void
    {
        // 处理需要在所有表创建完成后执行的逻辑
        // 例如：创建关联数据、初始化配置等
    }
}
```

## 执行同步

### 同步命令

使用命令行工具执行数据库同步：

```bash
# 同步所有应用的模型
php dux db:sync

# 同步指定应用的模型
php dux db:sync admin
php dux db:sync api
```

### 同步流程

1. **模型扫描**：框架自动扫描所有带有 `#[AutoMigrate]` 注解的模型
2. **表结构对比**：对比当前数据库表与模型定义的差异
3. **智能同步**：
   - 新表：直接创建
   - 已存在表：智能添加缺失字段，保留现有数据
   - 索引同步：自动添加新索引
4. **种子数据**：仅在表首次创建时执行 `seed()` 方法
5. **安装处理**：所有表同步完成后执行 `install()` 方法

### 同步特性

- **非破坏性**：不会删除已存在的字段和数据
- **智能对比**：只同步有变化的表结构
- **安全可靠**：保留现有数据的同时添加新功能
- **自动索引**：同步字段索引和约束
- **分应用同步**：支持按应用分别同步

## 字段类型

### 常用字段类型

| 方法 | 说明 | 示例 |
|------|------|------|
| `id()` | 自增主键 | `$table->id();` |
| `string()` | 字符串类型 | `$table->string('name', 100);` |
| `text()` | 文本类型 | `$table->text('content');` |
| `integer()` | 整数类型 | `$table->integer('count');` |
| `bigInteger()` | 大整数类型 | `$table->bigInteger('user_id');` |
| `tinyInteger()` | 小整数类型 | `$table->tinyInteger('status');` |
| `decimal()` | 小数类型 | `$table->decimal('price', 10, 2);` |
| `boolean()` | 布尔类型 | `$table->boolean('is_active');` |
| `date()` | 日期类型 | `$table->date('birth_date');` |
| `datetime()` | 日期时间类型 | `$table->datetime('published_at');` |
| `timestamp()` | 时间戳类型 | `$table->timestamp('created_at');` |
| `timestamps()` | 创建时间戳字段 | `$table->timestamps();` |
| `json()` | JSON类型 | `$table->json('metadata');` |

### 字段修饰符

| 修饰符 | 说明 | 示例 |
|------|------|------|
| `nullable()` | 允许NULL值 | `$table->string('phone')->nullable();` |
| `default()` | 设置默认值 | `$table->integer('status')->default(1);` |
| `unique()` | 唯一约束 | `$table->string('email')->unique();` |
| `index()` | 创建索引 | `$table->string('name')->index();` |
| `comment()` | 字段注释 | `$table->string('name')->comment('用户名');` |

## 索引管理

### 创建索引

```php
public function migration(Blueprint $table): void
{
    $table->id();
    $table->string('name', 100);
    $table->string('email', 191);
    $table->tinyInteger('status');
    $table->timestamps();
    
    // 单字段索引
    $table->index('name');
    $table->index('status');
    
    // 复合索引
    $table->index(['status', 'created_at']);
    $table->index(['name', 'email'], 'idx_name_email');
    
    // 唯一索引
    $table->unique('email');
    $table->unique(['name', 'type'], 'unique_name_type');
}
```

### 外键约束

```php
public function migration(Blueprint $table): void
{
    $table->id();
    $table->bigInteger('user_id')->index();
    $table->bigInteger('category_id')->index();
    $table->timestamps();
    
    // 外键约束
    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    $table->foreign('category_id')->references('id')->on('categories');
}
```

## 完整示例

### 商品模型

```php
#[AutoMigrate]
class Product extends Model
{
    protected $table = 'products';
    
    public function migration(Blueprint $table): void
    {
        $table->id();
        $table->string('name', 200)->index();
        $table->string('sku', 100)->unique();
        $table->text('description')->nullable();
        $table->decimal('price', 10, 2)->index();
        $table->decimal('original_price', 10, 2)->nullable();
        $table->integer('stock')->default(0);
        $table->bigInteger('category_id')->index();
        $table->json('images')->nullable();
        $table->json('attributes')->nullable();
        $table->tinyInteger('status')->default(1)->index();
        $table->integer('sort')->default(0)->index();
        $table->timestamps();
        
        // 复合索引
        $table->index(['category_id', 'status']);
        $table->index(['status', 'sort']);
        $table->index(['price', 'status']);
        
        // 外键约束
        $table->foreign('category_id')->references('id')->on('categories');
    }
    
    public function seed(Connection $db): void
    {
        $this->create([
            'name' => '示例商品',
            'sku' => 'DEMO001',
            'description' => '这是一个示例商品',
            'price' => 99.99,
            'stock' => 100,
            'category_id' => 1,
            'status' => 1
        ]);
    }
    
    public function transform(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'price' => $this->price,
            'stock' => $this->stock,
            'status' => $this->status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
```

### 用户角色关联表

```php
#[AutoMigrate]
class UserRole extends Model
{
    protected $table = 'user_roles';
    public $timestamps = false;
    
    public function migration(Blueprint $table): void
    {
        $table->id();
        $table->bigInteger('user_id')->index();
        $table->bigInteger('role_id')->index();
        
        // 复合唯一索引
        $table->unique(['user_id', 'role_id']);
        
        // 外键约束
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
    }
}
```

## 数据种子

### 种子方法特点

- **执行时机**：仅在表首次创建时执行
- **用途**：初始化基础数据，如管理员账户、默认配置等
- **安全性**：应该包含数据检查，避免重复插入

### 基础种子数据

```php
public function seed(Connection $db): void
{
    // 避免重复插入数据
    if ($this->query()->exists()) {
        return;
    }
    
    // 创建管理员用户
    $admin = $this->create([
        'name' => 'Administrator',
        'email' => 'admin@example.com',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'status' => 1
    ]);
    
    // 批量创建测试数据
    $users = [];
    for ($i = 1; $i <= 10; $i++) {
        $users[] = [
            'name' => "User {$i}",
            'email' => "user{$i}@example.com",
            'password' => password_hash('123456', PASSWORD_DEFAULT),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
    
    $db->table($this->getTable())->insert($users);
}
```

### 安装后处理

`install()` 方法在所有模型同步完成后执行，用于处理依赖关系：

```php
public function install(Connection $db): void
{
    // 确保依赖的表已存在数据
    $categories = $db->table('categories')->get();
    
    if ($categories->isEmpty()) {
        // 先创建分类数据
        $db->table('categories')->insert([
            'name' => '默认分类',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $categoryId = $db->getPdo()->lastInsertId();
    } else {
        $categoryId = $categories->first()->id;
    }
    
    // 检查是否已有商品数据
    if ($this->query()->exists()) {
        return;
    }
    
    // 创建商品数据
    $this->create([
        'name' => '示例商品',
        'category_id' => $categoryId,
        'price' => 99.99,
        'status' => 1
    ]);
}
```

## 最佳实践

### 字段命名规范

```php
public function migration(Blueprint $table): void
{
    $table->id();                           // 主键使用 id
    $table->bigInteger('user_id')->index();  // 外键使用 table_id 格式
    $table->string('name', 100);            // 字符串指定长度
    $table->tinyInteger('status')->default(1); // 状态字段使用 tinyInteger
    $table->decimal('price', 10, 2);        // 金额使用 decimal
    $table->timestamps();                   // 时间戳字段
}
```

### 索引优化

```php
public function migration(Blueprint $table): void
{
    // 单字段索引
    $table->string('email')->unique();     // 唯一字段
    $table->tinyInteger('status')->index(); // 常用查询字段
    
    // 复合索引（注意字段顺序）
    $table->index(['status', 'created_at']); // 状态+时间
    $table->index(['user_id', 'type']);      // 用户+类型
    
    // 避免过多索引
    // 不要为每个字段都创建索引
}
```

### 数据类型选择

```php
public function migration(Blueprint $table): void
{
    // 根据数据范围选择合适类型
    $table->tinyInteger('status');      // 0-255，适合状态值
    $table->integer('count');           // 一般计数
    $table->bigInteger('user_id');      // 外键使用 bigInteger
    
    // 文本类型选择
    $table->string('title', 200);      // 标题等短文本
    $table->text('content');           // 长文本内容
    $table->json('metadata');          // 结构化数据
    
    // 时间类型选择
    $table->timestamp('created_at');   // 精确时间
    $table->date('birth_date');        // 仅日期
}
```

通过自动同步功能，可以直接在模型中定义数据表结构，框架会智能对比并同步数据库变化，比传统的数据库迁移更加便捷和安全。
