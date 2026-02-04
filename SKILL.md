---
name: dux-lite
description: Dux Lite 项目开发规则模板（基于 dux-php-admin 规范去重整理）。
---

# Dux Lite 项目开发规则

> 用于统一后端与模板/视图开发习惯，保持可读与可执行。

## 适用范围
- 后端 PHP、模板/视图、控制器与 Service 改动

## 基础规范（必须）
- `declare(strict_types=1);`
- if/else 必须 `{}`，不允许单行省略
- 函数小驼峰、类大驼峰、命名空间 `App\模块名\...`
- PSR-4 结构
- 中间件洋葱模型：自定义在外层，认证在内层

## 参数处理（必须）
- 先补默认键再使用：`$params = $request->getQueryParams() + ['keyword' => null];`
- 直接用 `$params['keyword']`，不做 `trim/??/?:` 二次兜底
- 避免 `?:`（会把 0 当空值）
- `format()` 只做映射与最小强转，校验放 `validator()`
- `validator()` 规则用二维数组结构
- `format()` 不做空值归一，数值字段直接强转
- 空值判断风格：`if ($params['field'])`

## 数据输出与清洗（必须）
- Model 必须有 `transform()`，输出由模型负责
- Controller/Resource 不对 Model 输出二次拼装
- 上下架统一 `0/1`，输出开关状态用 `(bool)`
- 关系字段直接用关系属性，避免 `relationLoaded()/->first()`
- 用户字段统一 `user_id`
- 删除无意义兜底与重复转换

## 模块与目录
- 模块目录：`app/模块名`
- 模块菜单配置：`app/模块/app.json`

## 多管理端约定
- 菜单使用 `*Menu` 区分不同管理端（如 `companyMenu/orgMenu`）
- 菜单 name/label_lang 对应后端 route name
- 路由前缀与 app 名称解耦：app 仍为 `company`，前缀可为 `/company` 或 `/org`
- 复用 Admin 页面时透传 `manage` 参数，避免弹窗/子组件丢上下文
- `loader` 相对路径：同模块用 `../Admin/...`，跨模块用 `../../模块名/Admin/...`
- Company/Org 控制器建议继承 Admin 控制器，仅覆盖必要方法并复用父类逻辑

## 接口与事务
- 列表接口优先用 `format_data` 返回分页结构
- 本地事务提交后再调用第三方接口

## 测试与检查
- 搜索优先使用 `rg`
- Mall/Commerce：`./vendor/bin/pest app/Mall/Test app/Commerce/Test`
