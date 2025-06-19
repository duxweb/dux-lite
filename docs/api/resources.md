# Resources 资源

DuxLite RESTful 资源控制器系统的核心类定义和 API 规格说明。

## Register 类

**命名空间：** `Core\Resources\Register`

**说明：** 资源注册器，用于管理资源控制器。

### 方法

```php
public function set(string $name, Resource $resource): void
```
- **参数：**
  - `$name` - 资源名称
  - `$resource` - 资源实例
- **返回：** `void`
- **说明：** 注册资源控制器

```php
public function get(string $name): Resource
```
- **参数：** `$name` - 资源名称
- **返回：** `Resource` - 资源实例
- **说明：** 获取资源控制器

## Resource 类

**命名空间：** `Core\Resources\Resource`

**说明：** 资源控制器基类，用于定义资源路由和中间件。

### 属性

```php
public string $name
```
- **说明：** 资源名称

```php
public string $route
```
- **说明：** 资源路由前缀

### 方法

```php
public function __construct(string $name, string $route)
```
- **参数：**
  - `$name` - 资源名称
  - `$route` - 资源路由前缀
- **说明：** 构造资源实例

```php
public function addMiddleware(object ...$middleware): self
```
- **参数：** `...$middleware` - 中间件实例
- **返回：** `self` - 当前实例
- **说明：** 添加中间件

```php
public function addAuthMiddleware(object ...$middleware): self
```
- **参数：** `...$middleware` - 认证中间件实例
- **返回：** `self` - 当前实例
- **说明：** 添加认证中间件

```php
public function getMiddleware(): array
```
- **返回：** `array` - 中间件列表
- **说明：** 获取中间件列表

```php
public function getAuthMiddleware(): array
```
- **返回：** `array` - 认证中间件列表
- **说明：** 获取认证中间件列表

```php
public function getAllMiddleware(): array
```
- **返回：** `array` - 所有中间件列表
- **说明：** 获取所有中间件列表

```php
public function run(): void
```
- **返回：** `void`
- **说明：** 运行资源控制器，注册路由和权限

## Resources 抽象类

**命名空间：** `Core\Resources\Action\Resources`

**说明：** RESTful 资源控制器抽象基类。

### 属性

```php
protected string $key = "id"
```
- **说明：** 主键字段名

```php
protected string $label = ""
```
- **说明：** 资源标签

```php
protected string $model
```
- **说明：** 关联的模型类名

```php
protected bool $tree = false
```
- **说明：** 是否为树形结构

```php
protected array $pagination = ['status' => true, 'pageSize' => 10]
```
- **说明：** 分页配置

```php
public array $includesMany = []
```
- **说明：** 多条数据允许字段

```php
public array $excludesMany = []
```
- **说明：** 多条数据排除字段

```php
public array $includesOne = []
```
- **说明：** 单条数据允许字段

```php
public array $excludesOne = []
```
- **说明：** 单条数据排除字段

### 钩子属性

```php
public array $createHook = []
```
- **说明：** 创建钩子函数数组

```php
public array $editHook = []
```
- **说明：** 编辑钩子函数数组

```php
public array $storeHook = []
```
- **说明：** 存储钩子函数数组

```php
public array $delHook = []
```
- **说明：** 删除钩子函数数组

### 核心方法

```php
public function init(ServerRequestInterface $request, ResponseInterface $response, array $args): void
```
- **参数：**
  - `$request` - 请求对象
  - `$response` - 响应对象
  - `$args` - 路由参数
- **返回：** `void`
- **说明：** 初始化方法，在每个动作前调用

```php
public function queryModel(string $model): Builder
```
- **参数：** `$model` - 模型类名
- **返回：** `Builder` - 查询构造器
- **说明：** 获取模型查询构造器

```php
public function transform(object $item): array
```
- **参数：** `$item` - 数据项
- **返回：** `array` - 转换后的数据
- **说明：** 数据转换方法

```php
public function query(Builder $query): void
```
- **参数：** `$query` - 查询构造器
- **返回：** `void`
- **说明：** 通用查询条件

```php
public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
```
- **参数：**
  - `$query` - 查询构造器
  - `$request` - 请求对象
  - `$args` - 路由参数
- **返回：** `void`
- **说明：** 多条数据查询条件

```php
public function queryOne(Builder $query, ServerRequestInterface $request, array $args): void
```
- **参数：**
  - `$query` - 查询构造器
  - `$request` - 请求对象
  - `$args` - 路由参数
- **返回：** `void`
- **说明：** 单条数据查询条件

### 元数据方法

```php
public function metaMany(object|array $query, array $data, ServerRequestInterface $request, array $args): array
```
- **参数：**
  - `$query` - 查询对象或数组
  - `$data` - 数据数组
  - `$request` - 请求对象
  - `$args` - 路由参数
- **返回：** `array` - 元数据
- **说明：** 多条数据元数据

```php
public function metaOne(mixed $data, ServerRequestInterface $request, array $args): array
```
- **参数：**
  - `$data` - 数据
  - `$request` - 请求对象
  - `$args` - 路由参数
- **返回：** `array` - 元数据
- **说明：** 单条数据元数据

### 验证和格式化方法

```php
public function validator(array $data, ServerRequestInterface $request, array $args): array
```
- **参数：**
  - `$data` - 输入数据
  - `$request` - 请求对象
  - `$args` - 路由参数
- **返回：** `array` - 验证规则
- **说明：** 数据验证规则

```php
public function format(Data $data, ServerRequestInterface $request, array $args): array
```
- **参数：**
  - `$data` - 验证后的数据
  - `$request` - 请求对象
  - `$args` - 路由参数
- **返回：** `array` - 格式化后的数据
- **说明：** 数据入库格式化

### 工具方法

```php
public function formatData(array $rule, Data $data): array
```
- **参数：**
  - `$rule` - 格式化规则
  - `$data` - 数据对象
- **返回：** `array` - 格式化后的数据
- **说明：** 根据规则格式化数据

```php
public function transformData(Collection|LengthAwarePaginator|Model|null $data, callable $callback): array
```
- **参数：**
  - `$data` - 数据集合或模型
  - `$callback` - 转换回调函数
- **返回：** `array` - 转换后的数据
- **说明：** 批量数据转换

```php
public function translation(ServerRequestInterface $request, string $action): string
```
- **参数：**
  - `$request` - 请求对象
  - `$action` - 动作名称
- **返回：** `string` - 翻译后的消息
- **说明：** 获取动作翻译消息

```php
public function getSorts(array $params): array
```
- **参数：** `$params` - 请求参数
- **返回：** `array` - 排序条件
- **说明：** 解析排序参数

```php
public function filterData(array $includes, array $excludes, array $data): array
```
- **参数：**
  - `$includes` - 包含字段
  - `$excludes` - 排除字段
  - `$data` - 原始数据
- **返回：** `array` - 过滤后的数据
- **说明：** 过滤数据字段

## RESTful 动作 Traits

### Many Trait

**说明：** 多条数据查询动作。

```php
public function many(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
```

### One Trait

**说明：** 单条数据查询动作。

```php
public function one(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
```

### Create Trait

**说明：** 创建数据动作。

```php
public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
```

### Edit Trait

**说明：** 编辑数据动作。

```php
public function edit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
```

### Store Trait

**说明：** 存储数据动作。

```php
public function store(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
```

### Delete Trait

**说明：** 删除数据动作。

```php
public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
```

### DeleteMany Trait

**说明：** 批量删除动作。

```php
public function deleteMany(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
```

### Trash Trait

**说明：** 软删除数据动作。

```php
public function trash(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
```

### Restore Trait

**说明：** 恢复软删除数据动作。

```php
public function restore(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
```

## 使用示例

```php
use Core\Resources\Action\Resources;

class UserResource extends Resources
{
    protected string $model = User::class;
    protected string $label = '用户';

    // 字段过滤
    public array $includesMany = ['id', 'name', 'email', 'created_at'];
    public array $excludesOne = ['password'];

    // 查询条件
    public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
    {
        $params = $request->getQueryParams();

        if (!empty($params['keyword'])) {
            $query->where('name', 'like', '%' . $params['keyword'] . '%');
        }

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
    }

    // 数据验证
    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'name' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6'
        ];
    }

    // 数据格式化
    public function format(Data $data, ServerRequestInterface $request, array $args): array
    {
        $formatted = $data->toArray();

        if (!empty($formatted['password'])) {
            $formatted['password'] = password_hash($formatted['password'], PASSWORD_DEFAULT);
        }

        return $formatted;
    }

    // 数据转换
    public function transform(object $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'email' => $item->email,
            'status_text' => $item->status === 1 ? '启用' : '禁用',
            'created_at' => $item->created_at->format('Y-m-d H:i:s')
        ];
    }
}
```

## 资源路由映射

| HTTP方法 | 路径 | 动作 | 说明 |
|----------|------|------|------|
| `GET` | `/resource` | `many` | 获取资源列表 |
| `GET` | `/resource/{id}` | `one` | 获取单个资源 |
| `GET` | `/resource/create` | `create` | 获取创建表单 |
| `POST` | `/resource` | `store` | 创建资源 |
| `GET` | `/resource/{id}/edit` | `edit` | 获取编辑表单 |
| `PUT/PATCH` | `/resource/{id}` | `store` | 更新资源 |
| `DELETE` | `/resource/{id}` | `delete` | 删除资源 |
| `DELETE` | `/resource` | `deleteMany` | 批量删除 |
| `POST` | `/resource/{id}/trash` | `trash` | 软删除 |
| `POST` | `/resource/{id}/restore` | `restore` | 恢复删除 |