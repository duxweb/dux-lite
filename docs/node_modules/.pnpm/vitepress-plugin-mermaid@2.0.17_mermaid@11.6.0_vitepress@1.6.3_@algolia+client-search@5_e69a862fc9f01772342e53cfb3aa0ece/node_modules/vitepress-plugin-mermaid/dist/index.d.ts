import { UserConfig } from 'vitepress';
import { MermaidPluginConfig } from './mermaid-plugin';
import { MermaidConfig } from 'mermaid';

export { MermaidMarkdown } from './mermaid-markdown';
export { MermaidPlugin } from './mermaid-plugin';
export { UserConfig };
declare module "vitepress" {
    interface UserConfig {
        mermaid?: MermaidConfig;
        mermaidPlugin?: MermaidPluginConfig;
    }
}
export declare const withMermaid: (config: UserConfig) => UserConfig;
