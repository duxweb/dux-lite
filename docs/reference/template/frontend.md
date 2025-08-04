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

### 资源目录结构

```
public/
├── assets/
│   ├── css/
│   │   ├── app.css      # 主样式文件
│   │   └── admin.css    # 管理后台样式
│   ├── js/
│   │   ├── app.js       # 主脚本文件
│   │   └── admin.js     # 管理后台脚本
│   └── images/
│       ├── logo.png
│       └── icons/
└── uploads/             # 用户上传文件
```

### 模板中使用资源

```html
<!-- layouts/base.latte -->
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{block title}{$title|default:'DuxLite'}{/block}</title>
    
    <!-- CSS 资源 -->
    <link rel="stylesheet" href="/assets/css/app.css">
    {block styles}{/block}
</head>
<body>
    {block content}{/block}
    
    <!-- JavaScript 资源 -->
    <script src="/assets/js/app.js"></script>
    {block scripts}{/block}
</body>
</html>
```

### 传统JavaScript开发

```javascript
// public/assets/js/app.js
class DuxLiteApp {
    constructor() {
        this.init();
    }

    init() {
        // 初始化应用
        document.addEventListener('DOMContentLoaded', () => {
            this.bindEvents();
            this.initComponents();
        });
    }

    bindEvents() {
        // 绑定全局事件
        document.querySelectorAll('[data-action]').forEach(element => {
            element.addEventListener('click', this.handleAction.bind(this));
        });

        // 表单提交
        document.querySelectorAll('form[data-ajax]').forEach(form => {
            form.addEventListener('submit', this.handleAjaxForm.bind(this));
        });
    }

    handleAction(event) {
        const action = event.target.dataset.action;
        const target = event.target.dataset.target;

        switch (action) {
            case 'delete':
                this.confirmDelete(event.target, target);
                break;
            case 'toggle':
                this.toggleElement(target);
                break;
        }
    }

    async handleAjaxForm(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: form.method,
                body: formData
            });

            const result = await response.json();

            if (result.code === 200) {
                this.showMessage(result.message, 'success');
                if (result.redirect) {
                    window.location.href = result.redirect;
                }
            } else {
                this.showMessage(result.message, 'error');
            }
        } catch (error) {
            this.showMessage('操作失败', 'error');
        }
    }

    confirmDelete(element, target) {
        if (confirm('确定要删除吗？')) {
            this.deleteItem(target);
        }
    }

    async deleteItem(url) {
        try {
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            const result = await response.json();
            
            if (result.code === 200) {
                this.showMessage('删除成功', 'success');
                location.reload();
            } else {
                this.showMessage(result.message, 'error');
            }
        } catch (error) {
            this.showMessage('删除失败', 'error');
        }
    }

    showMessage(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
}

// 初始化应用
new DuxLiteApp();
```

### 页面特定脚本

```html
<!-- blog/index.latte -->
{layout 'layouts/base.latte'}

{block content}
    <div class="blog-list">
        {foreach $posts as $post}
            <article class="post-card">
                <h3>{$post->title}</h3>
                <p>{$post->excerpt}</p>
                <button data-action="toggle" data-target="#post-{$post->id}">
                    展开详情
                </button>
                <div id="post-{$post->id}" class="post-detail" style="display: none;">
                    <p>{$post->content}</p>
                </div>
            </article>
        {/foreach}
    </div>
{/block}

{block scripts}
    {include parent}
    <script>
        // 页面特定的JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // 搜索功能
            const searchInput = document.getElementById('search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const keyword = this.value.toLowerCase();
                    const posts = document.querySelectorAll('.post-card');
                    
                    posts.forEach(post => {
                        const title = post.querySelector('h3').textContent.toLowerCase();
                        const excerpt = post.querySelector('p').textContent.toLowerCase();
                        
                        if (title.includes(keyword) || excerpt.includes(keyword)) {
                            post.style.display = 'block';
                        } else {
                            post.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
{/block}
```

## 前后端分离模式

### API接口设计

后端只提供RESTful API接口，前端通过AJAX调用：

```php
// API控制器示例
class ApiUserController extends Resources
{
    protected string $model = User::class;

    #[Resource(
        app: 'api',
        route: '/api/users',
        name: 'users',
        middleware: [AuthMiddleware::class]
    )]
    
    // 自动生成以下API接口：
    // GET    /api/users         -> 用户列表
    // GET    /api/users/{id}    -> 用户详情
    // POST   /api/users         -> 创建用户
    // PUT    /api/users/{id}    -> 更新用户
    // DELETE /api/users/{id}    -> 删除用户
}
```

### 前端SPA示例

使用现代框架开发单页应用：

```javascript
// 前端API客户端
class ApiClient {
    constructor() {
        this.baseURL = '/api';
        this.token = localStorage.getItem('auth_token');
    }

    async request(method, url, data = null) {
        const config = {
            method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (this.token) {
            config.headers.Authorization = `Bearer ${this.token}`;
        }

        if (data) {
            config.body = JSON.stringify(data);
        }

        const response = await fetch(this.baseURL + url, config);
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || '请求失败');
        }

        return result;
    }

    // 用户相关API
    async getUsers(page = 1) {
        return this.request('GET', `/users?page=${page}`);
    }

    async getUser(id) {
        return this.request('GET', `/users/${id}`);
    }

    async createUser(userData) {
        return this.request('POST', '/users', userData);
    }

    async updateUser(id, userData) {
        return this.request('PUT', `/users/${id}`, userData);
    }

    async deleteUser(id) {
        return this.request('DELETE', `/users/${id}`);
    }
}

// 简单的状态管理
class AppState {
    constructor() {
        this.users = [];
        this.currentUser = null;
        this.loading = false;
    }

    setUsers(users) {
        this.users = users;
        this.render();
    }

    setLoading(loading) {
        this.loading = loading;
        this.render();
    }

    render() {
        // 触发界面更新
        window.dispatchEvent(new CustomEvent('stateChange'));
    }
}

// 应用主类
class App {
    constructor() {
        this.api = new ApiClient();
        this.state = new AppState();
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadUsers();
    }

    bindEvents() {
        window.addEventListener('stateChange', () => {
            this.renderUserList();
        });

        // 绑定表单提交
        document.getElementById('userForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmit(e);
        });
    }

    async loadUsers() {
        this.state.setLoading(true);
        
        try {
            const result = await this.api.getUsers();
            this.state.setUsers(result.data);
        } catch (error) {
            console.error('加载用户失败:', error);
        } finally {
            this.state.setLoading(false);
        }
    }

    renderUserList() {
        const container = document.getElementById('userList');
        if (!container) return;

        if (this.state.loading) {
            container.innerHTML = '<div class="loading">加载中...</div>';
            return;
        }

        const html = this.state.users.map(user => `
            <div class="user-card">
                <h3>${user.name}</h3>
                <p>${user.email}</p>
                <div class="actions">
                    <button onclick="app.editUser(${user.id})">编辑</button>
                    <button onclick="app.deleteUser(${user.id})">删除</button>
                </div>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    async handleFormSubmit(event) {
        const formData = new FormData(event.target);
        const userData = Object.fromEntries(formData);

        try {
            if (userData.id) {
                await this.api.updateUser(userData.id, userData);
            } else {
                await this.api.createUser(userData);
            }
            
            await this.loadUsers();
            event.target.reset();
        } catch (error) {
            alert('保存失败: ' + error.message);
        }
    }

    async deleteUser(id) {
        if (!confirm('确定要删除这个用户吗？')) {
            return;
        }

        try {
            await this.api.deleteUser(id);
            await this.loadUsers();
        } catch (error) {
            alert('删除失败: ' + error.message);
        }
    }
}

// 初始化应用
const app = new App();
```

### 前端页面结构

```html
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - SPA示例</title>
    <link rel="stylesheet" href="/assets/css/spa.css">
</head>
<body>
    <div id="app">
        <header>
            <h1>用户管理系统</h1>
        </header>
        
        <main>
            <section class="form-section">
                <h2>添加用户</h2>
                <form id="userForm">
                    <input type="hidden" name="id">
                    <div class="form-group">
                        <label for="name">姓名</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">邮箱</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <button type="submit">保存</button>
                </form>
            </section>
            
            <section class="list-section">
                <h2>用户列表</h2>
                <div id="userList"></div>
            </section>
        </main>
    </div>
    
    <script src="/assets/js/spa.js"></script>
</body>
</html>
```

## 部署方式对比

### 传统集成部署

- **简单部署**：前后端代码在同一项目中，一次部署即可
- **统一域名**：前后端使用同一域名，无跨域问题
- **服务器配置**：只需配置一个Web服务器
- **适合场景**：中小型项目、快速原型开发

### 前后端分离部署

- **独立部署**：前端可以部署到CDN，后端部署到应用服务器
- **扩展性好**：前后端可以独立扩展和升级
- **团队协作**：前后端团队可以并行开发
- **适合场景**：大型项目、需要高并发的应用

## 最佳实践

### 传统集成最佳实践

```javascript
// ✅ 推荐：使用现代JavaScript特性
class ComponentManager {
    constructor() {
        this.components = new Map();
    }
    
    register(name, component) {
        this.components.set(name, component);
    }
    
    init() {
        document.querySelectorAll('[data-component]').forEach(element => {
            const componentName = element.dataset.component;
            const ComponentClass = this.components.get(componentName);
            
            if (ComponentClass) {
                new ComponentClass(element);
            }
        });
    }
}

// ✅ 推荐：模块化组织
const componentManager = new ComponentManager();
componentManager.register('userList', UserListComponent);
componentManager.register('userForm', UserFormComponent);
componentManager.init();
```

### 前后端分离最佳实践

```javascript
// ✅ 推荐：统一错误处理
class APIClient {
    async request(method, url, data) {
        try {
            const response = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: data ? JSON.stringify(data) : null
            });

            if (!response.ok) {
                const error = await response.json();
                throw new APIError(error.message, response.status);
            }

            return response.json();
        } catch (error) {
            this.handleError(error);
            throw error;
        }
    }

    handleError(error) {
        if (error.status === 401) {
            // 重定向到登录页
            window.location.href = '/login';
        } else if (error.status === 403) {
            // 显示权限不足提示
            this.showMessage('权限不足', 'error');
        }
    }
}
```

通过选择合适的前端集成模式，可以满足不同规模和复杂度的项目需求。