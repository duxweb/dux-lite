---
layout: home
title: DuxLite v2
titleTemplate: 基于 SlimPHP 的现代化 PHP Web 框架

hero:
  name: DuxLite v2
  text: 基于 SlimPHP 的现代化 PHP Web 框架
  tagline: 一个轻量级、高性能的 PHP 框架，专注于快速开发和企业级应用
  subtitle: 🚀 高性能 • 模块化 • PSR 标准
  mockUrl: "duxlite.com"
  image: true
  actions:
    - theme: brand
      text: 快速开始
      link: /guide/quick-start
    - theme: alt
      text: 在 GitHub 查看
      link: https://github.com/duxweb/dux-lite
      target: _blank
    - theme: alt
      text: API 参考
      link: /reference/api/routing

features:
  - icon: rocket-launch
    color: blue
    title: 高性能架构
    details: 基于 SlimPHP 和 Eloquent ORM，轻量级高性能设计
  - icon: cube
    color: purple
    title: 模块化设计
    details: 灵活的模块化架构，支持插件式开发和独立部署
  - icon: shield-check
    color: green
    title: PSR 标准兼容
    details: 完全遵循 PSR-7、PSR-11、PSR-15 等现代 PHP 标准
  - icon: package
    color: orange
    title: 丰富的内置组件
    details: 缓存、队列、事件、认证、存储等企业级组件开箱即用
  - icon: wrench-screwdriver
    color: amber
    title: 强大的 CLI 工具
    details: 完善的命令行工具，支持数据库迁移、代码生成、任务调度
    link: /guide/cli
  - icon: sparkles
    color: indigo
    title: 现代化开发体验
    details: 属性注解、依赖注入、中间件、资源管理等现代特性

featuresConfig:
  title: 为什么选择 DuxLite？
  description: 专为高效 PHP 开发而设计的轻量级框架
  extraSection:
    title: 立即开始构建
    description: 仅需几步即可搭建高性能的 PHP 应用，让开发更简单高效
    tags:
      - 轻量级
      - 高性能
      - PSR 标准
      - Eloquent ORM
      - 模块化
      - PHP 8.2+
      - 中文文档
      - 企业级安全

quickStart:
  badge: 5 分钟上手
  title: 快速开始
  subtitle: 零配置
  description: 简单几步即可创建你的 PHP 应用
  steps:
    - step: "01"
      icon: "arrow-down-tray"
      color: "blue"
      title: "安装框架"
      description: "使用 Composer 快速安装"
      code: "composer create-project duxweb/dux-lite-starter my-app"
    - step: "02"
      icon: "cog-8-tooth"
      color: "green"
      title: "进入目录"
      description: "进入项目目录"
      code: "cd my-app"
    - step: "03"
      icon: "rocket-launch"
      color: "purple"
      title: "启动服务"
      description: "运行开发服务器"
      code: |
        php -S localhost:8000 -t public
  helpText: "需要帮助？查看我们的详细文档"
  helpLink: "/guide/quick-start"
  helpLinkText: "快速开始指南"
---