import { defineConfig } from 'vitepress'
import { MermaidMarkdown, MermaidPlugin, withMermaid } from "vitepress-plugin-mermaid";

// 自动检测 base 路径
const getBase = () => {
  // GitHub Pages 通过环境变量检测
  if (process.env.GITHUB_ACTIONS) {
    return '/dux-lite/'
  }
  // 其他平台或本地开发
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
      { text: '核心功能', link: '/core/routing' },
      { text: 'API 参考', link: '/api/introduction' }
    ],

    sidebar: {
      '/guide/': [
        {
          text: '开始使用',
          items: [
            { text: '框架概况', link: '/guide/overview' },
            { text: '快速开始', link: '/guide/getting-started' },
            { text: '安装配置', link: '/guide/installation' },
            { text: '目录结构', link: '/guide/directory-structure' },
            { text: '配置文件', link: '/guide/configuration' }
          ]
        },
        {
          text: '基础概念',
          items: [
            { text: '应用生命周期', link: '/guide/lifecycle' },
            { text: '依赖注入', link: '/guide/dependency-injection' },
            { text: '中间件', link: '/guide/middleware' },
            { text: '异常处理', link: '/guide/error-handling' }
          ]
        },
        {
          text: '开发指南',
          items: [
            { text: '最佳实践', link: '/guide/best-practices' },
            { text: '性能优化', link: '/guide/performance' },
            { text: '调试技巧', link: '/guide/debugging' },
            { text: '部署指南', link: '/guide/deployment' },
            { text: 'Worker 模式', link: '/guide/worker' }
          ]
        }
      ],
      '/core/': [
        {
          text: '核心功能',
          items: [
            { text: '路由系统', link: '/core/routing' },
            { text: '控制器', link: '/core/controllers' },
            { text: '资源控制器', link: '/core/resources' },
            { text: '请求响应', link: '/core/request-response' },
            { text: '视图模板', link: '/core/views' }
          ]
        },
        {
          text: '数据库',
          items: [
            { text: 'Eloquent ORM', link: '/core/database/eloquent' },
            { text: '数据库迁移', link: '/core/database/migrations' },
            { text: '模型关系', link: '/core/database/relationships' },
            { text: '查询构建器', link: '/core/database/query-builder' }
          ]
        },
        {
          text: '安全认证',
          items: [
            { text: '概述', link: '/core/auth/index' },
            { text: '身份验证', link: '/core/auth/authentication' },
            { text: '权限管理', link: '/core/auth/authorization' },
            { text: 'JWT 令牌', link: '/core/auth/jwt' },
            { text: '安全中间件', link: '/core/auth/middleware' }
          ]
        },
        {
          text: '高级功能',
          items: [
            { text: '事件系统', link: '/core/events' },
            { text: '队列处理', link: '/core/queues' },
            { text: '任务调度', link: '/core/scheduling' },
            { text: '缓存系统', link: '/core/caching' },
            { text: '文件存储', link: '/core/storage' },
            { text: '原子锁', link: '/core/lock' },
            { text: 'Redis 集成', link: '/core/redis' }
          ]
        },
        {
          text: '工具组件',
          items: [
            { text: '数据验证', link: '/core/validation' },
            { text: '多语言支持', link: '/core/localization' },
            { text: '命令行工具', link: '/core/console' },
            { text: '辅助函数', link: '/core/helpers' },
            { text: '日志系统', link: '/core/logging' },
          ]
        }
      ],
      '/api/': [
        {
          text: 'API 参考',
          items: [
            { text: 'API 介绍', link: '/api/introduction' },
            { text: '核心类', link: '/api/core-classes' },
            { text: '属性注解', link: '/api/attributes' },
            { text: '异常类型', link: '/api/exceptions' }
          ]
        },
        {
          text: '组件 API',
          items: [
            { text: 'App 类', link: '/api/app' },
            { text: 'Bootstrap 类', link: '/api/bootstrap' },
            { text: 'Route 路由', link: '/api/route' },
            { text: 'Database 数据库', link: '/api/database' },
            { text: 'Auth 认证', link: '/api/auth' },
            { text: 'Cache 缓存', link: '/api/cache' },
            { text: 'Queue 队列', link: '/api/queue' },
            { text: 'Storage 存储', link: '/api/storage' },
            { text: 'Event 事件', link: '/api/event' },
            { text: 'Validator 验证', link: '/api/validator' },
            { text: 'Lock 原子锁', link: '/api/lock' },
            { text: 'Views 模板视图', link: '/api/views' },
            { text: 'Translation 翻译', link: '/api/translation' },
            { text: 'Logs 日志', link: '/api/logs' },
            { text: 'Model 模型扩展', link: '/api/model' },
            { text: 'Permission 权限', link: '/api/permission' },
            { text: 'Resources 资源', link: '/api/resources' },
            { text: 'Scheduler 计划任务', link: '/api/scheduler' },
            { text: 'Helpers 辅助函数', link: '/api/helpers' }
          ]
        }
      ]
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/duxweb/dux-lite' }
    ],

    footer: {
      message: '基于 MIT 许可证发布',
      copyright: 'Copyright © 2023-present DuxWeb'
    },

    editLink: {
      pattern: 'https://github.com/duxweb/dux-lite/edit/main/docs/:path',
      text: '在 GitHub 上编辑此页面'
    },

    search: {
      provider: 'local',
    },
    lastUpdatedText: '最后更新时间',
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
    },
    ssr: {
      noExternal: ['mermaid'],
    },
  },
})
