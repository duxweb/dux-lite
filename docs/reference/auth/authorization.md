# 授权系统

DuxLite 提供了完整的权限控制系统，支持基于角色的访问控制（RBAC）、细粒度权限管理和动态权限验证。通过灵活的权限模型和中间件系统，实现精确的访问控制。

## 基本概念

### 设计理念

DuxLite 授权系统采用**RBAC 模型、权限继承、动态验证**的设计：

- **RBAC 模型**：基于角色的访问控制，支持用户-角色-权限的三层结构
- **权限继承**：支持角色权限继承和权限组合
- **动态验证**：运行时权限验证和上下文相关的权限检查
- **细粒度控制**：支持资源级别的精确权限控制
- **可扩展性**：灵活的权限扩展机制和自定义权限策略

### 核心特性

- **角色管理**：多层级角色体系和角色权限分配
- **权限管理**：资源权限、操作权限和数据权限控制
- **权限继承**：父角色权限自动继承和权限覆盖
- **权限缓存**：高性能的权限缓存机制
- **权限策略**：自定义权限验证策略和规则

## 权限模型

### 数据库结构

```sql
-- 用户表
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 角色表
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    label VARCHAR(100),
    description TEXT,
    parent_id INT,
    level INT DEFAULT 0,
    status TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES roles(id) ON DELETE SET NULL
);

-- 权限表
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    label VARCHAR(100),
    description TEXT,
    resource VARCHAR(50),
    action VARCHAR(50),
    conditions JSON,
    status TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 用户角色关联表
CREATE TABLE user_roles (
    user_id INT,
    role_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- 角色权限关联表
CREATE TABLE role_permissions (
    role_id INT,
    permission_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- 用户直接权限表（特殊权限）
CREATE TABLE user_permissions (
    user_id INT,
    permission_id INT,
    granted TINYINT DEFAULT 1, -- 1:授予, 0:拒绝
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);
```

### 权限模型类

```php
class Role extends Model
{
    protected $table = 'roles';
    
    protected $fillable = [
        'name', 'label', 'description', 'parent_id', 'level', 'status'
    ];

    /**
     * 父角色关系
     */
    public function parent()
    {
        return $this->belongsTo(Role::class, 'parent_id');
    }

    /**
     * 子角色关系
     */
    public function children()
    {
        return $this->hasMany(Role::class, 'parent_id');
    }

    /**
     * 角色用户关系
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    /**
     * 角色权限关系
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * 获取角色的所有权限（包括继承的权限）
     */
    public function getAllPermissions(): Collection
    {
        $permissions = collect();
        
        // 获取当前角色的直接权限
        $permissions = $permissions->merge($this->permissions);
        
        // 获取父角色的权限（递归）
        if ($this->parent) {
            $permissions = $permissions->merge($this->parent->getAllPermissions());
        }
        
        return $permissions->unique('id');
    }

    /**
     * 检查角色是否有指定权限
     */
    public function hasPermission(string $permission): bool
    {
        return $this->getAllPermissions()->contains('name', $permission);
    }

    public function transform(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'level' => $this->level,
            'status' => $this->status,
            'parent' => $this->parent?->transform(),
            'permissions_count' => $this->permissions()->count(),
            'users_count' => $this->users()->count(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s')
        ];
    }
}

class Permission extends Model
{
    protected $table = 'permissions';
    
    protected $fillable = [
        'name', 'label', 'description', 'resource', 'action', 'conditions', 'status'
    ];

    protected $casts = [
        'conditions' => 'array'
    ];

    /**
     * 权限对应的角色
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    /**
     * 直接拥有此权限的用户
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_permissions')
                    ->withPivot('granted');
    }

    /**
     * 检查权限条件
     */
    public function checkConditions(array $context = []): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'eq';
        $value = $condition['value'] ?? null;
        
        if (!$field || !isset($context[$field])) {
            return false;
        }
        
        $contextValue = $context[$field];
        
        return match($operator) {
            'eq' => $contextValue == $value,
            'ne' => $contextValue != $value,
            'gt' => $contextValue > $value,
            'gte' => $contextValue >= $value,
            'lt' => $contextValue < $value,
            'lte' => $contextValue <= $value,
            'in' => in_array($contextValue, (array)$value),
            'not_in' => !in_array($contextValue, (array)$value),
            'contains' => str_contains($contextValue, $value),
            'starts_with' => str_starts_with($contextValue, $value),
            'ends_with' => str_ends_with($contextValue, $value),
            default => false
        };
    }

    public function transform(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'resource' => $this->resource,
            'action' => $this->action,
            'conditions' => $this->conditions,
            'status' => $this->status,
            'roles_count' => $this->roles()->count(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s')
        ];
    }
}
```

### 用户权限扩展

```php
// 在 User 模型中添加权限相关方法
class User extends Model
{
    // ... 其他代码

    /**
     * 用户角色关系
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * 用户直接权限关系
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
                    ->withPivot('granted');
    }

    /**
     * 获取用户的所有权限
     */
    public function getAllPermissions(): Collection
    {
        $permissions = collect();
        
        // 从角色获取权限
        foreach ($this->roles as $role) {
            $permissions = $permissions->merge($role->getAllPermissions());
        }
        
        // 获取用户直接权限
        $directPermissions = $this->permissions()->wherePivot('granted', 1)->get();
        $permissions = $permissions->merge($directPermissions);
        
        // 移除被拒绝的权限
        $deniedPermissions = $this->permissions()->wherePivot('granted', 0)->pluck('name');
        $permissions = $permissions->reject(function ($permission) use ($deniedPermissions) {
            return $deniedPermissions->contains($permission->name);
        });
        
        return $permissions->unique('id');
    }

    /**
     * 检查用户是否有指定权限
     */
    public function hasPermission(string $permission, array $context = []): bool
    {
        $userPermission = $this->getAllPermissions()->firstWhere('name', $permission);
        
        if (!$userPermission) {
            return false;
        }
        
        // 检查权限条件
        return $userPermission->checkConditions($context);
    }

    /**
     * 检查用户是否有任一权限
     */
    public function hasAnyPermission(array $permissions, array $context = []): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $context)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 检查用户是否有所有权限
     */
    public function hasAllPermissions(array $permissions, array $context = []): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission, $context)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 检查用户是否有指定角色
     */
    public function hasRole(string $role): bool
    {
        return $this->roles->contains('name', $role);
    }

    /**
     * 检查用户是否有任一角色
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles->pluck('name')->intersect($roles)->isNotEmpty();
    }

    /**
     * 分配角色给用户
     */
    public function assignRole(string|int $role): void
    {
        if (is_string($role)) {
            $roleModel = Role::where('name', $role)->first();
        } else {
            $roleModel = Role::find($role);
        }
        
        if ($roleModel && !$this->hasRole($roleModel->name)) {
            $this->roles()->attach($roleModel->id);
        }
    }

    /**
     * 移除用户角色
     */
    public function removeRole(string|int $role): void
    {
        if (is_string($role)) {
            $roleModel = Role::where('name', $role)->first();
        } else {
            $roleModel = Role::find($role);
        }
        
        if ($roleModel) {
            $this->roles()->detach($roleModel->id);
        }
    }

    /**
     * 直接授予权限给用户
     */
    public function grantPermission(string|int $permission): void
    {
        if (is_string($permission)) {
            $permissionModel = Permission::where('name', $permission)->first();
        } else {
            $permissionModel = Permission::find($permission);
        }
        
        if ($permissionModel) {
            $this->permissions()->syncWithoutDetaching([
                $permissionModel->id => ['granted' => 1]
            ]);
        }
    }

    /**
     * 拒绝用户权限
     */
    public function denyPermission(string|int $permission): void
    {
        if (is_string($permission)) {
            $permissionModel = Permission::where('name', $permission)->first();
        } else {
            $permissionModel = Permission::find($permission);
        }
        
        if ($permissionModel) {
            $this->permissions()->syncWithoutDetaching([
                $permissionModel->id => ['granted' => 0]
            ]);
        }
    }

    /**
     * 获取用户权限数组（用于缓存）
     */
    public function getPermissions(): array
    {
        return Cache::remember("user_permissions:{$this->id}", 3600, function () {
            return $this->getAllPermissions()->pluck('name')->toArray();
        });
    }

    /**
     * 清除用户权限缓存
     */
    public function clearPermissionCache(): void
    {
        Cache::forget("user_permissions:{$this->id}");
    }
}
```

## 权限服务

### 权限管理服务

```php
class PermissionService
{
    /**
     * 创建权限
     */
    public function createPermission(array $data): Permission
    {
        $permission = Permission::create([
            'name' => $data['name'],
            'label' => $data['label'] ?? null,
            'description' => $data['description'] ?? null,
            'resource' => $data['resource'] ?? null,
            'action' => $data['action'] ?? null,
            'conditions' => $data['conditions'] ?? null,
            'status' => $data['status'] ?? 1
        ]);

        // 清除相关缓存
        $this->clearPermissionCache();

        return $permission;
    }

    /**
     * 批量创建资源权限
     */
    public function createResourcePermissions(string $resource, array $actions = ['create', 'read', 'update', 'delete']): array
    {
        $permissions = [];

        foreach ($actions as $action) {
            $name = "{$resource}.{$action}";
            $label = ucfirst($action) . ' ' . ucfirst($resource);

            $permission = Permission::firstOrCreate(
                ['name' => $name],
                [
                    'label' => $label,
                    'resource' => $resource,
                    'action' => $action,
                    'status' => 1
                ]
            );

            $permissions[] = $permission;
        }

        $this->clearPermissionCache();

        return $permissions;
    }

    /**
     * 创建角色
     */
    public function createRole(array $data): Role
    {
        // 计算角色层级
        $level = 0;
        if (!empty($data['parent_id'])) {
            $parent = Role::find($data['parent_id']);
            if ($parent) {
                $level = $parent->level + 1;
            }
        }

        $role = Role::create([
            'name' => $data['name'],
            'label' => $data['label'] ?? null,
            'description' => $data['description'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'level' => $level,
            'status' => $data['status'] ?? 1
        ]);

        $this->clearPermissionCache();

        return $role;
    }

    /**
     * 为角色分配权限
     */
    public function assignPermissionsToRole(int $roleId, array $permissionIds): void
    {
        $role = Role::find($roleId);
        if (!$role) {
            throw new ExceptionBusiness('角色不存在');
        }

        $role->permissions()->sync($permissionIds);
        $this->clearPermissionCache();
    }

    /**
     * 为用户分配角色
     */
    public function assignRolesToUser(int $userId, array $roleIds): void
    {
        $user = User::find($userId);
        if (!$user) {
            throw new ExceptionBusiness('用户不存在');
        }

        $user->roles()->sync($roleIds);
        $user->clearPermissionCache();
    }

    /**
     * 为用户直接分配权限
     */
    public function grantPermissionToUser(int $userId, int $permissionId, bool $granted = true): void
    {
        $user = User::find($userId);
        if (!$user) {
            throw new ExceptionBusiness('用户不存在');
        }

        $permission = Permission::find($permissionId);
        if (!$permission) {
            throw new ExceptionBusiness('权限不存在');
        }

        $user->permissions()->syncWithoutDetaching([
            $permissionId => ['granted' => $granted ? 1 : 0]
        ]);

        $user->clearPermissionCache();
    }

    /**
     * 获取用户权限树
     */
    public function getUserPermissionTree(int $userId): array
    {
        $user = User::with(['roles.permissions', 'permissions'])->find($userId);
        if (!$user) {
            throw new ExceptionBusiness('用户不存在');
        }

        $permissions = $user->getAllPermissions();
        
        return $this->buildPermissionTree($permissions);
    }

    /**
     * 构建权限树结构
     */
    private function buildPermissionTree(Collection $permissions): array
    {
        $tree = [];
        
        foreach ($permissions as $permission) {
            $resource = $permission->resource ?: 'other';
            
            if (!isset($tree[$resource])) {
                $tree[$resource] = [
                    'resource' => $resource,
                    'label' => ucfirst($resource),
                    'permissions' => []
                ];
            }
            
            $tree[$resource]['permissions'][] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'label' => $permission->label,
                'action' => $permission->action
            ];
        }
        
        return array_values($tree);
    }

    /**
     * 同步角色权限
     */
    public function syncRolePermissions(): void
    {
        // 从代码中自动发现权限并同步到数据库
        $discoveredPermissions = $this->discoverPermissions();
        
        foreach ($discoveredPermissions as $permissionData) {
            Permission::firstOrCreate(
                ['name' => $permissionData['name']],
                $permissionData
            );
        }
    }

    /**
     * 从代码中发现权限
     */
    private function discoverPermissions(): array
    {
        // 这里可以实现从控制器、路由注解等地方自动发现权限
        // 简化示例
        return [
            [
                'name' => 'users.create',
                'label' => '创建用户',
                'resource' => 'users',
                'action' => 'create'
            ],
            [
                'name' => 'users.read',
                'label' => '查看用户',
                'resource' => 'users',
                'action' => 'read'
            ],
            // ... 更多权限
        ];
    }

    /**
     * 清除权限缓存
     */
    public function clearPermissionCache(): void
    {
        Cache::tags(['permissions'])->flush();
        
        // 清除所有用户权限缓存
        $userIds = User::pluck('id');
        foreach ($userIds as $userId) {
            Cache::forget("user_permissions:{$userId}");
        }
    }
}
```

## 权限中间件

### 权限验证中间件

```php
class PermissionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $auth = $request->getAttribute('auth');
        
        if (!$auth) {
            throw new ExceptionBusiness('未认证', 401);
        }

        // 获取路由信息
        $route = RouteContext::fromRequest($request)->getRoute();
        if (!$route) {
            return $handler->handle($request);
        }

        // 检查路由权限要求
        $requiredPermissions = $this->extractPermissionsFromRoute($route);
        
        if (empty($requiredPermissions)) {
            return $handler->handle($request);
        }

        // 获取用户
        $user = User::find($auth['user_id']);
        if (!$user) {
            throw new ExceptionBusiness('用户不存在', 404);
        }

        // 检查权限
        $context = $this->buildPermissionContext($request, $user);
        
        if (!$this->checkPermissions($user, $requiredPermissions, $context)) {
            throw new ExceptionBusiness('权限不足', 403);
        }

        return $handler->handle($request);
    }

    /**
     * 从路由中提取权限要求
     */
    private function extractPermissionsFromRoute($route): array
    {
        $permissions = [];
        
        // 从路由名称推断权限
        $routeName = $route->getName();
        if ($routeName) {
            $permissions[] = str_replace(':', '.', $routeName);
        }

        // 从控制器方法的注解中获取权限（如果有实现）
        // $controllerPermissions = $this->extractFromController($route);
        // $permissions = array_merge($permissions, $controllerPermissions);

        return $permissions;
    }

    /**
     * 构建权限验证上下文
     */
    private function buildPermissionContext(ServerRequestInterface $request, User $user): array
    {
        $context = [
            'user_id' => $user->id,
            'user_role' => $user->roles->first()?->name,
            'ip_address' => $this->getClientIp($request),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'method' => $request->getMethod(),
            'uri' => (string)$request->getUri()
        ];

        // 添加路由参数到上下文
        $route = RouteContext::fromRequest($request)->getRoute();
        if ($route) {
            $context = array_merge($context, $route->getArguments());
        }

        return $context;
    }

    /**
     * 检查用户权限
     */
    private function checkPermissions(User $user, array $requiredPermissions, array $context): bool
    {
        foreach ($requiredPermissions as $permission) {
            if (!$user->hasPermission($permission, $context)) {
                return false;
            }
        }

        return true;
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
}
```

### 注解权限中间件

```php
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class RequirePermission
{
    public function __construct(
        public string|array $permissions,
        public string $operator = 'and' // 'and' 或 'or'
    ) {}
}

class AnnotationPermissionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $auth = $request->getAttribute('auth');
        
        if (!$auth) {
            throw new ExceptionBusiness('未认证', 401);
        }

        // 获取控制器和方法
        $route = RouteContext::fromRequest($request)->getRoute();
        if (!$route) {
            return $handler->handle($request);
        }

        $callable = $route->getCallable();
        if (!is_array($callable) || count($callable) !== 2) {
            return $handler->handle($request);
        }

        [$controller, $method] = $callable;
        
        // 获取权限注解
        $requiredPermissions = $this->getPermissionsFromAnnotations($controller, $method);
        
        if (empty($requiredPermissions)) {
            return $handler->handle($request);
        }

        // 验证权限
        $user = User::find($auth['user_id']);
        if (!$user) {
            throw new ExceptionBusiness('用户不存在', 404);
        }

        if (!$this->validatePermissions($user, $requiredPermissions, $request)) {
            throw new ExceptionBusiness('权限不足', 403);
        }

        return $handler->handle($request);
    }

    /**
     * 从注解中获取权限要求
     */
    private function getPermissionsFromAnnotations(string $controller, string $method): array
    {
        $permissions = [];

        try {
            $reflectionClass = new ReflectionClass($controller);
            $reflectionMethod = $reflectionClass->getMethod($method);

            // 获取类级别的权限注解
            $classAttributes = $reflectionClass->getAttributes(RequirePermission::class);
            foreach ($classAttributes as $attribute) {
                $instance = $attribute->newInstance();
                $permissions[] = [
                    'permissions' => (array)$instance->permissions,
                    'operator' => $instance->operator
                ];
            }

            // 获取方法级别的权限注解
            $methodAttributes = $reflectionMethod->getAttributes(RequirePermission::class);
            foreach ($methodAttributes as $attribute) {
                $instance = $attribute->newInstance();
                $permissions[] = [
                    'permissions' => (array)$instance->permissions,
                    'operator' => $instance->operator
                ];
            }

        } catch (ReflectionException $e) {
            // 忽略反射异常
        }

        return $permissions;
    }

    /**
     * 验证权限
     */
    private function validatePermissions(User $user, array $permissionGroups, ServerRequestInterface $request): bool
    {
        $context = $this->buildContext($request, $user);

        foreach ($permissionGroups as $group) {
            if (!$this->validatePermissionGroup($user, $group, $context)) {
                return false;
            }
        }

        return true;
    }

    private function validatePermissionGroup(User $user, array $group, array $context): bool
    {
        $permissions = $group['permissions'];
        $operator = $group['operator'];

        if ($operator === 'or') {
            return $user->hasAnyPermission($permissions, $context);
        } else {
            return $user->hasAllPermissions($permissions, $context);
        }
    }

    private function buildContext(ServerRequestInterface $request, User $user): array
    {
        $context = [
            'user_id' => $user->id,
            'user_role' => $user->roles->first()?->name,
            'method' => $request->getMethod()
        ];

        // 添加路由参数
        $route = RouteContext::fromRequest($request)->getRoute();
        if ($route) {
            $context = array_merge($context, $route->getArguments());
        }

        return $context;
    }
}
```

## 权限控制器

### 角色管理控制器

```php
#[Resource(app: 'admin', route: '/admin/roles', name: 'admin.roles')]
class RoleController extends Resources
{
    protected string $model = Role::class;
    private PermissionService $permissionService;

    public function __construct()
    {
        $this->permissionService = new PermissionService();
    }

    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'name' => [
                ['required', '角色名称不能为空'],
                ['regex', '/^[a-zA-Z0-9_-]+$/', '角色名称只能包含字母、数字、下划线和连字符']
            ],
            'label' => [
                ['required', '角色标签不能为空'],
                ['lengthMax', 100, '角色标签不能超过100个字符']
            ],
            'description' => [
                ['lengthMax', 500, '描述不能超过500个字符']
            ]
        ];
    }

    public function format(Data $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'name' => $data->name,
            'label' => $data->label,
            'description' => $data->description ?? '',
            'parent_id' => $data->parent_id ?? null,
            'status' => (int)($data->status ?? 1)
        ];
    }

    /**
     * 分配权限给角色
     */
    #[Action(['POST'], '/{id}/permissions', name: 'assignPermissions')]
    #[RequirePermission('roles.manage')]
    public function assignPermissions(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $roleId = (int)$args['id'];
        $data = $request->getParsedBody();

        if (empty($data['permission_ids']) || !is_array($data['permission_ids'])) {
            throw new ExceptionBusiness('权限ID列表不能为空', 400);
        }

        $this->permissionService->assignPermissionsToRole($roleId, $data['permission_ids']);

        return send($response, '权限分配成功');
    }

    /**
     * 获取角色权限
     */
    #[Action(['GET'], '/{id}/permissions', name: 'getPermissions')]
    #[RequirePermission('roles.read')]
    public function getPermissions(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $roleId = (int)$args['id'];
        
        $role = Role::with('permissions')->find($roleId);
        if (!$role) {
            throw new ExceptionNotFound('角色不存在');
        }

        return send($response, '获取成功', [
            'role' => $role->transform(),
            'permissions' => $role->permissions->map(fn($p) => $p->transform()),
            'all_permissions' => $role->getAllPermissions()->map(fn($p) => $p->transform())
        ]);
    }
}
```

### 权限管理控制器

```php
#[Resource(app: 'admin', route: '/admin/permissions', name: 'admin.permissions')]
class PermissionController extends Resources
{
    protected string $model = Permission::class;
    private PermissionService $permissionService;

    public function __construct()
    {
        $this->permissionService = new PermissionService();
    }

    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'name' => [
                ['required', '权限名称不能为空'],
                ['regex', '/^[a-zA-Z0-9_.-]+$/', '权限名称只能包含字母、数字、下划线、点和连字符']
            ],
            'label' => [
                ['required', '权限标签不能为空']
            ],
            'resource' => [
                ['regex', '/^[a-zA-Z0-9_-]+$/', '资源名称格式不正确']
            ],
            'action' => [
                ['regex', '/^[a-zA-Z0-9_-]+$/', '操作名称格式不正确']
            ]
        ];
    }

    public function format(Data $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'name' => $data->name,
            'label' => $data->label,
            'description' => $data->description ?? '',
            'resource' => $data->resource ?? null,
            'action' => $data->action ?? null,
            'conditions' => $data->conditions ?? null,
            'status' => (int)($data->status ?? 1)
        ];
    }

    /**
     * 批量创建资源权限
     */
    #[Action(['POST'], '/batch/resource', name: 'createResourcePermissions')]
    #[RequirePermission('permissions.create')]
    public function createResourcePermissions(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $data = $request->getParsedBody();

        if (empty($data['resource'])) {
            throw new ExceptionBusiness('资源名称不能为空', 400);
        }

        $actions = $data['actions'] ?? ['create', 'read', 'update', 'delete'];
        
        $permissions = $this->permissionService->createResourcePermissions($data['resource'], $actions);

        return send($response, '批量创建成功', [
            'permissions' => array_map(fn($p) => $p->transform(), $permissions)
        ]);
    }

    /**
     * 同步权限
     */
    #[Action(['POST'], '/sync', name: 'syncPermissions')]
    #[RequirePermission('permissions.manage')]
    public function syncPermissions(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $this->permissionService->syncRolePermissions();

        return send($response, '权限同步成功');
    }

    /**
     * 获取权限树
     */
    #[Action(['GET'], '/tree', name: 'getPermissionTree')]
    #[RequirePermission('permissions.read')]
    public function getPermissionTree(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $permissions = Permission::where('status', 1)->get();
        $tree = $this->buildPermissionTree($permissions);

        return send($response, '获取成功', ['tree' => $tree]);
    }

    private function buildPermissionTree(Collection $permissions): array
    {
        $tree = [];
        
        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->name);
            $current = &$tree;
            
            foreach ($parts as $part) {
                if (!isset($current[$part])) {
                    $current[$part] = [
                        'name' => $part,
                        'children' => [],
                        'permissions' => []
                    ];
                }
                $current = &$current[$part]['children'];
            }
            
            // 添加权限到叶子节点
            $leafNode = &$tree;
            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    $leafNode[$part]['permissions'][] = $permission->transform();
                } else {
                    $leafNode = &$leafNode[$part]['children'];
                }
            }
        }
        
        return $this->convertTreeToArray($tree);
    }

    private function convertTreeToArray(array $tree): array
    {
        $result = [];
        
        foreach ($tree as $key => $node) {
            $result[] = [
                'name' => $key,
                'children' => $this->convertTreeToArray($node['children']),
                'permissions' => $node['permissions']
            ];
        }
        
        return $result;
    }
}
```

### 用户权限控制器

```php
class UserPermissionController
{
    private PermissionService $permissionService;

    public function __construct()
    {
        $this->permissionService = new PermissionService();
    }

    /**
     * 获取用户权限
     */
    #[RequirePermission('users.read')]
    public function getUserPermissions(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $userId = (int)$args['id'];
        
        $tree = $this->permissionService->getUserPermissionTree($userId);

        return send($response, '获取成功', ['permissions' => $tree]);
    }

    /**
     * 分配角色给用户
     */
    #[RequirePermission('users.manage')]
    public function assignRoles(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $userId = (int)$args['id'];
        $data = $request->getParsedBody();

        if (empty($data['role_ids']) || !is_array($data['role_ids'])) {
            throw new ExceptionBusiness('角色ID列表不能为空', 400);
        }

        $this->permissionService->assignRolesToUser($userId, $data['role_ids']);

        return send($response, '角色分配成功');
    }

    /**
     * 直接授予权限给用户
     */
    #[RequirePermission('users.manage')]
    public function grantPermission(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $userId = (int)$args['id'];
        $data = $request->getParsedBody();

        if (empty($data['permission_id'])) {
            throw new ExceptionBusiness('权限ID不能为空', 400);
        }

        $granted = $data['granted'] ?? true;

        $this->permissionService->grantPermissionToUser($userId, $data['permission_id'], $granted);

        return send($response, $granted ? '权限授予成功' : '权限拒绝成功');
    }
}
```

## 最佳实践

### 1. 权限设计原则

```php
// ✅ 推荐：层次化权限设计
'permissions' => [
    'users.create',      // 创建用户
    'users.read',        // 查看用户
    'users.update',      // 更新用户
    'users.delete',      // 删除用户
    'users.manage',      // 管理用户（包含所有操作）
]

// ✅ 推荐：资源级权限控制
'conditions' => [
    [
        'field' => 'user_id',
        'operator' => 'eq',
        'value' => '{{current_user_id}}'
    ]
]
```

### 2. 角色继承

```php
// ✅ 推荐：合理的角色层次
class RoleHierarchy
{
    public const ROLES = [
        'super_admin' => ['parent' => null, 'level' => 0],
        'admin' => ['parent' => 'super_admin', 'level' => 1],
        'manager' => ['parent' => 'admin', 'level' => 2],
        'user' => ['parent' => 'manager', 'level' => 3],
        'guest' => ['parent' => null, 'level' => 4]
    ];
}
```

### 3. 权限缓存

```php
// ✅ 推荐：多层缓存策略
class PermissionCache
{
    public function getUserPermissions(int $userId): array
    {
        // L1: 应用缓存
        static $cache = [];
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }
        
        // L2: Redis 缓存
        $permissions = Cache::remember("user_permissions:{$userId}", 3600, function () use ($userId) {
            $user = User::find($userId);
            return $user ? $user->getAllPermissions()->pluck('name')->toArray() : [];
        });
        
        $cache[$userId] = $permissions;
        return $permissions;
    }
}
```

### 4. 权限验证

```php
// ✅ 推荐：使用注解权限控制
#[RequirePermission(['posts.create', 'posts.publish'], operator: 'or')]
public function createPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    // 创建文章逻辑
}

// ✅ 推荐：上下文相关的权限检查
public function updatePost(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
{
    $postId = $args['id'];
    $user = $request->getAttribute('user');
    
    // 检查是否可以编辑这篇文章
    if (!$user->hasPermission('posts.update', ['post_id' => $postId])) {
        throw new ExceptionBusiness('权限不足', 403);
    }
    
    // 更新文章逻辑
}
```

### 5. 安全考虑

```php
// ✅ 推荐：权限操作审计
class PermissionAudit
{
    public function logPermissionChange(int $userId, string $action, array $details): void
    {
        PermissionLog::create([
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip_address' => request()->ip(),
            'created_at' => now()
        ]);
    }
}

// ✅ 推荐：敏感操作二次验证
public function deleteUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
{
    // 检查基本权限
    if (!$user->hasPermission('users.delete')) {
        throw new ExceptionBusiness('权限不足', 403);
    }
    
    // 敏感操作需要二次验证
    if (!$this->verifySecondaryAuth($request)) {
        throw new ExceptionBusiness('需要二次验证', 428);
    }
    
    // 删除用户逻辑
}
```

通过遵循这些最佳实践，您可以构建出安全、灵活、高性能的 DuxLite 授权系统。授权系统是应用安全的核心组件，需要仔细设计权限模型和验证策略。