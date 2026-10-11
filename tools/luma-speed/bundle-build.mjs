// Step 2 of JS bundling: builds RequireJS bundles from bundle-modules.json.
//  - "common": modules every storefront page type loaded (checkout excluded: it's a different app)
//  - one bundle per page type with its remaining modules
// Anonymous define() calls get their module id; text templates become named modules; files without define()
// (non-AMD/shimmed) are left out and keep loading on their own. Output: bundles/<name>.js + .min.js + manifest.json
import fs from 'node:fs';
import https from 'node:https';

const { base, pages } = JSON.parse(fs.readFileSync('bundle-modules.json', 'utf8'));
const HOST = new URL(base).hostname;
const BUNDLE_PREFIX = 'Softcode_LumaSpeed/bundle/';
const PAGE_TYPES = { 'cms-index-index': 'home', 'cms-page-view': 'cms', 'catalog-category-view': 'plp',
  'catalog-product-view': 'pdp', 'catalogsearch-result-index': 'search', 'checkout-cart-index': 'cart', 'checkout-index-index': 'checkout' };

const get = url => new Promise((resolve, reject) => {
  const u = new URL(url);
  https.get({ host: '127.0.0.1', port: u.port || 443, path: u.pathname + u.search, servername: HOST,
    headers: { Host: u.host }, rejectUnauthorized: false }, res => {
    let body = ''; res.setEncoding('utf8'); res.on('data', c => body += c);
    res.on('end', () => res.statusCode === 200 ? resolve(body) : reject(new Error(`${res.statusCode} ${url}`)));
  }).on('error', reject);
});

// Names the first anonymous define( call; leaves named defines, `typeof define` and `define.amd` alone.
function nameModule(id, source) {
  if (/(^|[^.\w$])define\s*\(\s*['"]/.test(source)) return source;                    // already named
  const anon = /(^|[^.\w$])define\s*\(\s*(?!['"])/;
  if (!anon.test(source)) return null;                                                  // not AMD
  return source.replace(anon, (m, pre) => `${pre}define(${JSON.stringify(id)},`);
}

const storefront = Object.keys(pages).filter(t => t !== 'checkout');
const common = [...new Set(pages[storefront[0]].map(m => m.id))]
  .filter(id => storefront.every(t => pages[t].some(m => m.id === id)));
const plan = { common: pages[storefront[0]].filter(m => common.includes(m.id)) };
for (const [type, mods] of Object.entries(pages)) plan[type] = mods.filter(m => !common.includes(m.id));

fs.mkdirSync('bundles', { recursive: true });
const manifest = { builtAt: new Date().toISOString(), prefix: BUNDLE_PREFIX, pageTypes: PAGE_TYPES, bundles: {} };
const cache = new Map();
for (const [name, mods] of Object.entries(plan)) {
  const parts = [], ids = [], skipped = [];
  for (const m of mods) {
    let src = cache.get(m.url);
    if (src === undefined) { src = await get(m.url).catch(() => null); cache.set(m.url, src); }
    if (src === null) { skipped.push(`${m.id} (fetch failed)`); continue; }
    const code = m.type === 'text'
      ? `define(${JSON.stringify(m.id)},function(){return ${JSON.stringify(src)};});`
      : nameModule(m.id, src);
    if (code === null) { skipped.push(`${m.id} (not AMD)`); continue; }
    parts.push(`/* ${m.id} */\n${code}\n;`);
    ids.push(m.id);
  }
  const body = parts.join('\n');
  fs.writeFileSync(`bundles/${name}.js`, body);
  fs.writeFileSync(`bundles/${name}.min.js`, body);
  manifest.bundles[name] = ids;
  console.log(`${name.padEnd(9)} ${String(ids.length).padStart(4)} modules ${String(Math.round(body.length / 1024)).padStart(5)} KB` + (skipped.length ? `  skipped: ${skipped.join(', ').slice(0, 300)}` : ''));
}
fs.writeFileSync('bundles/manifest.json', JSON.stringify(manifest));
