# 认证系统

DuxLite 提供了完整的用户认证系统，支持多种认证方式、会话管理和安全机制。通过灵活的认证配置和安全策略，构建可靠的用户身份验证和访问控制。

## 基本概念

### 设计理念

DuxLite 认证系统采用**多机制支持、安全优先、灵活配置**的设计：

- **多认证方式**：支持 JWT、Session、API Token 等多种认证机制
- **安全优先**：内置密码加密、令牌签名、防暴力破解等安全措施
- **会话管理**：完整的会话生命周期管理和状态跟踪
- **可扩展性**：支持自定义认证提供者和中间件
- **审计日志**：完整的认证操作日志记录

### 核心特性

- **JWT 认证**：基于 JSON Web Token 的无状态认证
- **Session 认证**：传统的会话状态认证
- **API 令牌**：用于 API 访问的长期令牌认证
- **密码策略**：可配置的密码强度和过期策略
- **多设备管理**：支持同一用户多设备同时登录管理

## 认证配置

### 基础配置

```toml
# config/auth.toml
[auth]
# 默认认证驱动
default = "jwt"

# 密码哈希
password_hash = "bcrypt"
password_cost = 12

# 令牌配置
[auth.jwt]
secret = "${JWT_SECRET}"
algorithm = "HS256"
ttl = 3600  # 1小时
refresh_ttl = 604800  # 7天
issuer = "DuxLite"

[auth.session]
name = "DUXLITE_SESSION"
lifetime = 7200  # 2小时
secure = false
httponly = true
samesite = "Lax"

[auth.api]
header = "Authorization"
prefix = "Bearer"
```

### 认证中间件配置

```php
// 在应用中配置认证中间件
class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 获取认证信息
        $auth = $this->extractAuth($request);
        
        if ($auth) {
            // 验证认证信息
            $user = $this->validateAuth($auth);
            if ($user) {
                // 将用户信息添加到请求属性
                $request = $request->withAttribute('auth', $user);
                $request = $request->withAttribute('user', $user);
            }
        }
        
        return $handler->handle($request);
    }

    private function extractAuth(ServerRequestInterface $request): ?array
    {
        // 尝试从 JWT 获取认证信息
        if ($jwt = $this->extractJWT($request)) {
            return $jwt;
        }
        
        // 尝试从 Session 获取认证信息
        if ($session = $this->extractSession($request)) {
            return $session;
        }
        
        // 尝试从 API Token 获取认证信息
        if ($apiToken = $this->extractApiToken($request)) {
            return $apiToken;
        }
        
        return null;
    }

    private function extractJWT(ServerRequestInterface $request): ?array
    {
        $header = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $this->decodeJWT($matches[1]);
        }
        
        return null;
    }

    private function decodeJWT(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(config('auth.jwt.secret'), config('auth.jwt.algorithm')));
            return (array) $decoded;
        } catch (Exception $e) {
            App::log()->warning('JWT decode failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
```

## JWT 认证

### JWT 令牌生成

```php
class JWTAuthService
{
    private string $secret;
    private string $algorithm;
    private int $ttl;
    private int $refreshTtl;

    public function __construct()
    {
        $this->secret = config('auth.jwt.secret');
        $this->algorithm = config('auth.jwt.algorithm');
        $this->ttl = config('auth.jwt.ttl');
        $this->refreshTtl = config('auth.jwt.refresh_ttl');
    }

    /**
     * 生成访问令牌
     */
    public function generateAccessToken(array $payload): string
    {
        $now = time();
        
        $token = [
            'iss' => config('auth.jwt.issuer'),  // 签发者
            'iat' => $now,                       // 签发时间
            'exp' => $now + $this->ttl,          // 过期时间
            'jti' => $this->generateJti(),       // 令牌ID
            'type' => 'access',                  // 令牌类型
            // 用户信息
            'user_id' => $payload['user_id'],
            'username' => $payload['username'],
            'role' => $payload['role'] ?? 'user',
            'permissions' => $payload['permissions'] ?? []
        ];

        return JWT::encode($token, $this->secret, $this->algorithm);
    }

    /**
     * 生成刷新令牌
     */
    public function generateRefreshToken(int $userId): string
    {
        $now = time();
        
        $token = [
            'iss' => config('auth.jwt.issuer'),
            'iat' => $now,
            'exp' => $now + $this->refreshTtl,
            'jti' => $this->generateJti(),
            'type' => 'refresh',
            'user_id' => $userId
        ];

        return JWT::encode($token, $this->secret, $this->algorithm);
    }

    /**
     * 解码并验证令牌
     */
    public function decodeToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            $payload = (array) $decoded;
            
            // 验证令牌类型
            if ($payload['type'] !== 'access') {
                return null;
            }
            
            // 验证是否在黑名单中
            if ($this->isTokenBlacklisted($payload['jti'])) {
                return null;
            }
            
            return $payload;
            
        } catch (ExpiredException $e) {
            App::log()->info('JWT token expired', ['jti' => $this->extractJti($token)]);
            return null;
        } catch (Exception $e) {
            App::log()->warning('JWT decode failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 刷新访问令牌
     */
    public function refreshToken(string $refreshToken): ?array
    {
        try {
            $decoded = JWT::decode($refreshToken, new Key($this->secret, $this->algorithm));
            $payload = (array) $decoded;
            
            // 验证令牌类型
            if ($payload['type'] !== 'refresh') {
                return null;
            }
            
            // 验证用户是否存在且有效
            $user = User::find($payload['user_id']);
            if (!$user || $user->status !== 1) {
                return null;
            }
            
            // 生成新的访问令牌和刷新令牌
            $accessToken = $this->generateAccessToken([
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'permissions' => $user->getPermissions()
            ]);
            
            $newRefreshToken = $this->generateRefreshToken($user->id);
            
            // 将旧的刷新令牌加入黑名单
            $this->blacklistToken($payload['jti']);
            
            return [
                'access_token' => $accessToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->ttl,
                'user' => $user->transform()
            ];
            
        } catch (Exception $e) {
            App::log()->warning('Token refresh failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 令牌黑名单管理
     */
    public function blacklistToken(string $jti): void
    {
        Cache::set("jwt_blacklist:{$jti}", true, $this->refreshTtl);
    }

    public function isTokenBlacklisted(string $jti): bool
    {
        return Cache::get("jwt_blacklist:{$jti}") === true;
    }

    private function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function extractJti(string $token): ?string
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }
            
            $payload = json_decode(base64_decode($parts[1]), true);
            return $payload['jti'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}
```

### JWT 认证控制器

```php
class AuthController
{
    private JWTAuthService $jwtService;
    private UserService $userService;

    public function __construct()
    {
        $this->jwtService = new JWTAuthService();
        $this->userService = new UserService();
    }

    /**
     * 用户登录
     */
    public function login(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $data = $request->getParsedBody();
        
        // 验证输入
        $validator = new Validator($data, [
            'username' => ['required', 'string'],
            'password' => ['required', 'string']
        ]);

        if (!$validator->validate()) {
            throw new ExceptionValidator($validator->errors());
        }

        // 查找用户
        $user = User::where('username', $data['username'])
                   ->orWhere('email', $data['username'])
                   ->first();

        if (!$user || !password_verify($data['password'], $user->password)) {
            // 记录登录失败
            $this->logLoginAttempt($data['username'], false, $request);
            throw new ExceptionBusiness('用户名或密码错误', 401);
        }

        // 检查用户状态
        if ($user->status !== 1) {
            throw new ExceptionBusiness('账户已被禁用', 403);
        }

        // 检查是否被锁定
        if ($this->isUserLocked($user->id)) {
            throw new ExceptionBusiness('账户已被锁定，请稍后再试', 423);
        }

        // 生成令牌
        $accessToken = $this->jwtService->generateAccessToken([
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'permissions' => $user->getPermissions()
        ]);

        $refreshToken = $this->jwtService->generateRefreshToken($user->id);

        // 更新用户登录信息
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $this->getClientIp($request)
        ]);

        // 记录登录成功
        $this->logLoginAttempt($user->username, true, $request);

        // 清除登录失败计数
        $this->clearLoginFailures($user->id);

        return send($response, '登录成功', [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => config('auth.jwt.ttl'),
            'user' => $user->transform()
        ]);
    }

    /**
     * 刷新令牌
     */
    public function refresh(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $data = $request->getParsedBody();
        
        if (empty($data['refresh_token'])) {
            throw new ExceptionBusiness('刷新令牌不能为空', 400);
        }

        $result = $this->jwtService->refreshToken($data['refresh_token']);
        
        if (!$result) {
            throw new ExceptionBusiness('刷新令牌无效或已过期', 401);
        }

        return send($response, '令牌刷新成功', $result);
    }

    /**
     * 用户退出
     */
    public function logout(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $auth = $request->getAttribute('auth');
        
        if ($auth && isset($auth['jti'])) {
            // 将令牌加入黑名单
            $this->jwtService->blacklistToken($auth['jti']);
        }

        return send($response, '退出成功');
    }

    /**
     * 获取当前用户信息
     */
    public function me(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $auth = $request->getAttribute('auth');
        
        if (!$auth) {
            throw new ExceptionBusiness('未认证', 401);
        }

        $user = User::find($auth['user_id']);
        
        if (!$user) {
            throw new ExceptionBusiness('用户不存在', 404);
        }

        return send($response, '获取成功', [
            'user' => $user->transform(),
            'permissions' => $user->getPermissions()
        ]);
    }

    /**
     * 修改密码
     */
    public function changePassword(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $auth = $request->getAttribute('auth');
        $data = $request->getParsedBody();

        // 验证输入
        $validator = new Validator($data, [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'minLength:8'],
            'confirm_password' => ['required', 'string', 'equals:new_password']
        ]);

        if (!$validator->validate()) {
            throw new ExceptionValidator($validator->errors());
        }

        $user = User::find($auth['user_id']);
        
        if (!$user) {
            throw new ExceptionBusiness('用户不存在', 404);
        }

        // 验证当前密码
        if (!password_verify($data['current_password'], $user->password)) {
            throw new ExceptionBusiness('当前密码错误', 400);
        }

        // 更新密码
        $user->update([
            'password' => password_hash($data['new_password'], PASSWORD_DEFAULT),
            'password_changed_at' => now()
        ]);

        // 将所有现有令牌加入黑名单（强制重新登录）
        $this->blacklistUserTokens($user->id);

        return send($response, '密码修改成功');
    }

    /**
     * 记录登录尝试
     */
    private function logLoginAttempt(string $username, bool $success, ServerRequestInterface $request): void
    {
        LoginAttempt::create([
            'username' => $username,
            'ip_address' => $this->getClientIp($request),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'success' => $success,
            'attempted_at' => now()
        ]);

        if (!$success) {
            $this->incrementLoginFailures($username);
        }
    }

    /**
     * 检查用户是否被锁定
     */
    private function isUserLocked(int $userId): bool
    {
        $maxAttempts = config('auth.max_login_attempts', 5);
        $lockoutTime = config('auth.lockout_time', 900); // 15分钟

        $failures = Cache::get("login_failures:{$userId}", 0);
        
        return $failures >= $maxAttempts;
    }

    private function incrementLoginFailures(string $username): void
    {
        $user = User::where('username', $username)->orWhere('email', $username)->first();
        if ($user) {
            $key = "login_failures:{$user->id}";
            $failures = Cache::get($key, 0) + 1;
            Cache::set($key, $failures, config('auth.lockout_time', 900));
        }
    }

    private function clearLoginFailures(int $userId): void
    {
        Cache::delete("login_failures:{$userId}");
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $headers = ['X-Forwarded-For', 'X-Real-IP', 'X-Client-IP'];
        
        foreach ($headers as $header) {
            $ip = $request->getHeaderLine($header);
            if ($ip) {
                return explode(',', $ip)[0];
            }
        }
        
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    private function blacklistUserTokens(int $userId): void
    {
        // 这里可以实现将用户所有令牌加入黑名单的逻辑
        // 由于 JWT 是无状态的，可以通过增加用户的 token_version 来实现
        $user = User::find($userId);
        if ($user) {
            $user->increment('token_version');
        }
    }
}
```

## Session 认证

### Session 配置和管理

```php
class SessionAuthService
{
    private string $sessionName;
    private int $lifetime;

    public function __construct()
    {
        $this->sessionName = config('auth.session.name');
        $this->lifetime = config('auth.session.lifetime');
        
        $this->configureSession();
    }

    private function configureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name($this->sessionName);
            session_set_cookie_params([
                'lifetime' => $this->lifetime,
                'path' => '/',
                'domain' => '',
                'secure' => config('auth.session.secure'),
                'httponly' => config('auth.session.httponly'),
                'samesite' => config('auth.session.samesite')
            ]);
        }
    }

    /**
     * 开始会话
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * 登录用户
     */
    public function login(User $user): void
    {
        $this->start();
        
        // 重新生成会话ID防止会话固定攻击
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['role'] = $user->role;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // 更新用户登录信息
        $user->update([
            'last_login_at' => now(),
            'last_session_id' => session_id()
        ]);
    }

    /**
     * 获取当前用户
     */
    public function getUser(): ?User
    {
        $this->start();
        
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        // 检查会话是否过期
        if ($this->isSessionExpired()) {
            $this->logout();
            return null;
        }
        
        // 更新最后活动时间
        $_SESSION['last_activity'] = time();
        
        return User::find($_SESSION['user_id']);
    }

    /**
     * 检查是否已认证
     */
    public function isAuthenticated(): bool
    {
        $this->start();
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    /**
     * 检查会话是否过期
     */
    public function isSessionExpired(): bool
    {
        if (!isset($_SESSION['last_activity'])) {
            return true;
        }
        
        return (time() - $_SESSION['last_activity']) > $this->lifetime;
    }

    /**
     * 退出登录
     */
    public function logout(): void
    {
        $this->start();
        
        // 清除会话数据
        $_SESSION = [];
        
        // 删除会话cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // 销毁会话
        session_destroy();
    }

    /**
     * 延长会话时间
     */
    public function extend(): void
    {
        $this->start();
        
        if ($this->isAuthenticated()) {
            $_SESSION['last_activity'] = time();
        }
    }
}
```

## API 令牌认证

### API 令牌模型

```php
class ApiToken extends Model
{
    protected $table = 'api_tokens';
    
    protected $fillable = [
        'user_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at'
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 检查令牌是否有指定权限
     */
    public function can(string $ability): bool
    {
        if (in_array('*', $this->abilities)) {
            return true;
        }
        
        return in_array($ability, $this->abilities);
    }

    /**
     * 检查令牌是否已过期
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * 更新最后使用时间
     */
    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function transform(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'abilities' => $this->abilities,
            'last_used_at' => $this->last_used_at?->format('Y-m-d H:i:s'),
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s')
        ];
    }
}
```

### API 令牌服务

```php
class ApiTokenService
{
    /**
     * 为用户创建 API 令牌
     */
    public function createToken(User $user, string $name, array $abilities = ['*'], ?DateTime $expiresAt = null): ApiToken
    {
        $token = $this->generateToken();
        
        return ApiToken::create([
            'user_id' => $user->id,
            'name' => $name,
            'token' => hash('sha256', $token),
            'abilities' => $abilities,
            'expires_at' => $expiresAt
        ]);
    }

    /**
     * 验证 API 令牌
     */
    public function validateToken(string $token): ?ApiToken
    {
        $hashedToken = hash('sha256', $token);
        
        $apiToken = ApiToken::where('token', $hashedToken)->first();
        
        if (!$apiToken) {
            return null;
        }
        
        // 检查是否过期
        if ($apiToken->isExpired()) {
            return null;
        }
        
        // 检查用户是否有效
        if (!$apiToken->user || $apiToken->user->status !== 1) {
            return null;
        }
        
        // 更新最后使用时间
        $apiToken->updateLastUsed();
        
        return $apiToken;
    }

    /**
     * 撤销令牌
     */
    public function revokeToken(int $tokenId): bool
    {
        return ApiToken::where('id', $tokenId)->delete() > 0;
    }

    /**
     * 撤销用户的所有令牌
     */
    public function revokeUserTokens(int $userId): int
    {
        return ApiToken::where('user_id', $userId)->delete();
    }

    /**
     * 清理过期令牌
     */
    public function cleanupExpiredTokens(): int
    {
        return ApiToken::where('expires_at', '<', now())->delete();
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
```

### API 令牌控制器

```php
class ApiTokenController
{
    private ApiTokenService $tokenService;

    public function __construct()
    {
        $this->tokenService = new ApiTokenService();
    }

    /**
     * 创建 API 令牌
     */
    public function create(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $auth = $request->getAttribute('auth');
        $data = $request->getParsedBody();

        // 验证输入
        $validator = new Validator($data, [
            'name' => ['required', 'string', 'maxLength:100'],
            'abilities' => ['array'],
            'expires_at' => ['dateTime']
        ]);

        if (!$validator->validate()) {
            throw new ExceptionValidator($validator->errors());
        }

        $user = User::find($auth['user_id']);
        if (!$user) {
            throw new ExceptionBusiness('用户不存在', 404);
        }

        // 检查令牌数量限制
        $tokenCount = ApiToken::where('user_id', $user->id)->count();
        if ($tokenCount >= config('auth.max_api_tokens', 10)) {
            throw new ExceptionBusiness('API令牌数量已达上限', 400);
        }

        $abilities = $data['abilities'] ?? ['*'];
        $expiresAt = isset($data['expires_at']) ? new DateTime($data['expires_at']) : null;

        $token = $this->tokenService->createToken($user, $data['name'], $abilities, $expiresAt);
        $plainTextToken = $this->generatePlainTextToken();

        return send($response, '令牌创建成功', [
            'token' => $token->transform(),
            'plain_text_token' => $plainTextToken
        ], [], 201);
    }

    /**
     * 获取用户的 API 令牌列表
     */
    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $auth = $request->getAttribute('auth');
        
        $tokens = ApiToken::where('user_id', $auth['user_id'])
                         ->orderBy('created_at', 'desc')
                         ->get();

        return send($response, '获取成功', [
            'tokens' => $tokens->map(fn($token) => $token->transform())
        ]);
    }

    /**
     * 撤销 API 令牌
     */
    public function revoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $auth = $request->getAttribute('auth');
        $tokenId = (int) $args['id'];

        $token = ApiToken::where('id', $tokenId)
                        ->where('user_id', $auth['user_id'])
                        ->first();

        if (!$token) {
            throw new ExceptionBusiness('令牌不存在', 404);
        }

        $this->tokenService->revokeToken($tokenId);

        return send($response, '令牌已撤销');
    }

    private function generatePlainTextToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
```

## 安全机制

### 密码策略

```php
class PasswordPolicy
{
    private array $config;

    public function __construct()
    {
        $this->config = config('auth.password_policy', []);
    }

    /**
     * 验证密码强度
     */
    public function validate(string $password): array
    {
        $errors = [];

        // 最小长度
        $minLength = $this->config['min_length'] ?? 8;
        if (strlen($password) < $minLength) {
            $errors[] = "密码长度至少{$minLength}个字符";
        }

        // 最大长度
        $maxLength = $this->config['max_length'] ?? 128;
        if (strlen($password) > $maxLength) {
            $errors[] = "密码长度不能超过{$maxLength}个字符";
        }

        // 必须包含大写字母
        if ($this->config['require_uppercase'] ?? false) {
            if (!preg_match('/[A-Z]/', $password)) {
                $errors[] = '密码必须包含至少一个大写字母';
            }
        }

        // 必须包含小写字母
        if ($this->config['require_lowercase'] ?? false) {
            if (!preg_match('/[a-z]/', $password)) {
                $errors[] = '密码必须包含至少一个小写字母';
            }
        }

        // 必须包含数字
        if ($this->config['require_numbers'] ?? false) {
            if (!preg_match('/[0-9]/', $password)) {
                $errors[] = '密码必须包含至少一个数字';
            }
        }

        // 必须包含特殊字符
        if ($this->config['require_symbols'] ?? false) {
            if (!preg_match('/[^A-Za-z0-9]/', $password)) {
                $errors[] = '密码必须包含至少一个特殊字符';
            }
        }

        // 禁止常见密码
        if ($this->config['forbid_common'] ?? true) {
            if ($this->isCommonPassword($password)) {
                $errors[] = '不能使用常见密码';
            }
        }

        return $errors;
    }

    /**
     * 检查是否为常见密码
     */
    private function isCommonPassword(string $password): bool
    {
        $commonPasswords = [
            '123456', 'password', '123456789', '12345678',
            'abc123', 'qwerty', 'monkey', 'letmein',
            'dragon', '111111', 'baseball', 'iloveyou',
            'trustno1', '1234567', 'sunshine', 'master',
            '123123', 'welcome', 'shadow', 'ashley'
        ];

        return in_array(strtolower($password), $commonPasswords);
    }

    /**
     * 检查密码是否过期
     */
    public function isExpired(?DateTime $passwordChangedAt): bool
    {
        if (!$passwordChangedAt) {
            return false;
        }

        $maxAge = $this->config['max_age_days'] ?? 0;
        if ($maxAge <= 0) {
            return false;
        }

        return $passwordChangedAt->diffInDays(now()) >= $maxAge;
    }
}
```

### 防暴力破解

```php
class BruteForceProtection
{
    private int $maxAttempts;
    private int $lockoutTime;
    private int $slidingWindow;

    public function __construct()
    {
        $this->maxAttempts = config('auth.max_login_attempts', 5);
        $this->lockoutTime = config('auth.lockout_time', 900); // 15分钟
        $this->slidingWindow = config('auth.sliding_window', 3600); // 1小时
    }

    /**
     * 记录登录失败
     */
    public function recordFailure(string $identifier, string $ip): void
    {
        $key = "login_failures:{$identifier}:{$ip}";
        $attempts = Cache::get($key, []);
        
        // 添加当前时间
        $attempts[] = time();
        
        // 移除超出滑动窗口的记录
        $cutoff = time() - $this->slidingWindow;
        $attempts = array_filter($attempts, fn($time) => $time > $cutoff);
        
        Cache::set($key, $attempts, $this->slidingWindow);
        
        // 如果达到最大尝试次数，锁定账户
        if (count($attempts) >= $this->maxAttempts) {
            $this->lockAccount($identifier, $ip);
        }
    }

    /**
     * 检查是否被锁定
     */
    public function isLocked(string $identifier, string $ip): bool
    {
        $lockKey = "account_locked:{$identifier}:{$ip}";
        return Cache::get($lockKey) === true;
    }

    /**
     * 获取剩余锁定时间
     */
    public function getRemainingLockTime(string $identifier, string $ip): int
    {
        $lockKey = "account_locked:{$identifier}:{$ip}";
        $ttl = Cache::ttl($lockKey);
        return max(0, $ttl);
    }

    /**
     * 清除失败记录
     */
    public function clearFailures(string $identifier, string $ip): void
    {
        $failureKey = "login_failures:{$identifier}:{$ip}";
        $lockKey = "account_locked:{$identifier}:{$ip}";
        
        Cache::delete($failureKey);
        Cache::delete($lockKey);
    }

    /**
     * 锁定账户
     */
    private function lockAccount(string $identifier, string $ip): void
    {
        $lockKey = "account_locked:{$identifier}:{$ip}";
        Cache::set($lockKey, true, $this->lockoutTime);
        
        // 记录安全事件
        App::log()->warning('Account locked due to multiple failed login attempts', [
            'identifier' => $identifier,
            'ip' => $ip,
            'lockout_time' => $this->lockoutTime
        ]);
    }
}
```

## 最佳实践

### 1. 安全配置

```php
// ✅ 推荐：强密码策略
'password_policy' => [
    'min_length' => 12,
    'require_uppercase' => true,
    'require_lowercase' => true,
    'require_numbers' => true,
    'require_symbols' => true,
    'forbid_common' => true,
    'max_age_days' => 90
]

// ✅ 推荐：合理的锁定策略
'max_login_attempts' => 5,
'lockout_time' => 900, // 15分钟
'sliding_window' => 3600 // 1小时
```

### 2. 令牌管理

```php
// ✅ 推荐：令牌过期管理
public function scheduleTokenCleanup(): void
{
    // 清理过期的JWT黑名单
    $this->cleanupExpiredBlacklist();
    
    // 清理过期的API令牌
    $this->apiTokenService->cleanupExpiredTokens();
    
    // 清理过期的会话
    $this->cleanupExpiredSessions();
}

// ✅ 推荐：令牌轮换
public function rotateTokens(): void
{
    // 定期轮换JWT签名密钥
    $this->rotateJWTSecret();
    
    // 强制用户重新登录
    $this->invalidateAllTokens();
}
```

### 3. 审计日志

```php
// ✅ 推荐：完整的认证日志
class AuthenticationLogger
{
    public function logLogin(User $user, string $ip, bool $success): void
    {
        AuthLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => $ip,
            'success' => $success,
            'created_at' => now()
        ]);
    }

    public function logLogout(User $user, string $ip): void
    {
        AuthLog::create([
            'user_id' => $user->id,
            'action' => 'logout',
            'ip_address' => $ip,
            'success' => true,
            'created_at' => now()
        ]);
    }
}
```

### 4. 错误处理

```php
// ✅ 推荐：统一的认证错误处理
try {
    $result = $this->authService->authenticate($credentials);
} catch (InvalidCredentialsException $e) {
    return send($response, '用户名或密码错误', null, [], 401);
} catch (AccountLockedException $e) {
    return send($response, '账户已被锁定', [
        'remaining_time' => $e->getRemainingTime()
    ], [], 423);
} catch (AccountDisabledException $e) {
    return send($response, '账户已被禁用', null, [], 403);
}
```

通过遵循这些最佳实践，您可以构建出安全可靠的 DuxLite 认证系统。认证系统是应用安全的第一道防线，必须谨慎设计和实施各种安全措施。