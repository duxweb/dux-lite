# Permission 权限

DuxLite 权限管理系统的核心类定义和 API 规格说明。

## Register 类

**命名空间：** `Core\Permission\Register`

**说明：** 权限注册器，用于管理不同应用的权限配置。

### 属性

```php
public array $app = []
```
- **说明：** 存储权限应用配置的数组

### 方法

```php
public function set(string $name, Permission $permission): void
```
- **参数：**
  - `$name` - 权限应用名称
  - `$permission` - 权限实例
- **返回：** `void`
- **说明：** 设置权限应用配置

```php
public function get(string $name): Permission
```
- **参数：** `$name` - 权限应用名称
- **返回：** `Permission` - 权限实例
- **异常：** `Exception` - 权限应用不存在时抛出
- **说明：** 获取权限应用配置

## Permission 类

**命名空间：** `Core\Permission\Permission`

**说明：** 权限管理核心类，用于定义权限结构。

### 属性

```php
private array $data = []
```
- **说明：** 权限数据数组

```php
private string $pattern
```
- **说明：** 权限模式字符串

```php
private string $app = ''
```
- **说明：** 应用名称

### 方法

```php
public function __construct(string $pattern = "")
```
- **参数：** `$pattern` - 权限模式（可选）
- **说明：** 构造权限实例

```php
public function setApp(string $app): void
```
- **参数：** `$app` - 应用名称
- **返回：** `void`
- **说明：** 设置权限所属应用

```php
public function group(string $name): PermissionGroup
```
- **参数：** `$name` - 权限组名称
- **返回：** `PermissionGroup` - 权限组实例
- **说明：** 创建权限组

```php
public function get(): array
```
- **返回：** `array` - 格式化的权限数据
- **说明：** 获取权限配置数据

```php
public function getData(): array
```
- **返回：** `array` - 原始权限数据
- **说明：** 获取原始权限数据

## PermissionGroup 类

**命名空间：** `Core\Permission\PermissionGroup`

**说明：** 权限组管理类，用于组织权限项。

### 方法

```php
public function __construct(string $app, string $name, string $pattern)
```
- **参数：**
  - `$app` - 应用名称
  - `$name` - 权限组名称
  - `$pattern` - 权限模式
- **说明：** 构造权限组实例

```php
public function add(string $name, string $label, string $route = ''): self
```
- **参数：**
  - `$name` - 权限名称
  - `$label` - 权限标签
  - `$route` - 关联路由（可选）
- **返回：** `self` - 当前实例
- **说明：** 添加权限项

```php
public function get(): array
```
- **返回：** `array` - 权限组数据
- **说明：** 获取权限组配置

```php
public function getData(): array
```
- **返回：** `array` - 权限项数据
- **说明：** 获取权限项数据

## Can 类

**命名空间：** `Core\Permission\Can`

**说明：** 权限检查工具类。

### 方法

```php
public static function check(string $permission, mixed $user = null): bool
```
- **参数：**
  - `$permission` - 权限标识符
  - `$user` - 用户实例（可选）
- **返回：** `bool` - 是否有权限
- **说明：** 检查用户是否具有指定权限

## PermissionMiddleware 类

**命名空间：** `Core\Permission\PermissionMiddleware`

**说明：** 权限检查中间件。

### 方法

```php
public function __construct(string $permission, ?string $model = null)
```
- **参数：**
  - `$permission` - 权限标识符
  - `$model` - 关联模型（可选）
- **说明：** 构造权限中间件

```php
public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
```
- **参数：**
  - `$request` - 请求对象
  - `$handler` - 请求处理器
- **返回：** `ResponseInterface` - 响应对象
- **异常：** `ExceptionBusiness` - 权限不足时抛出
- **说明：** 处理权限检查

## PermissionCommand 类

**命名空间：** `Core\Permission\PermissionCommand`

**说明：** 权限管理命令行工具。

### 方法

```php
public function list(): void
```
- **返回：** `void`
- **说明：** 列出所有权限配置

```php
public function sync(): void
```
- **返回：** `void`
- **说明：** 同步权限配置到数据库

## 使用示例

```php
// 定义权限
$permission = new Permission();

$userGroup = $permission->group('用户管理');
$userGroup->add('user.list', '用户列表', 'admin.user.index');
$userGroup->add('user.create', '创建用户', 'admin.user.create');
$userGroup->add('user.edit', '编辑用户', 'admin.user.edit');
$userGroup->add('user.delete', '删除用户', 'admin.user.delete');

$orderGroup = $permission->group('订单管理');
$orderGroup->add('order.list', '订单列表', 'admin.order.index');
$orderGroup->add('order.view', '查看订单', 'admin.order.show');
$orderGroup->add('order.edit', '编辑订单', 'admin.order.edit');

// 注册权限
App::permission()->set('admin', $permission);

// 权限检查
if (Can::check('user.create')) {
    // 有创建用户权限
}

// 中间件权限检查
$app->get('/admin/users', UserController::class . ':index')
   ->add(new PermissionMiddleware('user.list'));
```

## 权限配置结构

### 权限应用

| 字段 | 类型 | 说明 |
|------|------|------|
| `name` | `string` | 应用名称 |
| `groups` | `array` | 权限组列表 |

### 权限组

| 字段 | 类型 | 说明 |
|------|------|------|
| `name` | `string` | 权限组名称 |
| `permissions` | `array` | 权限项列表 |

### 权限项

| 字段 | 类型 | 说明 |
|------|------|------|
| `name` | `string` | 权限标识符 |
| `label` | `string` | 权限显示名称 |
| `route` | `string` | 关联路由名称 |

## 权限中间件

### 支持的权限类型

| 类型 | 说明 | 示例 |
|------|------|------|
| 基础权限 | 简单的权限检查 | `user.create` |
| 模型权限 | 基于模型的权限检查 | `user.edit:User` |
| 动态权限 | 运行时动态权限检查 | `order.view:own` |