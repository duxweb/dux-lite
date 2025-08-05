# 日志系统

DuxLite 基于 Monolog 提供简单的日志功能，支持分通道记录和文件轮转。

## 核心组件

- **LogHandler**：日志处理器类 (`Core\Logs\LogHandler`)
- **App::log()**：日志记录方法
- **RotatingFileHandler**：Monolog 文件轮转处理器

## 基本使用

### 记录日志

```php
// 使用默认通道 (app)
App::log()->info('用户登录成功', ['user_id' => 123]);

// 使用指定通道
App::log('error')->error('系统错误', ['error' => $e->getMessage()]);
App::log('sql')->debug('SQL查询', ['sql' => $sql, 'time' => $time]);

// 不同日志级别
App::log()->debug('调试信息');
App::log()->info('一般信息');
App::log()->warning('警告信息');
App::log()->error('错误信息');
App::log()->critical('严重错误');
```

### LogHandler 实现

```php
// LogHandler::init() 方法签名
public static function init(string $name, Level $level): Logger

// 创建轮转文件处理器，保留15天日志
// $name: 日志通道名称
// $level: 日志级别
```

## 实际应用示例

### 控制器中记录日志

```php
class UserController
{
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        
        // 记录登录尝试
        App::log('access')->info('用户尝试登录', [
            'username' => $data['username'],
            'ip' => $request->getServerParams()['REMOTE_ADDR'],
        ]);
        
        try {
            $user = $this->authenticateUser($data);
            
            // 记录登录成功
            App::log('access')->info('用户登录成功', [
                'user_id' => $user->id,
                'username' => $user->username,
            ]);
            
            return send($response, '登录成功', ['user' => $user->transform()]);
            
        } catch (ExceptionBusiness $e) {
            // 记录登录失败
            App::log('access')->warning('用户登录失败', [
                'username' => $data['username'],
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
}
```

### 错误处理中记录日志

```php
try {
    // 业务逻辑
    $result = $this->processData($data);
} catch (Exception $e) {
    App::log('error')->error('数据处理失败', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'data' => $data
    ]);
    throw $e;
}
```

## 文件存储

日志文件存储在 `data/logs/` 目录下，按通道名称和日期自动轮转：

```
data/logs/
├── app-2024-01-15.log      # 应用日志
├── error-2024-01-15.log    # 错误日志
├── sql-2024-01-15.log      # SQL查询日志
└── scheduler-2024-01-15.log # 调度任务日志
```

系统自动保留15天的日志文件，过期文件会被删除。

DuxLite 日志系统基于 Monolog，提供简单实用的日志记录功能。