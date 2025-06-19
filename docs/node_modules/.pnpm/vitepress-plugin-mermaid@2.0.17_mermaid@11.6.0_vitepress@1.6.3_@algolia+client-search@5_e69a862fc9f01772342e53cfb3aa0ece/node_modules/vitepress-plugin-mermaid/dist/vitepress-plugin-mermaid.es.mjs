const p = (e, i) => {
  const a = e.renderer.rules.fence.bind(e.renderer.rules);
  e.renderer.rules.fence = (s, t, n, r, d) => {
    const l = s[t];
    if (l.info.trim() === "mermaid")
      try {
        const m = t, o = (i == null ? void 0 : i.class) || "mermaid";
        return `
      <Suspense> 
      <template #default>
      <Mermaid id="mermaid-${m}" class="${o}" graph="${encodeURIComponent(
          l.content
        )}"></Mermaid>
      </template>
        <!-- loading state via #fallback slot -->
        <template #fallback>
          Loading...
        </template>
      </Suspense>`;
      } catch (m) {
        return `<pre>${m}</pre>`;
      }
    return l.info.trim() === "mmd" && (s[t].info = "mermaid"), a(s, t, n, r, d);
  };
}, u = {
  //We set loose as default here because is needed to load images
  securityLevel: "loose",
  startOnLoad: !1
};
function v(e) {
  const i = {
    ...u,
    ...e
  }, a = "virtual:mermaid-config", s = "\0" + a;
  return {
    name: "vite-plugin-mermaid",
    enforce: "post",
    transform(t, n) {
      if (n.includes("vitepress/dist/client/app/index.js")) {
        t = `
import Mermaid from 'vitepress-plugin-mermaid/Mermaid.vue';
` + t;
        const r = t.split(`
`), d = r.findIndex(
          (l) => l.includes("app.component")
        );
        return r.splice(
          d,
          0,
          '  app.component("Mermaid", Mermaid);'
        ), t = r.join(`
`), {
          code: t,
          map: null
          // provide source map if available
        };
      }
    },
    async resolveId(t) {
      if (t === a)
        return s;
    },
    async load(t) {
      if (t === s)
        return `export default ${JSON.stringify(i)};`;
    }
  };
}
const c = (e) => {
  e.markdown || (e.markdown = {});
  const i = e.markdown.config || (() => {
  });
  e.markdown.config = (...s) => {
    p(...s, e.mermaidPlugin), i(...s);
  }, e.vite || (e.vite = {}), e.vite.plugins || (e.vite.plugins = []), e.vite.plugins.push(v(e.mermaid)), e.vite.optimizeDeps || (e.vite.optimizeDeps = {}), e.vite.optimizeDeps.include || (e.vite.optimizeDeps.include = []), e.vite.optimizeDeps.include = [
    ...e.vite.optimizeDeps.include,
    "@braintree/sanitize-url",
    "dayjs",
    "debug",
    "cytoscape-cose-bilkent",
    "cytoscape"
  ], e.vite.resolve || (e.vite.resolve = {});
  const a = {
    "dayjs/plugin/advancedFormat.js": "dayjs/esm/plugin/advancedFormat",
    "dayjs/plugin/customParseFormat.js": "dayjs/esm/plugin/customParseFormat",
    "dayjs/plugin/isoWeek.js": "dayjs/esm/plugin/isoWeek",
    "cytoscape/dist/cytoscape.umd.js": "cytoscape/dist/cytoscape.esm.js"
  };
  return e.vite.resolve.alias ? Array.isArray(e.vite.resolve.alias) ? e.vite.resolve.alias = [
    ...e.vite.resolve.alias,
    ...Object.entries(a).map(([s, t]) => ({
      find: s,
      replacement: t
    }))
  ] : e.vite.resolve.alias = {
    ...e.vite.resolve.alias,
    ...a
  } : e.vite.resolve.alias = a, e;
};
export {
  p as MermaidMarkdown,
  v as MermaidPlugin,
  c as withMermaid
};
