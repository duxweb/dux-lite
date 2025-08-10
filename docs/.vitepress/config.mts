import { defineConfig } from 'vitepress'
import { MermaidMarkdown, MermaidPlugin, withMermaid } from "vitepress-plugin-mermaid";

// 自动检测 base 路径
const getBase = () => {
  if (process.env.GITHUB_ACTIONS) {
    return '/dux-lite/'
  }
  return '/'
}

// https://vitepress.dev/reference/site-config
export default defineConfig({
  title: "DuxLite v2",
  description: "基于 SlimPHP 的轻量级 PHP Web 框架",
  lang: 'zh-CN',
  base: getBase(),
  lastUpdated: true,
  themeConfig: {
    // https://vitepress.dev/reference/default-theme-config
    nav: [
      { text: '首页', link: '/' },
      { text: '指南', link: '/guide/overview' },
      { text: '参考', link: '/reference/api/routing' }
    ],

    sidebar: {
      '/guide/': [
        {
          text: '快速入门',
          items: [
            { text: '框架概述', link: '/guide/overview' },
            { text: '快速开始', link: '/guide/quick-start' },
            { text: '目录结构', link: '/guide/directory-structure' }
          ]
        },
        {
          text: '核心概念',
          items: [
            { text: '应用生命周期', link: '/guide/lifecycle' },
            { text: '配置系统', link: '/guide/configuration' },
            { text: '依赖注入', link: '/guide/dependency-injection' },
            { text: '中间件机制', link: '/guide/middleware' }
          ]
        },
        {
          text: '部署运维',
          items: [
            { text: '环境配置', link: '/guide/environment' },
            { text: '性能优化', link: '/guide/performance' },
            { text: '生产部署', link: '/guide/deployment' },
            { text: 'Worker 模式', link: '/guide/worker' }
          ]
        },
        {
          text: '开发实践',
          items: [
            { text: '编码规范', link: '/guide/coding-standards' },
            { text: 'API 开发', link: '/guide/api-development' },
            { text: 'CRUD 开发', link: '/guide/crud-development' },
            { text: '调试技巧', link: '/guide/debugging' },
            { text: '常见问题', link: '/guide/faq' }
          ]
        }
      ],
      
      '/reference/': [
        {
          text: 'API开发',
          items: [
            { text: '路由', link: '/reference/api/routing' },
            { text: '控制器', link: '/reference/api/controllers' },
            { text: '中间件', link: '/reference/api/middleware' },
            { text: '响应处理', link: '/reference/api/responses' },
            { text: 'OpenAPI文档', link: '/reference/api/openapi' }
          ]
        },
        {
          text: '模板开发',
          items: [
            { text: '路由', link: '/reference/template/routing' },
            { text: '控制器', link: '/reference/template/controllers' },
            { text: '视图模板', link: '/reference/template/views' },
            { text: '前端集成', link: '/reference/template/frontend' }
          ]
        },
        {
          text: 'CRUD开发',
          items: [
            { text: '路由', link: '/reference/crud/resource-routes' },
            { text: '控制器', link: '/reference/crud/resources' },
            { text: '权限控制', link: '/reference/crud/permissions' }
          ]
        },
        {
          text: '数据处理',
          items: [
            { text: '数据模型', link: '/reference/data/database' },
            { text: '自动同步', link: '/reference/data/auto-migration' },
            { text: '数据验证', link: '/reference/data/validation' },
            { text: '缓存系统', link: '/reference/data/cache' }
          ]
        },
        {
          text: '认证授权',
          items: [
            { text: '用户认证', link: '/reference/auth/authentication' },
            { text: '权限管理', link: '/reference/auth/authorization' }
          ]
        },
        {
          text: '系统功能',
          items: [
            { text: '事件系统', link: '/reference/system/events' },
            { text: '队列处理', link: '/reference/system/queues' },
            { text: '文件存储', link: '/reference/system/storage' },
            { text: '日志记录', link: '/reference/system/logging' },
            { text: '任务调度', link: '/reference/system/scheduling' }
          ]
        },
        {
          text: '核心架构',
          items: [
            { text: '应用核心类', link: '/reference/core/app' },
            { text: '属性注解', link: '/reference/core/attributes' },
            { text: '异常处理', link: '/reference/core/exceptions' },
            { text: '辅助工具', link: '/reference/core/helpers' }
          ]
        }
      ]
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/duxweb/dux-lite' }
    ],

    footer: {
      message: '基于 MIT 许可证发布',
      copyright: 'Copyright © 2025 DuxWeb'
    },

    editLink: {
      pattern: 'https://github.com/duxweb/dux-lite/edit/main/docs/:path',
      text: '在 GitHub 上编辑此页面'
    },

  },
  markdown: {
    config(md) {
      md.use(MermaidMarkdown); // add this
    },
  },
  vite: {
    plugins: [MermaidPlugin()], // add plugins
    optimizeDeps: { // include mermaid
      include: ['mermaid'],
      exclude: ['@duxweb/vitepress-theme']
    },
    ssr: {
      noExternal: ['mermaid', '@duxweb/vitepress-theme'],
    },
    build: {
      cssMinify: false
    }
  },
})