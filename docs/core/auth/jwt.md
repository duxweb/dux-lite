# JWT 令牌

DuxLite 使用 JSON Web Token (JWT) 作为身份验证的核心技术，提供无状态、安全、可扩展的认证解决方案。

## JWT 概述

### 什么是 JWT

JWT (JSON Web Token) 是一个开放标准 (RFC 7519)，它定义了一种紧凑的、自包含的方式，用于作为 JSON 对象在各方之间安全地传输信息。

### JWT 优势

- **无状态**：服务端不需要存储会话状态
- **可扩展**：适合分布式系统和微服务架构
- **跨域支持**：支持跨域资源共享 (CORS)
- **安全性**：通过签名保证数据完整性
- **自包含**：令牌本身包含所有必要信息

## JWT 结构

### 三部分组成

JWT 由三部分组成，用点 (`.`) 分隔：

```
Header.Payload.Signature
```

#### 1. Header（头部）

包含令牌类型和签名算法：

```json
{
  "alg": "HS256",
  "typ": "JWT"
}
```

#### 2. Payload（载荷）

包含声明（claims），即实际传输的数据：

```json
{
  "sub": "user_app",        // 应用标识符
  "iat": 1234567890,        // 签发时间
  "exp": 1234654290,        // 过期时间
  "id": 123,                // 用户ID
  "username": "john",       // 用户名
  "role": "admin"           // 角色
}
```

#### 3. Signature（签名）

用于验证令牌的完整性：

```
HMACSHA256(
  base64UrlEncode(header) + "." +
  base64UrlEncode(payload),
  secret
)
```

### 完整 JWT 示例

```
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJ1c2VyX2FwcCIsImlhdCI6MTYzNDU2Nzg5MCwiZXhwIjoxNjM0NjU0MjkwLCJpZCI6MTIzLCJ1c2VybmFtZSI6ImpvaG4iLCJyb2xlIjoiYWRtaW4ifQ.signature_hash_here
```

## DuxLite JWT 实现

### 依赖库

DuxLite 使用 Firebase JWT 库处理 JWT 操作：

```php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
```

### Auth 类核心方法

#### token() 方法

生成 JWT 令牌：

```php
public static function token(string $app, $params = [], int $expire = 86400): string
{
    $time = time();
    $payload = [
        'sub' => $app,           // 应用标识符
        'iat' => $time,          // 签发时间
        'exp' => $time + $expire,// 过期时间
    ];
    $payload = [...$payload, ...$params]; // 合并自定义参数

    return 'Bearer ' . JWT::encode($payload, App::config("use")->get("app.secret"), 'HS256');
}
```

#### decode() 方法

解码和验证 JWT 令牌：

```php
public static function decode(Request $request, string $app): ?array
{
    // 提取 Bearer token
    $jwtStr = str_replace('Bearer ', '', $request->getHeaderLine('Authorization'));

    try {
        // 解码令牌
        $jwt = JWT::decode($jwtStr, new Key(App::config("use")->get("app.secret"), 'HS256'));
    } catch (\Exception $e) {
        return null; // 解码失败
    }

    // 验证必要字段
    if (!$jwt->sub || !$jwt->id) {
        return null;
    }

    // 验证应用标识符
    if ($jwt->sub !== $app) {
        return null;
    }

    return (array) $jwt;
}
```

## JWT 载荷设计

### 标准声明 (Claims)

DuxLite 使用以下标准 JWT 声明：

| 声明 | 名称 | 说明 | 示例 |
|------|------|------|------|
| `sub` | Subject | 应用标识符 | `user_app`, `admin_app` |
| `iat` | Issued At | 签发时间 | `1634567890` |
| `exp` | Expiration | 过期时间 | `1634654290` |

### 自定义声明

根据业务需求添加自定义字段：

```php
// 用户应用的载荷
$userPayload = [
    'id' => 123,
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'role' => 'user',
    'department' => 'IT',
    'permissions' => ['read', 'write']
];

// 管理应用的载荷
$adminPayload = [
    'id' => 456,
    'username' => 'admin',
    'email' => 'admin@example.com',
    'role' => 'admin',
    'is_super_admin' => true,
    'last_login' => '2023-10-01 10:00:00'
];

// API 应用的载荷
$apiPayload = [
    'client_id' => 'api_client_001',
    'scope' => ['read:users', 'write:posts'],
    'rate_limit' => 1000,
    'api_version' => 'v1'
];
```

### 载荷最佳实践

```php
// ✅ 推荐的载荷设计
$payload = [
    'id' => $user->id,                    // 必需：用户标识
    'username' => $user->username,        // 用户名
    'role' => $user->role,                // 角色
    'status' => $user->status,            // 状态
    'tenant_id' => $user->tenant_id,      // 多租户标识
];

// ❌ 避免在载荷中包含敏感信息
$payload = [
    'password' => $user->password,        // 不要包含密码
    'credit_card' => $user->credit_card,  // 不要包含敏感数据
    'private_key' => $privateKey,         // 不要包含密钥
];
```

## 签名算法

### 支持的算法

DuxLite 使用 HMAC SHA256 (HS256) 签名算法：

```php
// 当前使用的算法
'HS256' // HMAC using SHA-256
```

### 算法特点

- **对称加密**：使用相同密钥进行签名和验证
- **性能优秀**：计算速度快，适合高并发场景
- **安全可靠**：SHA-256 哈希算法安全性高
- **简单易用**：不需要密钥对管理

### 密钥管理

```php
// 配置文件设置
[app]
secret = "your-very-long-and-random-secret-key-at-least-32-chars"

// 获取密钥
$secret = App::config("use")->get("app.secret");

// 签名示例
$token = JWT::encode($payload, $secret, 'HS256');

// 验证示例
$decoded = JWT::decode($token, new Key($secret, 'HS256'));
```

## 令牌生命周期

### 生成令牌

```php
use Core\Auth\Auth;

// 短期令牌（1小时）
$shortToken = Auth::token('api', $payload, 3600);

// 中期令牌（24小时）
$mediumToken = Auth::token('web', $payload, 86400);

// 长期令牌（7天）
$longToken = Auth::token('mobile', $payload, 604800);

// 自定义过期时间
$customToken = Auth::token('admin', $payload, 3600 * 8); // 8小时
```

### 令牌验证

验证过程包括：

1. **格式验证**：检查 JWT 格式是否正确
2. **签名验证**：验证令牌签名是否有效
3. **时间验证**：检查令牌是否过期
4. **应用验证**：验证应用标识符是否匹配
5. **业务验证**：检查用户状态等业务逻辑

```php
public function validateToken(string $token, string $app): array
{
    try {
        // 解码令牌
        $payload = Auth::decode($request, $app);

        if (!$payload) {
            throw new AuthException('令牌无效');
        }

        // 业务验证
        $user = User::find($payload['id']);
        if (!$user || $user->status !== 'active') {
            throw new AuthException('用户状态异常');
        }

        return $payload;

    } catch (\Firebase\JWT\ExpiredException $e) {
        throw new AuthException('令牌已过期');
    } catch (\Firebase\JWT\SignatureInvalidException $e) {
        throw new AuthException('令牌签名无效');
    } catch (\Exception $e) {
        throw new AuthException('令牌验证失败');
    }
}
```

### 令牌刷新

DuxLite 提供自动令牌刷新机制：

```php
// AuthMiddleware 中的自动续期逻辑
$expire = $token["exp"] - $token["iat"];           // 总有效期
$renewalTime = $token["iat"] + round($expire / 3); // 续期时间点（1/3处）
$currentTime = time();

if ($renewalTime <= $currentTime) {
    // 生成新令牌
    $token["exp"] = $currentTime + $expire;
    $newToken = JWT::encode($token, $secret, 'HS256');

    // 通过响应头返回新令牌
    $response = $response->withHeader("Authorization", "Bearer $newToken");
}
```

## 多应用支持

### 应用隔离

不同应用使用不同的标识符，实现令牌隔离：

```php
// 用户前端应用
$userToken = Auth::token('user_app', $userData, 86400);

// 管理后台应用
$adminToken = Auth::token('admin_app', $adminData, 28800);

// API 服务应用
$apiToken = Auth::token('api_v1', $apiData, 3600);

// 移动应用
$mobileToken = Auth::token('mobile_app', $mobileData, 604800);
```

### 跨应用认证

```php
class CrossAppAuth
{
    public static function validateMultipleApps(Request $request, array $allowedApps): ?array
    {
        $token = str_replace('Bearer ', '', $request->getHeaderLine('Authorization'));

        foreach ($allowedApps as $app) {
            try {
                $payload = Auth::decode($request, $app);
                if ($payload) {
                    return $payload;
                }
            } catch (\Exception $e) {
                continue; // 尝试下一个应用
            }
        }

        return null; // 所有应用都验证失败
    }
}

// 使用示例
$payload = CrossAppAuth::validateMultipleApps($request, ['user_app', 'admin_app']);
```

## 安全考虑

### 密钥安全

```php
// ✅ 推荐的密钥管理
// 1. 使用环境变量
$secret = $_ENV['JWT_SECRET'] ?? App::config("use")->get("app.secret");

// 2. 密钥轮换
class KeyRotation
{
    public static function getCurrentKey(): string
    {
        $keyVersion = App::config("use")->get("app.key_version", 1);
        return App::config("use")->get("app.secret_v{$keyVersion}");
    }

    public static function getOldKeys(): array
    {
        return [
            App::config("use")->get("app.secret_v1"),
            App::config("use")->get("app.secret_v2"),
            // 保留旧密钥用于验证现有令牌
        ];
    }
}
```

### 令牌传输安全

```php
// ✅ 安全的令牌传输
// 1. 使用 HTTPS
if (!$request->getUri()->getScheme() === 'https') {
    throw new SecurityException('JWT 必须通过 HTTPS 传输');
}

// 2. 设置安全响应头
$response = $response
    ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
    ->withHeader('X-Content-Type-Options', 'nosniff')
    ->withHeader('X-Frame-Options', 'DENY');
```

### 防止攻击

```php
// 防止重放攻击
class ReplayAttackProtection
{
    public static function addNonce(array $payload): array
    {
        $payload['nonce'] = bin2hex(random_bytes(16));
        $payload['iat'] = time();
        return $payload;
    }

    public static function validateNonce(array $payload): bool
    {
        $nonce = $payload['nonce'] ?? '';
        $iat = $payload['iat'] ?? 0;

        // 检查 nonce 是否已使用（需要缓存实现）
        if (Cache::has("jwt_nonce:$nonce")) {
            return false;
        }

        // 缓存 nonce，设置过期时间
        Cache::put("jwt_nonce:$nonce", true, 86400);

        return true;
    }
}
```

## 性能优化

### 缓存策略

```php
class JWTCache
{
    // 缓存已验证的令牌
    public static function cacheValidatedToken(string $token, array $payload): void
    {
        $cacheKey = 'jwt:' . hash('sha256', $token);
        $ttl = $payload['exp'] - time();

        Cache::put($cacheKey, $payload, $ttl);
    }

    // 获取缓存的令牌
    public static function getCachedToken(string $token): ?array
    {
        $cacheKey = 'jwt:' . hash('sha256', $token);
        return Cache::get($cacheKey);
    }

    // 令牌黑名单
    public static function blacklistToken(string $token): void
    {
        $cacheKey = 'jwt:blacklist:' . hash('sha256', $token);
        Cache::put($cacheKey, true, 86400 * 7); // 保持一周
    }

    public static function isBlacklisted(string $token): bool
    {
        $cacheKey = 'jwt:blacklist:' . hash('sha256', $token);
        return Cache::has($cacheKey);
    }
}
```

### 批量验证

```php
class BatchJWTValidation
{
    public static function validateBatch(array $tokens, string $app): array
    {
        $results = [];
        $secret = App::config("use")->get("app.secret");

        foreach ($tokens as $index => $token) {
            try {
                $jwt = JWT::decode($token, new Key($secret, 'HS256'));
                $results[$index] = [
                    'valid' => true,
                    'payload' => (array) $jwt
                ];
            } catch (\Exception $e) {
                $results[$index] = [
                    'valid' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
```

## 调试和监控

### 令牌分析工具

```php
class JWTAnalyzer
{
    public static function analyzeToken(string $token): array
    {
        $parts = explode('.', str_replace('Bearer ', '', $token));

        if (count($parts) !== 3) {
            return ['error' => 'Invalid JWT format'];
        }

        try {
            $header = json_decode(base64_decode($parts[0]), true);
            $payload = json_decode(base64_decode($parts[1]), true);

            return [
                'header' => $header,
                'payload' => $payload,
                'signature' => $parts[2],
                'issued_at' => date('Y-m-d H:i:s', $payload['iat'] ?? 0),
                'expires_at' => date('Y-m-d H:i:s', $payload['exp'] ?? 0),
                'is_expired' => ($payload['exp'] ?? 0) < time(),
                'time_to_expire' => ($payload['exp'] ?? 0) - time()
            ];
        } catch (\Exception $e) {
            return ['error' => 'Failed to decode JWT: ' . $e->getMessage()];
        }
    }
}

// 使用示例
$analysis = JWTAnalyzer::analyzeToken($request->getHeaderLine('Authorization'));
error_log('JWT Analysis: ' . json_encode($analysis));
```

### 监控指标

```php
class JWTMetrics
{
    public static function recordTokenGeneration(string $app): void
    {
        // 记录令牌生成次数
        Cache::increment("jwt:generated:$app");
        Cache::increment("jwt:generated:total");
    }

    public static function recordTokenValidation(string $app, bool $success): void
    {
        // 记录验证结果
        $key = $success ? "jwt:validated:$app" : "jwt:failed:$app";
        Cache::increment($key);
    }

    public static function getMetrics(): array
    {
        return [
            'total_generated' => Cache::get('jwt:generated:total', 0),
            'user_app_generated' => Cache::get('jwt:generated:user_app', 0),
            'admin_app_generated' => Cache::get('jwt:generated:admin_app', 0),
            'user_app_validated' => Cache::get('jwt:validated:user_app', 0),
            'user_app_failed' => Cache::get('jwt:failed:user_app', 0),
        ];
    }
}
```

## 故障排除

### 常见问题诊断

**Q: JWT 解码失败**
```php
// 诊断步骤
function diagnoseJWT(string $token): array
{
    $diagnosis = [];

    // 1. 检查格式
    $parts = explode('.', str_replace('Bearer ', '', $token));
    $diagnosis['format_valid'] = count($parts) === 3;

    if (!$diagnosis['format_valid']) {
        return $diagnosis;
    }

    // 2. 检查头部
    try {
        $header = json_decode(base64_decode($parts[0]), true);
        $diagnosis['header_valid'] = !empty($header);
        $diagnosis['algorithm'] = $header['alg'] ?? 'unknown';
    } catch (\Exception $e) {
        $diagnosis['header_valid'] = false;
        $diagnosis['header_error'] = $e->getMessage();
    }

    // 3. 检查载荷
    try {
        $payload = json_decode(base64_decode($parts[1]), true);
        $diagnosis['payload_valid'] = !empty($payload);
        $diagnosis['expires_at'] = $payload['exp'] ?? 0;
        $diagnosis['is_expired'] = ($payload['exp'] ?? 0) < time();
        $diagnosis['subject'] = $payload['sub'] ?? null;
    } catch (\Exception $e) {
        $diagnosis['payload_valid'] = false;
        $diagnosis['payload_error'] = $e->getMessage();
    }

    return $diagnosis;
}
```

**Q: 签名验证失败**
```
检查清单：
1. 密钥是否正确配置
2. 算法是否匹配 (HS256)
3. 令牌是否被篡改
4. 密钥是否发生变化
```

**Q: 令牌过期问题**
```
检查：
1. 服务器时间是否同步
2. 过期时间设置是否合理
3. 自动续期是否正常工作
4. 客户端是否正确处理过期
```

通过深入理解 JWT 的技术细节，您可以更好地使用 DuxLite 的认证系统，构建安全可靠的应用程序。