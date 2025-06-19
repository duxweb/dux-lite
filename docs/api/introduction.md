# API 参考

DuxLite 框架 API 参考文档，提供所有核心类、方法和接口的详细技术说明。

## 文档结构

### 核心模块

- **[核心类](./core-classes.md)** - App、Bootstrap 等框架核心类
- **[异常类型](./exceptions.md)** - 完整的异常体系和错误处理
- **[属性注解](./attributes.md)** - PHP 8 注解系统的使用

### 功能模块

- **[认证系统](./auth.md)** - 用户认证、权限控制、JWT 等
- **[路由系统](./route.md)** - 路由注册、中间件、URL 生成
- **[数据库](./database.md)** - Eloquent ORM、查询构造器、迁移
- **[缓存系统](./cache.md)** - 多种缓存后端、缓存策略
- **[队列系统](./queue.md)** - 异步任务、队列处理、任务调度

### 工具模块

- **[App 类](./app.md)** - 应用核心入口类的详细 API
- **[Bootstrap 类](./bootstrap.md)** - 应用引导程序的详细说明
- **[存储系统](./storage.md)** - 文件存储、云存储、文件管理
- **[事件系统](./event.md)** - 事件驱动编程、监听器、异步处理
- **[验证系统](./validator.md)** - 数据验证、规则定义、错误处理
- **[原子锁](./lock.md)** - 原子锁系统、分布式锁
- **[模板视图](./views.md)** - Latte模板引擎、视图渲染
- **[翻译国际化](./translation.md)** - 多语言支持、TOML语言文件
- **[日志系统](./logs.md)** - Monolog日志记录、文件轮转
- **[模型扩展](./model.md)** - 嵌套集合、翻译模型扩展
- **[权限管理](./permission.md)** - 权限系统、权限组、权限检查
- **[资源控制器](./resources.md)** - RESTful资源控制器、CRUD操作
- **[计划任务](./scheduler.md)** - 任务调度、Cron表达式、定时任务
- **[辅助函数](./helpers.md)** - 全局辅助函数和工具方法

## 使用说明

### 命名约定

- **类名**：PascalCase，如 `UserController`、`AuthMiddleware`
- **方法名**：camelCase，如 `getUserProfile()`、`validateRequest()`
- **属性名**：camelCase，如 `$userName`、`$isActive`
- **常量名**：UPPER_SNAKE_CASE，如 `MAX_RETRY_COUNT`

### 参数说明

- **必需参数**：用 `✅` 标记
- **可选参数**：用 `❌` 标记
- **参数类型**：使用 PHP 类型声明格式
- **默认值**：在参数说明中明确标注

### 异常处理

```
Exception (PHP 标准异常)
├── RuntimeException
│   ├── ExceptionBusiness (业务异常)
│   ├── ExceptionData (数据异常)
│   ├── ExceptionValidator (验证异常)
│   └── ExceptionInternal (内部异常)
└── ExceptionNotFound (404 异常)
```

---

**注意**：本文档对应 DuxLite v2.x 版本。