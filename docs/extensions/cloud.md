# Dux Lite Cloud

`duxweb/dux-lite-cloud` 是 DuxLite 的云扩展包管理器。

它提供：

- 应用包安装
- 应用包更新
- 应用包卸载
- 应用发布到云端仓库
- 依赖同步
- 插件自动注册

## 安装

```bash
composer require duxweb/dux-lite-cloud
```

如果是本地开发环境，可执行一次：

```bash
php dux plugin:refresh
```

## 适用场景

适合需要以下能力的项目：

- 从云端仓库安装 DuxLite 扩展包
- 在项目中统一管理应用模块
- 将自定义模块发布到云端

## 常用命令

### 安装包

```bash
php dux add package-name
php dux add package-name:1.0.0
```

### 删除包

```bash
php dux del package-name
```

### 更新包

```bash
php dux update
php dux update package-name
php dux update package-name:1.2.0
```

### 发布应用

```bash
php dux push module-folder
```

## 配置

在 `config/use.toml` 或 `config/use.dev.toml` 中配置：

```toml
[cloud]
key = "your-cloud-key"
url = "https://cloud.dux.plus"
```

## 相关文件

### 项目根目录 `app.json`

记录项目依赖的包和应用。

### 模块目录 `app/ModuleName/app.json`

记录模块的发布信息、版本和依赖。

### 模块目录 `app/ModuleName/CHANGELOG.md`

记录发布历史。

### 项目根目录 `app.lock`

记录已安装包的锁定信息。

## 仓库

- GitHub: `https://github.com/duxweb/dux-lite-cloud`

