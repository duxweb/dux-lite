# Database 数据库

DuxLite 数据库组件的核心类定义和 API 规格说明。

## Db 类

**命名空间：** `Core\Database\Db`

### 静态方法

```php
public static function connection(?string $name = null): \Illuminate\Database\Connection
```
- **参数：** `$name` - 数据库连接名称（可选）
- **返回：** `\Illuminate\Database\Connection` - 数据库连接实例

```php
public static function table(string $table, ?string $connection = null): \Illuminate\Database\Query\Builder
```
- **参数：**
  - `$table` - 表名
  - `$connection` - 连接名称（可选）
- **返回：** `\Illuminate\Database\Query\Builder` - 查询构造器

```php
public static function transaction(\Closure $callback, ?string $connection = null): mixed
```
- **参数：**
  - `$callback` - 事务回调函数
  - `$connection` - 连接名称（可选）
- **返回：** `mixed` - 回调函数返回值
- **异常：** `\Exception` - 事务失败时抛出

```php
public static function beginTransaction(?string $connection = null): void
```
- **参数：** `$connection` - 连接名称（可选）
- **返回：** `void`

```php
public static function commit(?string $connection = null): void
```
- **参数：** `$connection` - 连接名称（可选）
- **返回：** `void`

```php
public static function rollback(?string $connection = null): void
```
- **参数：** `$connection` - 连接名称（可选）
- **返回：** `void`

## Model 类

**命名空间：** `Core\Database\Model`
**继承：** `\Illuminate\Database\Eloquent\Model`

### 属性

| 属性 | 类型 | 说明 |
|------|------|------|
| `$table` | `string` | 数据表名 |
| `$primaryKey` | `string` | 主键字段名 |
| `$keyType` | `string` | 主键类型 |
| `$incrementing` | `bool` | 是否自增主键 |
| `$fillable` | `array` | 可批量赋值字段 |
| `$guarded` | `array` | 不可批量赋值字段 |
| `$hidden` | `array` | 隐藏字段 |
| `$visible` | `array` | 可见字段 |
| `$casts` | `array` | 类型转换 |
| `$dates` | `array` | 日期字段 |
| `$timestamps` | `bool` | 是否使用时间戳 |

### 方法

```php
public static function create(array $attributes = []): static
```
- **参数：** `$attributes` - 属性数组
- **返回：** `static` - 模型实例

```php
public static function find(mixed $id, array $columns = ['*']): ?static
```
- **参数：**
  - `$id` - 主键值
  - `$columns` - 查询字段
- **返回：** `static|null` - 模型实例或null

```php
public static function findOrFail(mixed $id, array $columns = ['*']): static
```
- **参数：**
  - `$id` - 主键值
  - `$columns` - 查询字段
- **返回：** `static` - 模型实例
- **异常：** `\Illuminate\Database\Eloquent\ModelNotFoundException`

```php
public static function where(string $column, mixed $operator = null, mixed $value = null): \Illuminate\Database\Eloquent\Builder
```
- **参数：**
  - `$column` - 字段名
  - `$operator` - 操作符
  - `$value` - 值
- **返回：** `\Illuminate\Database\Eloquent\Builder` - 查询构造器

```php
public function save(array $options = []): bool
```
- **参数：** `$options` - 保存选项
- **返回：** `bool` - 是否保存成功

```php
public function update(array $attributes = [], array $options = []): bool
```
- **参数：**
  - `$attributes` - 更新属性
  - `$options` - 更新选项
- **返回：** `bool` - 是否更新成功

```php
public function delete(): bool
```
- **返回：** `bool` - 是否删除成功

## Migrate 类

**命名空间：** `Core\Database\Migrate`

### 方法

```php
public function __construct()
```
- **说明：** 构造函数

```php
public function createTable(string $table, \Closure $callback): void
```
- **参数：**
  - `$table` - 表名
  - `$callback` - 表结构定义回调
- **返回：** `void`

```php
public function dropTable(string $table): void
```
- **参数：** `$table` - 表名
- **返回：** `void`

```php
public function hasTable(string $table): bool
```
- **参数：** `$table` - 表名
- **返回：** `bool` - 表是否存在

```php
public function hasColumn(string $table, string $column): bool
```
- **参数：**
  - `$table` - 表名
  - `$column` - 字段名
- **返回：** `bool` - 字段是否存在

```php
public function addColumn(string $table, string $column, string $type, array $options = []): void
```
- **参数：**
  - `$table` - 表名
  - `$column` - 字段名
  - `$type` - 字段类型
  - `$options` - 字段选项
- **返回：** `void`

```php
public function dropColumn(string $table, string $column): void
```
- **参数：**
  - `$table` - 表名
  - `$column` - 字段名
- **返回：** `void`

```php
public function createIndex(string $table, string|array $columns, string $name = null): void
```
- **参数：**
  - `$table` - 表名
  - `$columns` - 字段名或字段数组
  - `$name` - 索引名（可选）
- **返回：** `void`

```php
public function dropIndex(string $table, string $name): void
```
- **参数：**
  - `$table` - 表名
  - `$name` - 索引名
- **返回：** `void`

## 查询构造器方法

### 条件查询

```php
public function where(string $column, mixed $operator = null, mixed $value = null): static
public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
public function whereIn(string $column, array $values): static
public function whereNotIn(string $column, array $values): static
public function whereBetween(string $column, array $values): static
public function whereNull(string $column): static
public function whereNotNull(string $column): static
```

### 排序和分页

```php
public function orderBy(string $column, string $direction = 'asc'): static
public function limit(int $value): static
public function offset(int $value): static
public function skip(int $value): static
public function take(int $value): static
```

### 聚合查询

```php
public function count(string $column = '*'): int
public function sum(string $column): mixed
public function avg(string $column): mixed
public function min(string $column): mixed
public function max(string $column): mixed
```

### 连接查询

```php
public function join(string $table, string $first, string $operator = null, string $second = null): static
public function leftJoin(string $table, string $first, string $operator = null, string $second = null): static
public function rightJoin(string $table, string $first, string $operator = null, string $second = null): static
```

### 分组查询

```php
public function groupBy(string ...$groups): static
public function having(string $column, string $operator = null, mixed $value = null): static
```

### 数据操作

```php
public function insert(array $values): bool
public function insertGetId(array $values, string $sequence = null): int
public function update(array $values): int
public function delete(): int
public function get(array $columns = ['*']): \Illuminate\Support\Collection
public function first(array $columns = ['*']): mixed
```

## 数据库配置结构

配置文件：`config/database.toml`

### 基础配置

```toml
[default]
driver = "mysql"
host = "127.0.0.1"
port = 3306
database = "database_name"
username = "username"
password = "password"
charset = "utf8mb4"
collation = "utf8mb4_unicode_ci"
prefix = ""
```

### 多数据库配置

```toml
[mysql]
driver = "mysql"
# ... mysql配置

[pgsql]
driver = "pgsql"
# ... postgresql配置

[sqlite]
driver = "sqlite"
database = "database/database.sqlite"
```

## 字段类型常量

| 类型 | 说明 |
|------|------|
| `string` | 字符串类型 |
| `integer` | 整数类型 |
| `bigInteger` | 大整数类型 |
| `decimal` | 小数类型 |
| `boolean` | 布尔类型 |
| `date` | 日期类型 |
| `datetime` | 日期时间类型 |
| `timestamp` | 时间戳类型 |
| `text` | 文本类型 |
| `json` | JSON类型 |

## 关联关系方法

### 一对一关系

```php
public function hasOne(string $related, string $foreignKey = null, string $localKey = null): \Illuminate\Database\Eloquent\Relations\HasOne
```

### 一对多关系

```php
public function hasMany(string $related, string $foreignKey = null, string $localKey = null): \Illuminate\Database\Eloquent\Relations\HasMany
```

### 多对多关系

```php
public function belongsToMany(string $related, string $table = null, string $foreignPivotKey = null, string $relatedPivotKey = null): \Illuminate\Database\Eloquent\Relations\BelongsToMany
```

### 反向关系

```php
public function belongsTo(string $related, string $foreignKey = null, string $ownerKey = null): \Illuminate\Database\Eloquent\Relations\BelongsTo
```