# Route 路由

DuxLite 路由系统的核心类定义和 API 规格说明。

## Route 类

**命名空间：** `Core\Route\Route`

### 方法

```php
public function __construct(\Slim\App $app)
```
- **参数：** `$app` - Slim 应用实例

```php
public function get(string $pattern, callable|string $callable): \Slim\Interfaces\RouteInterface
```
- **参数：**
  - `$pattern` - 路由模式
  - `$callable` - 回调函数或控制器方法
- **返回：** `\Slim\Interfaces\RouteInterface` - 路由接口

```php
public function post(string $pattern, callable|string $callable): \Slim\Interfaces\RouteInterface
```
- **参数：**
  - `$pattern` - 路由模式
  - `$callable` - 回调函数或控制器方法
- **返回：** `\Slim\Interfaces\RouteInterface` - 路由接口

```php
public function put(string $pattern, callable|string $callable): \Slim\Interfaces\RouteInterface
```
- **参数：**
  - `$pattern` - 路由模式
  - `$callable` - 回调函数或控制器方法
- **返回：** `\Slim\Interfaces\RouteInterface` - 路由接口

```php
public function delete(string $pattern, callable|string $callable): \Slim\Interfaces\RouteInterface
```
- **参数：**
  - `$pattern` - 路由模式
  - `$callable` - 回调函数或控制器方法
- **返回：** `\Slim\Interfaces\RouteInterface` - 路由接口

```php
public function patch(string $pattern, callable|string $callable): \Slim\Interfaces\RouteInterface
```
- **参数：**
  - `$pattern` - 路由模式
  - `$callable` - 回调函数或控制器方法
- **返回：** `\Slim\Interfaces\RouteInterface` - 路由接口

```php
public function options(string $pattern, callable|string $callable): \Slim\Interfaces\RouteInterface
```
- **参数：**
  - `$pattern` - 路由模式
  - `$callable` - 回调函数或控制器方法
- **返回：** `\Slim\Interfaces\RouteInterface` - 路由接口

```php
public function any(string $pattern, callable|string $callable): \Slim\Interfaces\RouteInterface
```
- **参数：**
  - `$pattern` - 路由模式
  - `$callable` - 回调函数或控制器方法
- **返回：** `\Slim\Interfaces\RouteInterface` - 路由接口

```php
public function map(array $methods, string $pattern, callable|string $callable): \Slim\Interfaces\RouteInterface
```
- **参数：**
  - `$methods` - HTTP 方法数组
  - `$pattern` - 路由模式
  - `$callable` - 回调函数或控制器方法
- **返回：** `\Slim\Interfaces\RouteInterface` - 路由接口

```php
public function group(string $pattern, callable $callable): \Slim\Interfaces\RouteGroupInterface
```
- **参数：**
  - `$pattern` - 路由组前缀
  - `$callable` - 路由组回调函数
- **返回：** `\Slim\Interfaces\RouteGroupInterface` - 路由组接口

```php
public function resource(string $name, string $controller): void
```
- **参数：**
  - `$name` - 资源名称
  - `$controller` - 控制器类名
- **返回：** `void`
- **说明：** 注册 RESTful 资源路由

## Register 类

**命名空间：** `Core\Route\Register`

### 方法

```php
public function __construct()
```
- **说明：** 构造函数

```php
public function register(): void
```
- **返回：** `void`
- **说明：** 注册所有路由

```php
public function registerAttributes(): void
```
- **返回：** `void`
- **说明：** 注册注解路由

```php
public function registerRoutes(): void
```
- **返回：** `void`
- **说明：** 注册手动定义的路由

```php
public function registerResources(): void
```
- **返回：** `void`
- **说明：** 注册资源路由

```php
public function getRoutes(): array
```
- **返回：** `array` - 所有路由信息数组
- **说明：** 获取已注册的路由列表

## 路由注解

### Route 注解

**命名空间：** `Core\Route\Attribute\Route`

```php
#[\Attribute(\Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public string $method,
        public string $path,
        public ?string $name = null,
        public array $middleware = []
    ) {}
}
```

**属性：**
- `$method` - HTTP 方法（GET、POST、PUT、DELETE、PATCH、OPTIONS）
- `$path` - 路由路径
- `$name` - 路由名称（可选）
- `$middleware` - 中间件数组（可选）

### RouteGroup 注解

**命名空间：** `Core\Route\Attribute\RouteGroup`

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
class RouteGroup
{
    public function __construct(
        public string $app,
        public string $route,
        public array $middleware = []
    ) {}
}
```

**属性：**
- `$app` - 应用标识符
- `$route` - 路由组前缀
- `$middleware` - 中间件数组（可选）

## 路由模式规范

### 基础模式

| 模式 | 说明 |
|------|------|
| `/users` | 固定路径 |
| `/users/{id}` | 单个参数 |
| `/users/{id}/posts/{post_id}` | 多个参数 |

### 参数约束

| 约束 | 模式 | 说明 |
|------|------|------|
| 数字 | `/users/{id:\d+}` | 只匹配数字 |
| 字母 | `/users/{name:[a-zA-Z]+}` | 只匹配字母 |
| 自定义 | `/users/{id:[0-9]{1,3}}` | 自定义正则表达式 |

### 可选参数

| 模式 | 说明 |
|------|------|
| `/posts[/{id}]` | 可选的单个参数 |
| `/users[/{id}[/edit]]` | 嵌套可选参数 |

### 通配符

| 模式 | 说明 |
|------|------|
| `/files/{path:.*}` | 匹配任意路径 |

## 资源路由映射

### 标准资源路由

| HTTP方法 | 路径 | 控制器方法 | 路由名称 | 说明 |
|----------|------|-----------|----------|------|
| GET | `/users` | `index` | `users.index` | 列表页 |
| GET | `/users/create` | `create` | `users.create` | 创建页 |
| POST | `/users` | `store` | `users.store` | 保存 |
| GET | `/users/{id}` | `show` | `users.show` | 详情页 |
| GET | `/users/{id}/edit` | `edit` | `users.edit` | 编辑页 |
| PUT/PATCH | `/users/{id}` | `update` | `users.update` | 更新 |
| DELETE | `/users/{id}` | `destroy` | `users.destroy` | 删除 |

### 扩展资源路由

| HTTP方法 | 路径 | 控制器方法 | 路由名称 | 说明 |
|----------|------|-----------|----------|------|
| GET | `/users/trash` | `trash` | `users.trash` | 回收站 |
| POST | `/users/{id}/restore` | `restore` | `users.restore` | 恢复 |
| DELETE | `/users/batch` | `batchDestroy` | `users.batch` | 批量删除 |

## 中间件支持

### 中间件类型

| 类型 | 作用范围 | 说明 |
|------|----------|------|
| 全局中间件 | 所有路由 | 在应用级别添加 |
| 路由组中间件 | 路由组内所有路由 | 在路由组上添加 |
| 路由中间件 | 单个路由 | 在具体路由上添加 |
| 注解中间件 | 注解指定的路由 | 通过注解属性添加 |

### 中间件执行顺序

1. 全局中间件（按添加顺序）
2. 路由组中间件（从外到内）
3. 路由中间件（按添加顺序）

## 路由缓存

### 缓存文件格式

缓存文件：`data/cache/routes.php`

```php
<?php
return [
    'routes' => [
        // 路由定义数组
    ],
    'groups' => [
        // 路由组定义数组
    ],
    'resources' => [
        // 资源路由定义数组
    ]
];
```

### 缓存管理方法

```php
public function cacheRoutes(): void
```
- **返回：** `void`
- **说明：** 生成路由缓存文件

```php
public function clearCache(): void
```
- **返回：** `void`
- **说明：** 清除路由缓存文件

```php
public function loadFromCache(): bool
```
- **返回：** `bool` - 是否成功从缓存加载
- **说明：** 从缓存文件加载路由

## URL 生成

### 路由名称生成

```php
public function urlFor(string $routeName, array $data = [], array $queryParams = []): string
```
- **参数：**
  - `$routeName` - 路由名称
  - `$data` - 路由参数数组（可选）
  - `$queryParams` - 查询参数数组（可选）
- **返回：** `string` - 生成的URL
- **说明：** 根据路由名称生成URL

### 相对路径和绝对路径

```php
public function relativeUrlFor(string $routeName, array $data = [], array $queryParams = []): string
```
- **返回：** `string` - 相对URL路径

```php
public function fullUrlFor(string $routeName, array $data = [], array $queryParams = []): string
```
- **返回：** `string` - 完整的绝对URL

## 路由命令

### RouteCommand 类

**命名空间：** `Core\Route\RouteCommand`

#### 可用命令

```bash
# 查看所有路由
php dux route:list

# 查看指定应用的路由
php dux route:list --app=admin

# 生成路由缓存
php dux route:cache

# 清除路由缓存
php dux route:clear
```

#### 命令参数

| 参数 | 类型 | 说明 |
|------|------|------|
| `--app` | `string` | 指定应用名称 |
| `--method` | `string` | 过滤HTTP方法 |
| `--name` | `string` | 过滤路由名称 |
| `--uri` | `string` | 过滤路由URI |

## 路由信息结构

### 路由对象属性

| 属性 | 类型 | 说明 |
|------|------|------|
| `methods` | `array` | HTTP方法数组 |
| `pattern` | `string` | 路由模式 |
| `name` | `string` | 路由名称 |
| `callable` | `callable|string` | 路由处理器 |
| `middleware` | `array` | 中间件数组 |
| `arguments` | `array` | 路由参数 |

### 路由组对象属性

| 属性 | 类型 | 说明 |
|------|------|------|
| `pattern` | `string` | 路由组前缀 |
| `middleware` | `array` | 中间件数组 |
| `routes` | `array` | 子路由数组 |

## HTTP 状态码常量

| 常量 | 值 | 说明 |
|------|----|----- |
| `HTTP_OK` | 200 | 成功 |
| `HTTP_CREATED` | 201 | 已创建 |
| `HTTP_NO_CONTENT` | 204 | 无内容 |
| `HTTP_BAD_REQUEST` | 400 | 请求错误 |
| `HTTP_UNAUTHORIZED` | 401 | 未授权 |
| `HTTP_FORBIDDEN` | 403 | 禁止访问 |
| `HTTP_NOT_FOUND` | 404 | 未找到 |
| `HTTP_METHOD_NOT_ALLOWED` | 405 | 方法不允许 |
| `HTTP_INTERNAL_SERVER_ERROR` | 500 | 服务器内部错误 |