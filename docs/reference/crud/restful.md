# RESTful设计

RESTful API 是现代 Web 应用的标准架构风格。DuxLite 通过资源控制器和路由注解，提供了完整的 RESTful API 设计和实现方案，遵循 REST 原则，提供统一、可预测的 API 接口。

## 基本概念

### REST 原则

**REST (Representational State Transfer)** 的核心设计原则：

1. **资源 (Resources)**：所有内容都是资源，每个资源都有唯一的标识符 (URI)
2. **统一接口 (Uniform Interface)**：使用标准的 HTTP 方法操作资源
3. **无状态 (Stateless)**：每次请求都包含完整的信息，服务器不保存客户端状态
4. **可缓存 (Cacheable)**：响应可以被缓存以提高性能
5. **分层系统 (Layered System)**：架构可以由多个层次组成
6. **按需代码 (Code on Demand)**：可选，服务器可以向客户端发送可执行代码

### HTTP 方法语义

| HTTP方法 | 语义 | 幂等性 | 安全性 | 用途 |
|----------|------|--------|--------|------|
| **GET** | 获取资源 | ✅ | ✅ | 查询单个或多个资源 |
| **POST** | 创建资源 | ❌ | ❌ | 创建新资源，非幂等操作 |
| **PUT** | 更新/替换资源 | ✅ | ❌ | 完整更新资源，幂等操作 |
| **PATCH** | 部分更新资源 | ❌ | ❌ | 部分更新资源字段 |
| **DELETE** | 删除资源 | ✅ | ❌ | 删除指定资源 |
| **HEAD** | 获取资源头信息 | ✅ | ✅ | 获取资源元数据 |
| **OPTIONS** | 获取支持的方法 | ✅ | ✅ | 获取资源支持的操作 |

## URL 设计规范

### 资源命名

```php
// ✅ 推荐：使用复数名词
GET    /users           // 获取用户列表
GET    /users/123       // 获取指定用户
POST   /users           // 创建新用户
PUT    /users/123       // 更新用户
DELETE /users/123       // 删除用户

// ✅ 推荐：嵌套资源表示关联关系
GET    /users/123/posts        // 获取用户的文章
POST   /users/123/posts        // 为用户创建文章
GET    /users/123/posts/456    // 获取用户的指定文章

// ❌ 避免：使用动词
GET    /getUsers        // 不要使用动词
POST   /createUser      // 不要使用动词
PUT    /updateUser/123  // 不要使用动词
```

### DuxLite 资源路由映射

```php
#[Resource(app: 'api', route: '/api/users', name: 'users')]
class UserController extends Resources
{
    // 自动生成以下路由：
}
```

| HTTP方法 | URL路径 | 控制器方法 | 路由名称 | 功能描述 |
|----------|---------|-----------|----------|----------|
| **GET** | `/api/users` | `many()` | `api.users.list` | 获取用户列表 |
| **GET** | `/api/users/{id}` | `one()` | `api.users.show` | 获取单个用户 |
| **POST** | `/api/users` | `create()` | `api.users.create` | 创建新用户 |
| **PUT** | `/api/users/{id}` | `edit()` | `api.users.edit` | 完整更新用户 |
| **PATCH** | `/api/users/{id}` | `store()` | `api.users.store` | 部分更新用户 |
| **DELETE** | `/api/users/{id}` | `del()` | `api.users.delete` | 删除单个用户 |
| **DELETE** | `/api/users` | `delMany()` | `api.users.deleteMany` | 批量删除用户 |

### 查询参数设计

```php
// 分页参数
GET /api/users?page=1&limit=20

// 排序参数
GET /api/users?sort=created_at&order=desc
GET /api/users?sort=name:asc,created_at:desc

// 筛选参数
GET /api/users?status=active&role=admin
GET /api/users?created_at[gte]=2023-01-01&created_at[lt]=2024-01-01

// 搜索参数
GET /api/users?search=john&fields=username,email

// 字段选择
GET /api/users?fields=id,username,email
GET /api/users?exclude=password,token

// 关联数据
GET /api/users?include=profile,roles
GET /api/users/123?with=posts.comments
```

## 状态码规范

### 成功响应 (2xx)

```php
class UserController extends Resources
{
    public function many(): ResponseInterface
    {
        // 200 OK - 请求成功
        return send($response, '获取成功', $users, $meta);
    }

    public function create(): ResponseInterface
    {
        // 201 Created - 资源创建成功
        return send($response, '创建成功', $user, null, 201);
    }

    public function edit(): ResponseInterface
    {
        // 200 OK - 更新成功
        return send($response, '更新成功', $user);
    }

    public function del(): ResponseInterface
    {
        // 204 No Content - 删除成功，无返回内容
        return $response->withStatus(204);
    }
}
```

### 客户端错误 (4xx)

```php
class UserController extends Resources
{
    public function one(): ResponseInterface
    {
        $user = User::find($id);
        
        if (!$user) {
            // 404 Not Found - 资源不存在
            throw new ExceptionBusiness('用户不存在', 404);
        }
        
        return send($response, '获取成功', $user);
    }

    public function create(): ResponseInterface
    {
        $data = $request->getParsedBody();
        
        // 400 Bad Request - 请求参数错误
        if (empty($data['username'])) {
            throw new ExceptionBusiness('用户名不能为空', 400);
        }
        
        // 409 Conflict - 资源冲突
        if (User::where('username', $data['username'])->exists()) {
            throw new ExceptionBusiness('用户名已存在', 409);
        }
        
        // 422 Unprocessable Entity - 验证失败
        $validator = $this->validate($data);
        if ($validator->fails()) {
            throw new ExceptionBusiness('数据验证失败', 422, $validator->errors());
        }
        
        $user = User::create($data);
        return send($response, '创建成功', $user, null, 201);
    }

    public function edit(): ResponseInterface
    {
        $auth = $request->getAttribute('auth');
        
        // 403 Forbidden - 权限不足
        if (!$this->canEdit($auth, $user)) {
            throw new ExceptionBusiness('权限不足', 403);
        }
        
        // 更新逻辑...
    }
}
```

### 服务器错误 (5xx)

```php
class UserController extends Resources
{
    public function create(): ResponseInterface
    {
        try {
            DB::beginTransaction();
            
            $user = User::create($data);
            $user->profile()->create($profileData);
            
            DB::commit();
            
            return send($response, '创建成功', $user, null, 201);
            
        } catch (\\Exception $e) {
            DB::rollBack();
            
            // 500 Internal Server Error - 服务器内部错误
            error_log('用户创建失败: ' . $e->getMessage());
            throw new ExceptionBusiness('服务器内部错误', 500);
        }
    }
}
```

## 响应格式设计

### 统一响应结构

```php
// 成功响应格式
{
    "code": 200,
    "message": "请求成功",
    "data": {
        // 实际数据
    },
    "meta": {
        // 元数据（分页、统计等）
    },
    "timestamp": "2024-01-15T10:30:00Z"
}

// 错误响应格式
{
    "code": 400,
    "message": "请求参数错误",
    "errors": {
        "username": ["用户名不能为空"],
        "email": ["邮箱格式不正确"]
    },
    "timestamp": "2024-01-15T10:30:00Z"
}
```

### 列表响应设计

```php
class UserController extends Resources
{
    public function many(): ResponseInterface
    {
        $query = User::query();
        $this->applyFilters($query, $request);
        
        $users = $query->paginate(20);
        
        ["data" => $data, "meta" => $meta] = format_data($users, function($user) {
            return $user->transform();
        });
        
        return send($response, '获取成功', $data, [
            // 分页信息
            'pagination' => $meta,
            
            // 统计信息
            'statistics' => [
                'total_users' => User::count(),
                'active_users' => User::where('status', 1)->count(),
                'new_today' => User::whereDate('created_at', today())->count()
            ],
            
            // 筛选选项
            'filters' => [
                'status_options' => [
                    ['value' => 1, 'label' => '激活'],
                    ['value' => 0, 'label' => '禁用']
                ],
                'role_options' => Role::select('id as value', 'name as label')->get()
            ]
        ]);
    }
}
```

### 详情响应设计

```php
class UserController extends Resources
{
    public function one(): ResponseInterface
    {
        $user = User::with(['profile', 'roles'])->findOrFail($id);
        
        return send($response, '获取成功', $user->transform(), [
            // 关联数据
            'relationships' => [
                'posts_count' => $user->posts()->count(),
                'followers_count' => $user->followers()->count(),
                'following_count' => $user->following()->count()
            ],
            
            // 操作权限
            'permissions' => [
                'can_edit' => $this->canEdit($auth, $user),
                'can_delete' => $this->canDelete($auth, $user),
                'can_follow' => $this->canFollow($auth, $user)
            ],
            
            // 相关链接
            'links' => [
                'posts' => url("/api/users/{$user->id}/posts"),
                'followers' => url("/api/users/{$user->id}/followers"),
                'avatar_upload' => url("/api/users/{$user->id}/avatar")
            ]
        ]);
    }
}
```

## 资源关联设计

### 一对多关系

```php
// 获取用户的文章
#[Resource(app: 'api', route: '/api/users', name: 'users')]
class UserController extends Resources
{
    #[Action(['GET'], '/{id}/posts', name: 'posts')]
    public function getUserPosts(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $userId = $args['id'];
        $user = User::findOrFail($userId);
        
        $posts = $user->posts()
            ->with(['category', 'tags'])
            ->paginate(10);
        
        ["data" => $data, "meta" => $meta] = format_data($posts, function($post) {
            return $post->transform();
        });
        
        return send($response, '获取成功', $data, $meta);
    }

    #[Action(['POST'], '/{id}/posts', name: 'createPost')]
    public function createUserPost(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $userId = $args['id'];
        $user = User::findOrFail($userId);
        
        $data = $request->getParsedBody();
        $data['user_id'] = $userId;
        
        $post = Post::create($data);
        
        return send($response, '创建成功', $post->transform(), null, 201);
    }
}
```

### 多对多关系

```php
#[Resource(app: 'api', route: '/api/users', name: 'users')]
class UserController extends Resources
{
    // 获取用户的角色
    #[Action(['GET'], '/{id}/roles', name: 'roles')]
    public function getUserRoles(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = User::findOrFail($args['id']);
        $roles = $user->roles()->get();
        
        $data = $roles->map(function($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'assigned_at' => $role->pivot->created_at
            ];
        });
        
        return send($response, '获取成功', $data);
    }

    // 分配角色给用户
    #[Action(['POST'], '/{id}/roles', name: 'assignRoles')]
    public function assignRoles(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = User::findOrFail($args['id']);
        $data = $request->getParsedBody();
        $roleIds = $data['role_ids'] ?? [];
        
        // 验证角色是否存在
        $validRoles = Role::whereIn('id', $roleIds)->pluck('id')->toArray();
        
        // 同步角色关系
        $user->roles()->sync($validRoles);
        
        return send($response, '角色分配成功', [
            'assigned_roles' => $validRoles,
            'total_count' => count($validRoles)
        ]);
    }

    // 移除用户角色
    #[Action(['DELETE'], '/{id}/roles/{roleId}', name: 'removeRole')]
    public function removeRole(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = User::findOrFail($args['id']);
        $roleId = $args['roleId'];
        
        $user->roles()->detach($roleId);
        
        return $response->withStatus(204);
    }
}
```

## 高级 RESTful 设计模式

### 子资源操作

```php
#[Resource(app: 'api', route: '/api/posts', name: 'posts')]
class PostController extends Resources
{
    // 文章评论管理
    #[Action(['GET'], '/{id}/comments', name: 'comments')]
    public function getComments(): ResponseInterface
    {
        // GET /api/posts/123/comments
    }

    #[Action(['POST'], '/{id}/comments', name: 'createComment')]
    public function createComment(): ResponseInterface
    {
        // POST /api/posts/123/comments
    }

    // 文章标签管理
    #[Action(['GET'], '/{id}/tags', name: 'tags')]
    public function getTags(): ResponseInterface
    {
        // GET /api/posts/123/tags
    }

    #[Action(['PUT'], '/{id}/tags', name: 'updateTags')]
    public function updateTags(): ResponseInterface
    {
        // PUT /api/posts/123/tags
    }

    // 文章状态操作
    #[Action(['PUT'], '/{id}/publish', name: 'publish')]
    public function publish(): ResponseInterface
    {
        // PUT /api/posts/123/publish
    }

    #[Action(['PUT'], '/{id}/unpublish', name: 'unpublish')]
    public function unpublish(): ResponseInterface
    {
        // PUT /api/posts/123/unpublish
    }
}
```

### 批量操作

```php
#[Resource(app: 'api', route: '/api/users', name: 'users')]
class UserController extends Resources
{
    // 批量创建
    #[Action(['POST'], '/batch', name: 'batchCreate')]
    public function batchCreate(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = $request->getParsedBody();
        $users = $data['users'] ?? [];
        
        $results = [];
        $errors = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($users as $index => $userData) {
                try {
                    $user = User::create($userData);
                    $results[] = [
                        'index' => $index,
                        'id' => $user->id,
                        'status' => 'success'
                    ];
                } catch (\\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            if (empty($errors)) {
                DB::commit();
                return send($response, '批量创建成功', [
                    'created_count' => count($results),
                    'results' => $results
                ], null, 201);
            } else {
                DB::rollBack();
                return send($response, '批量创建部分失败', [
                    'success_count' => count($results),
                    'error_count' => count($errors),
                    'results' => $results,
                    'errors' => $errors
                ], null, 207); // 207 Multi-Status
            }
            
        } catch (\\Exception $e) {
            DB::rollBack();
            throw new ExceptionBusiness('批量创建失败', 500);
        }
    }

    // 批量更新
    #[Action(['PUT'], '/batch', name: 'batchUpdate')]
    public function batchUpdate(): ResponseInterface
    {
        // PUT /api/users/batch
        // Body: { "updates": [{"id": 1, "status": 1}, {"id": 2, "status": 0}] }
    }

    // 批量删除
    #[Action(['DELETE'], '/batch', name: 'batchDelete')]
    public function batchDelete(): ResponseInterface
    {
        // DELETE /api/users/batch
        // Body: { "ids": [1, 2, 3] }
    }
}
```

### 搜索和过滤

```php
#[Resource(app: 'api', route: '/api/products', name: 'products')]
class ProductController extends Resources
{
    #[Action(['GET'], '/search', name: 'search')]
    public function search(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $params = $request->getQueryParams();
        
        $query = Product::query();
        
        // 关键词搜索
        if (!empty($params['q'])) {
            $query->where(function($q) use ($params) {
                $q->where('name', 'like', "%{$params['q']}%")
                  ->orWhere('description', 'like', "%{$params['q']}%")
                  ->orWhere('sku', 'like', "%{$params['q']}%");
            });
        }
        
        // 分类筛选
        if (!empty($params['category'])) {
            $query->whereHas('category', function($q) use ($params) {
                $q->where('slug', $params['category']);
            });
        }
        
        // 价格范围
        if (!empty($params['price_min'])) {
            $query->where('price', '>=', $params['price_min']);
        }
        if (!empty($params['price_max'])) {
            $query->where('price', '<=', $params['price_max']);
        }
        
        // 标签筛选
        if (!empty($params['tags'])) {
            $tags = explode(',', $params['tags']);
            $query->whereHas('tags', function($q) use ($tags) {
                $q->whereIn('name', $tags);
            });
        }
        
        // 排序
        $sortField = $params['sort'] ?? 'created_at';
        $sortOrder = $params['order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);
        
        // 分页
        $products = $query->paginate($params['limit'] ?? 20);
        
        ["data" => $data, "meta" => $meta] = format_data($products, function($product) {
            return $product->transform();
        });
        
        return send($response, '搜索成功', $data, [
            'pagination' => $meta,
            'search_params' => $params,
            'facets' => [
                'categories' => $this->getCategoryFacets(),
                'price_ranges' => $this->getPriceRanges(),
                'available_tags' => $this->getAvailableTags()
            ]
        ]);
    }
}
```

## API 版本控制

### URL 版本控制

```php
// 版本 1
#[Resource(app: 'api', route: '/api/v1/users', name: 'users')]
class V1UserController extends Resources { }

// 版本 2
#[Resource(app: 'api', route: '/api/v2/users', name: 'users')]
class V2UserController extends Resources { }
```

### Header 版本控制

```php
#[Resource(app: 'api', route: '/api/users', name: 'users')]
class UserController extends Resources
{
    public function many(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $version = $request->getHeaderLine('API-Version') ?: 'v1';
        
        switch ($version) {
            case 'v1':
                return $this->getUsersV1();
            case 'v2':
                return $this->getUsersV2();
            default:
                throw new ExceptionBusiness('不支持的API版本', 400);
        }
    }
}
```

## 最佳实践

### 1. 资源设计原则

```php
// ✅ 推荐：资源导向设计
GET    /api/users/123/posts      // 获取用户的文章
POST   /api/users/123/posts      // 为用户创建文章
PUT    /api/posts/456/status     // 更新文章状态

// ❌ 避免：动作导向设计
POST   /api/getUserPosts         // 不要用动词
POST   /api/createPostForUser    // 不要用动词
POST   /api/updatePostStatus     // 不要用动词
```

### 2. 状态码使用

```php
// ✅ 推荐：合适的状态码
return send($response, '创建成功', $data, null, 201);  // 201 Created
return send($response, '更新成功', $data, null, 200);  // 200 OK
return $response->withStatus(204);                     // 204 No Content

// ❌ 避免：错误的状态码
return send($response, '创建成功', $data, null, 200);  // 应该用 201
return send($response, '删除成功', null, null, 200);   // 应该用 204
```

### 3. 错误处理

```php
// ✅ 推荐：详细的错误信息
throw new ExceptionBusiness('数据验证失败', 422, [
    'username' => ['用户名不能为空', '用户名至少3个字符'],
    'email' => ['邮箱格式不正确']
]);

// ❌ 避免：模糊的错误信息
throw new ExceptionBusiness('请求失败', 400);
```

### 4. 性能优化

```php
// ✅ 推荐：预加载关联数据
public function queryMany(Builder $query, ServerRequestInterface $request, array $args): void
{
    $query->with(['profile', 'roles']);
}

// ✅ 推荐：字段选择
public array $includesMany = ['id', 'username', 'email', 'created_at'];
public array $excludesMany = ['password', 'remember_token'];
```

通过遵循 RESTful 设计原则和 DuxLite 的资源控制器模式，您可以构建出规范、易用、可维护的 API 接口，为客户端应用提供一致的数据访问体验。