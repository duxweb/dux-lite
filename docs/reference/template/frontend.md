# 前端集成

DuxLite 支持两种主要的前端开发模式：传统的服务端渲染集成和现代的前后端分离架构。

## 开发模式选择

### 传统集成模式

适合中小型项目，前端资源与后端模板紧密集成：

- **服务端渲染**：使用 Latte 模板引擎渲染页面
- **渐进增强**：在模板基础上添加JavaScript交互
- **简单部署**：前后端代码在同一项目中
- **快速开发**：无需复杂的构建配置

### 前后端分离模式

适合大型项目，前端作为独立应用：

- **API驱动**：后端只提供RESTful API接口
- **单页应用**：使用Vue、React等现代框架
- **独立部署**：前后端可以分别部署
- **团队协作**：前后端可以并行开发

## 传统集成模式

### 目录结构

```
public/
├── assets/
│   ├── css/           # 样式文件
│   ├── js/            # 脚本文件
│   └── images/        # 图片资源
└── uploads/           # 用户上传文件
```

### 模板布局

```html
<!-- layouts/base.latte -->
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{block title}{$title|default:'DuxLite'}{/block}</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    {block styles}{/block}
</head>
<body>
    {block content}{/block}
    {block scripts}{/block}
</body>
</html>
```

### 页面示例

```html
<!-- user/list.latte -->
{layout 'layouts/base.latte'}

{block content}
    <div class="container">
        <h1>用户管理</h1>
        
        <table class="table">
            <thead>
                <tr>
                    <th>姓名</th>
                    <th>邮箱</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                {foreach $users as $user}
                    <tr>
                        <td>{$user->name}</td>
                        <td>{$user->email}</td>
                        <td>{$user->status ? '正常' : '禁用'}</td>
                        <td>
                            <a href="/admin/users/{$user->id}/edit">编辑</a>
                            <a href="/admin/users/{$user->id}" onclick="return confirm('确定删除？')">删除</a>
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
{/block}
```

## 前后端分离模式

### API接口架构

```php
#[Resource(
    app: 'api',
    route: '/api/users',
    name: 'users'
)]
class ApiUserController extends Resources
{
    protected string $model = User::class;
    
    // 自动生成RESTful API：
    // GET    /api/users         -> 用户列表
    // POST   /api/users         -> 创建用户
    // PUT    /api/users/{id}    -> 更新用户
    // DELETE /api/users/{id}    -> 删除用户
}
```

### 前端应用架构

前端作为独立的单页应用（SPA），通过API与后端交互：

- **技术选择**：Vue、React、Angular等现代框架
- **状态管理**：Vuex、Redux等状态管理库
- **路由管理**：Vue Router、React Router等
- **HTTP客户端**：Axios、Fetch API等
- **构建工具**：Vite、Webpack等

## 架构选择建议

### 传统集成模式适用场景

- **中小型项目**：功能相对简单，开发团队较小
- **快速原型**：需要快速验证想法和功能
- **内容管理**：以内容展示为主的网站
- **简单交互**：用户交互相对简单

### 前后端分离模式适用场景

- **大型项目**：功能复杂，需要多团队协作
- **高并发应用**：需要独立扩展前后端
- **多端应用**：同时支持Web、移动端、小程序等
- **复杂交互**：需要丰富的用户界面和交互

## 部署方式对比

### 传统集成部署

- **简单部署**：前后端代码在同一项目中，一次部署即可
- **统一域名**：前后端使用同一域名，无跨域问题
- **适合场景**：中小型项目、快速原型开发

### 前后端分离部署

- **独立部署**：前端可以部署到CDN，后端部署到应用服务器
- **扩展性好**：前后端可以独立扩展和升级
- **适合场景**：大型项目、需要高并发的应用

通过选择合适的前端集成模式，可以满足不同规模和复杂度的项目需求。