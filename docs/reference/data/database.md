# 数据模型

DuxLite 基于 Eloquent ORM 提供强大的数据访问能力，支持自动同步表结构。

## 数据库配置

配置文件：`config/database.toml`

```toml
[db.drivers.default]
driver = "mysql"
host = "localhost"
database = "dux_lite"
username = "root"
password = "root"
port = 3306
prefix = "app_"
```

## 基础模型

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
    protected $hidden = ['password'];
    
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
     * 数据种子（可选）
     */
    public function seed(Connection $db): void
    {
        if ($this->query()->exists()) {
            return;
        }
        
        $this->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'status' => 1
        ]);
    }
    
    /**
     * 统一输出转换方法
     */
    public function transform(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
```

## 基础操作

### 创建记录

```php
// 创建单条记录
$user = User::create([
    'name' => 'John',
    'email' => 'john@example.com',
    'password' => password_hash('password', PASSWORD_DEFAULT)
]);

// 批量插入
User::insert([
    ['name' => 'User1', 'email' => 'user1@example.com'],
    ['name' => 'User2', 'email' => 'user2@example.com'],
]);
```

### 查询记录

**重要：使用 `模型::query()` 获得完整的代码提示**

```php
// 推荐写法：使用 query() 方法
$users = User::query()->where('status', 1)->get();
$user = User::query()->where('email', 'admin@example.com')->first();

// 根据主键查找
$user = User::find(1);
$user = User::findOrFail(1); // 找不到会抛出异常

// 复杂查询
$users = User::query()
    ->where('status', 1)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// 分页查询
$users = User::query()->paginate(15);
```

### 更新和删除

```php
// 更新记录
$user = User::find(1);
$user->update(['name' => 'New Name']);

// 批量更新
User::query()->where('status', 0)->update(['status' => 1]);

// 删除记录
$user = User::find(1);
$user->delete();

// 批量删除
User::query()->where('status', 0)->delete();
```

## 查询构造器

### 条件查询

```php
// 基础条件
$users = User::query()->where('age', '>', 18)->get();
$users = User::query()->where('name', 'like', '%John%')->get();

// 多条件
$users = User::query()
    ->where('status', 1)
    ->where('age', '>', 18)
    ->get();

// orWhere
$users = User::query()
    ->where('name', 'John')
    ->orWhere('name', 'Jane')
    ->get();

// whereIn
$users = User::query()->whereIn('id', [1, 2, 3])->get();

// 时间查询
$users = User::query()->whereDate('created_at', '2024-01-01')->get();
```

### 排序和分页

```php
// 排序
$users = User::query()->orderBy('created_at', 'desc')->get();

// 分页
$users = User::query()->paginate(15);
$users = User::query()->limit(10)->offset(20)->get();
```

### 聚合查询

```php
// 计数
$count = User::query()->count();
$activeCount = User::query()->where('status', 1)->count();

// 其他聚合
$totalAge = User::query()->sum('age');
$avgAge = User::query()->avg('age');
$maxAge = User::query()->max('age');
```

## 模型关联

### 一对一关系

```php
class User extends Model
{
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}

class Profile extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// 使用关联
$user = User::query()->with('profile')->find(1);
echo $user->profile->bio;
```

### 一对多关系

```php
class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

class Post extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// 使用关联
$user = User::query()->with('posts')->find(1);
foreach ($user->posts as $post) {
    echo $post->title;
}
```

### 多对多关系

```php
class User extends Model
{
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}

// 使用关联
$user = User::query()->with('roles')->find(1);
foreach ($user->roles as $role) {
    echo $role->name;
}

// 附加关联
$user->roles()->attach([1, 2, 3]);
$user->roles()->sync([1, 2]);
$user->roles()->detach([1, 2]);
```

## 预加载优化

```php
// 解决 N+1 问题
$users = User::query()->with('posts')->get();

// 多层关联
$users = User::query()->with('posts.comments')->get();

// 条件预加载
$users = User::query()->with(['posts' => function ($query) {
    $query->where('published', true);
}])->get();
```

## 查询作用域

```php
class User extends Model
{
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
    
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
}

// 使用作用域
$activeUsers = User::query()->active()->get();
$adminUsers = User::query()->active()->ofType('admin')->get();
```

## 事务处理

```php
use Core\App;

// 自动事务
$result = App::db()->getConnection()->transaction(function () {
    $user = User::create(['name' => 'John', 'email' => 'john@example.com']);
    $profile = Profile::create(['user_id' => $user->id, 'bio' => 'Bio text']);
    return $user;
});

// 手动事务
App::db()->getConnection()->beginTransaction();
try {
    $user = User::create(['name' => 'John']);
    $profile = Profile::create(['user_id' => $user->id]);
    App::db()->getConnection()->commit();
} catch (\Exception $e) {
    App::db()->getConnection()->rollback();
    throw $e;
}
```

## 多数据库连接

```php
// 配置多个连接
[db.drivers.mysql]
driver = "mysql"
database = "mysql_database"

[db.drivers.sqlite]
driver = "sqlite"
database = "database/logs.sqlite"

// 使用指定连接
$users = App::db()->connection('mysql')->table('users')->get();

// 模型中指定连接
class LogEntry extends Model
{
    protected $connection = 'sqlite';
}
```

## 最佳实践

1. **使用 `query()` 方法**：获得完整代码提示
2. **使用 `transform()` 方法**：统一数据输出格式
3. **使用预加载**：避免 N+1 查询问题
4. **使用事务**：确保数据一致性
5. **使用自动同步**：通过 `#[AutoMigrate]` 注解管理表结构

DuxLite 的数据模型系统简单易用，通过 Eloquent ORM 提供强大的数据操作能力。