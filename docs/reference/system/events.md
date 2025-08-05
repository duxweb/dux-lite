# 事件系统

DuxLite 基于 Symfony EventDispatcher 提供事件系统，支持注解监听器和事件分发。

## 事件的作用

事件系统采用发布-订阅模式，实现代码解耦和扩展性：

- **解耦业务逻辑**：将复杂的业务处理分散到不同的监听器中
- **可扩展性**：无需修改核心代码即可添加新功能
- **异步处理**：配合队列系统处理耗时操作
- **生命周期钩子**：在模型和控制器的关键节点插入自定义逻辑

## 核心组件

- **Event**：事件调度器 (`Core\Event\Event`)
- **Listener**：监听器注解 (`Core\Event\Attribute\Listener`)
- **DatabaseEvent**：数据库模型事件 (`Core\Database\DatabaseEvent`)
- **ResourcesEvent**：资源控制器事件 (`Core\Resources\ResourcesEvent`)

## 使用方法

### 注解监听器

使用 `#[Listener]` 注解声明监听器：

```php
use Core\Event\Attribute\Listener;

class UserEventListener
{
    #[Listener('user.login')]
    public function handleUserLogin($user): void
    {
        // 用户登录处理
    }

    #[Listener('user.register', priority: 10)]
    public function handleUserRegister($user): void
    {
        // 用户注册处理（priority 参数设置优先级）
    }
}
```

### 编程式监听器

```php
use Core\App;

// 添加监听器
App::event()->addListener('user.created', function ($user) {
    // 处理逻辑
});
```

### 事件触发

```php
use Core\App;

// 触发事件
App::event()->dispatch('user.login', $user);

// 触发带多个参数的事件
App::event()->dispatch('order.completed', $order, $user);
```

## 框架提供的全局事件

### 数据库模型事件

模型自动触发事件，使用 `model.类名` 格式监听：

```php
class UserModelListener
{
    #[Listener('model.App\Models\User')]
    public function handleUserModelEvents(DatabaseEvent $event): void
    {
        $event->creating(function ($user) {
            // 创建前处理
        });

        $event->created(function ($user) {
            // 创建后处理
        });

        $event->updating(function ($user) {
            // 更新前处理
        });

        $event->deleting(function ($user) {
            // 删除前处理
        });
    }
}
```

**可用的模型事件方法：**

- `retrieved()` - 检索后
- `creating()` - 创建前  
- `created()` - 创建后
- `updating()` - 更新前
- `updated()` - 更新后
- `saving()` - 保存前
- `saved()` - 保存后
- `deleting()` - 删除前
- `deleted()` - 删除后
- `replicating()` - 复制时
- `migration()` - 迁移时

### 资源控制器事件

资源控制器自动触发事件，使用 `resources.控制器类名` 格式监听：

```php
class ProductResourceListener
{
    #[Listener('resources.App\Api\Controller\ProductController')]
    public function handleProductResourceEvents(ResourcesEvent $event): void
    {
        $event->queryOne(function ($query) {
            // 单个查询前处理
            return ['status' => 'active'];
        });
        
        $event->queryMany(function ($query) {
            // 列表查询前处理
            return ['status' => 'active'];
        });

        $event->createBefore(function ($data) {
            // 创建前验证
        });

        $event->createAfter(function ($model, $data) {
            // 创建后处理
        });

        $event->transform(function ($item) {
            // 数据转换
            return $item->toArray();
        });
    }
}
```

**可用的资源事件方法：**

- `init()` - 初始化
- `validator()` - 数据验证  
- `transform()` - 数据转换
- `format()` - 数据格式化
- `queryOne()` - 单个查询前
- `queryMany()` - 列表查询前
- `query()` - 通用查询前
- `metaOne()` - 单个元数据
- `metaMany()` - 列表元数据
- `createBefore()` - 创建前
- `createAfter()` - 创建后
- `editBefore()` - 编辑前
- `editAfter()` - 编辑后
- `delBefore()` - 删除前
- `delAfter()` - 删除后
- `storeBefore()` - 存储前
- `storeAfter()` - 存储后
- `restoreBefore()` - 恢复前
- `restoreAfter()` - 恢复后
- `trashBefore()` - 回收前
- `trashAfter()` - 回收后

## 实际应用示例

### 用户注册流程

```php
class UserRegistrationListener
{
    #[Listener('user.registered')]
    public function handleUserRegistered($user): void
    {
        // 发送欢迎邮件
        $this->sendWelcomeEmail($user);
        
        // 分配默认角色
        $user->assignRole('member');
        
        // 记录注册日志
        $this->logUserActivity($user, 'registered');
    }
}

// 在控制器中触发
public function register($data)
{
    $user = User::create($data);
    
    // 触发注册事件
    App::event()->dispatch('user.registered', $user);
    
    return $user;
}
```

### 订单完成处理

```php
class OrderEventListener
{
    #[Listener('order.completed')]
    public function handleOrderCompleted($order): void
    {
        // 发送确认邮件
        $this->sendOrderConfirmation($order);
        
        // 更新库存
        $this->updateInventory($order);
        
        // 计算积分
        $this->calculatePoints($order->user, $order->total);
    }
}
```

### 异步事件处理

```php
class AsyncEventListener
{
    #[Listener('user.activity')]
    public function trackActivity($data): void
    {
        // 使用队列异步处理统计
        Queue::push('analytics', $data);
    }
    
    #[Listener('email.send')]
    public function sendEmailAsync($emailData): void
    {
        // 异步发送邮件
        Queue::push('email', $emailData);
    }
}
```

DuxLite 事件系统基于 Symfony EventDispatcher，支持注解监听器和事件分发，主要用于模型和资源控制器的生命周期处理。