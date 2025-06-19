(function(s,n){typeof exports=="object"&&typeof module<"u"?n(exports):typeof define=="function"&&define.amd?define(["exports"],n):(s=typeof globalThis<"u"?globalThis:s||self,n(s.MermaidPlugin={}))})(this,function(s){"use strict";const n=(e,r)=>{const a=e.renderer.rules.fence.bind(e.renderer.rules);e.renderer.rules.fence=(t,i,m,d,o)=>{const l=t[i];if(l.info.trim()==="mermaid")try{const u=i,y=(r==null?void 0:r.class)||"mermaid";return`
      <Suspense> 
      <template #default>
      <Mermaid id="mermaid-${u}" class="${y}" graph="${encodeURIComponent(l.content)}"></Mermaid>
      </template>
        <!-- loading state via #fallback slot -->
        <template #fallback>
          Loading...
        </template>
      </Suspense>`}catch(u){return`<pre>${u}</pre>`}return l.info.trim()==="mmd"&&(t[i].info="mermaid"),a(t,i,m,d,o)}},v={securityLevel:"loose",startOnLoad:!1};function p(e){const r={...v,...e},a="virtual:mermaid-config",t="\0"+a;return{name:"vite-plugin-mermaid",enforce:"post",transform(i,m){if(m.includes("vitepress/dist/client/app/index.js")){i=`
import Mermaid from 'vitepress-plugin-mermaid/Mermaid.vue';
`+i;const d=i.split(`
`),o=d.findIndex(l=>l.includes("app.component"));return d.splice(o,0,'  app.component("Mermaid", Mermaid);'),i=d.join(`
`),{code:i,map:null}}},async resolveId(i){if(i===a)return t},async load(i){if(i===t)return`export default ${JSON.stringify(r)};`}}}const c=e=>{e.markdown||(e.markdown={});const r=e.markdown.config||(()=>{});e.markdown.config=(...t)=>{n(...t,e.mermaidPlugin),r(...t)},e.vite||(e.vite={}),e.vite.plugins||(e.vite.plugins=[]),e.vite.plugins.push(p(e.mermaid)),e.vite.optimizeDeps||(e.vite.optimizeDeps={}),e.vite.optimizeDeps.include||(e.vite.optimizeDeps.include=[]),e.vite.optimizeDeps.include=[...e.vite.optimizeDeps.include,"@braintree/sanitize-url","dayjs","debug","cytoscape-cose-bilkent","cytoscape"],e.vite.resolve||(e.vite.resolve={});const a={"dayjs/plugin/advancedFormat.js":"dayjs/esm/plugin/advancedFormat","dayjs/plugin/customParseFormat.js":"dayjs/esm/plugin/customParseFormat","dayjs/plugin/isoWeek.js":"dayjs/esm/plugin/isoWeek","cytoscape/dist/cytoscape.umd.js":"cytoscape/dist/cytoscape.esm.js"};return e.vite.resolve.alias?Array.isArray(e.vite.resolve.alias)?e.vite.resolve.alias=[...e.vite.resolve.alias,...Object.entries(a).map(([t,i])=>({find:t,replacement:i}))]:e.vite.resolve.alias={...e.vite.resolve.alias,...a}:e.vite.resolve.alias=a,e};s.MermaidMarkdown=n,s.MermaidPlugin=p,s.withMermaid=c,Object.defineProperty(s,Symbol.toStringTag,{value:"Module"})});
