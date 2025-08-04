# API开发的问题

系统的模型都是使用的 laravel orm，全部代码参考 src，根据实际代码来修正问题，不要去写不存在的代码，仔细想想，可以参考 /Volumes/Web/dux-vue-admin 的实现


## reference/api/routing 路由系统

1. 缺少路由注册，应该在 App.php 中注册，注册可以参考 

## reference/api/controllers 控制器开发

1. 需要演示获取 query  headers body 还有数据验证、上传功能
2. 分页不需要处理$page并且模型使用 pagination($limit) 方法来处理分页，现在的太复杂了
3. 简化示例，只展控制器方法
4. 删除 资源控制器 部分，这个是放到 CURD 章节的
5. 错误处理 直接展示抛出异常，示例太复杂了
6. 不需要 最佳实践

## reference/api/middleware 中间件

1. 篇章太复杂了，只展示API 用到中间件，包括 API 签名，多语言和 AUTH 
2. 去掉多余的内容比如最佳实践等

## reference/api/responses 响应处理

1. 示例太复杂，只需要展示基本的返回格式和 send sendText 方法使用还有原始的 slimphp 响应方法即可