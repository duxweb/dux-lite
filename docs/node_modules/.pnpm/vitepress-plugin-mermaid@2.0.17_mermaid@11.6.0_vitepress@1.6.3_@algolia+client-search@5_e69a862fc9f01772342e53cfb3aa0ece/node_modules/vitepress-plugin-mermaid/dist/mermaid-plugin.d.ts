import { Plugin } from 'vite';
import { ExternalDiagramDefinition, MermaidConfig } from 'mermaid';

interface MermaidPluginOptions extends MermaidConfig {
    externalDiagrams: ExternalDiagramDefinition[];
}
export interface MermaidPluginConfig {
    class?: string;
}
export declare function MermaidPlugin(inlineOptions?: Partial<MermaidPluginOptions>): Plugin;
export {};
