# 编码规范

DuxLite 框架的核心编码约定和开发建议。

## 基本约定

### 模型数据转换

所有模型建议实现 `transform()` 方法统一数据输出：

```php
class User extends Model
{
    public function transform(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
        ];
    }
}
```

### 分页数据处理

统一使用 `format_data()` 函数处理分页：

```php
$list = User::paginate();
["data" => $data, "meta" => $meta] = format_data($list, function ($item) {
    return $item->transform();
});
return send($response, 'ok', $data, $meta);
```

## 路由注解规范

### 资源控制器

```php
#[Resource(app: 'web', route: '/users')]
class UserController extends Resources
{
    #[Action(methods: 'GET', route: '')]
    public function index(): ResponseInterface
    {
        // 实现逻辑
    }
}
```

### 路由中间件

```php
#[Route(methods: 'GET', route: '/users')]
public function index(): ResponseInterface
{
    // 路由示例
}
```


## 配置规范

### TOML 配置优先

```toml
# config/use.dev.toml - 开发环境
[app]
debug = true
secret = "dev-secret-key"

# config/use.toml - 生产环境  
[app]
debug = false
secret = "production-secret-key"
```

### 占位符语法

```toml
[app]
secret = "%env(APP_SECRET)%"
log_file = "%data_path(logs)%/app_%date(Y-m-d)%.log"
```

这些是 DuxLite 框架特有的编码约定，遵循这些规范可以确保代码的一致性和可维护性。
