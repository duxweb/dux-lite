# 事件系统

DuxLite 提供了强大而灵活的事件系统，基于 Symfony EventDispatcher 组件，支持事件监听、分发和自定义事件。事件系统是实现松耦合架构的核心机制。

## 系统概述

### 事件系统架构

DuxLite 的事件系统采用发布-订阅模式：

```
事件触发 → 事件调度器 → 匹配监听器 → 执行回调函数 → 返回结果
```

### 核心组件

- **Event**：事件调度器，继承自 Symfony EventDispatcher
- **Listener**：事件监听器注解，用于声明事件监听方法
- **DatabaseEvent**：数据库模型事件
- **ResourcesEvent**：资源路由事件

## 基础用法

### 事件触发

```php
use Core\App;

// 触发简单事件
App::event()->dispatch('user.login', $user);

// 触发带事件对象的事件
$event = new UserLoginEvent($user);
App::event()->dispatch($event, 'user.login');
```

### 事件监听

#### 1. 注解监听器（推荐）

使用 `#[Listener]` 注解声明监听器：

```php
use Core\Event\Attribute\Listener;

class UserEventListener
{
    #[Listener('user.login')]
    public function handleUserLogin($user): void
    {
        // 记录登录日志
        $this->logUserActivity($user, 'login');

        // 更新最后登录时间
        $user->update(['last_login_at' => now()]);
    }

    #[Listener('user.register', priority: 10)]
    public function handleUserRegister($user): void
    {
        // 发送欢迎邮件（高优先级）
        $this->sendWelcomeEmail($user);
    }

    #[Listener('user.logout')]
    public function handleUserLogout($user): void
    {
        // 清理用户缓存
        $this->clearUserCache($user);
    }
}
```

#### 2. 编程式监听器

在应用模块中手动注册监听器：

```php
use Core\App\AppExtend;
use Core\Bootstrap;

class UserApp extends AppExtend
{
    public function register(Bootstrap $bootstrap): void
    {
        // 使用闭包监听器
        App::event()->addListener('user.created', function ($user) {
            // 新用户创建后处理
            $this->assignDefaultRole($user);
        });

        // 使用类方法监听器
        App::event()->addListener('order.completed', [
            new OrderNotificationService(),
            'handleOrderCompletion'
        ], 5); // 优先级为 5
    }
}
```

### 监听器优先级

监听器支持优先级设置，数字越小优先级越高：

```php
class EventListener
{
    #[Listener('user.login', priority: 1)]  // 最高优先级
    public function validateUser($user): void
    {
        // 用户验证（优先执行）
    }

    #[Listener('user.login', priority: 5)]  // 中等优先级
    public function logActivity($user): void
    {
        // 记录活动日志
    }

    #[Listener('user.login', priority: 10)] // 较低优先级
    public function updateStats($user): void
    {
        // 更新统计数据（最后执行）
    }
}
```

## 内置事件系统

### 数据库模型事件

DuxLite 自动为所有模型提供生命周期事件：

```php
use Core\Database\Model;
use Core\Event\Attribute\Listener;

class User extends Model
{
    protected static function boot()
    {
        parent::boot();

        // 模型内部事件监听
        static::creating(function ($user) {
            $user->uuid = Str::uuid();
        });

        static::created(function ($user) {
            // 用户创建后自动触发事件
            App::event()->dispatch('user.created', $user);
        });
    }
}

// 外部事件监听器
class UserModelListener
{
    #[Listener('model.App\Models\User')]
    public function handleUserModelEvents(DatabaseEvent $event): void
    {
        // 监听用户模型的所有事件
        $event->creating(function ($user) {
            // 创建前处理
            $user->status = 'active';
        });

        $event->created(function ($user) {
            // 创建后处理
            $this->sendWelcomeNotification($user);
        });

        $event->updating(function ($user) {
            // 更新前处理
            $user->updated_by = auth()->id();
        });

        $event->deleting(function ($user) {
            // 删除前处理
            $this->backupUserData($user);
        });
    }
}
```

### 可用的模型事件

| 事件 | 触发时机 | 说明 |
|------|----------|------|
| `retrieved` | 模型查询后 | 数据从数据库检索后 |
| `creating` | 创建前 | 模型保存到数据库前（仅新建） |
| `created` | 创建后 | 模型保存到数据库后（仅新建） |
| `updating` | 更新前 | 模型更新到数据库前（仅更新） |
| `updated` | 更新后 | 模型更新到数据库后（仅更新） |
| `saving` | 保存前 | 模型保存前（创建或更新） |
| `saved` | 保存后 | 模型保存后（创建或更新） |
| `deleting` | 删除前 | 模型删除前 |
| `deleted` | 删除后 | 模型删除后 |

### 资源路由事件

资源控制器的生命周期事件：

```php
use Core\Resources\Action\Resources;
use Core\Event\Attribute\Listener;

class ProductResourceListener
{
    #[Listener('resource.App\Api\Controller\ProductController')]
    public function handleProductResourceEvents(ResourcesEvent $event): void
    {
        // 查询前处理
        $event->queryMany(function ($query) {
            // 为列表查询添加默认条件
            return ['status' => 'active'];
        });

        // 创建前验证
        $event->createBefore(function ($data) {
            $this->validateProductData($data);
        });

        // 创建后处理
        $event->createAfter(function ($product, $data) {
            // 清理缓存
            $this->clearProductCache();

            // 发送通知
            $this->notifyProductCreated($product);
        });

        // 数据转换
        $event->transform(function ($item) {
            // 转换输出数据格式
            return [
                'id' => $item->id,
                'name' => $item->name,
                'price_formatted' => number_format($item->price, 2),
                'created_at' => $item->created_at->format('Y-m-d H:i:s')
            ];
        });
    }
}
```

### 可用的资源事件

| 事件 | 触发时机 | 参数 | 说明 |
|------|----------|------|------|
| `queryOne` | 单个查询前 | `$query` | 修改单个资源查询条件 |
| `queryMany` | 列表查询前 | `$query` | 修改列表查询条件 |
| `createBefore` | 创建前 | `$data` | 创建前数据验证和处理 |
| `createAfter` | 创建后 | `$model, $data` | 创建后业务处理 |
| `editBefore` | 编辑前 | `$model, $data` | 编辑前数据验证 |
| `editAfter` | 编辑后 | `$model, $data` | 编辑后业务处理 |
| `delBefore` | 删除前 | `$model` | 删除前检查和备份 |
| `delAfter` | 删除后 | `$model` | 删除后清理处理 |
| `transform` | 数据转换 | `$item` | 输出数据格式转换 |
| `validator` | 数据验证 | `$data` | 自定义验证规则 |

## 自定义事件

### 创建事件类

```php
namespace App\Events;

use Symfony\Contracts\EventDispatcher\Event;

class OrderCompletedEvent extends Event
{
    public function __construct(
        public readonly Order $order,
        public readonly User $user,
        public readonly float $amount
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }
}
```

### 触发自定义事件

```php
use App\Events\OrderCompletedEvent;

class OrderService
{
    public function completeOrder(Order $order): void
    {
        // 业务逻辑处理
        $order->update(['status' => 'completed']);

        // 触发事件
        $event = new OrderCompletedEvent(
            order: $order,
            user: $order->user,
            amount: $order->total_amount
        );

        App::event()->dispatch($event, 'order.completed');
    }
}
```

### 监听自定义事件

```php
class OrderEventListener
{
    #[Listener('order.completed')]
    public function handleOrderCompleted(OrderCompletedEvent $event): void
    {
        $order = $event->getOrder();
        $user = $event->getUser();
        $amount = $event->getAmount();

        // 发送确认邮件
        $this->sendOrderConfirmation($user, $order);

        // 更新积分
        $this->updateUserPoints($user, $amount);

        // 库存处理
        $this->updateInventory($order);
    }

    #[Listener('order.completed', priority: 5)]
    public function handleOrderAnalytics(OrderCompletedEvent $event): void
    {
        // 更新销售统计
        $this->updateSalesStats($event->getOrder());
    }
}
```

## 事件的停止传播

事件可以停止传播，阻止后续监听器执行：

```php
use Symfony\Contracts\EventDispatcher\Event;

class SecurityEventListener
{
    #[Listener('user.login', priority: 1)]
    public function validateSecurity(Event $event, $user): void
    {
        if ($this->isBlacklisted($user)) {
            // 停止事件传播
            $event->stopPropagation();

            // 抛出异常或记录日志
            throw new SecurityException('用户已被列入黑名单');
        }
    }

    #[Listener('user.login', priority: 10)]
    public function logLogin($user): void
    {
        // 如果前面的监听器停止了传播，这里不会执行
        $this->logActivity($user, 'login');
    }
}
```

## 异步事件处理

结合队列系统处理耗时的事件：

```php
use Core\Queue\Queue;

class AsyncEventListener
{
    #[Listener('user.registered')]
    public function handleAsyncTasks($user): void
    {
        // 同步处理关键任务
        $this->assignDefaultRole($user);

        // 异步处理耗时任务
        Queue::push('email', [
            'type' => 'welcome',
            'user_id' => $user->id
        ]);

        Queue::push('analytics', [
            'event' => 'user_registered',
            'user_id' => $user->id,
            'timestamp' => time()
        ]);
    }
}
```

## 事件调试

### 查看注册的监听器

```php
// 获取所有注册的监听器
$listeners = App::event()->registers;

// 查看特定事件的监听器
$userLoginListeners = $listeners['user.login'] ?? [];

// 调试输出
foreach ($userLoginListeners as $listener) {
    echo "监听器: {$listener}\n";
}
```

### 事件调试监听器

```php
class DebugEventListener
{
    #[Listener('*')] // 监听所有事件
    public function debugAllEvents($event, string $eventName): void
    {
        if (App::$debug) {
            error_log("事件触发: {$eventName} - " . get_class($event));
        }
    }
}
```

## 最佳实践

### 1. 事件命名规范

```php
// ✅ 推荐：使用点号分隔的命名
'user.created'
'order.completed'
'payment.failed'
'email.sent'

// ✅ 推荐：包含动作和状态
'user.login.success'
'user.login.failed'
'order.status.changed'

// ❌ 不推荐：使用驼峰或下划线
'userCreated'
'user_created'
'OrderCompleted'
```

### 2. 监听器组织

```php
// ✅ 推荐：按业务模块组织监听器
class UserEventListener
{
    #[Listener('user.created')]
    #[Listener('user.updated')]
    #[Listener('user.deleted')]
    public function handleUserEvents($user): void
    {
        // 用户相关事件处理
    }
}

// ✅ 推荐：按功能职责分离监听器
class EmailNotificationListener
{
    #[Listener('user.created')]
    public function sendWelcomeEmail($user): void {}

    #[Listener('order.completed')]
    public function sendOrderConfirmation($order): void {}
}

class AnalyticsListener
{
    #[Listener('user.created')]
    #[Listener('order.completed')]
    public function trackEvent($data): void {}
}
```

### 3. 错误处理

```php
class RobustEventListener
{
    #[Listener('user.created')]
    public function handleUserCreated($user): void
    {
        try {
            // 业务逻辑
            $this->sendWelcomeEmail($user);
        } catch (\Exception $e) {
            // 记录错误但不影响其他监听器
            error_log("邮件发送失败: " . $e->getMessage());

            // 可选：使用队列重试
            Queue::push('email_retry', [
                'user_id' => $user->id,
                'type' => 'welcome'
            ]);
        }
    }
}
```

### 4. 性能优化

```php
class OptimizedEventListener
{
    #[Listener('order.created')]
    public function handleOrderCreated($order): void
    {
        // ✅ 批量处理而非单个处理
        static $orders = [];
        $orders[] = $order;

        // 每 10 个订单批量处理一次
        if (count($orders) >= 10) {
            $this->batchProcessOrders($orders);
            $orders = [];
        }
    }

    #[Listener('user.activity')]
    public function trackActivity($data): void
    {
        // ✅ 使用异步队列处理统计
        Queue::push('analytics', $data);
    }
}
```

## 事件系统与其他组件集成

### 与缓存系统集成

```php
class CacheEventListener
{
    #[Listener('user.updated')]
    public function clearUserCache($user): void
    {
        App::cache()->delete("user.{$user->id}");
        App::cache()->delete("user.profile.{$user->id}");
    }

    #[Listener('product.updated')]
    public function clearProductCache($product): void
    {
        App::cache()->tag('products')->flush();
    }
}
```

### 与权限系统集成

```php
class PermissionEventListener
{
    #[Listener('user.role.changed')]
    public function updateUserPermissions($user): void
    {
        // 重新计算用户权限
        $permissions = $this->calculateUserPermissions($user);
        $user->update(['permissions' => $permissions]);

        // 清理权限缓存
        App::cache()->delete("permissions.{$user->id}");
    }
}
```

DuxLite 的事件系统为应用程序提供了强大的解耦机制，通过合理使用事件和监听器，可以构建出灵活、可维护的应用架构。