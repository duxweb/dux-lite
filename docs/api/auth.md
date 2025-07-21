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
public static function decode(ServerRequestInterface $request, string $app, string $source = 'auto'): ?array
```
- **参数：**
  - `$request` - HTTP 请求对象
  - `$app` - 应用标识符
  - `$source` - 令牌来源（可选，默认 'auto'）
    - `'auto'` - 自动检测（优先 header，然后 cookie）
    - `'header'` - 仅从 Authorization header 获取
    - `'cookie'` - 仅从 token cookie 获取
- **返回：** `array|null` - 解码后的载荷数组，失败返回 null
- **说明：** 解码和验证 JWT 令牌，支持指定令牌来源

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

当令牌接近过期时，中间件会根据登录方式智能选择续期方式：

#### Header 登录续期

| 响应头 | 值 | 说明 |
|--------|----|----- |
| `Authorization` | `Bearer <new_token>` | 刷新后的新令牌 |

#### Cookie 登录续期

| 响应头 | 值 | 说明 |
|--------|----|----- |
| `Set-Cookie` | `token=Bearer%20<new_token>; Path=/; HttpOnly; SameSite=Lax` | 刷新后的新令牌 Cookie |

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

### 智能续期策略

- **触发条件：** 令牌剩余有效期小于总有效期的 1/3 时
- **检测逻辑：** 自动检测令牌来源（Header 或 Cookie）
- **刷新方式：**
  - **Header 登录：** 通过 `Authorization` 响应头返回新令牌
  - **Cookie 登录：** 通过 `Set-Cookie` 响应头设置新令牌
- **回调函数：** 可通过构造函数传入回调函数处理刷新事件

### 令牌来源检测

中间件按以下优先级检测令牌来源：

1. **Authorization Header：** 检查 `Authorization: Bearer <token>` 格式
2. **Cookie：** 检查 `token` Cookie 字段
3. **默认处理：** 未找到令牌时默认按 Header 方式处理

### 续期行为

| 登录方式 | 检测条件 | 续期方式 | 客户端处理 |
|----------|----------|----------|------------|
| Header | 存在有效的 `Authorization` 头 | `Authorization` 响应头 | 需要读取响应头并更新存储的令牌 |
| Cookie | 不存在 `Authorization` 头但存在 `token` Cookie | `Set-Cookie` 响应头 | 浏览器自动处理，无需手动操作 |