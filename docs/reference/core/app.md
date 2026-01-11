# 应用核心类

App 类是 DuxLite 框架的核心管理类，提供了所有系统服务的统一访问接口。通过静态方法访问各种服务，是框架的服务定位器。

## 应用生命周期

### 创建应用

```php
// 基本创建
App::create('/path/to/project');

// 完整配置
App::create(
    basePath: '/path/to/project',
    lang: 'zh-CN',
    timezone: 'Asia/Shanghai',
    host: '0.0.0.0',
    debug: true,
    logo: 'Custom Logo'
);
```

### 运行模式

```php
// CLI 模式运行
App::run();

// Web 模式运行
App::runWeb();

// Worker 模式运行（需要 FrankenPHP）
App::runWorker(maxRequests: 1000);
```

## 核心服务访问

### 依赖注入容器

```php
// 获取 DI 容器
$container = App::di();

// 从容器获取服务
$service = App::di()->get('service_name');

// 设置服务到容器
App::di()->set('service_name', $serviceInstance);
```

### Web 应用实例

```php
// 获取 Slim 应用实例
$slimApp = App::web();

// 添加路由
App::web()->get('/test', function() {
    return 'Hello World';
});
```

## 数据库服务

### 数据库连接

```php
// 获取 Eloquent 数据库管理器
$db = App::db();

// 使用查询构建器
$users = App::db()->table('users')->where('status', 1)->get();

// 获取 PDO 连接
$pdo = App::db()->getConnection()->getPdo();
```

### 数据库迁移

```php
// 获取迁移管理器
$migrate = App::dbMigrate();

// 执行迁移
$migrate->run();

// 回滚迁移
$migrate->rollback();
```

## 缓存和存储

### 缓存服务

```php
// 获取默认缓存
$cache = App::cache();

// 获取指定类型缓存
$fileCache = App::cache('file');
$redisCache = App::cache('redis');

// 缓存操作
$cache->set('key', 'value', 3600);
$value = $cache->get('key', 'default');
```

### Redis 连接

```php
// 获取默认 Redis 连接
$redis = App::redis();

// 获取指定连接和数据库
$redis = App::redis('session', 1);

// Redis 操作
$redis->set('key', 'value');
$value = $redis->get('key');
```

### 文件存储

```php
// 获取默认存储
$storage = App::storage();

// 获取指定存储驱动
$s3Storage = App::storage('s3');

// 文件操作
$storage->put('path/file.txt', 'content');
$content = $storage->get('path/file.txt');
```

## 配置和本地化

### 配置管理

```php
// 获取配置
$appConfig = App::config('app');
$dbConfig = App::config('database');

// 读取配置值
$debug = App::config('app')->get('debug', false);
$dbHost = App::config('database')->get('db.drivers.mysql.host');
```

### 翻译服务

```php
// 获取翻译器
$translator = App::trans();

// 加载翻译目录
App::loadTrans('/path/to/translations', $translator);

// 使用翻译（通常通过 __() 函数）
$message = __('user.welcome', ['name' => 'John']);
```

## 业务服务

### 事件系统

```php
// 获取事件管理器
$events = App::event();

// 触发事件
App::event()->dispatch($user, 'user.created');

// 监听事件
App::event()->addListener('user.created', function ($user) {
    // 处理逻辑
});
```

### 队列服务

```php
// 获取默认队列
$queue = App::queue();
```

### 任务调度

```php
// 获取调度器
$scheduler = App::scheduler();

// 收集调度任务（callback 仅支持 类 或 类:方法）
App::scheduler()->add(
    TaskService::class . ':dailyBackup',
    [],
    '0 2 * * *',
    'daily-backup',
    '每日备份'
);

// 生成任务文件（保存到 data/scheduler/jobs.php）
App::scheduler()->gen();
```

## 框架服务

### 注解数据

```php
// 获取所有注解数据
$attributes = App::attributes();

// 遍历注解
foreach ($attributes as $item) {
    $className = $item['class'];
    $annotations = $item['annotations'];
}
```

### 权限管理

```php
// 获取权限注册器
$permission = App::permission();

// 注册权限（通常自动处理）
App::permission()->register('user.edit', '编辑用户');
```

### 资源管理

```php
// 获取资源注册器
$resource = App::resource();

// 注册资源路由（通常自动处理）
App::resource()->register($controllerClass);
```

### 路由管理

```php
// 获取路由注册器
$route = App::route();

// 获取应用路由实例
$apiApp = App::route()->get('api');
```

## 视图和日志

### 视图引擎

```php
// 获取指定视图引擎
$webView = App::view('web');
$adminView = App::view('admin');

// 渲染模板
$html = App::view('web')->renderToString('template.latte', $data);
```

### 日志服务

```php
// 获取默认应用日志
$logger = App::log();

// 获取指定应用日志
$apiLogger = App::log('api');
$paymentLogger = App::log('payment');

// 记录日志
App::log()->info('用户登录', ['user_id' => 123]);
App::log('payment')->error('支付失败', $context);
```

## 工具服务

### 分布式锁

```php
// 获取默认锁工厂
$lockFactory = App::lock();

// 获取指定类型锁
$redisLock = App::lock('redis');
$semaphoreLock = App::lock('semaphore');

// 使用锁
$lock = App::lock()->createLock('resource_name');
if ($lock->acquire()) {
    try {
        // 执行需要锁保护的代码
    } finally {
        $lock->release();
    }
}
```

### 地理位置

```php
// 获取地理位置服务
$geo = App::geo();

// IP 地理位置查询
if ($geo) {
    $location = $geo->search('8.8.8.8');
    // 返回地理位置信息
}
```

### 启动横幅

```php
// 显示启动横幅
App::banner([
    'Version' => '1.0.0',
    'Environment' => 'production'
], [
    'Database' => 'Connected',
    'Cache' => 'Redis'
]);
```

## 静态属性

### 路径配置

```php
App::$basePath    // 项目根目录
App::$configPath  // 配置目录
App::$dataPath    // 数据目录  
App::$publicPath  // 公共目录
App::$appPath     // 应用目录
```

### 全局配置

```php
App::$debug       // 调试模式
App::$version     // 框架版本
App::$lang        // 当前语言
App::$timezone    // 时区设置
App::$host        // 主机地址
```

## 最佳实践

### 服务访问

```php
// ✅ 推荐：通过 App 类访问服务
$cache = App::cache();
$db = App::db();

// ❌ 避免：直接实例化服务类
$cache = new Cache('redis', $config);
```

### 配置管理

```php
// ✅ 推荐：使用配置服务
$setting = App::config('app')->get('setting.name');

// ❌ 避免：直接读取配置文件
$setting = parse_ini_file('app.ini')['setting']['name'];
```

### 服务单例

App 类中的所有服务都是单例模式，首次调用时创建并缓存在 DI 容器中，后续调用直接返回缓存实例，确保性能和一致性。

DuxLite 的 App 类提供了框架所有核心功能的统一访问接口，是开发中最常用的工具类。通过静态方法调用，简化了服务访问，提高了开发效率。
