# 数据验证

DuxLite 基于 [Valitron](https://github.com/vlucas/valitron) 验证库构建了简洁的数据验证系统，提供自动异常处理和类型安全返回。

## 基础使用

### 核心方法

```php
use Core\Validator\Validator;

/**
 * 数据验证核心方法
 * @param array|object|null $data 待验证数据
 * @param array $rules 验证规则 ["字段名" => [["规则", "参数", "错误信息"]]]
 * @return Data 验证后的数据对象
 * @throws ExceptionValidator 验证失败时抛出异常
 */
$validated = Validator::parser($data, $rules);
```

### 基础示例

```php
use Core\Validator\Validator;

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
$array = $validated->toArray();
```

## 常用验证规则

### 必填和字符串

```php
$rules = [
    'username' => [
        ['required', '用户名不能为空'],
        ['lengthMin', 3, '用户名至少3个字符'],
        ['lengthMax', 20, '用户名不能超过20个字符']
    ],
    'title' => [
        ['required', '标题不能为空'],
        ['length', 50, '标题必须是50个字符']  // 固定长度
    ]
];
```

### 邮箱和URL

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
    'quantity' => [
        ['integer', '数量必须是整数']
    ]
];
```

### 正则表达式

```php
$rules = [
    'phone' => [
        ['regex', '/^1[3-9]\d{9}$/', '手机号格式无效']
    ],
    'username' => [
        ['regex', '/^[a-zA-Z0-9_]{3,20}$/', '用户名格式无效']
    ]
];
```

### 数组和选择

```php
$rules = [
    'category' => [
        ['required', '请选择分类'],
        ['in', ['tech', 'life', 'travel'], '分类选择无效']
    ],
    'tags' => [
        ['array', '标签必须是数组']
    ]
];
```

## Data 对象使用

验证成功后返回 `Data` 对象，支持属性访问和数组操作：

```php
// 属性访问
echo $validated->name;
echo $validated->email;

// 数组访问
echo $validated['name'];
isset($validated->name);

// 转换为数组
$array = $validated->toArray();

// 设置新属性
$validated->phone = '13888888888';
```

## 控制器中使用

```php
use Core\Validator\Validator;

class UserController
{
    public function create(ServerRequestInterface $request): ResponseInterface 
    {
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
            'email' => $validated->email
        ]);

        return send($response, '用户创建成功', $user->transform());
    }
}
```

## 资源控制器集成

在资源控制器中定义验证规则：

```php
use Core\Resources\Action\Resources;

class UserController extends Resources
{
    protected string $model = User::class;

    public function validator(array $data, ServerRequestInterface $request, array $args): array
    {
        return [
            'name' => [
                ['required', '姓名不能为空'],
                ['lengthMax', 50, '姓名不能超过50个字符']
            ],
            'email' => [
                ['required', '邮箱不能为空'],
                ['email', '邮箱格式无效']
            ]
        ];
    }
}
```

## 异常处理

验证失败时自动抛出 `ExceptionValidator` 异常，框架会自动处理并返回 422 状态码：

```php
try {
    $validated = Validator::parser($data, $rules);
} catch (ExceptionValidator $e) {
    // 获取错误信息
    $errors = $e->getData();
    /*
    $errors 格式:
    [
        'name' => ['姓名不能为空'],
        'email' => ['邮箱格式无效']
    ]
    */
}
```

## 动态表单验证

框架提供 `Validator::rule()` 方法处理动态表单配置：

```php
// 表单字段配置
$formFields = [
    [
        'name' => 'title',
        'label' => '标题',
        'required' => true,
        'setting' => [
            'rules' => json_encode([
                ['min' => 5, 'message' => '标题至少5个字符']
            ])
        ]
    ]
];

// 转换为验证规则
$rules = Validator::rule($formFields);
```

**支持的动态规则映射：**

| 前端规则 | Valitron 规则 | 说明 |
|----------|--------------|------|
| `required` | `required` | 必填验证 |
| `email` | `email` | 邮箱格式验证 |
| `number` | `numeric` | 数字验证 |
| `min` | `lengthMin` | 最小长度验证 |
| `max` | `lengthMax` | 最大长度验证 |
| `pattern` | `regex` | 正则表达式验证 |
| `telnumber` | `regex` | 手机号验证 |
| `idcard` | `regex` | 身份证号验证 |

## 更多验证规则

DuxLite 的验证系统基于 [Valitron](https://github.com/vlucas/valitron) 库构建。

**查看完整验证规则列表：**
- 访问 [Valitron 官方文档](https://github.com/vlucas/valitron#built-in-validation-rules)
- 支持所有 Valitron 内置规则，包括：
  - 字符串规则：`alpha`, `alphaNum`, `slug`, `contains` 等
  - 数值规则：`between`, `min`, `max` 等  
  - 日期规则：`date`, `dateFormat`, `dateBefore`, `dateAfter` 等
  - 文件规则：`uploaded`, `mimes`, `size` 等
  - 其他规则：`equals`, `different`, `accepted` 等

**自定义验证规则：**
```php
// 可以使用 Valitron 的自定义规则功能
// 详见 Valitron 文档的 Custom Validation Rules 部分
```

## 最佳实践

1. **规则组织**：将常用验证规则封装成静态方法
2. **错误信息**：使用清晰的中文错误提示
3. **条件验证**：根据不同场景应用不同规则
4. **数据库唯一性**：需要手动实现，Valitron 不直接支持
5. **复杂验证**：复杂业务逻辑建议在验证通过后单独检查

DuxLite 的验证系统简单易用，基于成熟的 Valitron 库，提供了丰富的验证功能。