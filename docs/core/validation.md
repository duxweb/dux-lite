# 数据验证

DuxLite 提供了强大而灵活的数据验证系统，基于 [Valitron 验证库](https://github.com/vlucas/valitron)，并在此基础上增加了自动异常处理、类型安全返回和与框架的深度集成。

## 设计理念

DuxLite 验证系统采用**显式、安全、易用的数据验证**设计：

- **自动异常处理**：验证失败自动抛出结构化异常，无需手动检查
- **类型安全返回**：返回验证后的 Data 对象，支持属性访问和数组转换
- **丰富验证规则**：支持 30+ 内置验证规则，覆盖常见验证需求
- **国际化支持**：错误信息支持多语言翻译
- **框架深度集成**：与资源控制器、异常处理系统无缝集成

### 核心组件

- **Validator 类**：主要验证入口，提供 `parser()` 静态方法
- **Data 类**：验证后的数据容器，支持属性访问和数组操作
- **ExceptionValidator**：验证异常类，自动包含详细错误信息
- **验证规则映射**：将前端表单规则映射为 Valitron 规则

## 基础验证

### 核心验证方法

```php
use Core\Validator\Validator;

/**
 * 数据验证核心方法
 * @param array|object|null $data 待验证数据
 * @param array $rules 验证规则 ["字段名" => [["规则", "错误信息"]]]
 * @return Data 验证后的数据对象
 * @throws ExceptionValidator 验证失败时抛出异常
 */
$validated = Validator::parser($data, $rules);
```

### 基础使用示例

```php
use Core\Validator\Validator;
use Core\Handlers\ExceptionValidator;

// 基础验证示例
try {
    $data = [
        'name' => 'DuxLite',
        'email' => 'admin@duxlite.com',
        'age' => 25
    ];

    $validated = Validator::parser($data, [
        'name' => [
            ['required', '姓名不能为空'],
            ['lengthMin', 2, '姓名至少需要2个字符']
        ],
        'email' => [
            ['required', '邮箱不能为空'],
            ['email', '邮箱格式无效']
        ],
        'age' => [
            ['numeric', '年龄必须是数字'],
            ['min', 18, '年龄不能小于18岁']
        ]
    ]);

    // 验证成功，获取数据
    echo $validated->name;    // 'DuxLite'
    echo $validated->email;   // 'admin@duxlite.com'
    echo $validated->age;     // 25

    // 转换为数组
    $array = $validated->toArray();

} catch (ExceptionValidator $e) {
    // 验证失败，获取错误信息
    $errors = $e->getData();
    /*
    $errors 格式:
    [
        'name' => ['姓名至少需要2个字符'],
        'email' => ['邮箱格式无效'],
        'age' => ['年龄不能小于18岁']
    ]
    */
}
```

## 验证规则详解

### 必填验证

```php
$rules = [
    'username' => [
        ['required', '用户名不能为空']
    ],
    'password' => [
        ['required', '密码不能为空']
    ]
];
```

### 字符串验证

```php
$rules = [
    'title' => [
        ['required', '标题不能为空'],
        ['lengthMin', 3, '标题至少3个字符'],
        ['lengthMax', 100, '标题不能超过100个字符'],
        ['length', 50, '标题必须是50个字符']  // 固定长度
    ],
    'content' => [
        ['lengthMax', 1000, '内容不能超过1000个字符']
    ]
];
```

### 邮箱和URL验证

```php
$rules = [
    'email' => [
        ['required', '邮箱不能为空'],
        ['email', '邮箱格式无效']
    ],
    'website' => [
        ['url', '网站地址格式无效']
    ]
];
```

### 数字验证

```php
$rules = [
    'age' => [
        ['required', '年龄不能为空'],
        ['numeric', '年龄必须是数字'],
        ['min', 1, '年龄不能小于1'],
        ['max', 120, '年龄不能超过120']
    ],
    'price' => [
        ['numeric', '价格必须是数字'],
        ['min', 0.01, '价格不能小于0.01']
    ],
    'quantity' => [
        ['integer', '数量必须是整数'],
        ['min', 1, '数量至少为1']
    ]
];
```

### 正则表达式验证

```php
$rules = [
    'phone' => [
        ['regex', '/^1[3-9]\d{9}$/', '手机号格式无效']
    ],
    'id_card' => [
        ['regex', '/^(\d{18}|\d{15}|\d{17}[xX])$/', '身份证号格式无效']
    ],
    'username' => [
        ['regex', '/^[a-zA-Z0-9_]{3,20}$/', '用户名只能包含字母、数字和下划线，长度3-20位']
    ],
    'password' => [
        ['regex', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', '密码必须包含大小写字母和数字']
    ]
];
```

### 日期和时间验证

```php
$rules = [
    'birthday' => [
        ['date', '生日格式无效']
    ],
    'created_at' => [
        ['dateFormat', 'Y-m-d H:i:s', '创建时间格式必须为 YYYY-MM-DD HH:MM:SS']
    ]
];
```

### 布尔值验证

```php
$rules = [
    'is_active' => [
        ['boolean', '状态必须是布尔值']
    ],
    'agree_terms' => [
        ['required', '必须同意条款'],
        ['boolean', '同意条款必须是布尔值'],
        ['accepted', '必须同意服务条款']
    ]
];
```

### 数组和选择验证

```php
$rules = [
    'category' => [
        ['required', '请选择分类'],
        ['in', ['tech', 'life', 'travel'], '分类选择无效']
    ],
    'tags' => [
        ['array', '标签必须是数组'],
        ['lengthMax', 5, '最多选择5个标签']
    ],
    'status' => [
        ['oneOf', [0, 1, 2], '状态值无效']
    ]
];
```

## 高级验证技巧

### 多规则验证

```php
// 用户注册验证示例
$validated = Validator::parser($data, [
    'username' => [
        ['required', '用户名不能为空'],
        ['lengthMin', 3, '用户名至少3个字符'],
        ['lengthMax', 20, '用户名不能超过20个字符'],
        ['regex', '/^[a-zA-Z0-9_]+$/', '用户名只能包含字母、数字和下划线'],
        ['slug', '用户名格式无效']
    ],
    'email' => [
        ['required', '邮箱不能为空'],
        ['email', '邮箱格式无效'],
        ['lengthMax', 255, '邮箱长度不能超过255个字符']
    ],
    'password' => [
        ['required', '密码不能为空'],
        ['lengthMin', 8, '密码至少8个字符'],
        ['lengthMax', 32, '密码不能超过32个字符'],
        ['regex', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', '密码必须包含大小写字母和数字']
    ],
    'confirm_password' => [
        ['required', '确认密码不能为空'],
        ['equals', 'password', '两次密码输入不一致']
    ],
    'age' => [
        ['required', '年龄不能为空'],
        ['integer', '年龄必须是整数'],
        ['min', 18, '年龄不能小于18岁'],
        ['max', 65, '年龄不能超过65岁']
    ],
    'phone' => [
        ['regex', '/^1[3-9]\d{9}$/', '手机号格式无效']
    ]
]);
```

### 条件验证

```php
// 根据不同条件应用不同验证规则
public function validateUser(array $data, string $scenario = 'create'): Data
{
    $baseRules = [
        'name' => [
            ['required', '姓名不能为空'],
            ['lengthMax', 50, '姓名不能超过50个字符']
        ],
        'email' => [
            ['required', '邮箱不能为空'],
            ['email', '邮箱格式无效']
        ]
    ];

    // 创建时需要密码
    if ($scenario === 'create') {
        $baseRules['password'] = [
            ['required', '密码不能为空'],
            ['lengthMin', 6, '密码至少6个字符']
        ];
    }

    // 更新时密码可选
    if ($scenario === 'update' && !empty($data['password'])) {
        $baseRules['password'] = [
            ['lengthMin', 6, '密码至少6个字符']
        ];
    }

    return Validator::parser($data, $baseRules);
}
```

### 自定义验证消息

```php
// 基于字段动态生成错误消息
$validated = Validator::parser($data, [
    'title' => [
        ['required', '请输入文章标题'],
        ['lengthMin', 5, '文章标题至少需要5个字符'],
        ['lengthMax', 200, '文章标题不能超过200个字符']
    ],
    'content' => [
        ['required', '请输入文章内容'],
        ['lengthMin', 50, '文章内容至少需要50个字符']
    ],
    'category_id' => [
        ['required', '请选择文章分类'],
        ['integer', '分类ID必须是整数'],
        ['min', 1, '分类ID必须大于0']
    ],
    'tags' => [
        ['array', '标签必须是数组格式'],
        ['lengthMax', 10, '最多只能选择10个标签']
    ]
]);
```

## Data 对象详解

### Data 类特性

验证成功后返回的 `Data` 对象具有以下特性：

```php
use Core\Validator\Data;

// Data 对象实现了 ArrayAccess 接口
$data = new Data(['name' => 'DuxLite', 'email' => 'admin@example.com']);

// 属性访问
echo $data->name;      // 'DuxLite'
echo $data->email;     // 'admin@example.com'

// 数组访问
echo $data['name'];    // 'DuxLite'
echo $data['email'];   // 'admin@example.com'

// 检查属性是否存在
if (isset($data->name)) {
    echo "姓名: " . $data->name;
}

// 设置属性
$data->phone = '13888888888';
$data['address'] = '北京市';

// 转换为数组
$array = $data->toArray();

// 删除属性
unset($data->phone);
unset($data['address']);
```

### 在控制器中使用

```php
use Core\Validator\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function create(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $data = $request->getParsedBody();

        // 验证数据
        $validated = Validator::parser($data, [
            'name' => [
                ['required', '姓名不能为空'],
                ['lengthMax', 50, '姓名不能超过50个字符']
            ],
            'email' => [
                ['required', '邮箱不能为空'],
                ['email', '邮箱格式无效']
            ]
        ]);

        // 使用验证后的数据
        $user = User::create([
            'name' => $validated->name,
            'email' => $validated->email,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return send($response, '用户创建成功', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email
        ]);
    }
}
```

## 资源控制器集成

### 资源控制器验证

在资源控制器中定义验证规则：

```php
use Core\Resources\Action\Resources;
use Core\Validator\Data;

class UserController extends Resources
{
    protected string $model = User::class;

    /**
     * 定义验证规则
     */
    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        $action = $request->getAttribute('action', '');

        // 根据不同操作定义不同验证规则
        $rules = [
            'name' => [
                ['required', '姓名不能为空'],
                ['lengthMin', 2, '姓名至少2个字符'],
                ['lengthMax', 50, '姓名不能超过50个字符']
            ],
            'email' => [
                ['required', '邮箱不能为空'],
                ['email', '邮箱格式无效']
            ]
        ];

        // 创建时需要密码
        if ($action === 'create') {
            $rules['password'] = [
                ['required', '密码不能为空'],
                ['lengthMin', 6, '密码至少6个字符']
            ];
        }

        // 更新时密码可选
        if ($action === 'edit' && !empty($data['password'])) {
            $rules['password'] = [
                ['lengthMin', 6, '密码至少6个字符']
            ];
        }

        return $rules;
    }

    /**
     * 数据格式化（验证后处理）
     */
    public function format(Data $data, ServerRequestInterface $request, array $args): array
    {
        $formatted = [
            'name' => $data->name,
            'email' => strtolower($data->email), // 邮箱转小写
        ];

        // 密码加密
        if (isset($data->password)) {
            $formatted['password'] = password_hash($data->password, PASSWORD_DEFAULT);
        }

        return $formatted;
    }
}
```

### 动态表单验证

资源控制器支持基于配置的动态表单验证，通过 `Validator::rule()` 方法处理：

```php
// 使用 Validator::rule() 方法处理动态表单
public function validator(array $data, ServerRequestInterface $request, array $args): array
{
    // 从数据库或配置获取表单字段定义
    $formFields = [
        [
            'name' => 'title',
            'label' => '标题',
            'required' => true,
            'setting' => [
                'rules' => json_encode([
                    ['min' => 5, 'message' => '标题至少5个字符'],
                    ['max' => 100, 'message' => '标题不能超过100个字符']
                ])
            ]
        ],
        [
            'name' => 'email',
            'label' => '邮箱',
            'required' => true,
            'setting' => [
                'rules' => json_encode([
                    ['email' => true, 'message' => '邮箱格式无效']
                ])
            ]
        ],
        [
            'name' => 'phone',
            'label' => '手机号',
            'required' => false,
            'setting' => [
                'rules' => json_encode([
                    ['telnumber' => true, 'message' => '手机号格式无效']
                ])
            ]
        ]
    ];

    // 将表单配置转换为验证规则
    return Validator::rule($formFields);
}
```

**支持的动态规则映射：**

| 前端规则 | Valitron 规则 | 说明 |
|----------|--------------|------|
| `boolean` | `boolean` | 布尔值验证 |
| `date` | `date` | 日期格式验证 |
| `email` | `email` | 邮箱格式验证 |
| `enum` | 自定义函数 | 枚举值验证 |
| `idcard` | `regex` | 身份证号验证 |
| `min` | `lengthMin` | 最小长度验证 |
| `max` | `lengthMax` | 最大长度验证 |
| `length` | `length` | 固定长度验证 |
| `number` | `numeric` | 数字验证 |
| `pattern` | `regex` | 正则表达式验证 |
| `required` | `required` | 必填验证 |
| `telnumber` | `regex` | 手机号验证 |
| `url` | `url` | URL格式验证 |
```

## 异常处理

### ExceptionValidator 异常

验证失败时会抛出 `ExceptionValidator` 异常：

```php
use Core\Handlers\ExceptionValidator;

try {
    $validated = Validator::parser($data, $rules);
} catch (ExceptionValidator $e) {
    // 获取错误信息
    $errors = $e->getData();

    // 获取HTTP状态码（默认422）
    $statusCode = $e->getCode();

    // 获取错误消息
    $message = $e->getMessage(); // "数据验证失败"

    // 在API响应中返回错误
    return send($response, '数据验证失败', $errors, [], 422);
}
```

### 全局异常处理

DuxLite 框架已内置了 `ExceptionValidator` 的全局异常处理，无需手动配置。验证异常会自动转换为 HTTP 422 响应。

**框架内置处理流程：**

1. **自动注册**：框架在 `Bootstrap::loadRoute()` 中自动注册了 `ErrorHandler`
2. **异常拦截**：`ExceptionValidator` 异常会被自动捕获
3. **响应格式化**：自动格式化为标准的错误响应

```php
// 框架内置的异常处理（无需手动实现）
// 位置：src/Bootstrap.php
public function loadRoute(): void
{
    // ...
    $errorMiddleware = $this->web->addErrorMiddleware(App::$debug, true, true);
    $errorHandler = new ErrorHandler(/*...*/);
    $errorMiddleware->setDefaultErrorHandler($errorHandler);

    // 注册异常渲染器
    $errorHandler->registerErrorRenderer("application/json", ErrorJsonRenderer::class);
    $errorHandler->registerErrorRenderer("text/html", ErrorHtmlRenderer::class);
    // ...
}
```

**自定义异常处理（可选）：**

如果需要自定义验证异常处理逻辑，可以在控制器中手动捕获：

```php
public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    try {
        $validated = Validator::parser($request->getParsedBody(), [
            'name' => [['required', '姓名不能为空']],
            'email' => [['email', '邮箱格式无效']]
        ]);

        // 业务逻辑处理...
        return send($response, '操作成功', $validated->toArray());

    } catch (ExceptionValidator $e) {
        // 自定义验证错误处理
        $errors = $e->getData();

        // 记录日志
        App::log()->warning('Validation failed', [
            'errors' => $errors,
            'request' => $request->getUri()->getPath()
        ]);

        // 返回自定义响应
        return send($response, '数据验证失败', [
            'validation_errors' => $errors,
            'timestamp' => time(),
            'request_id' => uniqid()
        ], [], 422);
    }
}
```

## 完整验证规则列表

### 基础验证规则

| 规则 | 参数 | 说明 | 示例 |
|------|------|------|------|
| `required` | - | 必填字段 | `['required', '字段不能为空']` |
| `equals` | 字段名 | 字段相等 | `['equals', 'password', '两次密码不一致']` |
| `different` | 字段名 | 字段不同 | `['different', 'email', '不能与邮箱相同']` |
| `accepted` | - | 必须接受（true, 1, yes, on） | `['accepted', '必须同意条款']` |
| `numeric` | - | 数字类型 | `['numeric', '必须是数字']` |
| `integer` | - | 整数类型 | `['integer', '必须是整数']` |
| `boolean` | - | 布尔值 | `['boolean', '必须是布尔值']` |
| `array` | - | 数组类型 | `['array', '必须是数组']` |
| `scalar` | - | 标量类型 | `['scalar', '必须是标量']` |

### 字符串验证规则

| 规则 | 参数 | 说明 | 示例 |
|------|------|------|------|
| `length` | 长度 | 固定长度 | `['length', 11, '必须是11位']` |
| `lengthBetween` | 最小,最大 | 长度范围 | `['lengthBetween', [6, 20], '长度6-20位']` |
| `lengthMin` | 最小长度 | 最小长度 | `['lengthMin', 6, '至少6个字符']` |
| `lengthMax` | 最大长度 | 最大长度 | `['lengthMax', 50, '不超过50个字符']` |
| `in` | 值数组 | 在指定值中 | `['in', ['A', 'B'], '值无效']` |
| `notIn` | 值数组 | 不在指定值中 | `['notIn', ['admin'], '不能是保留字']` |
| `contains` | 子字符串 | 包含指定字符串 | `['contains', 'dux', '必须包含dux']` |
| `regex` | 正则表达式 | 正则匹配 | `['regex', '/^1[3-9]\d{9}$/', '手机号无效']` |
| `alpha` | - | 只能是字母 | `['alpha', '只能包含字母']` |
| `alphaNum` | - | 字母和数字 | `['alphaNum', '只能包含字母和数字']` |
| `slug` | - | URL友好字符 | `['slug', '只能包含字母、数字、连字符']` |
| `email` | - | 邮箱格式 | `['email', '邮箱格式无效']` |
| `emailDNS` | - | 邮箱DNS验证 | `['emailDNS', '邮箱域名无效']` |
| `url` | - | URL格式 | `['url', 'URL格式无效']` |
| `urlActive` | - | URL可访问 | `['urlActive', 'URL无法访问']` |
| `ip` | - | IP地址 | `['ip', 'IP地址无效']` |

### 数值验证规则

| 规则 | 参数 | 说明 | 示例 |
|------|------|------|------|
| `min` | 最小值 | 最小值 | `['min', 1, '不能小于1']` |
| `max` | 最大值 | 最大值 | `['max', 100, '不能大于100']` |
| `between` | 最小,最大 | 值范围 | `['between', [1, 100], '值必须在1-100之间']` |

### 日期验证规则

| 规则 | 参数 | 说明 | 示例 |
|------|------|------|------|
| `date` | - | 日期格式 | `['date', '日期格式无效']` |
| `dateFormat` | 格式 | 指定日期格式 | `['dateFormat', 'Y-m-d', '日期格式必须为YYYY-MM-DD']` |
| `dateBefore` | 日期 | 早于指定日期 | `['dateBefore', '2024-12-31', '必须早于2024年底']` |
| `dateAfter` | 日期 | 晚于指定日期 | `['dateAfter', '2024-01-01', '必须晚于2024年初']` |

### 文件验证规则

| 规则 | 参数 | 说明 | 示例 |
|------|------|------|------|
| `uploaded` | - | 是否上传文件 | `['uploaded', '请选择文件']` |
| `mimes` | MIME类型数组 | 文件类型限制 | `['mimes', ['jpg', 'png'], '只能上传JPG或PNG']` |
| `size` | 大小(字节) | 文件大小限制 | `['size', 1048576, '文件不能超过1MB']` |

## 最佳实践

### 1. 验证规则组织

```php
class ValidationRules
{
    public static function userRules(string $scenario = 'create'): array
    {
        $rules = [
            'name' => [
                ['required', '姓名不能为空'],
                ['lengthMax', 50, '姓名不能超过50个字符']
            ],
            'email' => [
                ['required', '邮箱不能为空'],
                ['email', '邮箱格式无效']
            ]
        ];

        if ($scenario === 'create') {
            $rules['password'] = self::passwordRules();
        }

        return $rules;
    }

    public static function passwordRules(): array
    {
        return [
            ['required', '密码不能为空'],
            ['lengthMin', 8, '密码至少8个字符'],
            ['regex', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', '密码必须包含大小写字母和数字']
        ];
    }
}

// 使用
$validated = Validator::parser($data, ValidationRules::userRules('create'));
```

### 2. 国际化错误消息

```php
// 在验证规则中使用翻译函数
$rules = [
    'name' => [
        ['required', __('validation.required', ['field' => '姓名'])],
        ['lengthMax', 50, __('validation.max_length', ['field' => '姓名', 'max' => 50])]
    ]
];
```

### 3. 复杂验证场景

```php
// 用户资料更新验证
public function validateProfileUpdate(array $data, int $userId): Data
{
    $rules = [
        'nickname' => [
            ['lengthMax', 30, '昵称不能超过30个字符']
        ],
        'bio' => [
            ['lengthMax', 200, '个人简介不能超过200个字符']
        ]
    ];

    // 如果更改邮箱，需要验证邮箱唯一性
    if (isset($data['email'])) {
        $rules['email'] = [
            ['required', '邮箱不能为空'],
            ['email', '邮箱格式无效'],
            // 注意：Valitron不直接支持数据库验证，需要自定义验证
        ];

        // 手动检查邮箱唯一性
        if (User::where('email', $data['email'])->where('id', '!=', $userId)->exists()) {
            throw new ExceptionValidator(['email' => ['邮箱已被使用']]);
        }
    }

    return Validator::parser($data, $rules);
}
```

### 4. 验证错误处理

```php
public function handleValidationErrors(ExceptionValidator $e): array
{
    $errors = $e->getData();
    $formattedErrors = [];

    foreach ($errors as $field => $messages) {
        $formattedErrors[$field] = [
            'field' => $field,
            'messages' => $messages,
            'first_message' => $messages[0] ?? ''
        ];
    }

    return $formattedErrors;
}
```

::: warning 注意事项
1. **数据库验证**：Valitron 不直接支持数据库验证，需要手动实现唯一性检查
2. **文件上传验证**：文件验证需要配合 PSR-7 UploadedFile 接口使用
3. **复杂条件验证**：对于复杂的业务逻辑验证，建议在验证通过后进行额外检查
4. **性能考虑**：大量数据验证时注意性能，可考虑批量验证或异步验证
:::

::: tip 性能优化建议
- 将常用验证规则缓存或预编译
- 对于复杂表单，考虑分步验证
- 使用验证规则组织器避免重复定义
- 在生产环境中考虑验证结果缓存
:::

DuxLite 的验证系统为您提供了强大而灵活的数据验证能力，通过合理使用验证规则和异常处理，您可以构建安全可靠的应用程序。