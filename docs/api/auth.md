# Auth 认证

DuxLite 认证系统的核心类定义和 API 规格说明。

## Auth 类

**命名空间：** `Core\Auth\Auth`

### 静态方法

```php
public static function token(string $app, array $params = [], int $expire = 86400): string
```
- **参数：**
  - `$app` - 应用标识符
  - `$params` - 载荷参数数组（可选）
  - `$expire` - 过期时间（秒，默认86400）
- **返回：** `string` - JWT token（包含 Bearer 前缀）
- **说明：** 生成 JWT 认证令牌

```php
public static function decode(ServerRequestInterface $request, string $app): ?array
```
- **参数：**
  - `$request` - HTTP 请求对象
  - `$app` - 应用标识符
- **返回：** `array|null` - 解码后的载荷数组，失败返回 null
- **说明：** 解码和验证 JWT 令牌

## AuthMiddleware 类

**命名空间：** `Core\Auth\AuthMiddleware`

### 构造函数

```php
public function __construct(string $app, ?\Closure $callback = null)
```
- **参数：**
  - `$app` - 应用标识符
  - `$callback` - 令牌刷新回调函数（可选）

### 方法

```php
public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
```
- **参数：**
  - `$request` - HTTP 请求对象
  - `$handler` - 请求处理器
- **返回：** `ResponseInterface` - HTTP 响应对象
- **异常：** `ExceptionBusiness` - 认证失败时抛出（401状态码）
- **说明：** 中间件调用方法，执行认证验证

### 请求属性注入

认证中间件会向请求对象注入以下属性：

| 属性名 | 类型 | 说明 |
|--------|------|------|
| `auth` | `array` | JWT 解码后的载荷数据 |
| `app` | `string` | 应用标识符 |

### 响应头设置

当令牌接近过期时，中间件会在响应头中返回新的令牌：

| 响应头 | 值 | 说明 |
|--------|----|----- |
| `Authorization` | `Bearer <new_token>` | 刷新后的新令牌 |

## 配置要求

认证系统需要在 `config/use.toml` 中配置应用密钥：

```toml
[app]
secret = "your-jwt-secret-key"
```

## JWT 载荷结构

### 标准字段

| 字段 | 类型 | 说明 |
|------|------|------|
| `sub` | `string` | 应用标识符 |
| `iat` | `int` | 签发时间戳 |
| `exp` | `int` | 过期时间戳 |

### 自定义字段

可通过 `$params` 参数传入自定义载荷数据：

| 常用字段 | 类型 | 说明 |
|----------|------|------|
| `id` | `int|string` | 用户ID |
| `username` | `string` | 用户名 |
| `role` | `string` | 用户角色 |
| `permissions` | `array` | 权限列表 |

## 异常类型

| 异常类 | 状态码 | 说明 |
|--------|--------|------|
| `ExceptionBusiness` | 401 | 认证失败、令牌无效等 |

## 令牌刷新机制

- **触发条件：** 令牌剩余有效期小于总有效期的 1/3 时
- **刷新方式：** 自动生成新令牌并通过 `Authorization` 响应头返回
- **回调函数：** 可通过构造函数传入回调函数处理刷新事件