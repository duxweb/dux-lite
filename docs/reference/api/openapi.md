# OpenAPI 文档生成

DuxLite 提供了完整的 OpenAPI 3.0 文档自动生成系统，通过注解定义 API 接口信息，自动生成标准的 OpenAPI 文档。

## 基本概念

### 文档生成流程

1. **注解定义** - 在控制器方法上使用注解描述 API
2. **自动扫描** - 框架扫描所有控制器注解
3. **生成文档** - 自动生成 OpenAPI 3.0 JSON 文档
4. **接口展示** - 可集成 Swagger UI 展示 API 文档

### 核心注解

- `#[Docs]` - 定义控制器分组信息
- `#[Api]` - 定义 API 接口基本信息
- `#[Query]` - 定义查询参数
- `#[Params]` - 定义路径参数
- `#[Header]` - 定义请求头参数
- `#[Payload]` - 定义请求体数据
- `#[Result]` - 定义返回数据结构
- `#[ResultStatus]` - 定义自定义状态码

## 基础用法

### 1. 控制器分组

```php
use Core\Docs\Attribute\Docs;

#[Docs(name: '用户管理', desc: '用户相关接口')]
class UserController
{
    // 控制器方法
}
```

### 2. API 接口定义

```php
use Core\Docs\Attribute\Api;
use Core\Docs\Enum\PayloadTypeEnum;
use Core\Docs\Enum\ResultMimeEnum;
use Core\Docs\Enum\ResultTypeEnum;

#[Api(
    name: '获取用户信息',
    payloadType: PayloadTypeEnum::JSON,
    resultMime: ResultMimeEnum::JSON,
    resultType: ResultTypeEnum::MESSAGE
)]
#[Route('/users/{id}', ['GET'])]
public function show($request, $response, $args)
{
    // 实现逻辑
}
```

### 3. 参数定义

```php
use Core\Docs\Attribute\Query;
use Core\Docs\Attribute\Params;
use Core\Docs\Attribute\Header;
use Core\Docs\Enum\FieldEnum;

// 查询参数
#[Query(field: 'page', name: '页码', type: FieldEnum::INTEGER, required: false, example: 1)]
#[Query(field: 'limit', name: '每页条数', type: FieldEnum::INTEGER, required: false, example: 20)]

// 路径参数
#[Params(field: 'id', name: '用户ID', type: FieldEnum::INTEGER, required: true, example: 123)]

// 请求头参数
#[Header(field: 'Authorization', name: '认证令牌', type: FieldEnum::STRING, required: true)]
public function index($request, $response, $args)
{
    // 实现逻辑
}
```

### 4. 请求体定义

```php
use Core\Docs\Attribute\Payload;

#[Payload(field: 'username', name: '用户名', type: FieldEnum::STRING, required: true, example: 'admin')]
#[Payload(field: 'email', name: '邮箱', type: FieldEnum::STRING, required: true, example: 'admin@example.com')]
#[Payload(field: 'password', name: '密码', type: FieldEnum::STRING, required: true, example: '123456')]
#[Api(name: '创建用户')]
#[Route('/users', ['POST'])]
public function store($request, $response, $args)
{
    // 实现逻辑
}
```

### 5. 返回数据定义

```php
use Core\Docs\Attribute\Result;
use Core\Docs\Attribute\ResultData;
use Core\Docs\Attribute\ResultMeta;

// 使用 Result 定义简单返回
#[Result(field: 'id', name: '用户ID', type: FieldEnum::INTEGER, example: 123)]
#[Result(field: 'username', name: '用户名', type: FieldEnum::STRING, example: 'admin')]
#[Result(field: 'email', name: '邮箱', type: FieldEnum::STRING, example: 'admin@example.com')]

// 使用 ResultData 定义 data 字段内容
#[ResultData(field: 'id', name: '用户ID', type: FieldEnum::INTEGER, example: 123)]
#[ResultData(field: 'username', name: '用户名', type: FieldEnum::STRING, example: 'admin')]

// 使用 ResultMeta 定义 meta 字段内容
#[ResultMeta(field: 'total', name: '总数', type: FieldEnum::INTEGER, example: 100)]
#[ResultMeta(field: 'page', name: '当前页', type: FieldEnum::INTEGER, example: 1)]
```

## 高级用法

### 嵌套对象定义

```php
use Core\Docs\Attribute\ResultData;

// 定义嵌套对象
#[ResultData(
    field: 'user', 
    name: '用户信息', 
    type: FieldEnum::OBJECT,
    children: [
        ['field' => 'id', 'name' => '用户ID', 'type' => FieldEnum::INTEGER, 'example' => 123],
        ['field' => 'username', 'name' => '用户名', 'type' => FieldEnum::STRING, 'example' => 'admin'],
        ['field' => 'email', 'name' => '邮箱', 'type' => FieldEnum::STRING, 'example' => 'admin@example.com']
    ]
)]

// 定义数组对象
#[ResultData(
    field: 'users', 
    name: '用户列表', 
    type: FieldEnum::ARRAY,
    children: [
        ['field' => 'id', 'name' => '用户ID', 'type' => FieldEnum::INTEGER, 'example' => 123],
        ['field' => 'username', 'name' => '用户名', 'type' => FieldEnum::STRING, 'example' => 'admin']
    ]
)]
```

### 自定义状态码

```php
use Core\Docs\Attribute\ResultStatus;

#[ResultStatus(code: 404, name: '用户不存在', desc: '指定ID的用户不存在')]
#[ResultStatus(code: 422, name: '数据验证失败', desc: '提交的数据不符合验证规则')]
#[Api(name: '更新用户信息')]
public function update($request, $response, $args)
{
    // 实现逻辑
}
```

### 根级字段定义

```php
// 将字段定义到根级别而不是 data 内
#[ResultData(field: 'success', name: '操作状态', type: FieldEnum::BOOLEAN, root: true, example: true)]
#[ResultData(field: 'timestamp', name: '时间戳', type: FieldEnum::INTEGER, root: true, example: 1642572000)]
```

## 数据类型

### FieldEnum 枚举值

```php
use Core\Docs\Enum\FieldEnum;

FieldEnum::STRING    // 字符串
FieldEnum::INTEGER   // 整数
FieldEnum::NUMBER    // 数字（浮点数）
FieldEnum::BOOLEAN   // 布尔值
FieldEnum::ARRAY     // 数组
FieldEnum::OBJECT    // 对象
```

### PayloadTypeEnum 请求类型

```php
use Core\Docs\Enum\PayloadTypeEnum;

PayloadTypeEnum::JSON           // application/json
PayloadTypeEnum::FORM           // application/x-www-form-urlencoded
PayloadTypeEnum::MULTIPART      // multipart/form-data
```

### ResultMimeEnum 返回类型

```php
use Core\Docs\Enum\ResultMimeEnum;

ResultMimeEnum::JSON    // application/json
ResultMimeEnum::XML     // application/xml
ResultMimeEnum::HTML    // text/html
ResultMimeEnum::TEXT    // text/plain
```

### ResultTypeEnum 返回格式

```php
use Core\Docs\Enum\ResultTypeEnum;

ResultTypeEnum::MESSAGE   // 标准消息格式 {code, message, data, meta}
ResultTypeEnum::DEFAULT   // 自定义格式
```

## 文档生成

### 生成命令

```bash
# 生成 OpenAPI 文档
php bin/cli docs:build

# 指定主机和端口
php bin/cli docs:build --host=api.example.com --port=443

# 指定版本号
php bin/cli docs:build --ver=2.0.0
```

### 输出文件

生成的文档保存在 `data/docs/openapi.json`，可以使用 Swagger UI 或其他工具展示。

## 完整示例

```php
<?php

use Core\Docs\Attribute\Api;
use Core\Docs\Attribute\Docs;
use Core\Docs\Attribute\Query;
use Core\Docs\Attribute\Params;
use Core\Docs\Attribute\Payload;
use Core\Docs\Attribute\ResultData;
use Core\Docs\Attribute\ResultMeta;
use Core\Docs\Attribute\ResultStatus;
use Core\Docs\Enum\FieldEnum;
use Core\Docs\Enum\PayloadTypeEnum;
use Core\Docs\Enum\ResultTypeEnum;
use Core\Route\Attribute\Route;

#[Docs(name: '用户管理', desc: '用户相关的增删改查接口')]
class UserController extends Resources
{
    #[Query(field: 'page', name: '页码', type: FieldEnum::INTEGER, required: false, example: 1)]
    #[Query(field: 'limit', name: '每页条数', type: FieldEnum::INTEGER, required: false, example: 20)]
    #[Query(field: 'keyword', name: '搜索关键词', type: FieldEnum::STRING, required: false)]
    #[ResultData(field: 'id', name: '用户ID', type: FieldEnum::INTEGER, example: 123)]
    #[ResultData(field: 'username', name: '用户名', type: FieldEnum::STRING, example: 'admin')]
    #[ResultData(field: 'email', name: '邮箱', type: FieldEnum::STRING, example: 'admin@example.com')]
    #[ResultData(field: 'created_at', name: '创建时间', type: FieldEnum::STRING, example: '2024-01-01 12:00:00')]
    #[ResultMeta(field: 'total', name: '总数', type: FieldEnum::INTEGER, example: 100)]
    #[ResultMeta(field: 'page', name: '当前页', type: FieldEnum::INTEGER, example: 1)]
    #[Api(
        name: '获取用户列表',
        resultType: ResultTypeEnum::MESSAGE,
        resultExample: [
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                ['id' => 1, 'username' => 'admin', 'email' => 'admin@example.com'],
                ['id' => 2, 'username' => 'user', 'email' => 'user@example.com']
            ],
            'meta' => ['total' => 100, 'page' => 1]
        ]
    )]
    #[Route('/users', ['GET'])]
    public function index($request, $response, $args)
    {
        // 实现逻辑
    }

    #[Params(field: 'id', name: '用户ID', type: FieldEnum::INTEGER, required: true, example: 123)]
    #[ResultData(field: 'id', name: '用户ID', type: FieldEnum::INTEGER, example: 123)]
    #[ResultData(field: 'username', name: '用户名', type: FieldEnum::STRING, example: 'admin')]
    #[ResultData(field: 'email', name: '邮箱', type: FieldEnum::STRING, example: 'admin@example.com')]
    #[ResultStatus(code: 404, name: '用户不存在', desc: '指定ID的用户不存在')]
    #[Api(name: '获取用户详情')]
    #[Route('/users/{id}', ['GET'])]
    public function show($request, $response, $args)
    {
        // 实现逻辑
    }

    #[Payload(field: 'username', name: '用户名', type: FieldEnum::STRING, required: true, example: 'newuser')]
    #[Payload(field: 'email', name: '邮箱', type: FieldEnum::STRING, required: true, example: 'newuser@example.com')]
    #[Payload(field: 'password', name: '密码', type: FieldEnum::STRING, required: true, example: '123456')]
    #[ResultData(field: 'id', name: '用户ID', type: FieldEnum::INTEGER, example: 124)]
    #[ResultData(field: 'username', name: '用户名', type: FieldEnum::STRING, example: 'newuser')]
    #[ResultData(field: 'email', name: '邮箱', type: FieldEnum::STRING, example: 'newuser@example.com')]
    #[ResultStatus(code: 422, name: '数据验证失败', desc: '提交的数据不符合验证规则')]
    #[Api(
        name: '创建用户',
        payloadType: PayloadTypeEnum::JSON,
        payloadExample: [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => '123456'
        ]
    )]
    #[Route('/users', ['POST'])]
    public function store($request, $response, $args)
    {
        // 实现逻辑
    }
}
```

DuxLite 的 OpenAPI 文档生成系统通过注解驱动，自动生成标准的 API 文档，大大简化了文档维护工作。开发者只需要在代码中添加相应注解，即可自动生成完整的 API 文档。